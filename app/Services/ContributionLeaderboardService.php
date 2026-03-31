<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ContributionLeaderboardService
{
    public const TIME_RANGES = ['7days', '30days', 'thisweek', '1month'];

    /**
     * @return list<array{rank: int, total_score: int, stats: array{tickets_created: int, status_changes: int, comments_made: int, active_days: int}, user: array{id: int, name: string, avatar_url: string, avatar_initials: string}}>
     */
    public function getLeaderboard(string $timeRange, int $topCount): array
    {
        $users = User::orderBy('name')->get();
        $leaderboardData = [];

        foreach ($users as $user) {
            $stats = $this->getUserStats($user->id, $timeRange);
            $totalScore = $this->calculateContributionScore($stats);

            if ($totalScore > 0) {
                $leaderboardData[] = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name ?? '',
                        'avatar_url' => $user->avatar_url,
                        'avatar_initials' => $user->avatar_initials,
                    ],
                    'stats' => $stats,
                    'total_score' => $totalScore,
                    'rank' => 0,
                ];
            }
        }

        usort($leaderboardData, fn ($a, $b) => $b['total_score'] <=> $a['total_score']);

        foreach ($leaderboardData as $index => &$data) {
            $data['rank'] = $index + 1;
        }
        unset($data);

        return array_slice($leaderboardData, 0, $topCount);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function getDateRangeFromTimeRange(string $timeRange): array
    {
        $endDate = Carbon::now(config('app.timezone'));

        return match ($timeRange) {
            '7days' => [
                'start' => $endDate->copy()->subDays(6),
                'end' => $endDate,
            ],
            '30days' => [
                'start' => $endDate->copy()->subDays(29),
                'end' => $endDate,
            ],
            'thisweek' => [
                'start' => $endDate->copy()->startOfWeek(),
                'end' => $endDate->copy()->endOfWeek(),
            ],
            '1month' => [
                'start' => $endDate->copy()->subDays(29),
                'end' => $endDate,
            ],
            default => [
                'start' => $endDate->copy()->subDays(6),
                'end' => $endDate,
            ],
        };
    }

    public function getTimeRangeLabel(string $timeRange): string
    {
        return match ($timeRange) {
            '7days' => 'Last 7 Days',
            '30days' => 'Last 30 Days',
            'thisweek' => 'This Week',
            '1month' => 'Last Month',
            default => 'Last 7 Days',
        };
    }

    /**
     * @return array{period_start: string, period_end: string}
     */
    public function getPeriodIsoBounds(string $timeRange): array
    {
        $range = $this->getDateRangeFromTimeRange($timeRange);

        return [
            'period_start' => $range['start']->copy()->startOfDay()->toIso8601String(),
            'period_end' => $range['end']->copy()->endOfDay()->toIso8601String(),
        ];
    }

    /**
     * @param  array{tickets_created: int, status_changes: int, comments_made: int, active_days: int}  $stats
     */
    public function calculateContributionScore(array $stats): int
    {
        $weights = [
            'tickets_created' => 2,
            'status_changes' => 5,
            'comments_made' => 2,
            'active_days' => 1,
        ];

        return ($stats['tickets_created'] * $weights['tickets_created'])
            + ($stats['status_changes'] * $weights['status_changes'])
            + ($stats['comments_made'] * $weights['comments_made'])
            + ($stats['active_days'] * $weights['active_days']);
    }

    /**
     * @return array{tickets_created: int, status_changes: int, comments_made: int, active_days: int}
     */
    public function getUserStats(int $userId, string $timeRange): array
    {
        $dateRange = $this->getDateRangeFromTimeRange($timeRange);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        try {
            return [
                'tickets_created' => Ticket::where('created_by', $userId)
                    ->whereBetween('created_at', [
                        $startDate->copy()->startOfDay()->utc(),
                        $endDate->copy()->endOfDay()->utc(),
                    ])
                    ->count(),
                'status_changes' => TicketHistory::where('user_id', $userId)
                    ->whereBetween('created_at', [
                        $startDate->copy()->startOfDay()->utc(),
                        $endDate->copy()->endOfDay()->utc(),
                    ])
                    ->count(),
                'comments_made' => TicketComment::where('user_id', $userId)
                    ->whereBetween('created_at', [
                        $startDate->copy()->startOfDay()->utc(),
                        $endDate->copy()->endOfDay()->utc(),
                    ])
                    ->count(),
                'active_days' => $this->getUserActiveDays($userId, $timeRange),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user stats: '.$e->getMessage());

            return [
                'tickets_created' => 0,
                'status_changes' => 0,
                'comments_made' => 0,
                'active_days' => 0,
            ];
        }
    }

    public function getUserActiveDays(int $userId, string $timeRange): int
    {
        $dateRange = $this->getDateRangeFromTimeRange($timeRange);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $startUtc = $startDate->copy()->startOfDay()->utc();
        $endUtc = $endDate->copy()->endOfDay()->utc();

        $ticketDates = Ticket::where('created_by', $userId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        $historyDates = TicketHistory::where('user_id', $userId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        $commentDates = TicketComment::where('user_id', $userId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        return $ticketDates->merge($historyDates)
            ->merge($commentDates)
            ->unique()
            ->count();
    }
}
