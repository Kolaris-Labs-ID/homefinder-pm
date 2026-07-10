<?php

use App\Filament\Pages\PaymentApprovalView;
use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentRequestService;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

function rj_submittedPr(): PaymentRequest
{
    $pr = app(PaymentRequestService::class)->createManual(FinanceCenterFlowFactory::financeCreator(), [
        'payee_name' => 'PT Reject Test',
        'bank_name' => 'BCA',
        'bank_account_number' => '111122223333',
        'amount' => 1000000,
        'currency' => 'IDR',
        'description' => 'reject test',
    ]);
    app(PaymentRequestService::class)->submitToCoo($pr);

    return $pr->fresh();
}

it('an allowlisted user (no finance role) can reject a submitted PR with a reason', function () {
    $pr = rj_submittedPr();
    $user = User::factory()->create();
    PaymentAuthorizationSetting::current()->update(['pr_rejecter_user_ids' => [$user->id]]);

    actingAs($user);
    expect(PaymentApprovalView::canAccess())->toBeTrue();

    Livewire::actingAs($user)
        ->test(PaymentApprovalView::class)
        ->callTableAction('reject', $pr, ['reason' => 'Not in budget'])
        ->assertHasNoTableActionErrors();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_REJECTED_BY_COO)
        ->and($pr->fresh()->coo_rejection_reason)->toBe('Not in budget');
})->group('pr-rejecter');

it('an allowlisted user can only reject (approve + request_clarification hidden)', function () {
    $pr = rj_submittedPr();
    $user = User::factory()->create();
    PaymentAuthorizationSetting::current()->update(['pr_rejecter_user_ids' => [$user->id]]);

    Livewire::actingAs($user)
        ->test(PaymentApprovalView::class)
        ->assertTableActionHidden('approve', $pr)
        ->assertTableActionHidden('request_clarification', $pr);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_SUBMITTED_TO_COO);
})->group('pr-rejecter');

it('a user neither allowlisted nor approver is denied the page (403)', function () {
    $stranger = User::factory()->create();

    actingAs($stranger);
    expect(PaymentApprovalView::canAccess())->toBeFalse();

    Livewire::actingAs($stranger)
        ->test(PaymentApprovalView::class)
        ->assertStatus(403);
})->group('pr-rejecter');

it('COO can still both approve and reject (regression)', function () {
    $coo = FinanceCenterFlowFactory::coo();

    $pr1 = rj_submittedPr();
    Livewire::actingAs($coo)
        ->test(PaymentApprovalView::class)
        ->callTableAction('approve', $pr1)
        ->assertHasNoTableActionErrors();
    expect($pr1->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);

    $pr2 = rj_submittedPr();
    Livewire::actingAs($coo)
        ->test(PaymentApprovalView::class)
        ->callTableAction('reject', $pr2, ['reason' => 'Duplicate'])
        ->assertHasNoTableActionErrors();
    expect($pr2->fresh()->status)->toBe(PaymentRequest::STATUS_REJECTED_BY_COO);
})->group('pr-rejecter');
