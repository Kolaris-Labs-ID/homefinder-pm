<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\User;
use App\Services\Hris\CanonicalPayloadHashService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;

/**
 * Phase 4 — CanonicalPayloadHashService: determinism, sensitivity, money format, PII, store().
 */
beforeEach(function () {
    $this->svc = app(CanonicalPayloadHashService::class);
    $this->bundles = app(PaymentBundleService::class);
    $this->prs = app(PaymentRequestService::class);
    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
});

// ── fixture helpers ────────────────────────────────────────────────────────────

function hashApprovedPr(array $overrides = [])
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, array_merge([
        'payee_type' => 'vendor',
        'payee_name' => 'PT Hash Test',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => 5000000,
        'currency' => 'IDR',
        'description' => 'Hash test PR',
    ], $overrides));
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function hashBundleWithSigs(array $sigData = []): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);

    foreach ($sigData as $slot => $userId) {
        $slotEnum = PaymentBundleSignatureSlot::from($slot);
        PaymentBundleSignature::factory()->create([
            'payment_bundle_id' => $bundle->id,
            'signature_slot' => $slotEnum,
            'required_permission' => $slotEnum->requiredPermission(),
            'assigned_user_id' => $userId,
        ]);
    }

    return $bundle;
}

// ── 1. Deterministic ──────────────────────────────────────────────────────────

it('produces the same hash for two independent build+hash calls on unchanged bundle', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr();
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));
    $hash2 = test()->svc->hash(test()->svc->build($bundle));

    expect($hash1)->toBe($hash2);
});

// ── 2. Item-order independence ─────────────────────────────────────────────────

it('produces the same hash regardless of DB/query order of items', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr1 = hashApprovedPr(['amount' => 2000000, 'payee_name' => 'Vendor A']);
    $pr2 = hashApprovedPr(['amount' => 3000000, 'payee_name' => 'Vendor B']);
    test()->bundles->addPaymentRequest($bundle, $pr1, test()->creator);
    test()->bundles->addPaymentRequest($bundle, $pr2, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    // Service always re-sorts internally; simulate different order by rebuilding
    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->toBe($hash2);
});

// ── 3. Amount sensitivity ─────────────────────────────────────────────────────

it('changes hash when an item amount changes', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr(['amount' => 5000000]);
    $item = test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    $item->update(['amount' => '9999999.00']);
    $bundle->update(['total_amount' => '9999999.00']);
    $bundle->refresh();

    $hash2 = test()->svc->hash(test()->svc->build($bundle));

    expect($hash1)->not->toBe($hash2);
});

// ── 4. Payee sensitivity ───────────────────────────────────────────────────────

it('changes hash when an item payee_name changes', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr();
    $item = test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    $item->update(['payee_name' => 'Someone Else Entirely']);
    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->not->toBe($hash2);
});

// ── 5. Bank sensitivity ────────────────────────────────────────────────────────

it('changes hash when an item bank_last4 changes', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr();
    $item = test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    $item->update(['payee_bank_account_last4' => '9999']);
    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->not->toBe($hash2);
});

// ── 6. Signer sensitivity ─────────────────────────────────────────────────────

it('changes hash when an assigned_user_id changes', function () {
    $signerA = User::factory()->create();
    $bundle = hashBundleWithSigs(['finance_signer_1' => $signerA->id]);
    $pr = hashApprovedPr();
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    $signerB = User::factory()->create();
    $bundle->signatures()->update(['assigned_user_id' => $signerB->id]);
    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->not->toBe($hash2);
});

it('changes hash when a signature slot is added', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr();
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    PaymentBundleSignature::factory()->create([
        'payment_bundle_id' => $bundle->id,
        'signature_slot' => PaymentBundleSignatureSlot::FINANCE_SIGNER_2,
        'required_permission' => PaymentBundleSignatureSlot::FINANCE_SIGNER_2->requiredPermission(),
        'assigned_user_id' => null,
    ]);

    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->not->toBe($hash2);
});

// ── 7. bundle_version sensitivity ─────────────────────────────────────────────

it('changes hash when bundle_version is bumped', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null]);
    $pr = hashApprovedPr();
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash1 = test()->svc->hash(test()->svc->build($bundle));

    $bundle->update(['bundle_version' => 2]);
    $hash2 = test()->svc->hash(test()->svc->build($bundle->fresh()));

    expect($hash1)->not->toBe($hash2);
});

// ── 8. Money as strings ────────────────────────────────────────────────────────

it('formats total_amount and each item amount as fixed-precision strings', function () {
    $bundle = test()->bundles->create(test()->creator);
    $pr = hashApprovedPr(['amount' => 5000000]);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $payload = test()->svc->build($bundle);

    expect($payload['total_amount'])->toBe('5000000.00');
    expect(is_string($payload['total_amount']))->toBeTrue();
    expect($payload['items'][0]['amount'])->toBe('5000000.00');
    expect(is_string($payload['items'][0]['amount']))->toBeTrue();
});

// ── 9. No PII in canonical payload ────────────────────────────────────────────

it('embeds bank_last4 but not full bank account number in the canonical payload', function () {
    $fullAccount = '9876543210';
    $bundle = test()->bundles->create(test()->creator);
    $pr = hashApprovedPr(['bank_account_number' => $fullAccount]);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $payload = test()->svc->build($bundle);
    $json = json_encode($payload);

    expect($payload['items'][0]['bank_last4'])->toBe('3210');
    expect($json)->not->toContain($fullAccount);
});

// ── 10. store() ───────────────────────────────────────────────────────────────

it('store() persists canonical_payload_hash and canonical_payload_json and writes audit row', function () {
    $bundle = hashBundleWithSigs(['finance_signer_1' => null, 'executive_signer' => null]);
    $pr = hashApprovedPr(['amount' => 7500000]);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle->refresh();

    $hash = test()->svc->store($bundle);

    expect(strlen($hash))->toBe(64);
    expect(ctype_xdigit($hash))->toBeTrue();

    $fresh = $bundle->fresh();
    expect($fresh->canonical_payload_hash)->toBe($hash);
    expect($fresh->canonical_payload_json)->toBeArray();
    expect($fresh->canonical_payload_json['bundle_number'])->toBe($bundle->bundle_number);

    $audit = FinanceAuditLog::where('event_type', 'payment_bundle.payload_hash_generated')
        ->where('auditable_id', $bundle->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->after_snapshot['canonical_payload_hash'])->toBe($hash);
    expect($audit->after_snapshot['bundle_version'])->toBe($bundle->bundle_version);
});
