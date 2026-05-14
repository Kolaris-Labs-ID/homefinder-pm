<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property \App\Models\Project $project
 * @property \App\Models\TicketStatus $status
 * @property \App\Models\TicketPriority $priority
 * @property \App\Models\User $user
 * @property \App\Models\User $admin
 * @property \Spatie\Permission\Models\Role $role
 * @property \App\Services\Hris\PayrollSettingService $settingService
 * @property \App\Services\Hris\BpjsCalculationService $bpjsService
 * @property \App\Services\Hris\Pph21CalculationService $pph21Service
 * @property \App\Services\Hris\AttendancePayrollService $attendanceService
 * @property \App\Services\Hris\PayrollCalculationService $service
 * @property \App\Models\Hris\Employee $employee
 * @property \App\Models\Hris\LeaveType $annualType
 * @property \App\Models\Hris\LeaveType $unpaidType
 */
abstract class TestCase extends BaseTestCase
{
    public $project;
    public $status;
    public $priority;
    public $user;
    public $role;
    public $admin;
    public $settingService;
    public $bpjsService;
    public $pph21Service;
    public $attendanceService;
    public $service;
    public $employee;
    public $annualType;
    public $unpaidType;

    protected function setUp(): void
    {
        // Suppress PHP 8.5 deprecation warnings from vendor packages
        error_reporting(E_ALL & ~E_DEPRECATED);
        
        // Ensure sufficient memory for tests
        ini_set('memory_limit', '512M');
        parent::setUp();
    }
}
