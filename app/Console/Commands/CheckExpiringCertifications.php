<?php

namespace App\Console\Commands;

use App\Models\Hris\TrainingRecord;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiringCertifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hris:check-expiring-certifications
                            {--days=30 : Number of days before expiry to send notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for certifications expiring within the specified days and send notifications to employees and HR';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Checking for certifications expiring within {$days} days...");

        $expiringCertifications = TrainingRecord::with(['employee', 'employee.user'])
            ->expiringWithinDays($days)
            ->completed()
            ->get();

        $this->info("Found {$expiringCertifications->count()} expiring certification(s)");

        if ($expiringCertifications->isEmpty()) {
            $this->info('✅ No expiring certifications found.');
            return Command::SUCCESS;
        }

        $notifiedCount = 0;
        $hrUsers = $this->getHRUsers();

        foreach ($expiringCertifications as $record) {
            try {
                $daysUntilExpiry = $record->days_until_expiry;

                // Notify the employee
                $this->notifyEmployee($record, $daysUntilExpiry);
                $notifiedCount++;

                // Notify HR users
                foreach ($hrUsers as $hrUser) {
                    $this->notifyHR($record, $hrUser, $daysUntilExpiry);
                    $notifiedCount++;
                }

                $employeeName = $record->employee->user?->name ?? 'Unknown';
                $this->line("  ✓ Notified for expiring certification: {$record->training_name} (Employee: {$employeeName}, {$daysUntilExpiry} days remaining)");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to notify for certification {$record->id}: " . $e->getMessage());
                Log::error('Failed to send certification expiry notification', [
                    'training_record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("✅ Completed! Sent {$notifiedCount} notification(s).");

        return Command::SUCCESS;
    }

    /**
     * Notify the employee about their expiring certification.
     */
    private function notifyEmployee(TrainingRecord $record, int $daysUntilExpiry): void
    {
        $employee = $record->employee;

        if (!$employee || !$employee->user_id) {
            Log::warning('Cannot notify employee - no user linked', [
                'training_record_id' => $record->id,
                'employee_id' => $employee?->id,
            ]);
            return;
        }

        $employeeName = $employee->user?->name ?? 'Unknown';

        Notification::create([
            'user_id' => $employee->user_id,
            'type' => 'certification_expiring',
            'title' => 'Certification Expiring Soon',
            'message' => "Your certification '{$record->training_name}' will expire in {$daysUntilExpiry} day(s) on {$record->expiry_date->format('Y-m-d')}",
            'data' => [
                'training_record_id' => $record->id,
                'training_name' => $record->training_name,
                'provider' => $record->provider,
                'expiry_date' => $record->expiry_date->format('Y-m-d'),
                'days_until_expiry' => $daysUntilExpiry,
                'employee_id' => $employee->id,
                'employee_name' => $employeeName,
                'actor_id' => null,
                'actor_name' => 'System',
                'actor_avatar' => null,
                'actor_verified' => false,
            ],
        ]);
    }

    /**
     * Notify HR user about an employee's expiring certification.
     */
    private function notifyHR(TrainingRecord $record, User $hrUser, int $daysUntilExpiry): void
    {
        $employee = $record->employee;
        $employeeName = $employee->user?->name ?? 'Unknown';

        Notification::create([
            'user_id' => $hrUser->id,
            'type' => 'certification_expiring_hr',
            'title' => 'Employee Certification Expiring',
            'message' => "{$employeeName}'s certification '{$record->training_name}' will expire in {$daysUntilExpiry} day(s)",
            'data' => [
                'training_record_id' => $record->id,
                'training_name' => $record->training_name,
                'provider' => $record->provider,
                'expiry_date' => $record->expiry_date->format('Y-m-d'),
                'days_until_expiry' => $daysUntilExpiry,
                'employee_id' => $employee->id,
                'employee_name' => $employeeName,
                'actor_id' => null,
                'actor_name' => 'System',
                'actor_avatar' => null,
                'actor_verified' => false,
            ],
        ]);
    }

    /**
     * Get all HR users who should receive certification expiry notifications.
     */
    private function getHRUsers(): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['hr-admin', 'hr-manager']);
        })->get();
    }
}
