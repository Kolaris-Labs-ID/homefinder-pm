<?php

// Suppress PHP 8.5 deprecation warnings from vendor packages
error_reporting(E_ALL & ~E_DEPRECATED);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Load .env.local if it exists for local development, otherwise fall back to .env
// This allows you to test locally without affecting production settings
// $envLocalPath = __DIR__.'/../.env.local';
// $envPath = __DIR__.'/../.env';

// if (file_exists($envLocalPath)) {
//     // Load .env.local for local development
//     $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env.local');
//     $dotenv->load();
// } elseif (file_exists($envPath)) {
//     // Fall back to .env if .env.local doesn't exist
//     $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env');
//     $dotenv->load();
// }

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind Cloudflare/Vercel/proxy: trust forwarded headers so request IP
        // is the real client IP (used for applicant applied_ip + rate limiting).
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
