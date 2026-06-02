<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 5 — submit-for-signature defaults from Payment Authorization Settings + the
 * payment_bundle_signers_assigned audit event. Explicit (non-empty) assignments keep the
 * pre-existing behavior; only a fully-empty assignment array consults settings.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
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

function signerInkPng(): string
{
    return \Tests\Support\BatchPaymentTestFactory::inkPng();
}

function approvedPrForSignerTest(User $creator, User $coo, int $amount = 5000000): PaymentRequest
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

function draftBundleForSignerTest(int $amount = 5000000): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, approvedPrForSignerTest(test()->creator, test()->coo, $amount), test()->creator);

    return $bundle->fresh();
}

function setSettingsTo(?int $f1, ?int $f2, ?int $exec): void
{
    PaymentAuthorizationSetting::current()->update([
        'finance_signer_1_user_id' => $f1,
        'finance_signer_2_user_id' => $f2,
        'executive_signer_user_id' => $exec,
    ]);
}

it('falls back to configured default signers when no assignments are passed', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, $this->exec->id);

    $bundle = draftBundleForSignerTest();
    $bundle = $this->bundles->submitForSignature($bundle, [], $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
    expect($bundle->signatures()->count())->toBe(3);

    $byslot = fn (PaymentBundleSignatureSlot $s) => $bundle->signatures()
        ->where('signature_slot', $s->value)->first()->assigned_user_id;

    expect($byslot(PaymentBundleSignatureSlot::FINANCE_SIGNER_1))->toBe($this->financeA->id);
    expect($byslot(PaymentBundleSignatureSlot::FINANCE_SIGNER_2))->toBe($this->financeB->id);
    expect($byslot(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER))->toBe($this->exec->id);
});

it('throws the incomplete-settings message when no assignments are passed and settings are incomplete', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, null);

    $bundle = draftBundleForSignerTest();

    expect(fn () => $this->bundles->submitForSignature($bundle, [], $this->creator))
        ->toThrow(
            RuntimeException::class,
            'Payment Authorization Settings are incomplete. Please assign Finance Signer 1, Finance Signer 2, and Executive Signer before submitting this bundle.'
        );

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_DRAFT);
    expect($bundle->signatures()->count())->toBe(0);
});

it('validates settings-sourced signers and throws when a default signer lacks permission', function () {
    $noPerm = User::factory()->create();
    setSettingsTo($noPerm->id, $this->financeB->id, $this->exec->id);

    $bundle = draftBundleForSignerTest();

    expect(fn () => $this->bundles->submitForSignature($bundle, [], $this->creator))
        ->toThrow(
            RuntimeException::class,
            'Assigned Finance Signer 1 lacks the required permission (payment_bundles.sign.finance).'
        );

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_DRAFT);
    expect($bundle->signatures()->count())->toBe(0);
});

it('does not mutate an already-submitted bundle when settings change afterwards', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, $this->exec->id);

    $bundle = draftBundleForSignerTest();
    $bundle = $this->bundles->submitForSignature($bundle, [], $this->creator);

    $before = $bundle->signatures()->get()
        ->mapWithKeys(fn ($s) => [$s->signature_slot->value => $s->assigned_user_id])
        ->all();

    $newF1 = User::factory()->create();
    $newF1->assignRole('finance_signer');
    $newF2 = User::factory()->create();
    $newF2->assignRole('finance_signer');
    $newExec = User::factory()->create();
    $newExec->assignRole('executive_signer');
    setSettingsTo($newF1->id, $newF2->id, $newExec->id);

    $after = $bundle->fresh()->signatures()->get()
        ->mapWithKeys(fn ($s) => [$s->signature_slot->value => $s->assigned_user_id])
        ->all();

    expect($after)->toBe($before);
    expect($after[PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value])->toBe($this->financeA->id);
});

it('writes a payment_bundle_signers_assigned audit event on submit', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, $this->exec->id);

    $bundle = draftBundleForSignerTest();
    $bundle = $this->bundles->submitForSignature($bundle, [], $this->creator);

    $log = FinanceAuditLog::where('event_type', 'payment_bundle_signers_assigned')
        ->where('auditable_id', $bundle->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->after_snapshot['finance_signer_1'])->toBe($this->financeA->id);
    expect($log->after_snapshot['finance_signer_2'])->toBe($this->financeB->id);
    expect($log->after_snapshot['executive_signer'])->toBe($this->exec->id);
    expect($log->after_snapshot['bundle_number'])->toBe($bundle->bundle_number);
});

it('uses explicit assignments and does not consult settings when assignments are non-empty', function () {
    $bundle = draftBundleForSignerTest();

    $bundle = $this->bundles->submitForSignature($bundle, [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $this->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $this->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $this->exec->id,
    ], $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);

    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    expect($f1->assigned_user_id)->toBe($this->financeA->id);

    expect(PaymentAuthorizationSetting::current()->finance_signer_1_user_id)->toBeNull();
});

it('overrides a non-manager caller-supplied signers with the configured defaults', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, $this->exec->id);

    $otherF1 = User::factory()->create();
    $otherF1->assignRole('finance_signer');
    $otherF2 = User::factory()->create();
    $otherF2->assignRole('finance_signer');
    $otherExec = User::factory()->create();
    $otherExec->assignRole('executive_signer');

    $bundle = draftBundleForSignerTest();

    $bundle = $this->bundles->submitForSignature($bundle, [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $otherF1->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $otherF2->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $otherExec->id,
    ], $this->creator);

    $byslot = fn (PaymentBundleSignatureSlot $s) => $bundle->signatures()
        ->where('signature_slot', $s->value)->first()->assigned_user_id;

    expect($byslot(PaymentBundleSignatureSlot::FINANCE_SIGNER_1))->toBe($this->financeA->id);
    expect($byslot(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER))->toBe($this->exec->id);
});

it('honors caller-supplied signers when the actor may manage signers', function () {
    setSettingsTo($this->financeA->id, $this->financeB->id, $this->exec->id);

    $manager = User::factory()->create();
    $manager->assignRole('finance_creator');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $otherF1 = User::factory()->create();
    $otherF1->assignRole('finance_signer');
    $otherF2 = User::factory()->create();
    $otherF2->assignRole('finance_signer');
    $otherExec = User::factory()->create();
    $otherExec->assignRole('executive_signer');

    $bundle = draftBundleForSignerTest();

    $bundle = $this->bundles->submitForSignature($bundle, [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $otherF1->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $otherF2->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $otherExec->id,
    ], $manager);

    $f1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    expect($f1->assigned_user_id)->toBe($otherF1->id);
});
