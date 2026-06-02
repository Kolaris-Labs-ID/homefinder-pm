<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 10 — Payment execution from the frozen snapshot: beginPaymentRecording transitions an
 * authorized + signed bundle to payment_recording and returns instructions read ONLY from each
 * item's frozen snapshot (never the live source). Drift is detected, audited, and blocking.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');
});

// ─── helpers (unique names) ─────────────────────────────────────────────────────

function payExecInkPng(): string
{
    $img = imagecreatetruecolor(100, 50);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagealphablending($img, true);
    $black = imagecolorallocate($img, 0, 0, 0);
    imageline($img, 5, 25, 95, 25, $black);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

function payExecApprovedPr(int $amount = 5000000, string $account = '1234567890'): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Frozen Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => $account,
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Office supplies',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function payExecValidAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function payExecSignSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): PaymentBundleSignature
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();

    return test()->signatures->submitSignature($sig, $sig->assignedUser, payExecInkPng());
}

/** Build a bundle all the way to authorized_for_payment (signed PDF present) from one PR. */
function payExecAuthorizedBundle(PaymentRequest $pr): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle = test()->bundles->submitForSignature($bundle->fresh(), payExecValidAssignment(), test()->creator);

    payExecSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    payExecSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    payExecSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    return $bundle->fresh();
}

/** Mutate a live PR's frozen-relevant fields (incl. its encrypted bank account) directly. */
function payExecMutateLivePr(PaymentRequest $pr): void
{
    $pr->update(['payee_name' => 'CHANGED', 'amount' => '1.00', 'bank_name' => 'CHANGED_BANK']);
    $pr->forceFill([
        'bank_account_number_encrypted' => Crypt::encryptString('0000000000'),
        'bank_account_last4' => '0000',
    ])->save();
}

// ─── 1. beginPaymentRecording happy path ────────────────────────────────────────

it('moves an authorized + signed bundle to payment_recording and returns frozen instructions', function () {
    $pr = payExecApprovedPr(5000000, '9876543210');
    $bundle = payExecAuthorizedBundle($pr);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($bundle->signed_pdf_attachment_id)->not->toBeNull();

    $instructions = $this->payments->beginPaymentRecording($bundle, $this->creator);

    $bundle->refresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_PAYMENT_RECORDING);
    expect($bundle->items()->where('status', PaymentBundleItem::STATUS_PAYMENT_RECORDING)->count())->toBe(1);
    expect($bundle->items()->where('status', PaymentBundleItem::STATUS_ACTIVE)->count())->toBe(0);

    expect($instructions)->toHaveCount(1);
    expect($instructions[0]['payee_name'])->toBe('PT Frozen Vendor');
    expect($instructions[0]['bank_account_number'])->toBe('9876543210');
    expect($instructions[0]['amount'])->toBe('5000000.00');
    expect($instructions[0]['currency'])->toBe('IDR');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.payment_recording_started')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'batch_bank_detail_revealed')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

// ─── 2. Payment uses the frozen snapshot, not the live source ───────────────────

it('returns the original snapshot values even after the live source is mutated', function () {
    $pr = payExecApprovedPr(5000000, '9876543210');
    $bundle = payExecAuthorizedBundle($pr);

    payExecMutateLivePr($pr->fresh());

    $instructions = $this->payments->paymentInstructions($bundle);
    expect($instructions[0]['payee_name'])->toBe('PT Frozen Vendor');
    expect($instructions[0]['amount'])->toBe('5000000.00');
    expect($instructions[0]['bank_account_number'])->toBe('9876543210');
    expect($instructions[0]['bank_name'])->toBe('BCA'); // frozen, not the mutated live 'CHANGED_BANK'

    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');
});

// ─── 3. Drift detection ──────────────────────────────────────────────────────────

it('detects drift, audits it, and blocks payment recording when the live source changed', function () {
    $pr = payExecApprovedPr(5000000, '9876543210');
    $bundle = payExecAuthorizedBundle($pr);
    $item = $bundle->items()->first();

    payExecMutateLivePr($pr->fresh());

    expect($this->payments->detectDrift($item->fresh()))->toBeTrue();

    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');

    expect(FinanceAuditLog::where('event_type', 'batch_source_drift_detected')
        ->where('auditable_id', $bundle->id)->count())->toBe(1);
});

it('reports no drift and writes no drift audit when the live source is unchanged', function () {
    $pr = payExecApprovedPr(5000000, '9876543210');
    $bundle = payExecAuthorizedBundle($pr);
    $item = $bundle->items()->first();

    expect($this->payments->detectDrift($item))->toBeFalse();

    $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator);

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.source_drift_detected')
        ->where('auditable_id', $bundle->id)->exists())->toBeFalse();
});

// ─── 4. Unsigned / partially-signed cannot be paid ──────────────────────────────

it('refuses to begin payment recording on a partially-signed bundle', function () {
    $pr = payExecApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), payExecValidAssignment(), $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);

    expect(fn () => $this->payments->beginPaymentRecording($bundle, $this->creator))
        ->toThrow(RuntimeException::class);

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
});

it('refuses to begin payment recording on a draft bundle', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, payExecApprovedPr(), $this->creator);

    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class);
});

// ─── 5. Signed PDF required ──────────────────────────────────────────────────────

it('refuses to begin payment recording when the signed PDF is missing', function () {
    $pr = payExecApprovedPr();
    $bundle = payExecAuthorizedBundle($pr);

    $bundle->forceFill(['signed_pdf_attachment_id' => null])->save();

    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class);

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
});

// ─── 6. Full account read from the frozen snapshot ──────────────────────────────

it('returns the full bank account from the frozen snapshot', function () {
    $fullAccount = '9876543210';
    $pr = payExecApprovedPr(5000000, $fullAccount);
    $bundle = payExecAuthorizedBundle($pr);

    $instructions = $this->payments->paymentInstructions($bundle);

    expect($instructions[0]['bank_account_number'])->toBe($fullAccount);
});
