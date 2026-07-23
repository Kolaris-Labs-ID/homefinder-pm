<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Filament\Resources\PaymentBundleResource\Pages\ViewPaymentBundle;
use App\Models\Hris\PaymentBundle;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

/**
 * Minimal Filament UI for viewing/copying Payment Bundle signing links (ViewPaymentBundle page).
 * Visibility is gated by payment_bundles.manage_signers (the same permission that authorizes
 * reassigning signers / reissuing links), and each slot falls back to a status string once its
 * token is no longer live.
 */
beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

function pslDraftBundleWithPr($creator): PaymentBundle
{
    $bundle = app(PaymentBundleService::class)->create($creator, ['title' => 'Signing Link Test Bundle']);

    $coo = FinanceCenterFlowFactory::coo();
    $prSvc = app(PaymentRequestService::class);
    $pr = $prSvc->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Signing Link Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => 2000000,
        'currency' => 'IDR',
        'description' => 'Signing link test PR',
    ]);
    $prSvc->submitToCoo($pr);
    $prSvc->approveAsCoo($pr, $coo);

    app(PaymentBundleService::class)->addPaymentRequest($bundle, $pr->fresh(), $creator);

    return $bundle->fresh();
}

function pslSubmittedBundle(): array
{
    $creator = FinanceCenterFlowFactory::financeCreator();
    $bundle = pslDraftBundleWithPr($creator);

    $fs1 = FinanceCenterFlowFactory::financeSigner();
    $fs2 = FinanceCenterFlowFactory::financeSigner();
    $exec = FinanceCenterFlowFactory::executiveSigner();

    $bundle = app(PaymentBundleService::class)->submitForSignature($bundle, [
        'finance_signer_1' => $fs1->id,
        'finance_signer_2' => $fs2->id,
        'executive_signer' => $exec->id,
    ], $creator);

    return [$bundle->fresh(), $creator, $fs1, $fs2, $exec];
}

it('authorized user sees the signing link for a pending slot', function () {
    [$bundle, $creator] = pslSubmittedBundle();

    $link = $bundle->metadata['signing_links'][PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value];

    Livewire::actingAs($creator)
        ->test(ViewPaymentBundle::class, ['record' => $bundle->id])
        ->assertSuccessful()
        ->assertSee($link);
});

it('unauthorized user does not see signing links', function () {
    [$bundle, $creator, $fs1] = pslSubmittedBundle();

    $link = $bundle->metadata['signing_links'][PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value];

    Livewire::actingAs($fs1)
        ->test(ViewPaymentBundle::class, ['record' => $bundle->id])
        ->assertSuccessful()
        ->assertDontSee($link)
        ->assertDontSee('Signing link');
});

it('a slot with a consumed or revoked token shows its status instead of a link', function () {
    [$bundle, $creator] = pslSubmittedBundle();
    $svc = app(PaymentBundleSignatureService::class);

    $sig1 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->firstOrFail();
    $sig2 = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value)->firstOrFail();
    $link1 = $bundle->metadata['signing_links'][PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value];
    $link2 = $bundle->metadata['signing_links'][PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value];

    $svc->consumeToken($sig1);
    $svc->revokeToken($sig2);

    Livewire::actingAs($creator)
        ->test(ViewPaymentBundle::class, ['record' => $bundle->id])
        ->assertSuccessful()
        ->assertDontSee($link1)
        ->assertDontSee($link2)
        ->assertSee('Used')
        ->assertSee('Revoked');
});
