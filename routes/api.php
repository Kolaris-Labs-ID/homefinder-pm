<?php

use App\Http\Controllers\Api\Hris\LeaveBalanceController;
use App\Http\Controllers\Api\Hris\LeaveRequestController;
use App\Http\Controllers\Api\Hris\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('hris')
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
        Route::get('/leave-types', [LeaveBalanceController::class, 'leaveTypes']);

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::put('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
    });

