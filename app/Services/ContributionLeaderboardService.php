<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContributionLeaderboardService
{
    public const TIME_RANGES = ['7days', '30days', 'thisweek', '1month'];

    /**
     * @return list<array{rank: int, total_score: int, stats: array{tickets_created: int, status_changes: int, comments_made: int, active_days: int}, user: array{id: int, name: string, avatar_url: string, avatar_initials: string}}>
     */
    public function getLeaderboard(string $timeRange, int $topCount): array
    {
        $dateRange = $this->getDateRangeFromTimeRange($timeRange);
        $startUtc = $dateRange['start']->copy()->startOfDay()->utc();
        $endUtc = $dateRange['end']->copy()->endOfDay()->utc();

        // 1. Gabungkan semua aktivitas (Tiket, History, Komentar) menggunakan UNION ALL
        $activities = DB::table('tickets')
            ->selectRaw("created_by as user_id, created_at, 'ticket' as type")
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->unionAll(
                DB::table('ticket_histories')
                    ->selectRaw("user_id, created_at, 'history' as type")
                    ->whereBetween('created_at', [$startUtc, $endUtc])
            )
            ->unionAll(
                DB::table('ticket_comments')
                    ->selectRaw("user_id, created_at, 'comment' as type")
                    ->whereBetween('created_at', [$startUtc, $endUtc])
            );

        // 2. Satu Query Utama: Join user, hitung skor, dan urutkan langsung dari Database
        $users = User::query()
            // Eager load posisi agar tidak memicu N+1 query saat mengambil fallback title
            ->with(['employee.primaryPosition.position', 'employee.positions.position'])
            ->leftJoinSub($activities, 'activities', 'users.id', '=', 'activities.user_id')
            // avatar_initials is an accessor computed from name, not a
            // column: selecting it fails the query and empties the board.
            ->select([
                'users.id',
                'users.name',
                'users.avatar_url',
            ])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN activities.type = 'ticket' THEN 1 ELSE 0 END), 0) as tickets_created,
                COALESCE(SUM(CASE WHEN activities.type = 'history' THEN 1 ELSE 0 END), 0) as status_changes,
                COALESCE(SUM(CASE WHEN activities.type = 'comment' THEN 1 ELSE 0 END), 0) as comments_made,
                COUNT(DISTINCT DATE(activities.created_at)) as active_days,
                (
                    COALESCE(SUM(CASE WHEN activities.type = 'ticket' THEN 1 ELSE 0 END), 0) * 2 +
                    COALESCE(SUM(CASE WHEN activities.type = 'history' THEN 1 ELSE 0 END), 0) * 5 +
                    COALESCE(SUM(CASE WHEN activities.type = 'comment' THEN 1 ELSE 0 END), 0) * 2 +
                    COUNT(DISTINCT DATE(activities.created_at)) * 1
                ) as total_score
            ")
            ->groupBy('users.id', 'users.name', 'users.avatar_url')
            // Filter hanya user yang memiliki skor > 0
            ->havingRaw('total_score > 0')
            // Urutkan berdasarkan skor tertinggi; skor sama diurutkan menurut nama,
            // seperti versi lama (users diurutkan per nama sebelum usort yang stabil).
            ->orderByDesc('total_score')
            ->orderBy('users.name')
            ->limit($topCount)
            ->get();

        // 3. Formatting Data (Tanpa query tambahan)
        $leaderboardData = [];
        $rank = 1;

        foreach ($users as $user) {
            $ticketsCreated = (int) $user->tickets_created;
            $statusChanges  = (int) $user->status_changes;
            $commentsMade   = (int) $user->comments_made;
            $activeDays     = (int) $user->active_days;
            $totalScore     = (int) $user->total_score;

            // Ambil posisi dari memory (Eager Loaded), bukan query baru
            $latestPosition = $user->employee?->positions->sortByDesc('start_date')->first()?->position?->title;

            $leaderboardData[] = [
                'user' => [
                    'id'               => $user->id,
                    'name'             => $user->name ?? '',
                    'avatar_url'       => $user->avatar_url,
                    'avatar_initials'  => $user->avatar_initials,
                    'position_title'   => $user->employee?->primaryPosition?->position?->title ?? $latestPosition,
                ],
                'stats' => [
                    'tickets_created' => $ticketsCreated,
                    'status_changes'  => $statusChanges,
                    'comments_made'   => $commentsMade,
                    'active_days'     => $activeDays,
                ],
                'total_score' => $totalScore,
                'rank' => $rank++,
            ];
        }

        return $leaderboardData;
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
}
