<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\NotificationSetting;
use App\Models\Hris\PushToken;
use App\Models\Hris\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Register a push notification token.
     *
     * POST /api/hris/notifications/register-token
     */
    public function registerToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['required', 'string', 'in:ios,android,web'],
        ]);

        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found.',
            ], 404);
        }

        // Update or create push token
        PushToken::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'platform' => $request->platform,
            ],
            [
                'token' => $request->token,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Push token registered successfully.',
        ]);
    }

    /**
     * Get notification settings.
     *
     * GET /api/hris/notifications/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found.',
            ], 404);
        }

        try {
            $settings = NotificationSetting::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'enabled' => true,
                    'check_in_reminder' => true,
                    'check_out_reminder' => true,
                    'break_reminder' => true,
                    'announcements' => true,
                    'leave_updates' => true,
                    'reimbursement_updates' => true,
                    'payslip_notifications' => true,
                    'ticket_updates' => true,
                    'activity_updates' => true,
                ]
            );

            return response()->json([
                'data' => [
                    'enabled' => $settings->enabled,
                    'checkInReminder' => $settings->check_in_reminder,
                    'checkOutReminder' => $settings->check_out_reminder,
                    'breakReminder' => $settings->break_reminder,
                    'announcements' => $settings->announcements,
                    'leaveUpdates' => $settings->leave_updates,
                    'reimbursementUpdates' => $settings->reimbursement_updates,
                    'payslipNotifications' => $settings->payslip_notifications,
                    'ticketUpdates' => $settings->ticket_updates,
                    'activityUpdates' => $settings->activity_updates,
                ],
            ]);
        } catch (\Exception $e) {
            // If table doesn't exist, return default settings
            \Log::warning('Notification settings fetch error: '.$e->getMessage());

            return response()->json([
                'data' => [
                    'enabled' => true,
                    'checkInReminder' => true,
                    'checkOutReminder' => true,
                    'breakReminder' => true,
                    'announcements' => true,
                    'leaveUpdates' => true,
                    'reimbursementUpdates' => true,
                    'payslipNotifications' => true,
                    'ticketUpdates' => true,
                    'activityUpdates' => true,
                ],
            ]);
        }
    }

    /**
     * Update notification settings.
     *
     * PUT /api/hris/notifications/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
            'checkInReminder' => ['required', 'boolean'],
            'checkOutReminder' => ['required', 'boolean'],
            'breakReminder' => ['required', 'boolean'],
            'announcements' => ['required', 'boolean'],
            'leaveUpdates' => ['required', 'boolean'],
            'reimbursementUpdates' => ['required', 'boolean'],
            'payslipNotifications' => ['required', 'boolean'],
            'ticketUpdates' => ['required', 'boolean'],
            'activityUpdates' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found.',
            ], 404);
        }

        NotificationSetting::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'enabled' => $request->enabled,
                'check_in_reminder' => $request->checkInReminder,
                'check_out_reminder' => $request->checkOutReminder,
                'break_reminder' => $request->breakReminder,
                'announcements' => $request->announcements,
                'leave_updates' => $request->leaveUpdates,
                'reimbursement_updates' => $request->reimbursementUpdates,
                'payslip_notifications' => $request->payslipNotifications,
                'ticket_updates' => $request->ticketUpdates,
                'activity_updates' => $request->activityUpdates,
            ]
        );

        return response()->json([
            'message' => 'Notification settings updated successfully.',
        ]);
    }

    /**
     * Get user notifications.
     *
     * GET /api/hris/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found.',
            ], 404);
        }

        try {
            $notifications = UserNotification::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'data' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'data' => $notification->data,
                        'read_at' => $notification->read_at?->toISOString(),
                        'created_at' => $notification->created_at->toISOString(),
                    ];
                }),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'total' => $notifications->total(),
                ],
            ]);
        } catch (\Exception $e) {
            // If table doesn't exist or other error, return empty array
            \Log::warning('Notification fetch error: '.$e->getMessage());

            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ],
            ]);
        }
    }

    /**
     * Mark a notification as read.
     *
     * PUT /api/hris/notifications/{notification}/read
     */
    public function markAsRead(Request $request, UserNotification $notification): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee || $notification->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * PUT /api/hris/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee record not found.',
            ], 404);
        }

        UserNotification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get the count of unread notifications.
     *
     * GET /api/hris/notifications/unread-count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'count' => 0,
            ]);
        }

        try {
            $count = UserNotification::where('employee_id', $employee->id)
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'count' => 0,
            ]);
        }
    }
}
