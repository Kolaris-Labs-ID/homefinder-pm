<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyTodoReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hris:send-weekly-todo-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly todo reminder to employees who have checked in on Monday at 10 AM';

    /**
     * Execute the console command.
     */
    public function handle(PushNotificationService $pushService): int
    {
        $this->info('Starting weekly todo reminder process...');

        // Get today's date
        $today = now()->toDateString();

        // Check if today is Monday
        if (now()->dayOfWeek !== 1) {
            $this->info('Today is not Monday. Skipping reminder.');
            return Command::SUCCESS;
        }

        // Get all employees who have checked in today
        $checkedInEmployees = AttendanceLog::where('date', $today)
            ->whereNotNull('check_in_time')
            ->with('employee')
            ->get()
            ->pluck('employee')
            ->filter()
            ->unique('id');

        if ($checkedInEmployees->isEmpty()) {
            $this->info('No employees have checked in today.');
            return Command::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($checkedInEmployees as $employee) {
            try {
                $sent = $pushService->sendToEmployee(
                    $employee,
                    'weekly_todo_reminder',
                    '📝 Weekly Todo Reminder',
                    "Don't forget to update and create your Task or Weekly Todo list on pm.partnershub.co",
                    [
                        'url' => 'https://pm.partnershub.co',
                        'action' => 'open_todo',
                    ]
                );

                if ($sent) {
                    $sentCount++;
                    $this->info("✓ Sent reminder to: {$employee->user->name}");
                } else {
                    $failedCount++;
                    $this->warn("✗ Failed to send reminder to: {$employee->user->name} (notifications disabled or no push token)");
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Error sending reminder to: {$employee->user->name} - {$e->getMessage()}");
                Log::error('Weekly todo reminder error', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total checked-in employees: {$checkedInEmployees->count()}");
        $this->info("Successfully sent: {$sentCount}");
        $this->info("Failed: {$failedCount}");

        return Command::SUCCESS;
    }
}
