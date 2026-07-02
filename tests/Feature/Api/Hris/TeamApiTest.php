<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
