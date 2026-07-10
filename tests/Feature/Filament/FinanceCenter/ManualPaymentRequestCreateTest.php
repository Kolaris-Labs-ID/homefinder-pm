<?php

use App\Filament\Resources\PaymentRequestResource\Pages\ListPaymentRequests;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

it('finance_creator can create a manual PR with an invoice via the drawer', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    $invoice = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->assertActionVisible('createManual')
        ->mountAction('createManual')
        ->set('mountedActionsData.0.payee_name', 'PT Hosting Co')
        ->set('mountedActionsData.0.bank_name', 'BCA')
        ->set('mountedActionsData.0.bank_account_number', '9998887776')
        ->set('mountedActionsData.0.amount', 500000)
        ->set('mountedActionsData.0.currency', 'IDR')
        ->set('mountedActionsData.0.description', 'Annual hosting')
        ->set('mountedActionsData.0.invoice', $invoice)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $pr = PaymentRequest::where('payee_name', 'PT Hosting Co')->first();

    expect($pr)->not->toBeNull()
        ->and($pr->source_type)->toBe(PaymentRequest::SOURCE_MANUAL)
        ->and($pr->status)->toBe(PaymentRequest::STATUS_DRAFT)
        ->and((string) $pr->amount)->toBe('500000.00')
        ->and($pr->bank_account_last4)->toBe('7776')
        ->and($pr->financeAttachments()->where('attachment_role', FinanceAttachment::ROLE_SUPPORTING_DOCUMENT)->exists())->toBeTrue();
})->group('manual-pr');

it('a user without payment_requests.create cannot see the create action', function () {
    $executor = FinanceCenterFlowFactory::financeExecutor();

    Livewire::actingAs($executor)
        ->test(ListPaymentRequests::class)
        ->assertActionHidden('createManual');
})->group('manual-pr');

it('createManual rejects a non-positive amount', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    actingAs($creator);

    expect(fn () => app(PaymentRequestService::class)->createManual($creator, [
        'payee_name' => 'PT Zero',
        'amount' => 0,
        'currency' => 'IDR',
    ]))->toThrow(RuntimeException::class);
})->group('manual-pr');

it('finance_creator can create a manual PR without an invoice', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->mountAction('createManual')
        ->set('mountedActionsData.0.payee_name', 'PT No Invoice')
        ->set('mountedActionsData.0.amount', 250000)
        ->set('mountedActionsData.0.currency', 'IDR')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $pr = PaymentRequest::where('payee_name', 'PT No Invoice')->first();

    expect($pr)->not->toBeNull()
        ->and($pr->financeAttachments()->count())->toBe(0);
})->group('manual-pr');

it('writes an audit entry when a manual PR is created', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    actingAs($creator);

    $pr = app(PaymentRequestService::class)->createManual($creator, [
        'payee_name' => 'PT Audit',
        'amount' => 750000,
        'currency' => 'IDR',
    ]);

    expect(\App\Models\Hris\FinanceAuditLog::query()
        ->where('auditable_type', PaymentRequest::class)
        ->where('auditable_id', $pr->id)
        ->where('event_type', 'payment_request.created_manually')
        ->exists())->toBeTrue();
})->group('manual-pr');

it('rolls back the PR when the invoice attachment fails (atomic)', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    $invoice = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');

    app()->bind(\App\Services\Hris\FinanceAttachmentService::class, function () {
        return new class extends \App\Services\Hris\FinanceAttachmentService
        {
            public function __construct() {}

            public function store(\Illuminate\Database\Eloquent\Model $attachable, \Illuminate\Http\UploadedFile $file, string $role, \App\Models\User $uploader): \App\Models\Hris\FinanceAttachment
            {
                throw new \RuntimeException('forced attachment failure');
            }
        };
    });

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->mountAction('createManual')
        ->set('mountedActionsData.0.payee_name', 'PT Rollback')
        ->set('mountedActionsData.0.amount', 300000)
        ->set('mountedActionsData.0.currency', 'IDR')
        ->set('mountedActionsData.0.invoice', $invoice)
        ->callMountedAction();

    expect(PaymentRequest::where('payee_name', 'PT Rollback')->exists())->toBeFalse();
})->group('manual-pr');
