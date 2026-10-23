<?php

namespace App\Http\Middleware;

use App\Facades\Features;
use App\Features\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `feature:<key>`. A route behind a feature the company has off
 * answers 404, as if it did not exist.
 */
class EnsureFeatureActive
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        abort_unless(Features::active(Feature::from($key)), 404);

        return $next($request);
    }
}
