<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketComment;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

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

    public string $timeRange = '7days'; // Changed from 'thisweek' to '7days'
    public int $topCount = 10; // Number of top contributors to show

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
        $users = User::orderBy('name')->get();
        $leaderboardData = [];
        
        foreach ($users as $user) {
            $stats = $this->getUserStats($user->id);
            $totalScore = $this->calculateContributionScore($stats);
            
            if ($totalScore > 0) { // Only include users with contributions
                $leaderboardData[] = [
                    'user' => $user,
                    'stats' => $stats,
                    'total_score' => $totalScore,
                    'rank' => 0 // Will be set after sorting
                ];
            }
        }

        // Sort by total score descending
        usort($leaderboardData, function($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });

        // Assign ranks
        foreach ($leaderboardData as $index => &$data) {
            $data['rank'] = $index + 1;
        }

        // Return only top contributors
        return array_slice($leaderboardData, 0, $this->topCount);
    }

    private function calculateContributionScore(array $stats): int
    {
        // Updated weighted scoring system
        $weights = [
            'tickets_created' => 2,    // Updated: Tickets created = 2 points
            'status_changes' => 5,     // Updated: Status changes = 5 points
            'comments_made' => 2,      // Comments remain 2 points
            'active_days' => 1         // Consistency bonus remains 1 point
        ];

        return (
            ($stats['tickets_created'] * $weights['tickets_created']) +
            ($stats['status_changes'] * $weights['status_changes']) +
            ($stats['comments_made'] * $weights['comments_made']) +
            ($stats['active_days'] * $weights['active_days'])
        );
    }

    private function getUserStats(int $userId): array
    {
        $dateRange = $this->getDateRangeFromTimeRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        try {
            return [
                'tickets_created' => Ticket::where('created_by', $userId)
                    ->whereBetween('created_at', [
                        $startDate->startOfDay()->utc(), 
                        $endDate->endOfDay()->utc()
                    ])
                    ->count(),
                'status_changes' => TicketHistory::where('user_id', $userId)
                    ->whereBetween('created_at', [
                        $startDate->startOfDay()->utc(), 
                        $endDate->endOfDay()->utc()
                    ])
                    ->count(),
                'comments_made' => TicketComment::where('user_id', $userId)
                    ->whereBetween('created_at', [
                        $startDate->startOfDay()->utc(), 
                        $endDate->endOfDay()->utc()
                    ])
                    ->count(),
                'active_days' => $this->getUserActiveDays($userId)
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting user stats: ' . $e->getMessage());
            return [
                'tickets_created' => 0,
                'status_changes' => 0,
                'comments_made' => 0,
                'active_days' => 0
            ];
        }
    }

    private function getUserActiveDays(int $userId): int
    {
        $dateRange = $this->getDateRangeFromTimeRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        
        // Get unique dates where user had activity - simplified approach
        $ticketDates = Ticket::where('created_by', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(), 
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');
            
        $historyDates = TicketHistory::where('user_id', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(), 
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');
            
        $commentDates = TicketComment::where('user_id', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(), 
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');
            
        // Merge and count unique dates
        return $ticketDates->merge($historyDates)
            ->merge($commentDates)
            ->unique()
            ->count();
    }

    private function getDateRangeFromTimeRange(): array
    {
        $endDate = Carbon::now(config('app.timezone'));
        
        return match($this->timeRange) {
            '7days' => [
                'start' => $endDate->copy()->subDays(6), // 7 days including today
                'end' => $endDate
            ],
            '30days' => [
                'start' => $endDate->copy()->subDays(29), // 30 days including today
                'end' => $endDate
            ],
            'thisweek' => [
                'start' => $endDate->copy()->startOfWeek(),
                'end' => $endDate->copy()->endOfWeek()
            ],
            '1month' => [
                'start' => $endDate->copy()->subDays(29),
                'end' => $endDate
            ],
            default => [
                'start' => $endDate->copy()->subDays(6),
                'end' => $endDate
            ]
        };
    }

    public function getTimeRangeLabel(): string
    {
        return match($this->timeRange) {
            '7days' => 'Last 7 Days',
            '30days' => 'Last 30 Days', 
            'thisweek' => 'This Week',
            '1month' => 'Last Month',
            default => 'Last 7 Days'
        };
    }

    public function getRankBadgeColor(int $rank): string
    {
        return match($rank) {
            1 => 'bg-yellow-500 text-white', // Gold
            2 => 'bg-gray-400 text-white',   // Silver
            3 => 'bg-amber-600 text-white',  // Bronze
            default => 'bg-blue-500 text-white'
        };
    }

    public function getRankIcon(int $rank): string
    {
        return match($rank) {
            1 => '🏆',
            2 => '🥈',
            3 => '🥉',
            default => '🏅'
        };
    }
}