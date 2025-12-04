<?php

namespace App\Providers;

use App\Filament\Resources\TicketResource\Pages\EditCommentModal;
use App\Models\Ticket;
use App\Observers\TicketObserver;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
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

        // Configure Filament notifications to be centered
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);

        // Configure default notification view
        Notification::configureUsing(function (Notification $notification): void {
            $notification->view('filament.notifications.notification');
        });
    }
}
