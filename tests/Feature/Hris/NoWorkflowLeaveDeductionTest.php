<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveBalanceService;
use App\Services\Hris\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoWorkflowLeaveDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_workflow_approval_deducts_balance()
    {
        $annualLeaveType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_annual_quota' => 12,
        ]);

        $managerUser = User::factory()->create(['name' => 'Manager']);
        // Create role if doesn't exist
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'hr-admin']);
        $managerUser->assignRole('hr-admin');
        
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
        
        $employeeUser = User::factory()->create(['name' => 'John Doe']);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
        
        $position = Position::factory()->create();
        EmployeePosition::factory()->create([
            'employee_id' => $employee->id,
            'position_id' => $position->id,
            'is_primary' => true
        ]);

        $year = now()->year;

        $balance = LeaveBalance::factory()->create([
            'employee_id'     => $employee->id,
            'leave_type_id'   => $annualLeaveType->id,
            'year'            => $year,
            'starting_balance' => 12,
            'used_days'       => 0,
            'remaining_days'  => 12,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $annualLeaveType->id,
            'start_date'    => now()->addWeek()->startOfDay(),
            'end_date'      => now()->addWeek()->addDays(2),
            'total_days'    => 3,
            'status'        => LeaveRequest::STATUS_DRAFT,
        ]);

        app(LeaveWorkflowService::class)->initializeWorkflow($leaveRequest);

        $step = $leaveRequest->currentApprovalStep();
        $this->assertNotNull($step, 'Step should be created');

        app(ApprovalWorkflowService::class)->approve($step, $managerUser);
        
        $balance->refresh();
        $this->assertEquals(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
        $this->assertEquals(3, $balance->used_days);
        $this->assertEquals(9, $balance->remaining_days);
    }
}
