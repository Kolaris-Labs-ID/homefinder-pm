<?php

use App\Models\Hris\Employee;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\FinanceAttachmentService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 3 — FinanceAttachment protected download (IDOR) + locked-delete guard.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->owner = Employee::factory()->create();
    $this->reimbursement = Reimbursement::factory()->create(['employee_id' => $this->owner->id]);
    $this->attachment = app(FinanceAttachmentService::class)->store(
        $this->reimbursement,
        UploadedFile::fake()->createWithContent('evidence.pdf', 'secret-evidence'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $this->owner->user,
    );
});

$downloadUrl = fn ($id) => "/api/hris/finance/attachments/{$id}/download";
$deleteUrl = fn ($id) => "/api/hris/finance/attachments/{$id}";

it('lets the owning employee download their own evidence', function () use ($downloadUrl) {
    Sanctum::actingAs($this->owner->user);
    $this->get($downloadUrl($this->attachment->id))->assertOk();
});

it('forbids another employee from downloading evidence (IDOR)', function () use ($downloadUrl) {
    $other = Employee::factory()->create();
    Sanctum::actingAs($other->user);
    $this->get($downloadUrl($this->attachment->id))->assertForbidden();
});

it('lets a finance reviewer download authorized evidence', function () use ($downloadUrl) {
    $financeUser = User::factory()->create();
    $financeUser->assignRole('finance_reviewer');
    Sanctum::actingAs($financeUser);
    $this->get($downloadUrl($this->attachment->id))->assertOk();
});

it('rejects unauthenticated download', function () use ($downloadUrl) {
    $this->getJson($downloadUrl($this->attachment->id))->assertUnauthorized();
});

it('returns 404 when the stored file is missing', function () use ($downloadUrl) {
    Sanctum::actingAs($this->owner->user);
    Storage::disk('private')->delete($this->attachment->file_path);
    $this->get($downloadUrl($this->attachment->id))->assertNotFound();
});

it('blocks deletion of a locked attachment', function () use ($deleteUrl) {
    app(FinanceAttachmentService::class)->lock($this->attachment, 'part of approval snapshot');
    Sanctum::actingAs($this->owner->user);
    $this->deleteJson($deleteUrl($this->attachment->id))->assertStatus(422);
    expect(FinanceAttachment::find($this->attachment->id))->not->toBeNull();
});

it('lets the owner delete their own unlocked attachment', function () use ($deleteUrl) {
    Sanctum::actingAs($this->owner->user);
    $this->deleteJson($deleteUrl($this->attachment->id))->assertOk();
    expect(FinanceAttachment::find($this->attachment->id))->toBeNull();
});
