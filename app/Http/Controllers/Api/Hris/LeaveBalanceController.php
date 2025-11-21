<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = optional($request->user())->employee;

        abort_if(! $employee, 404, 'Employee profile not found.');

        $balances = $employee->leaveBalances()->with('leaveType')->get()->map(function ($balance) {
            return [
                'id' => $balance->id,
                'leave_type' => $balance->leaveType->only(['id', 'code', 'name']),
                'year' => $balance->year,
                'starting_balance' => $balance->starting_balance,
                'used_days' => $balance->used_days,
                'remaining_days' => $balance->remaining_days,
            ];
        });

        return response()->json([
            'data' => $balances,
        ]);
    }

    public function leaveTypes(): JsonResponse
    {
        $types = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'default_annual_quota']);

        return response()->json([
            'data' => $types,
        ]);
    }
}

