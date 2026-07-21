<?php

namespace App\Console\Commands;

use App\Models\Hris\OvertimePayBatch;
use App\Services\Hris\OvertimePayService;
use Illuminate\Console\Command;

class GenerateWeeklyOvertimePay extends Command
{
    protected $signature = 'overtime-pay:generate
                            {--week-start= : Monday of the week to generate (Y-m-d format)}
                            {--force : Force generation even if batch already exists}';

    protected $description = 'Generate weekly overtime pay batch from approved overtime logs';

    public function handle(OvertimePayService $service): int
    {
        $this->info('Generating weekly overtime pay batch...');

        if ($weekStart = $this->option('week-start')) {
            try {
                $start = \Carbon\Carbon::parse($weekStart)->startOfDay();
                $end = $start->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $payment = $end->copy()->next(\Carbon\Carbon::TUESDAY);
            } catch (\Exception $e) {
                $this->error('Invalid date format. Use Y-m-d (e.g., 2026-04-07)');

                return Command::FAILURE;
            }
        } else {
            $dates = OvertimePayService::getPreviousWeekDates();
            $start = $dates['start'];
            $end = $dates['end'];
            $payment = $dates['payment'];
        }

        $this->info("Week: {$start->format('d M Y')} - {$end->format('d M Y')}");
        $this->info("Payment Date: {$payment->format('d M Y')}");

        $existingBatch = OvertimePayBatch::forWeek($start)->first();
        if ($existingBatch && ! $this->option('force')) {
            $this->warn("Batch already exists for this week (ID: {$existingBatch->id}, Status: {$existingBatch->status})");
            $this->warn('Use --force to regenerate');

            return Command::FAILURE;
        }

        if ($existingBatch && $this->option('force') && $existingBatch->isLocked()) {
            $this->error("Batch {$existingBatch->id} is {$existingBatch->status} and must not be regenerated.");
            $this->error('Settle rate corrections for locked or paid batches as a separate adjustment.');

            return Command::FAILURE;
        }

        if ($existingBatch && $this->option('force')) {
            $this->warn("Deleting existing batch (ID: {$existingBatch->id})...");
            $existingBatch->payments()->each(function ($payment) {
                // Unlink OvertimeLogs before deleting
                $payment->overtimeLogs()->update(['overtime_payment_id' => null]);
            });
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
                    ['Batch ID',                 $batch->id],
                    ['Week',                     $batch->week_label],
                    ['Total Employees',          $batch->total_employees],
                    ['Employees with Warnings',  $batch->employees_with_warnings],
                    ['Total Amount',             'Rp '.number_format($batch->total_amount, 0, ',', '.')],
                    ['Status',                   $batch->status_label],
                ]
            );

            if ($batch->employees_with_warnings > 0) {
                $this->newLine();
                $this->warn("{$batch->employees_with_warnings} employee(s) have missing bank information.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate batch: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
