<?php

namespace App\Providers;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Policies\Hris\AttendanceLogPolicy;
use App\Policies\Hris\DepartmentPolicy;
use App\Policies\Hris\EmployeePolicy;
use App\Policies\Hris\LeaveRequestPolicy;
use App\Policies\Hris\LeaveTypePolicy;
use App\Policies\Hris\PositionPolicy;
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
        AttendanceLog::class => AttendanceLogPolicy::class,
        Department::class => DepartmentPolicy::class,
        Employee::class => EmployeePolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        LeaveType::class => LeaveTypePolicy::class,
        Position::class => PositionPolicy::class,
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
    }
}

