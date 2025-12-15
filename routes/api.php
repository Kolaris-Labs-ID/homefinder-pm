<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Hris\AttendanceController;
use App\Http\Controllers\Api\Hris\LeaveBalanceController;
use App\Http\Controllers\Api\Hris\LeaveRequestController;
use App\Http\Controllers\Api\Hris\PayslipController;
use App\Http\Controllers\Api\Hris\ProfileController;
use App\Http\Controllers\Api\Hris\ReimbursementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes that require authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum'])
    ->prefix('hris')
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
        Route::get('/leave-types', [LeaveBalanceController::class, 'leaveTypes']);

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::put('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);

        // Attendance endpoints
        Route::prefix('attendance')->group(function () {
            Route::post('/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/check-out', [AttendanceController::class, 'checkOut']);
            Route::get('/daily', [AttendanceController::class, 'daily']);
            Route::get('/history', [AttendanceController::class, 'history']);
            Route::get('/wfh-quota', [AttendanceController::class, 'wfhQuota']);
        });

        // Payslip endpoints
        Route::get('/payslips', [PayslipController::class, 'index']);
        Route::get('/payslips/{payrollRecord}', [PayslipController::class, 'show']);

        // Reimbursement endpoints
        Route::get('/reimbursements', [ReimbursementController::class, 'index']);
        Route::post('/reimbursements', [ReimbursementController::class, 'store']);
        Route::put('/reimbursements/{reimbursement}/cancel', [ReimbursementController::class, 'cancel']);
    });

