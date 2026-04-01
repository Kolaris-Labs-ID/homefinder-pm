<?php

namespace App\Http\Controllers\Api\Pm;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get recent activity logs for the PM module.
     *
     * GET /api/pm/activities
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 15);

        // Get activity logs for projects the user is a member of
        $query = ActivityLog::query()
            ->with(['user:id,name,email,avatar_url'])
            ->latest();

        if (!$user->hasRole('super_admin')) {
            $projectIds = $user->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        }

        $activities = $query->limit($limit)->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'event_type' => $activity->event_type,
                'subject_type' => class_basename($activity->subject_type),
                'subject_id' => $activity->subject_id,
                'project_id' => $activity->project_id,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'avatar_url' => $activity->user->avatar_url,
                ] : null,
                'meta' => $activity->meta,
                'created_at' => $activity->created_at?->toIso8601String(),
                'time_ago' => $activity->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'data' => $activities,
        ]);
    }
}
