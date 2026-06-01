<?php

namespace App\Providers;

use App\Models\Hris\Announcement;
use App\Models\Hris\Applicant;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\JobPosting;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\PerformanceGoal;
use App\Models\Hris\PerformanceReview;
use App\Models\Hris\Position;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\SalaryStructure;
use App\Models\Hris\TrainingRecord;
use App\Models\TicketTemplate;
use App\Policies\Hris\AnnouncementPolicy;
use App\Policies\Hris\ApplicantPolicy;
use App\Policies\Hris\AttendanceLogPolicy;
use App\Policies\Hris\DepartmentPolicy;
use App\Policies\Hris\EmployeePolicy;
use App\Policies\Hris\FinanceAttachmentPolicy;
use App\Policies\Hris\JobPostingPolicy;
use App\Policies\Hris\LeaveRequestPolicy;
use App\Policies\Hris\LeaveTypePolicy;
use App\Policies\Hris\PayrollRecordPolicy;
use App\Policies\Hris\PayrunBatchPolicy;
use App\Policies\Hris\PerformanceGoalPolicy;
use App\Policies\Hris\PerformanceReviewPolicy;
use App\Policies\Hris\PositionPolicy;
use App\Policies\Hris\ReimbursementPolicy;
use App\Policies\Hris\SalaryStructurePolicy;
use App\Policies\Hris\TrainingRecordPolicy;
use App\Policies\TicketTemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Announcement::class => AnnouncementPolicy::class,
        Applicant::class => ApplicantPolicy::class,
        AttendanceLog::class => AttendanceLogPolicy::class,
        Department::class => DepartmentPolicy::class,
        Employee::class => EmployeePolicy::class,
        FinanceAttachment::class => FinanceAttachmentPolicy::class,
        JobPosting::class => JobPostingPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        LeaveType::class => LeaveTypePolicy::class,
        PayrollRecord::class => PayrollRecordPolicy::class,
        PayrunBatch::class => PayrunBatchPolicy::class,
        PerformanceGoal::class => PerformanceGoalPolicy::class,
        PerformanceReview::class => PerformanceReviewPolicy::class,
        Position::class => PositionPolicy::class,
        Reimbursement::class => ReimbursementPolicy::class,
        SalaryStructure::class => SalaryStructurePolicy::class,
        TrainingRecord::class => TrainingRecordPolicy::class,
        TicketTemplate::class => TicketTemplatePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Register HR Analytics gates
        Gate::define('view_hr_analytics', function ($user) {
            return $user->hasAnyRole(['hr-admin', 'hr-manager']);
        });

        Gate::define('access_hr_dashboard', function ($user) {
            return $user->hasAnyRole(['hr-admin', 'hr-manager']);
        });

        Gate::define('export_hr_data', function ($user) {
            return $user->hasAnyRole(['hr-admin', 'hr-manager']);
        });
    }
}

