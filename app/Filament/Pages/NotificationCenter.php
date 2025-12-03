<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class NotificationCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Notification Center';

    protected static ?string $title = 'Notification Center';

    protected static ?string $slug = 'notification-center';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.notification-center';

    public static function getNavigationBadge(): ?string
    {
        return auth()->user()?->unreadNotifications()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
