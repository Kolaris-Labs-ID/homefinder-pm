<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use RefreshDatabase;

    public $user;
    public Employee $employee;
    public LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->leaveType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'requires_approval' => true,
            'requires_attachment' => false,
            'default_annual_quota' => 12,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'starting_balance' => 12,
            'used_days' => 0,
        ]);
    }

    #[Test]
    public function authenticated_user_can_get_leave_balances()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/leave-balances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'leave_type',
                        'year',
                        'starting_balance',
                        'used_days',
                        'remaining_days',
                    ],
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_leave_types()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/leave-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                    ],
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_leave_requests()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/leave-requests');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_create_leave_request()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/hris/leave-requests', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'reason' => 'Family vacation',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'leave_type',
                    'start_date',
                    'end_date',
                    'total_days',
                    'reason',
                    'status',
                    'created_at',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_leave_endpoints()
    {
        $response = $this->getJson('/api/hris/leave-balances');

        $response->assertStatus(401);
    }
}
