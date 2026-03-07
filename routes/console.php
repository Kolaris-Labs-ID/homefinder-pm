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

// Schedule weekly todo reminder every Monday at 10:00 AM
// DISABLED - using kanban-based project/epic/ticket system instead
// Schedule::command('hris:send-weekly-todo-reminder')
//     ->weeklyOn(1, '10:00')
//     ->timezone('Asia/Jakarta')
//     ->description('Send weekly todo reminder to employees who have checked in on Monday');

// Flag attendance records with missing checkout at end of day
Schedule::command('attendance:flag-missing-checkouts')
    ->daily()
    ->at('23:55')
    ->timezone('Asia/Jakarta')
    ->description('Flag attendance records that have check-in but no check-out');
