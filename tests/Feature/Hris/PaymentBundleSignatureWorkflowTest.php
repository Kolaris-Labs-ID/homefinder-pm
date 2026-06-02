<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 8 — Sequential signature workflow: submit-for-signature (assignment + SoD), status-driven
 * ordering (finance_1 → finance_2 → executive), sign-time SoD, and authorize-exactly-once.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
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

function workflowInkPng(): string
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

function approvedPrForBundle(User $creator, User $coo, int $amount = 5000000): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Office supplies',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, $coo);

    return $pr->fresh();
}

function draftBundleWithItem(int $amount = 5000000): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, approvedPrForBundle(test()->creator, test()->coo, $amount), test()->creator);

    return $bundle->fresh();
}

function validAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function signSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): PaymentBundleSignature
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();

    return test()->signatures->submitSignature($sig, $sig->assignedUser, workflowInkPng());
}

// ─── 1. submitForSignature happy path ───────────────────────────────────────────

it('creates three pending, assigned signature rows and moves the bundle to pending_finance_signature_1', function () {
    $bundle = draftBundleWithItem();

    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
    expect($bundle->signatures()->count())->toBe(3);

    foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
        $sig = $bundle->signatures()->where('signature_slot', $slot->value)->first();
        expect($sig)->not->toBeNull();
        expect($sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
        expect($sig->required_permission)->toBe($slot->requiredPermission());
        expect($sig->assigned_user_id)->not->toBeNull();
        expect($sig->signature_token_hash)->not->toBeNull();
        expect(strlen($sig->signature_token_hash))->toBe(64);
    }

    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    expect($f1->assigned_user_id)->toBe($this->financeA->id);

    // Unsigned PDF + canonical payload frozen at submit.
    expect($bundle->unsigned_pdf_attachment_id)->not->toBeNull();
    expect($bundle->canonical_payload_hash)->not->toBeNull();
    expect($bundle->submitted_at)->not->toBeNull();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.submitted_for_signature')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

// ─── 2. SoD at submit ───────────────────────────────────────────────────────────

it('rejects identical finance signers at submit', function () {
    $bundle = draftBundleWithItem();
    $a = validAssignment();
    $a[PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value] = $this->financeA->id;

    expect(fn () => $this->bundles->submitForSignature($bundle, $a, $this->creator))
        ->toThrow(RuntimeException::class);

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_DRAFT);
    expect($bundle->signatures()->count())->toBe(0);
});

it('rejects the executive equal to a finance signer at submit', function () {
    $bundle = draftBundleWithItem();
    $a = validAssignment();
    $a[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value] = $this->financeA->id;

    expect(fn () => $this->bundles->submitForSignature($bundle, $a, $this->creator))
        ->toThrow(RuntimeException::class);
});

it('rejects an assigned signer who lacks the slot permission at submit', function () {
    $bundle = draftBundleWithItem();
    $noPerm = User::factory()->create(); // no role/permission
    $a = validAssignment();
    $a[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value] = $noPerm->id;

    expect(fn () => $this->bundles->submitForSignature($bundle, $a, $this->creator))
        ->toThrow(RuntimeException::class);
});

// ─── 3. High-value SoD (creator != executive above threshold) ───────────────────

it('rejects creator-as-executive on a high-value bundle', function () {
    // Creator holds the executive permission and is assigned as executive on a >= 10M bundle.
    $this->creator->assignRole('executive_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $bundle = draftBundleWithItem(10000000);
    $a = validAssignment();
    $a[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value] = $this->creator->id;

    expect(fn () => $this->bundles->submitForSignature($bundle, $a, $this->creator))
        ->toThrow(RuntimeException::class);
});

it('allows creator-as-executive on a low-value bundle (threshold gates the rule)', function () {
    $this->creator->assignRole('executive_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $bundle = draftBundleWithItem(5000000); // below 10M
    $a = validAssignment();
    $a[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value] = $this->creator->id;

    $bundle = $this->bundles->submitForSignature($bundle, $a, $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
});

// ─── 4. Sequential happy path → authorized exactly once ─────────────────────────

it('advances finance_1 → finance_2 → executive and authorizes exactly once', function () {
    $bundle = draftBundleWithItem();
    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2);

    signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE);

    signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($bundle->authorized_at)->not->toBeNull();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.authorized')
        ->where('auditable_id', $bundle->id)->count())->toBe(1);
});

// ─── 5. Out-of-order blocked ────────────────────────────────────────────────────

it('blocks the executive from signing before finance', function () {
    $bundle = draftBundleWithItem();
    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    expect(fn () => signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER))
        ->toThrow(RuntimeException::class);

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
});

it('blocks finance_2 before finance_1', function () {
    $bundle = draftBundleWithItem();
    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    expect(fn () => signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2))
        ->toThrow(RuntimeException::class);
});

// ─── 6. Authorize idempotent ────────────────────────────────────────────────────

it('does not re-authorize or change authorized_at when advance runs again', function () {
    $bundle = draftBundleWithItem();
    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $authorizedAt = $bundle->fresh()->authorized_at;

    // Re-run the advance directly against the already-authorized bundle.
    $this->signatures->advanceAfterSignature($bundle->fresh());

    $bundle->refresh();
    expect($bundle->authorized_at->equalTo($authorizedAt))->toBeTrue();
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.authorized')
        ->where('auditable_id', $bundle->id)->count())->toBe(1);
});

// ─── 7. Sign-time SoD: same human can't sign two slots ──────────────────────────

it('blocks the same human from signing two slots even if assignments were tampered', function () {
    $bundle = draftBundleWithItem();
    $bundle = $this->bundles->submitForSignature($bundle, validAssignment(), $this->creator);

    // financeA legitimately signs finance_1.
    signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2);

    // Tamper: reassign finance_2 to financeA (who already signed finance_1).
    $f2 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value)->first();
    $f2->forceFill(['assigned_user_id' => $this->financeA->id])->save();

    expect(fn () => $this->signatures->submitSignature($f2->fresh(), $this->financeA, workflowInkPng()))
        ->toThrow(RuntimeException::class);

    expect($f2->fresh()->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2);
});
