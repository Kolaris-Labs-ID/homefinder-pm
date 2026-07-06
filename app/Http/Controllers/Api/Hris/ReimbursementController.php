<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\Reimbursement;
use App\Services\Hris\ReimbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReimbursementController extends Controller
{
    public function __construct(
        protected ReimbursementService $reimbursementService
    ) {}

    /**
     * List employee's reimbursements.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee profile not found',
            ], 404);
        }

        $reimbursements = $this->reimbursementService->getEmployeeReimbursements($employee);

        return response()->json([
            'data' => $reimbursements->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'amount' => $r->amount,
                'description' => $r->description,
                'status' => $r->status,
                'rejection_reason' => $r->rejection_reason,
                'paid_at' => $r->paid_at?->toDateTimeString(),
                'created_at' => $r->created_at->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Create a new reimbursement request.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee profile not found',
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(Reimbursement::getTypes())],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $receipt = $request->file('receipt');

        $reimbursement = $this->reimbursementService->create($employee, $validated, $receipt);

        return response()->json([
            'message' => 'Reimbursement request created successfully',
            'data' => [
                'id' => $reimbursement->id,
                'type' => $reimbursement->type,
                'amount' => $reimbursement->amount,
                'status' => $reimbursement->status,
            ],
        ], 201);
    }

    /**
     * Cancel a pending reimbursement.
     */
    public function cancel(Request $request, Reimbursement $reimbursement): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'Employee profile not found',
            ], 404);
        }

        // Authorization: employee can only cancel their own reimbursements
        if ($reimbursement->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $this->reimbursementService->cancel($reimbursement);

            return response()->json([
                'message' => 'Reimbursement cancelled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
