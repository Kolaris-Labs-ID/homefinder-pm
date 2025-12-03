<?php

namespace App\Providers;

use App\Filament\Resources\TicketResource\Pages\EditCommentModal;
use App\Models\Ticket;
use App\Observers\TicketObserver;
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
    }
}
