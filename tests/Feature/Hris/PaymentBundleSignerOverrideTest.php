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
 * Phases 6–7 — pre-first-signature signer reassignment + signing-time assigned-signer enforcement.
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

    $this->manager = User::factory()->create();
    $this->manager->assignRole('finance_creator');

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');

    $this->financeC = User::factory()->create();
    $this->financeC->assignRole('finance_signer');
    $this->financeD = User::factory()->create();
    $this->financeD->assignRole('finance_signer');
    $this->exec2 = User::factory()->create();
    $this->exec2->assignRole('executive_signer');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function overrideInkPng(): string
{
    return \Tests\Support\BatchPaymentTestFactory::inkPng();
}

function overrideApprovedPr(User $creator, User $coo, int $amount = 5000000): PaymentRequest
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

function submittedBundleForOverride(): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, overrideApprovedPr(test()->creator, test()->coo), test()->creator);

    return test()->bundles->submitForSignature($bundle->fresh(), [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ], test()->creator);
}

function newAssignmentForOverride(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeC->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeD->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec2->id,
    ];
}

function slotAssignee(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): ?int
{
    return $bundle->signatures()->where('signature_slot', $slot->value)->first()->assigned_user_id;
}

// ─── 1. Reassign before any signature ────────────────────────────────────────────

it('lets a manage_signers actor reassign the pending signers before any signature', function () {
    $bundle = submittedBundleForOverride();

    $bundle = $this->bundles->reassignSigners($bundle, newAssignmentForOverride(), $this->manager);

    expect(slotAssignee($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1))->toBe($this->financeC->id);
    expect(slotAssignee($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2))->toBe($this->financeD->id);
    expect(slotAssignee($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER))->toBe($this->exec2->id);

    foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
        $sig = $bundle->signatures()->where('signature_slot', $slot->value)->first();
        expect($sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
        expect($sig->signature_token_hash)->not->toBeNull();
    }
});

// ─── 2. Reassign blocked after the first signature ───────────────────────────────

it('refuses to reassign after the first signature has been captured', function () {
    $bundle = submittedBundleForOverride();

    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    $this->signatures->submitSignature($f1, $f1->assignedUser, overrideInkPng());

    expect(fn () => $this->bundles->reassignSigners($bundle->fresh(), newAssignmentForOverride(), $this->manager))
        ->toThrow(RuntimeException::class, 'Signers cannot be changed after the first signature has been captured.');

    expect(slotAssignee($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_2))->toBe($this->financeB->id);
});

// ─── 3. Reassign refused for a non-manager ───────────────────────────────────────

it('refuses to reassign for an actor without manage_signers', function () {
    $bundle = submittedBundleForOverride();
    $outsider = User::factory()->create();
    $outsider->assignRole('finance_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $this->bundles->reassignSigners($bundle, newAssignmentForOverride(), $outsider))
        ->toThrow(RuntimeException::class, 'You are not allowed to reassign signers.');

    expect(slotAssignee($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_1))->toBe($this->financeA->id);
});

// ─── 4. Assigned signer can sign ─────────────────────────────────────────────────

it('lets the assigned signer sign their slot', function () {
    $bundle = submittedBundleForOverride();
    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();

    $signed = $this->signatures->submitSignature($f1, $this->financeA, overrideInkPng());

    expect($signed->status)->toBe(PaymentBundleSignature::STATUS_SIGNED);
    expect($signed->signed_by)->toBe($this->financeA->id);
});

// ─── 5. A non-assigned (but permissioned) user cannot sign ───────────────────────

it('blocks a permissioned-but-not-assigned user from signing an assigned slot', function () {
    $bundle = submittedBundleForOverride();
    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();

    expect(fn () => $this->signatures->submitSignature($f1, $this->financeC, overrideInkPng()))
        ->toThrow(RuntimeException::class, 'You are not the assigned signer for this slot.');

    expect($f1->fresh()->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 6. Audit row on reassign ────────────────────────────────────────────────────

it('writes a payment_bundle_signers_changed audit row with before/after ids', function () {
    $bundle = submittedBundleForOverride();

    $this->bundles->reassignSigners($bundle, newAssignmentForOverride(), $this->manager);

    $log = FinanceAuditLog::where('event_type', 'payment_bundle_signers_changed')
        ->where('auditable_id', $bundle->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->before_snapshot)->toBe([
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $this->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $this->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $this->exec->id,
    ]);
    expect($log->after_snapshot)->toBe([
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $this->financeC->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $this->financeD->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $this->exec2->id,
    ]);
});

// ─── 7. Defensive fallback: null assigned_user_id falls back to permission ───────

it('falls back to the slot permission when assigned_user_id is null', function () {
    $bundle = submittedBundleForOverride();
    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    $f1->forceFill(['assigned_user_id' => null])->save();

    $signed = $this->signatures->submitSignature($f1->fresh(), $this->financeA, overrideInkPng());
    expect($signed->status)->toBe(PaymentBundleSignature::STATUS_SIGNED);
});

it('rejects a null-assigned slot when the signer lacks the slot permission', function () {
    $bundle = submittedBundleForOverride();
    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    $f1->forceFill(['assigned_user_id' => null])->save();

    $noPerm = User::factory()->create();

    expect(fn () => $this->signatures->submitSignature($f1->fresh(), $noPerm, overrideInkPng()))
        ->toThrow(RuntimeException::class, 'You do not have permission to sign this slot.');

    expect($f1->fresh()->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 8. Reassign requires a signature-pending state ──────────────────────────────

it('refuses to reassign once the bundle is no longer awaiting signatures', function () {
    $bundle = submittedBundleForOverride();
    $bundle->update(['status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT]);

    expect(fn () => $this->bundles->reassignSigners($bundle->fresh(), newAssignmentForOverride(), $this->manager))
        ->toThrow(RuntimeException::class, 'Signers can only be reassigned while the bundle is awaiting signatures.');
});
