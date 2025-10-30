<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use App\Models\Notification;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        
        // For super_admin, show "All Notifications" tab first
        if (auth()->user()->hasRole('super_admin')) {
            $tabs['all_notifications'] = Tab::make('All Notifications')
                ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'ticket.project']))
                ->badge(Notification::count())
                ->badgeColor('primary');
        }
        
        // Standard tabs for all users
        $tabs['all'] = Tab::make(auth()->user()->hasRole('super_admin') ? 'My Notifications' : 'All')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->badge(Notification::where('user_id', auth()->id())->count());
            
        $tabs['unread'] = Tab::make('Unread')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('user_id', auth()->id())->unread()
            )
            ->badge(Notification::where('user_id', auth()->id())->unread()->count())
            ->badgeColor('danger');
            
        $tabs['read'] = Tab::make('Read')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('user_id', auth()->id())->read()
            )
            ->badge(Notification::where('user_id', auth()->id())->read()->count())
            ->badgeColor('success');
            
        return $tabs;
    }
}
