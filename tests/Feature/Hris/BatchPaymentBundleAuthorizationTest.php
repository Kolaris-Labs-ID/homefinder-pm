<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 13/14 — Authorization flow for batch bundles: submit, drift gates, signed identity, and the
 * requirement that only a fully-signed (authorized) bundle can be marked paid.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);

    $this->creator = User::factory()->create();
    $this->financeA = tap(User::factory()->create())->assignRole('finance_signer');
    $this->financeB = tap(User::factory()->create())->assignRole('finance_signer');
    $this->exec = tap(User::factory()->create())->assignRole('executive_signer');
    $this->payer = User::factory()->create();
    $this->employee = F::employee();
});

function authAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

it('submits a batch bundle for signature', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->creator);

    $bundle = $this->bundles->submitForSignature($bundle->fresh(), authAssignment(), $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
    expect($bundle->signatures()->count())->toBe(3);
});

it('blocks submit-for-signature when the batch source has drifted', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $batch->update(['total_amount' => '999999.00']); // live source drifts from the frozen snapshot

    expect(fn () => $this->bundles->submitForSignature($bundle->fresh(), authAssignment(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');
});

it('binds the signed PDF identity to the canonical payload hash on authorization', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($bundle->signed_pdf_attachment_id)->not->toBeNull();
    expect($bundle->signed_payload_hash)->toBe($bundle->canonical_payload_hash);
});

it('refuses to mark a draft batch bundle paid', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->creator);

    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf')))
        ->toThrow(RuntimeException::class, 'Only an authorized/recording/proof-rejected bundle can be marked paid');
});

it('refuses to mark a partially-signed batch bundle paid', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), authAssignment(), $this->creator);

    // Sign only the first finance slot.
    $sig = $bundle->fresh()->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->firstOrFail();
    $this->signatures->submitSignature($sig, $sig->assignedUser, F::inkPng());

    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf')))
        ->toThrow(RuntimeException::class, 'Only an authorized/recording/proof-rejected bundle can be marked paid');
});
