<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    public $user;

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
    public function authenticated_user_can_get_team_members()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'summary',
            ]);
    }

    #[Test]
    public function team_member_includes_work_mode_when_checked_in()
    {
        Sanctum::actingAs($this->user);

        \App\Models\Hris\AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'mode' => \App\Models\Hris\AttendanceLog::MODE_WFH,
            'check_in_time' => now()->setTime(8, 30),
            'check_out_time' => null,
        ]);

        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(200);
        $member = collect($response->json('data'))->firstWhere('id', $this->employee->id);
        $this->assertSame('wfh', $member['work_mode']);
    }

    #[Test]
    public function team_member_work_mode_is_null_when_not_checked_in()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/team');

        $member = collect($response->json('data'))->firstWhere('id', $this->employee->id);
        $this->assertNull($member['work_mode']);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_team_endpoint()
    {
        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(401);
    }

    #[Test]
    public function team_member_department_reflects_primary_position_department()
    {
        Sanctum::actingAs($this->user);

        $department = Department::factory()->create(['name' => 'Engineering']);
        EmployeePosition::factory()->primary()->create([
            'employee_id' => $this->employee->id,
            'department_id' => $department->id,
            'position_id' => Position::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(200);
        $member = collect($response->json('data'))->firstWhere('id', $this->employee->id);
        $this->assertSame('Engineering', $member['department']);
    }

    #[Test]
    public function team_member_department_is_null_when_employee_has_no_primary_position()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/team');

        $member = collect($response->json('data'))->firstWhere('id', $this->employee->id);
        $this->assertNull($member['department']);
    }

    #[Test]
    public function team_endpoint_query_count_does_not_scale_with_employee_count()
    {
        Sanctum::actingAs($this->user);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create();

        $makeEmployeeWithPosition = function () use ($department, $position) {
            $employee = Employee::factory()->create(['is_active' => true]);
            EmployeePosition::factory()->primary()->create([
                'employee_id' => $employee->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
            ]);

            return $employee;
        };

        foreach (range(1, 3) as $_) {
            $makeEmployeeWithPosition();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/hris/team')->assertStatus(200);

        $smallQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(1, 12) as $_) {
            $makeEmployeeWithPosition();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/hris/team')->assertStatus(200);

        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $smallQueryCount,
            $largeQueryCount,
            "Query count grew from {$smallQueryCount} to {$largeQueryCount} as employee headcount increased, indicating an N+1."
        );
    }
}
