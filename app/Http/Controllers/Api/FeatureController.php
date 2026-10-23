<?php

namespace App\Http\Controllers\Api;

use App\Facades\Features;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FeatureController extends Controller
{
    /**
     * GET /api/features
     *
     * The company's feature flags, so the mobile app can show or hide features
     * (for example the Google sign-in button) before anyone logs in. Public on
     * purpose: the values say which features exist, nothing more.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Features::all(),
        ]);
    }
}
