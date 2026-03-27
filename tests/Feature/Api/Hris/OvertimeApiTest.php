<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OvertimeApiTest extends TestCase
{
    use RefreshDatabase;

    public $employee;
    public $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create();
        Employee::factory()->create([
            'user_id'   => $this->manager->id,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/hris/overtime')->assertStatus(401);
        $this->getJson('/api/hris/overtime/active')->assertStatus(401);
        $this->postJson('/api/hris/overtime/start')->assertStatus(401);
        $this->postJson('/api/hris/overtime/stop')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Managers endpoint
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_fetch_managers_list(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/overtime/managers');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    // -------------------------------------------------------------------------
    // Active
    // -------------------------------------------------------------------------

    #[Test]
    public function active_returns_null_when_no_session(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/overtime/active');

        $response->assertStatus(200)
            ->assertJson(['data' => null]);
    }

    #[Test]
    public function active_returns_running_session(): void
    {
        Sanctum::actingAs($this->user);

        OvertimeLog::create([
            'user_id'    => $this->user->id,
            'manager_id' => $this->manager->id,
            'task_name'  => 'Test Task',
            'start_time' => now(),
            'hourly_rate'=> 50_000,
            'status'     => OvertimeLog::STATUS_RUNNING,
        ]);

        $response = $this->getJson('/api/hris/overtime/active');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', OvertimeLog::STATUS_RUNNING);
    }

    // -------------------------------------------------------------------------
    // Start
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_start_overtime(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/hris/overtime/start', [
            'manager_id' => $this->manager->id,
            'task_name'  => 'Emergency Deploy',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', OvertimeLog::STATUS_RUNNING);

        $this->assertDatabaseHas('overtime_logs', [
            'user_id'   => $this->user->id,
            'task_name' => 'Emergency Deploy',
            'status'    => OvertimeLog::STATUS_RUNNING,
        ]);
    }

    #[Test]
    public function start_fails_validation_without_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/hris/overtime/start', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['manager_id', 'task_name']);
    }

    #[Test]
    public function user_cannot_start_two_sessions(): void
    {
        Sanctum::actingAs($this->user);

        OvertimeLog::create([
            'user_id'    => $this->user->id,
            'manager_id' => $this->manager->id,
            'task_name'  => 'First task',
            'start_time' => now(),
            'hourly_rate'=> 0,
            'status'     => OvertimeLog::STATUS_RUNNING,
        ]);

        $response = $this->postJson('/api/hris/overtime/start', [
            'manager_id' => $this->manager->id,
            'task_name'  => 'Second task',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Stop
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_stop_running_session(): void
    {
        Sanctum::actingAs($this->user);

        OvertimeLog::create([
            'user_id'    => $this->user->id,
            'manager_id' => $this->manager->id,
            'task_name'  => 'Task A',
            'start_time' => now()->subHour(),
            'hourly_rate'=> 50_000,
            'status'     => OvertimeLog::STATUS_RUNNING,
        ]);

        $response = $this->postJson('/api/hris/overtime/stop');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', OvertimeLog::STATUS_PENDING);

        $this->assertDatabaseHas('overtime_logs', [
            'user_id' => $this->user->id,
            'status'  => OvertimeLog::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function stop_returns_404_when_no_session(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/hris/overtime/stop')->assertStatus(404);
    }

    #[Test]
    public function client_session_requires_evidence_on_stop(): void
    {
        Sanctum::actingAs($this->user);

        OvertimeLog::create([
            'user_id'     => $this->user->id,
            'manager_id'  => $this->manager->id,
            'task_name'   => 'Client work',
            'client_name' => 'Acme Corp',
            'start_time'  => now()->subHour(),
            'hourly_rate' => 50_000,
            'status'      => OvertimeLog::STATUS_RUNNING,
        ]);

        $response = $this->postJson('/api/hris/overtime/stop');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['evidence']);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_list_their_overtime_logs(): void
    {
        Sanctum::actingAs($this->user);

        OvertimeLog::create([
            'user_id'    => $this->user->id,
            'manager_id' => $this->manager->id,
            'task_name'  => 'Completed task',
            'start_time' => now()->subHours(2),
            'end_time'   => now()->subHour(),
            'duration_minutes' => 60,
            'hourly_rate'=> 50_000,
            'status'     => OvertimeLog::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/hris/overtime');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'task_name', 'status']]]);
    }
}
