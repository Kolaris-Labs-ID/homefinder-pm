<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily check for overdue and nearing overdue tickets/projects
Schedule::command('tickets:check-overdue')
    ->daily()
    ->at('09:00')
    ->timezone('Asia/Jakarta')
    ->description('Check for overdue and nearing overdue tickets and projects');

// Schedule daily generation of recurring tickets at 00:01
Schedule::command('tickets:generate-recurring')
    ->daily()
    ->at('00:01')
    ->timezone('Asia/Jakarta')
    ->description('Generate tickets from active recurring templates');

// Schedule daily check for expiring certifications (HRIS)
Schedule::command('hris:check-expiring-certifications')
    ->daily()
    ->at('08:00')
    ->timezone('Asia/Jakarta')
    ->description('Check for certifications expiring within 30 days and notify employees and HR');

// Flag attendance records with missing checkout at end of day
Schedule::command('attendance:flag-missing-checkouts')
    ->daily()
    ->at('23:55')
    ->timezone('Asia/Jakarta')
    ->description('Flag attendance records that have check-in but no check-out');

// Auto-close overtime sessions that have been running more than 6 hours
Schedule::command('lembur:auto-close')
    ->everyThirtyMinutes()
    ->timezone('Asia/Jakarta')
    ->description('Auto-close overtime sessions running longer than 6 hours and flag for HR review');

// Remind employees/HR about approaching Sick Leave medical certificate deadlines
Schedule::command('hris:leave-certificate-remind')
    ->everySixHours()
    ->timezone('Asia/Jakarta')
    ->description('Notify employees and HR when a Sick Leave certificate deadline is within 24 hours');

// Auto-reject provisionally-approved Sick Leave requests whose 48h certificate deadline passed
Schedule::command('hris:leave-certificate-expire')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->description('Auto-reject Sick Leave requests with no certificate submitted within 48 hours of approval');
