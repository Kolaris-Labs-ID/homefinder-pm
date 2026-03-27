<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientVisitApiTest extends TestCase
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
    public function authenticated_user_can_get_visit_purposes()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/visits/purposes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'value',
                        'label',
                    ],
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_visits()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/visits');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_create_visit()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/hris/visits', [
            'client_name' => 'PT ABC Company',
            'client_address' => 'Jl. Sudirman No. 123, Jakarta',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'purpose' => 'tax_consultation',
            'visit_date' => now()->addDays(1)->toDateString(),
            'planned_start_time' => '09:00',
            'planned_end_time' => '11:00',
            'notes' => 'Discuss annual tax filing',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'client_name',
                    'client_address',
                    'purpose',
                    'visit_date',
                    'status',
                    'created_at',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_visit_endpoints()
    {
        $response = $this->getJson('/api/hris/visits');

        $response->assertStatus(401);
    }

    #[Test]
    public function check_in_reuses_existing_daily_attendance_and_does_not_throw_server_error()
    {
        Sanctum::actingAs($this->user);
        Carbon::setTestNow('2026-03-27 11:00:00');

        try {
            $visit = ClientVisit::create([
                'employee_id' => $this->employee->id,
                'client_name' => 'PT Existing Attendance',
                'client_address' => 'Jl. Gatot Subroto No. 1, Jakarta',
                'purpose' => ClientVisit::PURPOSE_OTHER,
                'visit_date' => Carbon::today()->toDateString(),
                'status' => ClientVisit::STATUS_PLANNED,
            ]);

            $attendanceLog = AttendanceLog::factory()->create([
                'employee_id' => $this->employee->id,
                'date' => Carbon::today()->toDateString(),
                'source' => AttendanceLog::SOURCE_MOBILE,
                'status' => AttendanceLog::STATUS_PRESENT,
                'client_visit_id' => null,
            ]);
            $initialAttendanceCount = AttendanceLog::count();

            $response = $this->postJson("/api/hris/visits/{$visit->id}/check-in", [
                'latitude' => -6.200000,
                'longitude' => 106.816666,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.status', ClientVisit::STATUS_IN_PROGRESS);

            $this->assertDatabaseHas('attendance_logs', [
                'id' => $attendanceLog->id,
                'employee_id' => $this->employee->id,
                'date' => Carbon::today()->toDateString(),
                'client_visit_id' => $visit->id,
            ]);

            $this->assertSame($initialAttendanceCount, AttendanceLog::count());
        } finally {
            Carbon::setTestNow();
        }
    }
}
