<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Services\ContributionLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    /**
     * Contribution leaderboard (matches Filament Analytics → Leaderboard).
     * Auth: any Sanctum user (same as /hris/team). For Filament parity use permission page_Leaderboard instead.
     */
    public function index(Request $request, ContributionLeaderboardService $leaderboard): JsonResponse
    {
        $validated = $request->validate([
            'time_range' => ['sometimes', 'string', Rule::in(ContributionLeaderboardService::TIME_RANGES)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $timeRange = $validated['time_range'] ?? '7days';
        $limit = (int) ($validated['limit'] ?? 10);

        try {
            $data = $leaderboard->getLeaderboard($timeRange, $limit);
            $period = $leaderboard->getPeriodIsoBounds($timeRange);

            return response()->json([
                'data' => $data,
                'meta' => array_merge([
                    'time_range' => $timeRange,
                    'time_range_label' => $leaderboard->getTimeRangeLabel($timeRange),
                    'limit' => $limit,
                ], $period),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load leaderboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
