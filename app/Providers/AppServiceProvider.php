<?php

namespace App\Providers;

use App\Filament\Resources\TicketResource\Pages\EditCommentModal;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\SalaryStructure;
use App\Models\Ticket;
use App\Observers\Hris\PayrollAuditObserver;
use App\Observers\TicketObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('edit-comment-modal', EditCommentModal::class);

        // Register TicketObserver for auto-done detection
        // Requirements: 3.1
        Ticket::observe(TicketObserver::class);

        // Register Payroll audit observers
        $this->registerPayrollAuditObservers();

        // Configure Filament notifications to be centered
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);

        // Configure default notification view
        Notification::configureUsing(function (Notification $notification): void {
            $notification->view('filament.notifications.notification');
        });
    }

    /**
     * Register payroll audit observers for logging critical operations.
     */
    protected function registerPayrollAuditObservers(): void
    {
        $observer = new PayrollAuditObserver();

        SalaryStructure::created(fn ($model) => $observer->salaryStructureCreated($model));
        SalaryStructure::updated(fn ($model) => $observer->salaryStructureUpdated($model));
        PayrunBatch::updated(fn ($model) => $observer->payrunBatchUpdated($model));
        Reimbursement::updated(fn ($model) => $observer->reimbursementUpdated($model));
    }
}
