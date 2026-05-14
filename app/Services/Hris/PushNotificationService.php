<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\NotificationSetting;
use App\Models\Hris\PushToken;
use App\Models\Hris\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send push notification to a User (resolves Employee internally).
     * Useful for PM-side code that works with User models.
     */
    public function sendToUser(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): bool {
        $employee = $user->employee;
        if (!$employee) {
            return false;
        }
        return $this->sendToEmployee($employee, $type, $title, $body, $data);
    }

    /**
     * Send push notification to a specific employee.
     */
    public function sendToEmployee(
        Employee $employee,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): bool {
        // Check if employee has notification settings enabled for this type
        if (!$this->shouldSendNotification($employee, $type)) {
            return false;
        }

        // Get active push tokens for the employee
        $tokens = PushToken::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return false;
        }

        // Store notification in database
        UserNotification::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        // Send push notification via Expo
        return $this->sendExpoPushNotification($tokens, $title, $body, array_merge($data, ['type' => $type]));
    }

    /**
     * Send push notification to multiple employees.
     */
    public function sendToEmployees(
        array $employeeIds,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): int {
        $sentCount = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if ($employee && $this->sendToEmployee($employee, $type, $title, $body, $data)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Send announcement notification to all employees.
     */
    public function sendAnnouncementToAll(string $title, string $body, array $data = []): int
    {
        $employees = Employee::where('is_active', true)->get();
        $sentCount = 0;

        foreach ($employees as $employee) {
            if ($this->sendToEmployee($employee, 'announcement', $title, $body, $data)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Send leave request status update notification.
     */
    public function sendLeaveStatusUpdate(Employee $employee, string $status, string $leaveType): bool
    {
        $statusMessages = [
            'approved' => 'Your leave request has been approved',
            'rejected' => 'Your leave request has been rejected',
            'cancelled' => 'Your leave request has been cancelled',
        ];

        $title = '📋 Leave Request Update';
        $body = ($statusMessages[$status] ?? 'Your leave request status has been updated') . " ({$leaveType})";

        return $this->sendToEmployee($employee, 'leave_update', $title, $body, [
            'status' => $status,
            'leave_type' => $leaveType,
        ]);
    }

    /**
     * Send reimbursement status update notification.
     */
    public function sendReimbursementStatusUpdate(Employee $employee, string $status, float $amount): bool
    {
        $statusMessages = [
            'approved' => 'Your reimbursement request has been approved',
            'rejected' => 'Your reimbursement request has been rejected',
            'paid' => 'Your reimbursement has been paid',
        ];

        $title = '💰 Reimbursement Update';
        $body = ($statusMessages[$status] ?? 'Your reimbursement status has been updated') .
                " (Rp " . number_format($amount, 0, ',', '.') . ")";

        return $this->sendToEmployee($employee, 'reimbursement_update', $title, $body, [
            'status' => $status,
            'amount' => $amount,
        ]);
    }

    /**
     * Send new payslip notification.
     */
    public function sendPayslipNotification(Employee $employee, string $period): bool
    {
        $title = '💵 New Payslip Available';
        $body = "Your payslip for {$period} is now available. Tap to view.";

        return $this->sendToEmployee($employee, 'payslip', $title, $body, [
            'period' => $period,
        ]);
    }

    /**
     * Check if notification should be sent based on employee settings.
     */
    private function shouldSendNotification(Employee $employee, string $type): bool
    {
        $settings = NotificationSetting::where('employee_id', $employee->id)->first();

        // If no settings exist, default to enabled
        if (!$settings) {
            return true;
        }

        // Check master toggle
        if (!$settings->enabled) {
            return false;
        }

        // Check specific notification type
        return match ($type) {
            'check_in_reminder' => $settings->check_in_reminder,
            'check_out_reminder' => $settings->check_out_reminder,
            'break_reminder' => $settings->break_reminder,
            'announcement' => $settings->announcements,
            'leave_update' => $settings->leave_updates,
            'reimbursement_update' => $settings->reimbursement_updates,
            'payslip' => $settings->payslip_notifications,
            'ticket_overdue',
            'ticket_nearing_overdue',
            'ticket_assigned',
            'project_overdue',
            'project_nearing_overdue',
            'project_assigned',
            'comment_added' => $settings->ticket_updates ?? true,
            'activity_log' => $settings->activity_updates ?? true,
            default => true,
        };
    }

    /**
     * Send push notification via Expo Push API.
     */
    private function sendExpoPushNotification(
        array $tokens,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $messages = array_map(function ($token) use ($title, $body, $data) {
                return [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ];
            }, $tokens);

            $response = Http::post(self::EXPO_PUSH_URL, $messages);

            if (!$response->successful()) {
                Log::error('Expo push notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            // Check for individual ticket errors
            $responseData = $response->json();
            if (isset($responseData['data'])) {
                foreach ($responseData['data'] as $index => $ticket) {
                    if ($ticket['status'] === 'error') {
                        Log::warning('Expo push notification ticket error', [
                            'token' => $tokens[$index] ?? 'unknown',
                            'error' => $ticket['message'] ?? 'Unknown error',
                        ]);

                        // Deactivate invalid tokens
                        if (isset($ticket['details']['error']) &&
                            in_array($ticket['details']['error'], ['DeviceNotRegistered', 'InvalidCredentials'])) {
                            PushToken::where('token', $tokens[$index])->update(['is_active' => false]);
                        }
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Expo push notification exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
