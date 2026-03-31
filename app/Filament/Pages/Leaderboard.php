<?php

namespace App\Filament\Pages;

use App\Services\ContributionLeaderboardService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class Leaderboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Leaderboard';

    protected static ?string $title = 'Contribution Leaderboard';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.leaderboard';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'leaderboard';

    public string $timeRange = '7days';

    public int $topCount = 10;

    public function getSubheading(): ?string
    {
        return 'Top contributors ranked by their overall activity and engagement';
    }

    public function setTimeRange(string $range): void
    {
        $this->timeRange = $range;
    }

    public function setTopCount(int $count): void
    {
        $this->topCount = $count;
    }

    public function getLeaderboardData(): array
    {
        return app(ContributionLeaderboardService::class)->getLeaderboard($this->timeRange, $this->topCount);
    }

    public function getTimeRangeLabel(): string
    {
        return app(ContributionLeaderboardService::class)->getTimeRangeLabel($this->timeRange);
    }

    public function getRankBadgeColor(int $rank): string
    {
        return match ($rank) {
            1 => 'bg-yellow-500 text-white',
            2 => 'bg-gray-400 text-white',
            3 => 'bg-amber-600 text-white',
            default => 'bg-blue-500 text-white',
        };
    }

    public function getRankIcon(int $rank): string
    {
        return match ($rank) {
            1 => '🏆',
            2 => '🥈',
            3 => '🥉',
            default => '🏅',
        };
    }
}
