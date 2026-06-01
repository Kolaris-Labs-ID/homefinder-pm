<?php

use App\Models\Hris\Employee;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\Reimbursement;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 3 — FinanceAttachment store + sha256 hashing + duplicate detection.
 * Attachable stand-in = Reimbursement (has employee_id); identical path for ER/PR.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->service = app(FinanceAttachmentService::class);
    $this->employee = Employee::factory()->create();
    $this->reimbursement = Reimbursement::factory()->create(['employee_id' => $this->employee->id]);
});

it('stores an attachment with a sha256 hash on the private disk', function () {
    $file = UploadedFile::fake()->createWithContent('receipt.pdf', 'invoice-12345');

    $att = $this->service->store($this->reimbursement, $file, FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);

    expect($att->file_hash)->toBe(hash('sha256', 'invoice-12345'));
    expect($att->attachment_role)->toBe(FinanceAttachment::ROLE_EXPENSE_EVIDENCE);
    expect($att->original_filename)->toBe('receipt.pdf');
    expect($att->uploaded_by)->toBe($this->employee->user->id);
    expect($att->attachable_id)->toBe($this->reimbursement->id);
    expect($att->attachable_type)->toBe(Reimbursement::class);
    expect($att->isLocked())->toBeFalse();
    Storage::disk('private')->assertExists($att->file_path);
});

it('flags a duplicate when identical content is uploaded again', function () {
    $first = $this->service->store($this->reimbursement, UploadedFile::fake()->createWithContent('a.pdf', 'same-bytes'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);
    $second = $this->service->store($this->reimbursement, UploadedFile::fake()->createWithContent('b.pdf', 'same-bytes'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);

    expect($first->hasDuplicateWarning())->toBeFalse();
    expect($second->hasDuplicateWarning())->toBeTrue();
    expect($second->metadata['duplicate_of'])->toContain($first->id);
});

it('does not flag distinct content as a duplicate', function () {
    $this->service->store($this->reimbursement, UploadedFile::fake()->createWithContent('a.pdf', 'alpha'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);
    $second = $this->service->store($this->reimbursement, UploadedFile::fake()->createWithContent('b.pdf', 'beta'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);

    expect($second->hasDuplicateWarning())->toBeFalse();
});

it('locks an attachment so it reports as locked', function () {
    $att = $this->service->store($this->reimbursement, UploadedFile::fake()->createWithContent('a.pdf', 'x'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $this->employee->user);

    $this->service->lock($att, 'included in COO approval snapshot');

    expect($att->fresh()->isLocked())->toBeTrue();
    expect($att->fresh()->locked_reason)->toBe('included in COO approval snapshot');
});
