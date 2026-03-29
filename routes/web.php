<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\ExternalDashboard;
use App\Livewire\ExternalLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Google Authentication Routes
Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Emergency logout route (accessible without authentication)
Route::get('logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/admin/login')->with('info', 'You have been logged out.');
})->name('emergency.logout');

// External Dashboard Routes
Route::prefix('external')->name('external.')->group(function () {
    Route::get('/{token}', ExternalLogin::class)->name('login');
    Route::get('/{token}/dashboard', ExternalDashboard::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::post('/locale/switch', function (\Illuminate\Http\Request $request) {
        $locale = $request->input('locale');
        if (in_array($locale, ['en', 'id'])) {
            session(['locale' => $locale]);
        }

        return redirect()->back();
    })->name('locale.switch');
});

Route::middleware('auth')->get('/admin/attendance-photo', function (\Illuminate\Http\Request $request) {
    $path = $request->query('path');
    if (! $path || ! str_starts_with($path, 'attendance-photos/') || ! Storage::disk('local')->exists($path)) {
        abort(404, 'Photo not found.');
    }
    $file = Storage::disk('local')->get($path);
    $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Cache-Control', 'private, max-age=86400');
})->name('admin.attendance-photo');

Route::middleware('auth')->get('/admin/overtime-evidence', function (\Illuminate\Http\Request $request) {
    $path = $request->query('path');
    if (! $path || ! str_starts_with($path, 'overtime-evidence/') || ! Storage::disk('local')->exists($path)) {
        abort(404, 'Evidence not found.');
    }
    $file = Storage::disk('local')->get($path);
    $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Cache-Control', 'private, max-age=86400');
})->name('admin.overtime-evidence');

Route::middleware('auth')->prefix('api')->name('api.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
});
