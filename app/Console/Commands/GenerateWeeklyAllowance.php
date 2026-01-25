<?php

namespace App\Console\Commands;

use App\Services\Hris\WeeklyAllowanceService;
use Illuminate\Console\Command;

class GenerateWeeklyAllowance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weekly-allowance:generate
                            {--week-start= : Monday of the week to generate (Y-m-d format)}
                            {--force : Force generation even if batch already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate weekly allowance batch for the previous week';

    /**
     * Execute the console command.
     */
    public function handle(WeeklyAllowanceService $service): int
    {
        $this->info('Generating weekly allowance batch...');

        // Determine week dates
        if ($weekStart = $this->option('week-start')) {
            try {
                $start = \Carbon\Carbon::parse($weekStart)->startOfDay();
                $end = $start->copy()->addDays(4); // Friday
                $payment = $end->copy()->next(\Carbon\Carbon::TUESDAY);
            } catch (\Exception $e) {
                $this->error('Invalid date format. Use Y-m-d (e.g., 2026-01-20)');

                return Command::FAILURE;
            }
        } else {
            $dates = WeeklyAllowanceService::getPreviousWeekDates();
            $start = $dates['start'];
            $end = $dates['end'];
            $payment = $dates['payment'];
        }

        $this->info("Week: {$start->format('d M Y')} - {$end->format('d M Y')}");
        $this->info("Payment Date: {$payment->format('d M Y')}");

        // Check if batch already exists
        $existingBatch = \App\Models\Hris\WeeklyAllowanceBatch::forWeek($start)->first();
        if ($existingBatch && !$this->option('force')) {
            $this->warn("Batch already exists for this week (ID: {$existingBatch->id}, Status: {$existingBatch->status})");
            $this->warn('Use --force to regenerate');

            return Command::FAILURE;
        }

        if ($existingBatch && $this->option('force')) {
            $this->warn("Deleting existing batch (ID: {$existingBatch->id})...");
            $existingBatch->payments()->delete();
            $existingBatch->delete();
        }

        try {
            $batch = $service->generateBatch($start, $end, $payment);

            $this->newLine();
            $this->info('Batch generated successfully!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Batch ID', $batch->id],
                    ['Week', $batch->week_label],
                    ['Total Employees', $batch->total_employees],
                    ['Employees with Warnings', $batch->employees_with_warnings],
                    ['Total Amount', 'Rp ' . number_format($batch->total_amount, 0, ',', '.')],
                    ['Status', $batch->status_label],
                ]
            );

            if ($batch->employees_with_warnings > 0) {
                $this->newLine();
                $this->warn("{$batch->employees_with_warnings} employee(s) have missing bank information.");
                $this->warn('Please update their data before submitting for approval.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate batch: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
