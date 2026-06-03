<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * ExpenseRequest lifecycle (EXPENSE_MODULE.MD Phase 4).
 * Evidence is required to submit; any sensitive change after review resets verification.
 */
class ExpenseRequestService
{
    /** Fields whose change after finance review invalidates that review. */
    private const SENSITIVE = ['amount', 'payee_name', 'payee_bank_name', 'payee_bank_account_number'];

    public function __construct(private FinanceAuditLogger $audit) {}

    /**
     * Idempotent on (employee, client_request_id): a repeated create with the same key
     * returns the existing ER instead of inserting a duplicate (mobile double-submit / retry).
     */
    public function create(Employee $employee, array $data, ?User $actor = null): ExpenseRequest
    {
        $clientRequestId = $data['client_request_id'] ?? null;

        if ($clientRequestId !== null) {
            if ($existing = $this->findByClientRequestId($employee, $clientRequestId)) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($employee, $data, $actor, $clientRequestId) {
            $attrs = $this->details($data) + [
                'request_number' => $this->generateRequestNumber(),
                'client_request_id' => $clientRequestId,
                'employee_id' => $employee->id,
                'currency' => $data['currency'] ?? 'IDR',
                'payment_mode' => $data['payment_mode'],
                'status' => ExpenseRequest::STATUS_DRAFT,
            ];
            $this->applyBankAccount($attrs, $data);

            try {
                $er = ExpenseRequest::create($attrs);
            } catch (QueryException $e) {
                // Concurrent duplicate for the same key lost the unique-index race — return the winner.
                if ($clientRequestId !== null && $existing = $this->findByClientRequestId($employee, $clientRequestId)) {
                    return $existing;
                }
                throw $e;
            }

            $this->audit->log($er, 'expense_request.draft_created', $actor ?? $employee->user);

            return $er;
        });
    }

    private function findByClientRequestId(Employee $employee, string $clientRequestId): ?ExpenseRequest
    {
        return ExpenseRequest::where('employee_id', $employee->id)
            ->where('client_request_id', $clientRequestId)
            ->first();
    }

    public function submit(ExpenseRequest $er, ?User $actor = null): void
    {
        if (! $er->hasEvidence()) {
            throw new \RuntimeException('An expense request requires evidence before it can be submitted.');
        }

        DB::transaction(function () use ($er, $actor) {
            $er->update([
                'status' => ExpenseRequest::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
            $this->audit->log($er, 'expense_request.submitted', $actor ?? $er->employee?->user);
        });
    }

    public function verify(ExpenseRequest $er, User $financeUser, ?string $notes = null): void
    {
        if (! in_array($er->status, [ExpenseRequest::STATUS_SUBMITTED, ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW], true)) {
            throw new \RuntimeException('Only a submitted expense request can be verified.');
        }

        DB::transaction(function () use ($er, $financeUser, $notes) {
            $er->update([
                'status' => ExpenseRequest::STATUS_FINANCE_VERIFIED,
                'finance_reviewed_by' => $financeUser->id,
                'finance_reviewed_at' => now(),
                'finance_notes' => $notes,
            ]);
            $this->audit->log($er, 'expense_request.finance_verified', $financeUser);
        });
    }

    public function reject(ExpenseRequest $er, User $financeUser, string $reason): void
    {
        if (! in_array($er->status, [ExpenseRequest::STATUS_SUBMITTED, ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW], true)) {
            throw new \RuntimeException('Only a submitted expense request can be rejected.');
        }

        DB::transaction(function () use ($er, $financeUser, $reason) {
            $er->update([
                'status' => ExpenseRequest::STATUS_REJECTED,
                'finance_reviewed_by' => $financeUser->id,
                'finance_reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $this->audit->log($er, 'expense_request.rejected', $financeUser, ['reason' => $reason]);
        });
    }

    public function requestRevision(ExpenseRequest $er, User $financeUser, string $note): void
    {
        if (! in_array($er->status, [ExpenseRequest::STATUS_SUBMITTED, ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW], true)) {
            throw new \RuntimeException('Only a submitted expense request can have a revision requested.');
        }

        DB::transaction(function () use ($er, $financeUser, $note) {
            $er->update([
                'status' => ExpenseRequest::STATUS_NEEDS_REVISION,
                'finance_reviewed_by' => $financeUser->id,
                'revision_requested_at' => now(),
                'finance_notes' => $note,
            ]);
            $this->audit->log($er, 'expense_request.revision_requested', $financeUser, ['reason' => $note]);
        });
    }

    public function revise(ExpenseRequest $er, array $data, ?User $actor = null): void
    {
        if ($er->status !== ExpenseRequest::STATUS_NEEDS_REVISION) {
            throw new \RuntimeException('An expense request can only be revised after a revision is requested.');
        }

        DB::transaction(function () use ($er, $data, $actor) {
            $attrs = $this->details($data) + [
                'status' => ExpenseRequest::STATUS_SUBMITTED,
                'finance_reviewed_by' => null,
                'finance_reviewed_at' => null,
                'revision_requested_at' => null,
            ];
            $this->applyBankAccount($attrs, $data);

            $er->update($attrs);
            $this->audit->log($er, 'expense_request.revised', $actor ?? $er->employee?->user);
        });
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

    public function closeAsRecordOnly(ExpenseRequest $er, ?User $actor = null): void
    {
        if (! $er->isRecordOnly()) {
            throw new \RuntimeException('Only company_paid_record_only expense requests can be closed as record-only.');
        }

        DB::transaction(function () use ($er, $actor) {
            $er->update([
                'status' => ExpenseRequest::STATUS_RECORD_ONLY_CLOSED,
                'closed_at' => now(),
            ]);
            $this->audit->log($er, 'expense_request.record_only_closed', $actor ?? $er->employee?->user);
        });
    }

    public function cancel(ExpenseRequest $er, ?User $actor = null): void
    {
        DB::transaction(function () use ($er, $actor) {
            $er->update(['status' => ExpenseRequest::STATUS_CANCELLED]);
            $this->audit->log($er, 'expense_request.cancelled', $actor ?? $er->employee?->user);
        });
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
