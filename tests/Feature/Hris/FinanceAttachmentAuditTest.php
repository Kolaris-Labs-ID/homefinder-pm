<?php

use App\Models\Hris\Employee;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\Reimbursement;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->employee = Employee::factory()->create();
    $this->reimbursement = Reimbursement::factory()->create(['employee_id' => $this->employee->id]);
    $this->service = app(FinanceAttachmentService::class);
});

function attAuditStore(Reimbursement $attachable, Employee $employee): FinanceAttachment
{
    return app(FinanceAttachmentService::class)->store(
        $attachable,
        UploadedFile::fake()->createWithContent('f.pdf', 'bytes'.uniqid()),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $employee->user,
    );
}

it('writes a finance_attachment.uploaded audit row after store', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.uploaded')
            ->where('auditable_id', $att->id)
            ->exists()
    )->toBeTrue();
});

it('uploaded audit row captures attachment_role and file_hash in after_snapshot', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);

    $log = FinanceAuditLog::where('event_type', 'finance_attachment.uploaded')
        ->where('auditable_id', $att->id)
        ->firstOrFail();

    expect($log->after_snapshot['attachment_role'])->toBe(FinanceAttachment::ROLE_EXPENSE_EVIDENCE);
    expect($log->after_snapshot['file_hash'])->toBe($att->file_hash);
    expect($log->after_snapshot['attachable_type'])->toBe(Reimbursement::class);
    expect($log->after_snapshot['attachable_id'])->toBe($this->reimbursement->id);
});

it('writes a finance_attachment.locked audit row after lock', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);

    $this->service->lock($att, 'approval snapshot', $this->employee->user);

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.locked')
            ->where('auditable_id', $att->id)
            ->exists()
    )->toBeTrue();
});

it('locked audit row captures the reason', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);

    $this->service->lock($att, 'my lock reason', $this->employee->user);

    $log = FinanceAuditLog::where('event_type', 'finance_attachment.locked')
        ->where('auditable_id', $att->id)
        ->firstOrFail();

    expect($log->reason)->toBe('my lock reason');
});

it('writes a finance_attachment.deleted audit row after delete', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);
    $attId = $att->id;

    $this->service->delete($att, $this->employee->user);

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.deleted')
            ->where('auditable_id', $attId)
            ->exists()
    )->toBeTrue();
});

it('deleted audit row captures attachment_id and attachment_role in before_snapshot', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);
    $attId = $att->id;
    $role = $att->attachment_role;

    $this->service->delete($att, $this->employee->user);

    $log = FinanceAuditLog::where('event_type', 'finance_attachment.deleted')
        ->where('auditable_id', $attId)
        ->firstOrFail();

    expect($log->before_snapshot['attachment_id'])->toBe($attId);
    expect($log->before_snapshot['attachment_role'])->toBe($role);
});

it('does not audit delete when the attachment is locked (throws instead)', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);
    $this->service->lock($att, 'locked');

    expect(fn () => $this->service->delete($att, $this->employee->user))
        ->toThrow(\RuntimeException::class);

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.deleted')
            ->where('auditable_id', $att->id)
            ->exists()
    )->toBeFalse();
});

it('lock without actor param still audits (actor=null)', function () {
    $att = attAuditStore($this->reimbursement, $this->employee);

    $this->service->lock($att, 'system lock');

    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.locked')
            ->where('auditable_id', $att->id)
            ->whereNull('actor_id')
            ->exists()
    )->toBeTrue();
});

// --- Lock routing through PaymentRequestService ---

it('approveAsCoo produces finance_attachment.locked rows via the service', function () {
    Storage::fake('private');
    $prService = app(PaymentRequestService::class);
    $creator = \App\Models\User::factory()->create();
    $coo = \App\Models\User::factory()->create();

    $pr = $prService->createManual($creator, [
        'payee_type' => 'vendor', 'payee_name' => 'PT Test', 'bank_name' => 'BCA',
        'bank_account_number' => '1234567890', 'amount' => 1000000, 'currency' => 'IDR',
        'description' => 'Audit test',
    ]);

    $att = app(FinanceAttachmentService::class)->store(
        $pr,
        UploadedFile::fake()->createWithContent('evidence.pdf', 'bytes'.uniqid()),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $creator,
    );

    $prService->submitToCoo($pr);
    $prService->approveAsCoo($pr, $coo);

    expect($att->fresh()->isLocked())->toBeTrue();
    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.locked')
            ->where('auditable_id', $att->id)
            ->exists()
    )->toBeTrue();
});

it('markPaid produces a finance_attachment.locked row for the payment proof via the service', function () {
    Storage::fake('private');
    $prService = app(PaymentRequestService::class);
    $creator = \App\Models\User::factory()->create();
    $coo = \App\Models\User::factory()->create();
    $payer = \App\Models\User::factory()->create();

    $pr = $prService->createManual($creator, [
        'payee_type' => 'vendor', 'payee_name' => 'PT Test', 'bank_name' => 'BCA',
        'bank_account_number' => '1234567890', 'amount' => 1000000, 'currency' => 'IDR',
        'description' => 'Audit test markPaid',
    ]);

    $prService->submitToCoo($pr);
    $prService->approveAsCoo($pr, $coo);

    $proof = app(FinanceAttachmentService::class)->store(
        $pr,
        UploadedFile::fake()->createWithContent('proof.pdf', 'proof'.uniqid()),
        FinanceAttachment::ROLE_PAYMENT_PROOF,
        $payer,
    );

    $prService->markPaid($pr->fresh(), $payer, 'TRX-001', $proof);

    expect($proof->fresh()->isLocked())->toBeTrue();
    expect(
        FinanceAuditLog::where('event_type', 'finance_attachment.locked')
            ->where('auditable_id', $proof->id)
            ->exists()
    )->toBeTrue();
});
