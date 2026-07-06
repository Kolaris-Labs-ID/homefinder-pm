<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Hris\AnnouncementController;
use App\Http\Controllers\Api\Hris\AttendanceController;
use App\Http\Controllers\Api\Hris\ClientVisitController;
use App\Http\Controllers\Api\Hris\ExpenseRequestController;
use App\Http\Controllers\Api\Hris\FinanceAttachmentController;
use App\Http\Controllers\Api\Hris\LeaderboardController;
use App\Http\Controllers\Api\Hris\LeaveBalanceController;
use App\Http\Controllers\Api\Hris\LeaveRequestController;
use App\Http\Controllers\Api\Hris\NotificationController;
use App\Http\Controllers\Api\Hris\OvertimeController;
use App\Http\Controllers\Api\Hris\PayslipController;
use App\Http\Controllers\Api\Hris\PgpSummaryController;
use App\Http\Controllers\Api\Hris\ProfileController;
use App\Http\Controllers\Api\Hris\ReimbursementController;
use App\Http\Controllers\Api\Hris\TeamController;
use App\Http\Controllers\Api\Hris\TrainingRecordController;
use App\Http\Controllers\Api\Pm\ActivityLogController;
use App\Http\Controllers\Api\Pm\BoardController;
use App\Http\Controllers\Api\Pm\ProjectController;
use App\Http\Controllers\Api\Pm\TicketCommentController;
use App\Http\Controllers\Api\Pm\TicketController;
use App\Http\Controllers\Api\Public\V1\JobApplicationController;
use App\Http\Controllers\Api\Public\V1\JobPortalController;
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

// Attendance photo serving (uses token query param for auth, needed by <Image> components)
Route::get('/hris/attendance/photo', [AttendanceController::class, 'servePhoto']);

/*
|--------------------------------------------------------------------------
| Public Job Portal API (v1) — unauthenticated, for jobs.partnershub.co
|--------------------------------------------------------------------------
*/
Route::prefix('public/v1')->group(function () {
    Route::get('/jobs', [JobPortalController::class, 'index'])->middleware('throttle:public-jobs');
    Route::get('/jobs/{slug}', [JobPortalController::class, 'show'])->middleware('throttle:public-jobs');
    Route::get('/companies/{companySlug}', [JobPortalController::class, 'company'])->middleware('throttle:public-jobs');
    Route::get('/companies/{companySlug}/jobs', [JobPortalController::class, 'companyJobs'])->middleware('throttle:public-jobs');
    Route::post('/jobs/{slug}/apply', [JobApplicationController::class, 'store'])->middleware('throttle:public-apply');
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
        Route::put('/profile/mood', [ProfileController::class, 'updateMood']);

        Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
        Route::get('/leave-types', [LeaveBalanceController::class, 'leaveTypes']);

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::put('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
        Route::post('/leave-requests/{leaveRequest}/certificate', [LeaveRequestController::class, 'uploadCertificate']);

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

        // Expense Requests (employee ESS)
        Route::get('/expense-requests', [ExpenseRequestController::class, 'index']);
        Route::post('/expense-requests', [ExpenseRequestController::class, 'store']);
        Route::get('/expense-requests/{expenseRequest}', [ExpenseRequestController::class, 'show']);
        Route::post('/expense-requests/{expenseRequest}/evidence', [ExpenseRequestController::class, 'uploadEvidence']);
        Route::post('/expense-requests/{expenseRequest}/submit', [ExpenseRequestController::class, 'submit']);
        Route::put('/expense-requests/{expenseRequest}/revise', [ExpenseRequestController::class, 'revise']);
        Route::put('/expense-requests/{expenseRequest}/cancel', [ExpenseRequestController::class, 'cancel']);

        // Finance Center — protected attachments (ER evidence, PR proof, bank statements)
        Route::get('/finance/attachments/{financeAttachment}/download', [FinanceAttachmentController::class, 'download']);
        Route::delete('/finance/attachments/{financeAttachment}', [FinanceAttachmentController::class, 'destroy']);

        // PGP / training records (mobile)
        Route::get('/pgp/summary', [PgpSummaryController::class, 'show']);
        Route::get('/training-records', [TrainingRecordController::class, 'index']);
        Route::post('/training-records', [TrainingRecordController::class, 'store']);
        Route::get('/training-records/{trainingRecord}', [TrainingRecordController::class, 'show']);

        // Client Visit endpoints
        Route::get('/visits/purposes', [ClientVisitController::class, 'purposes']);
        Route::get('/visits', [ClientVisitController::class, 'index']);
        Route::post('/visits', [ClientVisitController::class, 'store']);
        Route::get('/visits/{clientVisit}', [ClientVisitController::class, 'show']);
        Route::post('/visits/{clientVisit}/check-in', [ClientVisitController::class, 'checkIn']);
        Route::post('/visits/{clientVisit}/check-out', [ClientVisitController::class, 'checkOut']);
        Route::put('/visits/{clientVisit}/cancel', [ClientVisitController::class, 'cancel']);

        // Overtime (Lembur) endpoints
        Route::prefix('overtime')->group(function () {
            Route::get('/managers', [OvertimeController::class, 'managers']);
            Route::get('/active', [OvertimeController::class, 'active']);
            Route::get('/', [OvertimeController::class, 'index']);
            Route::post('/start', [OvertimeController::class, 'start']);
            Route::post('/stop', [OvertimeController::class, 'stop']);
            Route::get('/{overtimeLog}', [OvertimeController::class, 'show']);
        });

        // Team endpoints
        Route::get('/team', [TeamController::class, 'index']);

        // Leaderboard: any authenticated app user (not gated on page_Leaderboard)
        Route::get('/leaderboard', [LeaderboardController::class, 'index']);

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
            Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        });
    });

/*
|--------------------------------------------------------------------------
| Project Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])
    ->prefix('pm')
    ->group(function () {
        // Projects
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::put('/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
        Route::get('/projects/{project}/members', [ProjectController::class, 'members']);

        // Kanban Board
        Route::get('/projects/{project}/board', [BoardController::class, 'board']);
        Route::put('/tickets/{ticket}/move', [BoardController::class, 'moveTicket']);

        // Tickets
        Route::get('/tickets/assigned-to-me', [TicketController::class, 'assignedToMe']);
        Route::get('/tickets/all', [TicketController::class, 'all']);
        Route::get('/projects/{project}/tickets', [TicketController::class, 'index']);
        Route::post('/projects/{project}/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

        // Ticket Comments
        Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
        Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
        Route::put('/comments/{comment}', [TicketCommentController::class, 'update']);
        Route::delete('/comments/{comment}', [TicketCommentController::class, 'destroy']);

        // Activity Logs
        Route::get('/activities', [ActivityLogController::class, 'index']);
    });
