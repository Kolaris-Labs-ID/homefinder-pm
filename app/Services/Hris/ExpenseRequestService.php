<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ExpenseRequest lifecycle (EXPENSE_MODULE.MD Phase 4).
 * Evidence is required to submit; any sensitive change after review resets verification.
 */
class ExpenseRequestService
{
    /** Fields whose change after finance review invalidates that review. */
    private const SENSITIVE = ['amount', 'payee_name', 'payee_bank_name', 'payee_bank_account_number'];

    public function create(Employee $employee, array $data): ExpenseRequest
    {
        return DB::transaction(function () use ($employee, $data) {
            $attrs = $this->details($data) + [
                'request_number' => $this->generateRequestNumber(),
                'employee_id' => $employee->id,
                'currency' => $data['currency'] ?? 'IDR',
                'payment_mode' => $data['payment_mode'],
                'status' => ExpenseRequest::STATUS_DRAFT,
            ];
            $this->applyBankAccount($attrs, $data);

            return ExpenseRequest::create($attrs);
        });
    }

    public function submit(ExpenseRequest $er): void
    {
        if (! $er->hasEvidence()) {
            throw new \RuntimeException('An expense request requires evidence before it can be submitted.');
        }

        $er->update([
            'status' => ExpenseRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function verify(ExpenseRequest $er, User $financeUser, ?string $notes = null): void
    {
        $er->update([
            'status' => ExpenseRequest::STATUS_FINANCE_VERIFIED,
            'finance_reviewed_by' => $financeUser->id,
            'finance_reviewed_at' => now(),
            'finance_notes' => $notes,
        ]);
    }

    public function reject(ExpenseRequest $er, User $financeUser, string $reason): void
    {
        $er->update([
            'status' => ExpenseRequest::STATUS_REJECTED,
            'finance_reviewed_by' => $financeUser->id,
            'finance_reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function requestRevision(ExpenseRequest $er, User $financeUser, string $note): void
    {
        $er->update([
            'status' => ExpenseRequest::STATUS_NEEDS_REVISION,
            'finance_reviewed_by' => $financeUser->id,
            'revision_requested_at' => now(),
            'finance_notes' => $note,
        ]);
    }

    public function revise(ExpenseRequest $er, array $data): void
    {
        if ($er->status !== ExpenseRequest::STATUS_NEEDS_REVISION) {
            throw new \RuntimeException('An expense request can only be revised after a revision is requested.');
        }

        $attrs = $this->details($data) + [
            'status' => ExpenseRequest::STATUS_SUBMITTED,
            'finance_reviewed_by' => null,
            'finance_reviewed_at' => null,
            'revision_requested_at' => null,
        ];
        $this->applyBankAccount($attrs, $data);

        $er->update($attrs);
    }

    /**
     * Edit ER details. Touching a sensitive field while finance-verified/under-review
     * resets the verification — the ER must be re-reviewed.
     */
    public function updateDetails(ExpenseRequest $er, array $data): void
    {
        $attrs = $this->details($data);
        $this->applyBankAccount($attrs, $data);

        $touchesSensitive = (bool) array_intersect(array_keys($data), self::SENSITIVE);
        $reviewed = in_array($er->status, [
            ExpenseRequest::STATUS_FINANCE_VERIFIED,
            ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW,
        ], true);

        if ($touchesSensitive && $reviewed) {
            $attrs['status'] = ExpenseRequest::STATUS_SUBMITTED;
            $attrs['finance_reviewed_by'] = null;
            $attrs['finance_reviewed_at'] = null;
        }

        $er->update($attrs);
    }

    public function closeAsRecordOnly(ExpenseRequest $er): void
    {
        if (! $er->isRecordOnly()) {
            throw new \RuntimeException('Only company_paid_record_only expense requests can be closed as record-only.');
        }

        $er->update([
            'status' => ExpenseRequest::STATUS_RECORD_ONLY_CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function cancel(ExpenseRequest $er): void
    {
        $er->update(['status' => ExpenseRequest::STATUS_CANCELLED]);
    }

    private function details(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'category', 'title', 'description', 'amount', 'expense_date',
            'department_id', 'project_id', 'payee_name', 'payee_bank_name',
        ]));
    }

    private function applyBankAccount(array &$attrs, array $data): void
    {
        if (! array_key_exists('payee_bank_account_number', $data)) {
            return;
        }

        $number = $data['payee_bank_account_number'];
        $attrs['payee_bank_account_number_encrypted'] = $number;
        $attrs['payee_bank_account_last4'] = $number
            ? substr(preg_replace('/\D/', '', $number), -4)
            : null;
    }

    private function generateRequestNumber(): string
    {
        $prefix = 'ER-'.now()->format('Ym').'-';

        $last = ExpenseRequest::where('request_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('request_number')
            ->value('request_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
