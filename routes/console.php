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
