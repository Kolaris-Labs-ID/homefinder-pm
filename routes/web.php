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

Route::middleware(['web', 'auth'])->get(
    '/finance/payment-bundles/{bundle}/unsigned-pdf',
    [\App\Http\Controllers\PaymentBundlePdfController::class, 'unsigned']
)->name('finance.payment-bundles.unsigned-pdf');

// Named `login` target so the `auth` middleware can redirect guests (Laravel falls back to route('login')).
// Plain redirect (NOT ->guest()) so the intended signed URL stored by `auth` survives to /admin/login.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Signing-link preview. Middleware ORDER: signed (HMAC for everyone, incl. guests) → auth (bounce guests
// to login, preserving the full signed URL). See SignPaymentBundleController + PaymentBundleSignatureService.
// NOTE: the route param is `paymentSignature`, not `signature` — `signature` is reserved by
// Laravel's signed-URL generator (it owns the `signature` query param that carries the HMAC).
// Middleware: Laravel's priority list runs `auth` before `signed` regardless of array order, so a
// guest is bounced to login first (intended signed URL preserved) and `signed`+token gates enforce
// on the authenticated return. No bypass: a tampered/invalid HMAC still 403s once authenticated.
Route::middleware(['web', 'signed', 'auth'])->get(
    '/sign/payment-bundles/{bundle}/{paymentSignature}',
    [\App\Http\Controllers\SignPaymentBundleController::class, 'show']
)->name('sign.payment-bundle');

Route::middleware('auth')->prefix('api')->name('api.')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
});
