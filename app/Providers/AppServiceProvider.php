<?php

namespace App\Providers;

use App\Models\Hris\ClientVisit;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\SalaryStructure;
use App\Models\Project;
use App\Models\Ticket;
use App\Observers\Hris\ClientVisitObserver;
use App\Observers\Hris\PayrollAuditObserver;
use App\Observers\ProjectObserver;
use App\Observers\TicketObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

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

        // Register TicketObserver for auto-done detection
        // Requirements: 3.1
        Ticket::observe(TicketObserver::class);

        // Register ProjectObserver for auto-archiving tickets when project is done/archived
        Project::observe(ProjectObserver::class);

        // Register ClientVisitObserver for visit type classification and attendance update
        // Requirements: 5.1
        ClientVisit::observe(ClientVisitObserver::class);

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
