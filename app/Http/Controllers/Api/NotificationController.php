<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $perPage = (int) $request->integer('per_page', 10);

        $query = Notification::query()
            ->where('user_id', $userId)
            ->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function markAsRead(Notification $notification, NotificationService $service): JsonResponse
    {
        $userId = Auth::id();

        abort_if($notification->user_id !== $userId, 403);

        $service->markAsRead($notification->id, $userId);

        return response()->json(['status' => 'ok']);
    }

    public function markAllAsRead(NotificationService $service): JsonResponse
    {
        $userId = Auth::id();

        $service->markAllAsRead($userId);

        return response()->json(['status' => 'ok']);
    }
}

