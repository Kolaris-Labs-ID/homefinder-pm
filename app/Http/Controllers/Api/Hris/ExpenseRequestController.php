<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Employee-facing Expense Request API (EXPENSE_MODULE.MD Phase 4/14).
 * Money is always returned as a decimal string (never float). No direct PR creation here.
 */
class ExpenseRequestController extends Controller
{
    public function __construct(
        protected ExpenseRequestService $service,
        protected FinanceAttachmentService $attachments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'Employee profile not found');

        $items = ExpenseRequest::where('employee_id', $employee->id)->latest()->get();

        return response()->json(['data' => $items->map(fn ($er) => $this->summary($er))]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'Employee profile not found');

        $validated = $request->validate($this->rules());

        $er = $this->service->create($employee, $validated);

        return response()->json(['data' => $this->detail($er)], 201);
    }

    public function show(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorize('view', $expenseRequest);

        return response()->json(['data' => $this->detail($expenseRequest)]);
    }

    public function uploadEvidence(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorize('uploadEvidence', $expenseRequest);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $this->attachments->store(
            $expenseRequest,
            $validated['file'],
            FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
            $request->user(),
        );

        return response()->json(['data' => [
            'id' => $attachment->id,
            'original_filename' => $attachment->original_filename,
            'duplicate_warning' => $attachment->hasDuplicateWarning(),
        ]], 201);
    }

    public function submit(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorize('submit', $expenseRequest);

        try {
            $this->service->submit($expenseRequest);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->detail($expenseRequest->fresh())]);
    }

    public function revise(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorize('revise', $expenseRequest);

        $validated = $request->validate($this->rules(forCreate: false));

        try {
            $this->service->revise($expenseRequest, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->detail($expenseRequest->fresh())]);
    }

    public function cancel(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorize('update', $expenseRequest);

        $this->service->cancel($expenseRequest);

        return response()->json(['message' => 'Expense request cancelled']);
    }

    private function rules(bool $forCreate = true): array
    {
        $required = $forCreate ? 'required' : 'sometimes';

        return [
            'category' => [$required, 'string', 'max:100'],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => [$required, 'numeric', 'min:1'],
            'expense_date' => [$required, 'date'],
            'payment_mode' => [$forCreate ? 'required' : 'sometimes', Rule::in([
                ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
                ExpenseRequest::MODE_VENDOR_PAYMENT,
                ExpenseRequest::MODE_COMPANY_PAID_RECORD_ONLY,
                ExpenseRequest::MODE_OTHER,
            ])],
            'currency' => ['nullable', 'string', 'size:3'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'payee_bank_name' => ['nullable', 'string', 'max:255'],
            'payee_bank_account_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function summary(ExpenseRequest $er): array
    {
        return [
            'id' => $er->id,
            'request_number' => $er->request_number,
            'category' => $er->category,
            'title' => $er->title,
            'amount' => $er->amount, // decimal string — never float
            'currency' => $er->currency,
            'status' => $er->status,
            'payment_mode' => $er->payment_mode,
            'expense_date' => optional($er->expense_date)->toDateString(),
            'created_at' => $er->created_at->toDateTimeString(),
        ];
    }

    private function detail(ExpenseRequest $er): array
    {
        $pr = $er->paymentRequests()->latest('id')->first();

        return $this->summary($er) + [
            'description' => $er->description,
            'finance_notes' => $er->finance_notes,
            'rejection_reason' => $er->rejection_reason,
            'has_evidence' => $er->hasEvidence(),
            'payee_name' => $er->payee_name,
            'payee_bank_name' => $er->payee_bank_name,
            'payee_bank_account_last4' => $er->payee_bank_account_last4,
            'submitted_at' => optional($er->submitted_at)->toDateTimeString(),
            // Non-sensitive payment progress for the employee (no amount/bank details).
            'payment' => $pr ? [
                'status' => $pr->status,
                'status_label' => (string) str($pr->status)->headline(),
                'payment_request_number' => $pr->payment_request_number,
            ] : null,
        ];
    }
}
