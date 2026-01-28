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
