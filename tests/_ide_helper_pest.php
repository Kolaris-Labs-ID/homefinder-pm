<?php

/**
 * IDE Helper for Pest PHP Tests
 *
 * This file helps Intelephense understand Pest's dynamic property binding.
 * It's not executed, only used for static analysis.
 *
 * @noinspection PhpUnused
 */

namespace Tests;

use App\Models\User;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;

/**
 * @method $this actingAs(\App\Models\User $user, string $guard = null)
 */
class PestTestCase extends TestCase
{
    /** @var User */
    public $admin;

    /** @var PayrollSettingService */
    public $settingService;

    /** @var BpjsCalculationService */
    public $bpjsService;

    /** @var Pph21CalculationService */
    public $pph21Service;

    /** @var AttendancePayrollService */
    public $attendanceService;

    /** @var PayrollCalculationService|AttendancePayrollService|BpjsCalculationService|PayrollSettingService|Pph21CalculationService */
    public $service;
}
