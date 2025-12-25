<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Get all announcements
     * Returns announcements ordered by date (newest first)
     */
    public function index(Request $request)
    {
        $announcements = Announcement::where('is_active', true)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $announcements
        ]);
    }
}
