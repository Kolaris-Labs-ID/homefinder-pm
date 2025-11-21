<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Models\Hris\LeaveRequest;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class LeaveRequestActionHandler
{
    public static function approve(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                return;
            }

            app(LeaveBalanceService::class)->consume($leaveRequest);

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        });
    }

    public static function reject(LeaveRequest $leaveRequest, string $reason): void
    {
        DB::transaction(function () use ($leaveRequest, $reason) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                app(LeaveBalanceService::class)->release($leaveRequest);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }

    public static function cancel(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                app(LeaveBalanceService::class)->release($leaveRequest);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        });
    }
}

