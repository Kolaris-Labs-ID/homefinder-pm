<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\PayrollRecord;
use App\Services\Hris\PayslipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function __construct(
        protected PayslipService $payslipService
    ) {}

    /**
     * List employee's payslips.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'message' => 'Employee profile not found',
            ], 404);
        }

        $payslips = $this->payslipService->getEmployeePayslips($employee);

        return response()->json([
            'data' => $payslips->map(fn ($record) => [
                'id' => $record->id,
                'period_start' => $record->payrunBatch->period_start_date->toDateString(),
                'period_end' => $record->payrunBatch->period_end_date->toDateString(),
                'pay_date' => $record->payrunBatch->pay_date->toDateString(),
                'gross_income' => (float) $record->gross_income,
                'net_income' => (float) $record->net_income,
            ]),
        ]);
    }

    /**
     * Get payslip details.
     */
    public function show(Request $request, PayrollRecord $payrollRecord): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'message' => 'Employee profile not found',
            ], 404);
        }

        // Authorization: employee can only view their own payslips
        if ($payrollRecord->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if payslip is available
        if (!$this->payslipService->isPayslipAvailable($payrollRecord)) {
            return response()->json([
                'message' => 'Payslip not yet available',
            ], 404);
        }

        return response()->json([
            'data' => $this->payslipService->getPayslipSummary($payrollRecord),
        ]);
    }
}
