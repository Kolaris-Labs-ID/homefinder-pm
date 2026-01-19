<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public $user;
    public Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function authenticated_user_can_check_in()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'mode' => 'office',
            'source' => 'mobile',
        ]);

        // Check-in returns 201 Created
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'date',
                    'mode',
                    'source',
                    'status',
                    'check_in',
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_daily_attendance()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/attendance/daily');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_get_attendance_history()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/attendance/history?from=2025-12-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_get_wfh_quota()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/attendance/wfh-quota');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'remaining_quota',
                    'max_quota_per_week',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_attendance_endpoints()
    {
        $response = $this->postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        $response->assertStatus(401);
    }
}
