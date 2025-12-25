<?php

namespace App\Filament\Resources\AnnouncementResource\Widgets;

use App\Models\Hris\Announcement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnnouncementStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total = Announcement::count();
        $active = Announcement::where('is_active', true)->count();
        $today = Announcement::whereDate('date', today())->count();

        $byType = Announcement::where('is_active', true)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            Stat::make('Total Announcements', $total)
                ->description('All time')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('gray'),

            Stat::make('Active Announcements', $active)
                ->description('Visible in mobile app')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Today\'s Announcements', $today)
                ->description('Scheduled for today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('By Type', '')
                ->description(
                    collect($byType)
                        ->map(fn($count, $type) => ucfirst($type) . ': ' . $count)
                        ->join(' | ')
                )
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),
        ];
    }
}
