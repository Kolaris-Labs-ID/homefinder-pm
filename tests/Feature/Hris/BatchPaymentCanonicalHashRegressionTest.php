<?php

use App\Services\Hris\CanonicalPayloadHashService;
use App\Support\Finance\PaymentBundleSnapshot;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 16 — Hash regression. The canonical payload hash and the batch source-snapshot hash must be
 * byte-for-byte stable. Adding batch support must not silently change the pre-batch hash format.
 */
function regressionPayload(): array
{
    return [
        'bundle_number' => 'PB-202606-0001',
        'bundle_version' => 1,
        'currency' => 'IDR',
        'total_amount' => '5000000.00',
        'required_signature_slots' => ['finance_signer_1', 'finance_signer_2', 'executive_signer'],
        'assigned_signer_ids' => ['finance_signer_1' => 1, 'finance_signer_2' => 2, 'executive_signer' => 3],
        'items' => [[
            'source_type' => 'payment_request',
            'source_id' => 1,
            'source_hash' => 'frozenhash123',
            'payee_name' => 'PT Vendor Sejahtera',
            'bank_last4' => '3210',
            'amount' => '5000000.00',
            'currency' => 'IDR',
        ]],
    ];
}

it('keeps the pre-batch canonical payload hash byte-for-byte stable', function () {
    $hash = app(CanonicalPayloadHashService::class)->hash(regressionPayload());

    expect($hash)->toBe('086a3df03fd49e4fa1a25c4811fe532e51856948a1556d4aaf54ae03689cd699');
});

it('is order-independent: payload key order does not change the hash', function () {
    $svc = app(CanonicalPayloadHashService::class);
    $payload = regressionPayload();

    expect($svc->hash(array_reverse($payload, true)))->toBe($svc->hash($payload));
});

it('changes the canonical hash when amount, payee, bank last4, or source hash changes', function () {
    $svc = app(CanonicalPayloadHashService::class);
    $base = $svc->hash(regressionPayload());

    foreach ([
        fn (array &$p) => $p['items'][0]['amount'] = '5000000.01',
        fn (array &$p) => $p['items'][0]['payee_name'] = 'PT Other',
        fn (array &$p) => $p['items'][0]['bank_last4'] = '9999',
        fn (array &$p) => $p['items'][0]['source_hash'] = 'tampered',
    ] as $mutate) {
        $p = regressionPayload();
        $mutate($p);
        expect($svc->hash($p))->not->toBe($base);
    }
});

it('produces a deterministic, repeatable batch source-snapshot hash', function () {
    $batch = F::weeklyAllowance(F::employee(), '125000.00');

    $a = PaymentBundleSnapshot::forWeeklyAllowanceBatch($batch->fresh());
    $b = PaymentBundleSnapshot::forWeeklyAllowanceBatch($batch->fresh());

    expect($a['snapshot_version'])->toBe(1);
    expect($a['source_hash'])->toBe($b['source_hash']);
    expect(PaymentBundleSnapshot::hash($a))->toBe(PaymentBundleSnapshot::hash($b));
});
