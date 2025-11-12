<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Load .env.local if it exists for local development, otherwise fall back to .env
// This allows you to test locally without affecting production settings
$envLocalPath = __DIR__.'/../.env.local';
$envPath = __DIR__.'/../.env';

if (file_exists($envLocalPath)) {
    // Load .env.local for local development
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env.local');
    $dotenv->load();
} elseif (file_exists($envPath)) {
    // Fall back to .env if .env.local doesn't exist
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..', '.env');
    $dotenv->load();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
