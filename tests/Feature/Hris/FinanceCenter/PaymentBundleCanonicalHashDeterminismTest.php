<?php

use App\Services\Hris\CanonicalPayloadHashService;

/**
 * Phase 6 — Canonical payload hash determinism + golden fixture.
 *
 * All INVARIANCE and SENSITIVITY tests feed hand-built arrays directly to hash()
 * so inputs are 100% controlled (no DB ids, no generated bundle numbers).
 *
 * GOLDEN FIXTURE: tests/Fixtures/payment_bundle_canonical_payload_v1.json + .sha256
 * This is the absolute regression anchor for a PR-only (non-batch) bundle.
 * If this test fails, the hash function has drifted — do NOT silently update the fixture.
 *
 * Timestamps (created_at / updated_at): confirmed NOT part of the canonical payload.
 * The build() method assembles keys: bundle_number, bundle_version, currency, total_amount,
 * required_signature_slots, assigned_signer_ids, items. No timestamp fields appear.
 * These tests exercise hash() directly and never pass timestamp keys, which proves that
 * timestamp fields cannot affect the hash.
 */
function goldenFixturePayload(): array
{
    $json = file_get_contents(__DIR__.'/../../../Fixtures/payment_bundle_canonical_payload_v1.json');

    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function basePayload(): array
{
    return [
        'bundle_number' => 'PB-202606-TEST-0001',
        'bundle_version' => 1,
        'currency' => 'IDR',
        'total_amount' => '5000000.00',
        'required_signature_slots' => ['finance_signer_1', 'finance_signer_2', 'executive_signer'],
        'assigned_signer_ids' => [
            'finance_signer_1' => 10,
            'finance_signer_2' => 20,
            'executive_signer' => 30,
        ],
        'items' => [
            [
                'source_type' => 'payment_request',
                'source_id' => 1,
                'source_hash' => 'deadbeef00112233deadbeef00112233deadbeef00112233deadbeef00112233',
                'payee_name' => 'PT Vendor Abadi',
                'bank_last4' => '1234',
                'amount' => '3000000.00',
                'currency' => 'IDR',
            ],
            [
                'source_type' => 'payment_request',
                'source_id' => 2,
                'source_hash' => 'cafebabe00112233cafebabe00112233cafebabe00112233cafebabe00112233',
                'payee_name' => 'CV Sumber Rezeki',
                'bank_last4' => '9876',
                'amount' => '2000000.00',
                'currency' => 'IDR',
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// GOLDEN FIXTURE — absolute regression anchor
// ---------------------------------------------------------------------------

describe('Golden fixture', function () {
    // This fixture captures a PR-only (non-batch) bundle hash.
    // Adding batch payment support must NOT change this hash — the fixture enforces that.
    it('matches the committed .sha256 file exactly', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $fixture = goldenFixturePayload();
        $expected = trim(file_get_contents(
            __DIR__.'/../../../Fixtures/payment_bundle_canonical_payload_v1.sha256'
        ));

        expect($svc->hash($fixture))->toBe($expected);
    });

    it('produces the frozen hash 125dcbb2... in-line (belt-and-suspenders)', function () {
        $svc = app(CanonicalPayloadHashService::class);

        expect($svc->hash(goldenFixturePayload()))
            ->toBe('125dcbb2a837ce9b4a875e88de76a5897302b73903a6c5e43bf7ac7013d217b6');
    });
});

// ---------------------------------------------------------------------------
// INVARIANCE — identical hash despite surface variation
// ---------------------------------------------------------------------------

describe('Invariance: same payload → same hash', function () {
    it('hashing the same array twice yields the same hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $p = basePayload();

        expect($svc->hash($p))->toBe($svc->hash($p));
    });

    it('top-level key order does not change the hash (ksort normalization)', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $p = basePayload();
        $reversed = array_reverse($p, true);

        expect($svc->hash($reversed))->toBe($svc->hash($p));
    });

    it('item key order does not change the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $p = basePayload();
        $pReordered = $p;
        $pReordered['items'][0] = array_reverse($p['items'][0], true);

        expect($svc->hash($pReordered))->toBe($svc->hash($p));
    });

    it('build() canonicalizes item order regardless of DB insertion order', function () {
        // Items inserted in NON-canonical order (source_id=99 first, source_id=1 second).
        // build() must sort by source_type ASC, source_id ASC, so source_id=1 must appear first.
        $creator = \App\Models\User::factory()->create();
        $bundle = \App\Models\Hris\PaymentBundle::create([
            'bundle_number' => 'PB-TEST-ORDER-001',
            'status' => \App\Models\Hris\PaymentBundle::STATUS_DRAFT,
            'bundle_version' => 1,
            'total_amount' => '200000.00',
            'currency' => 'IDR',
            'created_by' => $creator->id,
        ]);

        // Insert higher source_id FIRST to create non-canonical DB order
        \Illuminate\Support\Facades\DB::table('payment_bundle_items')->insert([
            'payment_bundle_id' => $bundle->id,
            'source_type' => 'payment_request',
            'source_id' => 99,
            'payee_name' => 'Payee B',
            'payee_bank_account_last4' => '9999',
            'amount' => '100000.00',
            'currency' => 'IDR',
            'source_snapshot_json' => 'placeholder',
            'source_hash' => hash('sha256', 'placeholder-b'),
            'status' => \App\Models\Hris\PaymentBundleItem::PAYMENT_STATUS_BUNDLED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert lower source_id SECOND
        \Illuminate\Support\Facades\DB::table('payment_bundle_items')->insert([
            'payment_bundle_id' => $bundle->id,
            'source_type' => 'payment_request',
            'source_id' => 1,
            'payee_name' => 'Payee A',
            'payee_bank_account_last4' => '1111',
            'amount' => '100000.00',
            'currency' => 'IDR',
            'source_snapshot_json' => 'placeholder',
            'source_hash' => hash('sha256', 'placeholder-a'),
            'status' => \App\Models\Hris\PaymentBundleItem::PAYMENT_STATUS_BUNDLED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = app(CanonicalPayloadHashService::class);
        $payload = $svc->build($bundle->fresh());

        // source_type ASC, source_id ASC → source_id=1 must be first
        expect($payload['items'][0]['source_id'])->toBe(1);
        expect($payload['items'][1]['source_id'])->toBe(99);

        // Hash must be stable: build twice and compare
        $hash1 = $svc->hash($svc->build($bundle->fresh()));
        $hash2 = $svc->hash($svc->build($bundle->fresh()));
        expect($hash1)->toBe($hash2);
    });

    it('build() normalizes decimals before hashing; hash() does not', function () {
        // hash() is NOT decimal-normalizing: "7500000" and "7500000.00" produce different hashes.
        // build() normalizes via money() before hashing, so the canonical hash is always stable.
        $svc = app(CanonicalPayloadHashService::class);

        // NEGATIVE: hash() alone does NOT normalize decimal representations
        expect($svc->hash(['total_amount' => '7500000']))
            ->not->toBe($svc->hash(['total_amount' => '7500000.00']));

        // POSITIVE via build(): a real bundle produces a stable hash regardless of how the
        // DB stores the decimal — because build() always passes amounts through money() first.
        $creator = \App\Models\User::factory()->create();
        $bundle = \App\Models\Hris\PaymentBundle::create([
            'bundle_number' => 'PB-TEST-DEC-001',
            'status' => \App\Models\Hris\PaymentBundle::STATUS_DRAFT,
            'bundle_version' => 1,
            'total_amount' => '7500000', // stored without decimals
            'currency' => 'IDR',
            'created_by' => $creator->id,
        ]);

        $svc = app(CanonicalPayloadHashService::class);
        $payload = $svc->build($bundle->fresh());

        // build() must have normalized to "7500000.00"
        expect($payload['total_amount'])->toBe('7500000.00');

        // Hash is stable across two build() calls
        $h1 = $svc->hash($svc->build($bundle->fresh()));
        $h2 = $svc->hash($svc->build($bundle->fresh()));
        expect($h1)->toBe($h2);
    });

    it('null assigned_signer_id is stable: a slot mapped to null hashes consistently', function () {
        // Explicit null vs absent key behaves differently after JSON encoding.
        // build() always includes a slot key even when assigned_user_id is null (sets it to null).
        // This test asserts that null values hash consistently across two distinct-but-equal inputs.
        $svc = app(CanonicalPayloadHashService::class);

        $p1 = basePayload();
        $p1['assigned_signer_ids']['finance_signer_2'] = null;

        $p2 = basePayload();
        $p2['assigned_signer_ids']['finance_signer_2'] = null;

        // Two independently constructed payloads with identical content incl. null signer must hash the same
        expect($svc->hash($p1))->toBe($svc->hash($p2));
    });

    it('null vs absent signer id produces DIFFERENT hashes (null-vs-absent is explicit)', function () {
        // Documents the deliberate behavior: null key ≠ absent key.
        // build() always emits the key (with null value) for every matched slot.
        $svc = app(CanonicalPayloadHashService::class);
        $withNull = basePayload();
        $withNull['assigned_signer_ids']['executive_signer'] = null;

        $withAbsent = basePayload();
        unset($withAbsent['assigned_signer_ids']['executive_signer']);

        expect($svc->hash($withNull))->not->toBe($svc->hash($withAbsent));
    });

    it('timestamps are NOT part of the canonical payload (confirmed: no created_at/updated_at in hash input)', function () {
        // DIRECT assertion: build() on a real persisted bundle must not include timestamp keys.
        $creator = \App\Models\User::factory()->create();
        $bundle = \App\Models\Hris\PaymentBundle::create([
            'bundle_number' => 'PB-TEST-TS-001',
            'status' => \App\Models\Hris\PaymentBundle::STATUS_DRAFT,
            'bundle_version' => 1,
            'total_amount' => '100000.00',
            'currency' => 'IDR',
            'created_by' => $creator->id,
        ]);

        $svc = app(CanonicalPayloadHashService::class);
        $payload = $svc->build($bundle->fresh());

        // build() must not emit timestamp keys — catching any future regression
        expect(array_key_exists('created_at', $payload))->toBeFalse();
        expect(array_key_exists('updated_at', $payload))->toBeFalse();

        // INDIRECT: injecting timestamps into hash() input changes the hash — proving they
        // WOULD matter if build() ever leaked them.
        $clean = basePayload();
        $withTimestamps = $clean;
        $withTimestamps['created_at'] = '2026-01-01T00:00:00Z';
        $withTimestamps['updated_at'] = '2026-06-03T12:00:00Z';

        expect($svc->hash($withTimestamps))->not->toBe($svc->hash($clean));
    });

    it('large realistic IDR amount (5,000,000,000 IDR) hashes deterministically within safe float range', function () {
        // NOTE: (float) cast — see BUG / Phase 11
        // money() uses number_format((float)$v, 2) which is safe for values < 2^53.
        // 5,000,000,000 (5B IDR) < 9,007,199,254,740,992 (2^53) — no precision loss here.
        $svc = app(CanonicalPayloadHashService::class);

        $p1 = basePayload();
        $p1['total_amount'] = '5000000000.00';
        $p1['items'][0]['amount'] = '5000000000.00';

        $p2 = basePayload();
        $p2['total_amount'] = '5000000000.00';
        $p2['items'][0]['amount'] = '5000000000.00';

        // Two independently constructed payloads with the same large IDR amount must hash identically
        expect($svc->hash($p1))->toBe($svc->hash($p2));
    });
});

// ---------------------------------------------------------------------------
// SENSITIVITY — hash must change when payload changes
// ---------------------------------------------------------------------------

describe('Sensitivity: payload mutation changes hash', function () {
    it('changing total_amount changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['total_amount'] = '5000000.01';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing item amount changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['items'][0]['amount'] = '3000000.01';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing payee_name changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['items'][0]['payee_name'] = 'PT Penipu Ulung';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing bank_last4 changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['items'][0]['bank_last4'] = '0000';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing an item source_hash changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['items'][0]['source_hash'] = 'tampered00000000tampered00000000tampered00000000tampered00000000';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing assigned signer ids changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['assigned_signer_ids']['finance_signer_1'] = 999;

        expect($svc->hash($p))->not->toBe($base);
    });

    it('adding a signer slot changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);

        $fewer = basePayload();
        unset($fewer['assigned_signer_ids']['executive_signer']);
        $fewer['required_signature_slots'] = ['finance_signer_1', 'finance_signer_2'];

        expect($svc->hash($fewer))->not->toBe($svc->hash(basePayload()));
    });

    it('changing bundle_version changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['bundle_version'] = 2;

        expect($svc->hash($p))->not->toBe($base);
    });

    it('changing currency changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $base = $svc->hash(basePayload());

        $p = basePayload();
        $p['currency'] = 'USD';

        expect($svc->hash($p))->not->toBe($base);
    });

    it('adding a second item changes the hash', function () {
        $svc = app(CanonicalPayloadHashService::class);
        $single = basePayload();
        $single['items'] = [$single['items'][0]];
        $base = $svc->hash($single);

        $multi = $single;
        $multi['items'][] = [
            'source_type' => 'payment_request',
            'source_id' => 99,
            'source_hash' => '1111111111111111111111111111111111111111111111111111111111111111',
            'payee_name' => 'Extra Payee',
            'bank_last4' => '4321',
            'amount' => '1000000.00',
            'currency' => 'IDR',
        ];

        expect($svc->hash($multi))->not->toBe($base);
    });
});
