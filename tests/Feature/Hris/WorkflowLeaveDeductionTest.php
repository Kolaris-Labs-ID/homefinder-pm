<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowLeaveDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_approval_workflow_deducts_balance()
    {
        $leaveBalanceService = app(LeaveBalanceService::class);
        $workflowService = app(ApprovalWorkflowService::class);

        // Create an annual leave type
        $annualLeaveType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_annual_quota' => 12,
        ]);

        // Create manager
        $managerUser = User::factory()->create(['name' => 'Manager']);

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => 'Leave Approval',
            'for_module' => 'leave',
            'is_active' => true,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => (string) $managerUser->id,
                ]
            ],
        ]);

        // Create employee
        $user = User::factory()->create(['name' => 'John Doe']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $year = now()->year;

        // Ensure starting balance of 12 days
        $balance = LeaveBalance::factory()->create([
            'employee_id'     => $employee->id,
            'leave_type_id'   => $annualLeaveType->id,
            'year'            => $year,
            'starting_balance' => 12,
            'used_days'       => 0,
            'remaining_days'  => 12,
        ]);

        // Create a leave request for 3 days
        $start = now()->addWeek()->startOfDay();
        $end   = $start->copy()->addDays(2);

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $annualLeaveType->id,
            'start_date'    => $start,
            'end_date'      => $end,
            'total_days'    => 3,
            'status'        => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $workflowService->initializeParallelWorkflow($leaveRequest, 'leave');

        $step = $leaveRequest->currentApprovalStep();
        $this->assertNotNull($step);

        // Approve the leave via workflow
        $workflowService->approveParallel($step, $managerUser);
        
        $balance->refresh();

        $this->assertEquals(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
        $this->assertEquals(3, $balance->used_days);
        $this->assertEquals(9, $balance->remaining_days);
    }
}
