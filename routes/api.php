<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Hris\AnnouncementController;
use App\Http\Controllers\Api\Hris\AttendanceController;
use App\Http\Controllers\Api\Hris\ClientVisitController;
use App\Http\Controllers\Api\Hris\LeaveBalanceController;
use App\Http\Controllers\Api\Hris\LeaveRequestController;
use App\Http\Controllers\Api\Hris\NotificationController;
use App\Http\Controllers\Api\Hris\PayslipController;
use App\Http\Controllers\Api\Hris\ProfileController;
use App\Http\Controllers\Api\Hris\ReimbursementController;
use App\Http\Controllers\Api\Hris\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google', [AuthController::class, 'googleLogin']);
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
        Route::post('/change-password', [AuthController::class, 'changePassword']);
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
            Route::post('/break/start', [AttendanceController::class, 'startBreak']);
            Route::post('/break/end', [AttendanceController::class, 'endBreak']);
            Route::get('/daily', [AttendanceController::class, 'daily']);
            Route::get('/history', [AttendanceController::class, 'history']);
            Route::get('/wfh-quota', [AttendanceController::class, 'wfhQuota']);
        });

        // Payslip endpoints
        Route::get('/payslips', [PayslipController::class, 'index']);
        Route::get('/payslips/{payrollRecord}', [PayslipController::class, 'show']);
        Route::get('/payslips/{id}/download', [PayslipController::class, 'download']);

        // Reimbursement endpoints
        Route::get('/reimbursements', [ReimbursementController::class, 'index']);
        Route::post('/reimbursements', [ReimbursementController::class, 'store']);
        Route::put('/reimbursements/{reimbursement}/cancel', [ReimbursementController::class, 'cancel']);

        // Client Visit endpoints
        Route::get('/visits/purposes', [ClientVisitController::class, 'purposes']);
        Route::get('/visits', [ClientVisitController::class, 'index']);
        Route::post('/visits', [ClientVisitController::class, 'store']);
        Route::get('/visits/{clientVisit}', [ClientVisitController::class, 'show']);
        Route::post('/visits/{clientVisit}/check-in', [ClientVisitController::class, 'checkIn']);
        Route::post('/visits/{clientVisit}/check-out', [ClientVisitController::class, 'checkOut']);
        Route::put('/visits/{clientVisit}/cancel', [ClientVisitController::class, 'cancel']);

        // Team endpoints
        Route::get('/team', [TeamController::class, 'index']);

        // Announcement endpoints
        Route::get('/announcements', [AnnouncementController::class, 'index']);

        // Notification endpoints
        Route::prefix('notifications')->group(function () {
            Route::post('/register-token', [NotificationController::class, 'registerToken']);
            Route::get('/settings', [NotificationController::class, 'getSettings']);
            Route::put('/settings', [NotificationController::class, 'updateSettings']);
            Route::get('/', [NotificationController::class, 'index']);
            Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        });
    });

