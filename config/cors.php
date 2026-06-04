<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:8081',
        'http://127.0.0.1:8081',
        'http://localhost:19006',
        'http://127.0.0.1:19006',
        'http://localhost:3000',   // Next.js job portal (dev)
        'http://127.0.0.1:3000',
        'https://jobs.partnershub.co', // public job portal (prod)
        'https://expo.dev',
        'https://snack.expo.dev',
        'https://*.vercel.app',
        env('FRONTEND_URL', 'https://phi-connect.vercel.app'),
        env('JOBS_PORTAL_URL', 'https://jobs.partnershub.co'),
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
        '#^https://.*\.expo\.dev$#',
        '#^https://.*\.expo\.app$#',
        '#^https://.*\.exp\.host$#',
        '#^https://.*\.exp\.direct$#',
    ],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 7200,

    'supports_credentials' => false,

];
