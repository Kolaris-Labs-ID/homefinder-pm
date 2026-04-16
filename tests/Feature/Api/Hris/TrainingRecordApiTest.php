<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\PgpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrainingRecordApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function training_records_index_requires_authentication(): void
    {
        $this->getJson('/api/hris/training-records')->assertUnauthorized();
    }

    #[Test]
    public function training_records_require_employee_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/hris/training-records')->assertNotFound();
        $this->postJson('/api/hris/training-records', [
            'training_name' => 'Safety 101',
            'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            'status' => TrainingRecord::STATUS_COMPLETED,
        ])->assertNotFound();
        $this->getJson('/api/hris/pgp/summary')->assertNotFound();
    }

    #[Test]
    public function user_can_list_own_training_records_with_approval_metadata(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $otherEmployee = Employee::factory()->create();

        TrainingRecord::factory()->forEmployee($employee)->planned()->freeTraining()->create([
            'training_name' => 'Mine',
        ]);
        TrainingRecord::factory()->forEmployee($otherEmployee)->planned()->freeTraining()->create([
            'training_name' => 'Theirs',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hris/training-records');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.training_name', 'Mine')
            ->assertJsonPath('data.0.approval_status', 'none');
    }

    #[Test]
    public function user_can_show_own_training_record(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $record = TrainingRecord::factory()->forEmployee($employee)->completed()->freeTraining()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/hris/training-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.approval_status', 'none');
    }

    #[Test]
    public function user_cannot_show_another_employees_training_record(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $other = TrainingRecord::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/hris/training-records/{$other->id}")->assertForbidden();
    }

    #[Test]
    public function user_can_submit_training_record_and_initializes_workflow(): void
    {
        $approver = User::factory()->create();
        ApprovalWorkflow::create([
            'name' => 'Training API',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/hris/training-records', [
            'training_name' => 'Leadership',
            'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            'status' => TrainingRecord::STATUS_COMPLETED,
            'provider' => 'Internal',
            'description' => 'Quarterly program',
            'completion_date' => '2026-03-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.training_name', 'Leadership')
            ->assertJsonPath('data.approval_status', 'pending');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('training_records', [
            'id' => $id,
            'employee_id' => $employee->id,
            'training_name' => 'Leadership',
        ]);

        $this->assertDatabaseHas('approval_steps', [
            'approvable_type' => TrainingRecord::class,
            'approvable_id' => $id,
            'status' => ApprovalStep::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function user_can_submit_certificate_with_training_record(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf');

        $response = $this->post('/api/hris/training-records', [
            'training_name' => 'Cert course',
            'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
            'status' => TrainingRecord::STATUS_COMPLETED,
            'certificate' => $file,
        ]);

        $response->assertCreated();
        $path = $response->json('data.has_certificate');
        $this->assertTrue($path);

        $stored = TrainingRecord::find($response->json('data.id'))->certificate_file_path;
        Storage::disk('private')->assertExists($stored);
    }

    #[Test]
    public function pgp_summary_returns_quarter_totals(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(2, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/hris/pgp/summary?year=2026&quarter=2');

        $response->assertOk()
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.quarter', 2)
            ->assertJsonPath('data.points', TrainingRecord::PGP_FREE_TRAINING)
            ->assertJsonPath('data.target', PgpService::PGP_TARGET)
            ->assertJsonPath('data.records_count', 1);
    }

    #[Test]
    public function rejection_does_not_corrupt_training_status_enum(): void
    {
        $approver = User::factory()->create();
        ApprovalWorkflow::create([
            'name' => 'Training reject',
            'for_module' => ApprovalWorkflow::MODULE_TRAINING,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $record = TrainingRecord::factory()
            ->forEmployee($employee)
            ->completed()
            ->freeTraining()
            ->create();

        app(ApprovalWorkflowService::class)->initializeWorkflow($record, ApprovalWorkflow::MODULE_TRAINING);

        $step = ApprovalStep::where('approvable_id', $record->id)
            ->where('approvable_type', TrainingRecord::class)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->first();

        app(ApprovalWorkflowService::class)->reject($step, $approver, 'Missing certificate');

        $record->refresh();

        $this->assertSame(TrainingRecord::STATUS_COMPLETED, $record->status);
        $this->assertNotSame('rejected', $record->status);

        Sanctum::actingAs($user);

        $this->getJson("/api/hris/training-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Missing certificate');
    }
}
