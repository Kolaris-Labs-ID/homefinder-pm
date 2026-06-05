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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Hris\Screening\Contracts\ResumeTextExtractor::class,
            \App\Services\Hris\Screening\Extractors\PopplerResumeTextExtractor::class,
        );
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

        // Public job portal rate limits (browse moderate, apply strict)
        RateLimiter::for('public-jobs', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('public-apply', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

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
        $observer = new PayrollAuditObserver;

        SalaryStructure::created(fn ($model) => $observer->salaryStructureCreated($model));
        SalaryStructure::updated(fn ($model) => $observer->salaryStructureUpdated($model));
        PayrunBatch::updated(fn ($model) => $observer->payrunBatchUpdated($model));
        Reimbursement::updated(fn ($model) => $observer->reimbursementUpdated($model));
    }
}
