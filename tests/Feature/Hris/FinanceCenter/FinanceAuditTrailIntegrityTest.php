<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinanceCenterFlowFactory;

/**
 * Phase 14 — Finance audit trail tamper-evidence tests.
 *
 * Proves the hash chain is tamper-EVIDENT (detect-after-the-fact), not just that happy-path logging
 * works. Tamper simulations use raw DB::table() queries because the booted() guard only stops ORM
 * writes — a DB user with direct access (or a raw query path) can still mutate rows. The chain
 * re-validation walk exposes such mutations.
 *
 * Residual: immutability is APP-LEVEL ONLY. See "Residual hardening" note in finance-center-e2e-tests.md.
 */

// ---------------------------------------------------------------------------
// Chain-walk helper (private to this file)
// ---------------------------------------------------------------------------

/**
 * Walk the audit log in insertion order and return an array of validation errors.
 * An empty array means the chain is intact. Each error is a string describing the violation.
 *
 * @return string[]
 */
function walkChain(): array
{
    $logger = app(FinanceAuditLogger::class);
    $logs = FinanceAuditLog::orderBy('id')->get();
    $errors = [];
    $prev = null;

    foreach ($logs as $log) {
        // Hash integrity: recomputing from stored fields must match stored current_hash.
        $recomputed = $logger->recompute($log);
        if (! hash_equals($recomputed, $log->current_hash)) {
            $errors[] = "Row id={$log->id}: recomputed hash {$recomputed} ≠ stored current_hash {$log->current_hash}";
        }

        // Chain linkage: each row's previous_hash must equal the prior row's current_hash.
        if ($prev !== null) {
            if (! hash_equals($log->previous_hash ?? '', $prev->current_hash)) {
                $errors[] = "Row id={$log->id}: previous_hash {$log->previous_hash} ≠ prior row current_hash {$prev->current_hash}";
            }
        }

        $prev = $log;
    }

    return $errors;
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

// ---------------------------------------------------------------------------
// Area 1 — Happy-path chain: events exist + chain is internally consistent
// ---------------------------------------------------------------------------

describe('Area 1 — Happy-path chain integrity', function () {

    it('logs exist and form a valid hash chain after ER draft + submit + verify and PR create', function () {
        $employee = Employee::factory()->create();
        $financeUser = FinanceCenterFlowFactory::financeReviewer();
        $creator = FinanceCenterFlowFactory::financeCreator();
        $logger = app(FinanceAuditLogger::class);

        // --- ER lifecycle (real services) ---
        $erService = app(ExpenseRequestService::class);
        $er = $erService->create($employee, [
            'category' => 'transport',
            'title' => 'Taxi to client meeting',
            'amount' => '150000.00',
            'expense_date' => '2026-05-28',
            'payment_mode' => 'employee_reimbursement',
            'payee_name' => $employee->full_name ?? 'Test Employee',
        ], $creator);

        // Attach evidence directly so submit() can proceed (hasEvidence() check).
        DB::table('finance_attachments')->insert([
            'attachable_type' => ExpenseRequest::class,
            'attachable_id' => $er->id,
            'attachment_role' => \App\Models\Hris\FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
            'file_path' => 'evidence/test.pdf',
            'original_filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => hash('sha256', 'test-evidence'),
            'uploaded_by' => $creator->id,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $erService->submit($er->fresh(), $creator);
        $erService->verify($er->fresh(), $financeUser);

        // --- PR create (real service) ---
        $prService = app(PaymentRequestService::class);
        $pr = $prService->createFromExpenseRequest($er->fresh(), $creator);

        // --- Extra events via logger (real event_type strings used in code) ---
        // payment_bundle.created — emitted by PaymentBundleService::create()
        $bundle = PaymentBundle::create([
            'bundle_number' => 'PB-TEST-001',
            'public_verification_code' => 'vc-'.uniqid(),
            'status' => PaymentBundle::STATUS_DRAFT,
            'bundle_version' => 1,
            'total_amount' => '0.00',
            'currency' => 'IDR',
            'created_by' => $creator->id,
        ]);
        $logger->log($bundle, 'payment_bundle.created', $creator);

        // payment_bundle.payment_proof_uploaded + payment_bundle.marked_paid
        $logger->log($bundle, 'payment_bundle.payment_proof_uploaded', $creator, [
            'after' => ['attachment_id' => 1, 'file_hash' => hash('sha256', 'proof')],
        ]);
        $logger->log($bundle, 'payment_bundle.marked_paid', $creator, [
            'after' => ['status' => PaymentBundle::STATUS_PAID, 'total_amount' => '150000.00'],
        ]);

        // batch_source_marked_paid — emitted by BatchPaymentBundleService::markItemPaid()
        $logger->log($bundle, 'batch_source_marked_paid', $creator, [
            'after' => ['source_type' => 'payment_request', 'source_id' => $pr->id],
        ]);

        // --- Verify expected event_type strings are present ---
        $eventTypes = FinanceAuditLog::pluck('event_type')->all();

        // ER lifecycle events (from ExpenseRequestService)
        expect($eventTypes)->toContain('expense_request.draft_created');
        expect($eventTypes)->toContain('expense_request.submitted');
        expect($eventTypes)->toContain('expense_request.finance_verified');

        // PR create events (from PaymentRequestService::createFromExpenseRequest)
        expect($eventTypes)->toContain('payment_request.created_from_expense_request');
        expect($eventTypes)->toContain('expense_request.converted_to_pr');

        // Bundle + payment events (via logger direct — real event_type strings from code)
        expect($eventTypes)->toContain('payment_bundle.created');
        expect($eventTypes)->toContain('payment_bundle.payment_proof_uploaded');
        expect($eventTypes)->toContain('payment_bundle.marked_paid');
        expect($eventTypes)->toContain('batch_source_marked_paid');

        // --- Chain walk: MUST be error-free ---
        $errors = walkChain();
        expect($errors)->toBeEmpty('Chain walk errors: '.implode('; ', $errors));
    });

    it('first row has a null previous_hash (genesis)', function () {
        $logger = app(FinanceAuditLogger::class);
        $er = ExpenseRequest::create([
            'request_number' => 'ER-GENESIS-001',
            'employee_id' => Employee::factory()->create()->id,
            'category' => 'misc',
            'title' => 'Genesis',
            'amount' => '10000.00',
            'currency' => 'IDR',
            'expense_date' => '2026-05-01',
            'payment_mode' => 'employee_reimbursement',
            'status' => 'draft',
        ]);

        $logger->log($er, 'expense_request.draft_created', null);

        $first = FinanceAuditLog::orderBy('id')->first();
        expect($first->previous_hash)->toBeNull();

        $recomputed = $logger->recompute($first);
        expect(hash_equals($recomputed, $first->current_hash))->toBeTrue();
    });

    it('each subsequent row links previous_hash to the prior current_hash', function () {
        $logger = app(FinanceAuditLogger::class);
        $er = ExpenseRequest::create([
            'request_number' => 'ER-LINK-001',
            'employee_id' => Employee::factory()->create()->id,
            'category' => 'transport',
            'title' => 'Link test',
            'amount' => '50000.00',
            'currency' => 'IDR',
            'expense_date' => '2026-05-10',
            'payment_mode' => 'employee_reimbursement',
            'status' => 'draft',
        ]);

        $logger->log($er, 'expense_request.draft_created', null);
        $logger->log($er, 'expense_request.submitted', null);
        $logger->log($er, 'expense_request.finance_verified', null);

        $logs = FinanceAuditLog::orderBy('id')->get();
        expect($logs)->toHaveCount(3);

        expect($logs[0]->previous_hash)->toBeNull();
        expect($logs[1]->previous_hash)->toBe($logs[0]->current_hash);
        expect($logs[2]->previous_hash)->toBe($logs[1]->current_hash);

        expect(walkChain())->toBeEmpty();
    });

    // Event strings that correspond to each required lifecycle step but are NOT emitted by code:
    // - 'payment_bundle.signature_submitted' IS emitted by PaymentBundleSignatureService::submitSignature()
    //   (real event_type), but driving full signatures in this test is heavy (PDF, storage, PNG data).
    //   Noted here; covered by PaymentBundleSignatureCaptureTest.
    // - 'payment_bundle.signed_pdf_generated' IS emitted by PaymentBundleDocumentService::generateSignedPdf().
    //   Same reason — not exercised here.

});

// ---------------------------------------------------------------------------
// Area 2 — Tamper path: mutation detection via chain re-validation
// ---------------------------------------------------------------------------

describe('Area 2 — Tamper detection via chain re-validation', function () {

    /**
     * Build a 3-row chain and return the ordered log IDs.
     *
     * @return int[]
     */
    function buildThreeRowChain(): array
    {
        $logger = app(FinanceAuditLogger::class);
        $er = ExpenseRequest::create([
            'request_number' => 'ER-TAMPER-'.uniqid(),
            'employee_id' => Employee::factory()->create()->id,
            'category' => 'transport',
            'title' => 'Tamper test ER',
            'amount' => '200000.00',
            'currency' => 'IDR',
            'expense_date' => '2026-05-15',
            'payment_mode' => 'employee_reimbursement',
            'status' => 'draft',
        ]);

        $logger->log($er, 'expense_request.draft_created', null, ['reason' => 'original-row-1']);
        $logger->log($er, 'expense_request.submitted', null, ['reason' => 'original-row-2']);
        $logger->log($er, 'expense_request.finance_verified', null, ['reason' => 'original-row-3']);

        return FinanceAuditLog::orderBy('id')->pluck('id')->all();
    }

    it('chain is valid before any tampering (sanity)', function () {
        buildThreeRowChain();
        expect(walkChain())->toBeEmpty();
    });

    it('mutating a middle row field (reason) via raw SQL breaks chain validation for that row', function () {
        $ids = buildThreeRowChain();
        $middleId = $ids[1]; // 2nd row — middle of chain

        // Raw bypass — attacker path (booted() guard does NOT fire here)
        DB::table('finance_audit_logs')
            ->where('id', $middleId)
            ->update(['reason' => 'TAMPERED-REASON']);

        $errors = walkChain();
        expect($errors)->not->toBeEmpty();

        $affectedError = collect($errors)->first(fn ($e) => str_contains($e, "id={$middleId}"));
        expect($affectedError)->not->toBeNull("Expected a chain error for tampered row id={$middleId}");
    });

    it('mutating a middle row snapshot field (after_snapshot) via raw SQL breaks chain validation', function () {
        $ids = buildThreeRowChain();
        $middleId = $ids[1];

        DB::table('finance_audit_logs')
            ->where('id', $middleId)
            ->update(['after_snapshot' => json_encode(['injected' => 'tampered_data'])]);

        $errors = walkChain();
        expect($errors)->not->toBeEmpty();

        $affectedError = collect($errors)->first(fn ($e) => str_contains($e, "id={$middleId}"));
        expect($affectedError)->not->toBeNull("Expected a chain error for tampered row id={$middleId}");
    });

    it('deleting a middle row via raw SQL breaks the chain linkage', function () {
        $ids = buildThreeRowChain();
        $middleId = $ids[1];
        $lastId = $ids[2];

        // Raw bypass — attacker path
        DB::table('finance_audit_logs')->where('id', $middleId)->delete();

        // After deletion the 3rd row still has previous_hash = hash of the deleted 2nd row.
        // The chain walk now sees the 1st row followed directly by the 3rd row — the 3rd row's
        // previous_hash no longer matches the 1st row's current_hash → gap detected.
        $errors = walkChain();
        expect($errors)->not->toBeEmpty();

        $gapError = collect($errors)->first(fn ($e) => str_contains($e, "id={$lastId}") && str_contains($e, 'previous_hash'));
        expect($gapError)->not->toBeNull("Expected a linkage gap error for row id={$lastId} after middle row deleted");
    });

    it('breaking a previous_hash linkage via raw SQL update breaks the chain', function () {
        $ids = buildThreeRowChain();
        $lastId = $ids[2];

        // Point the last row's previous_hash at some garbage value
        DB::table('finance_audit_logs')
            ->where('id', $lastId)
            ->update(['previous_hash' => hash('sha256', 'forged-previous-hash')]);

        $errors = walkChain();
        expect($errors)->not->toBeEmpty();

        // The last row's hash recomputation also breaks because previous_hash is part of the hash input.
        $hashError = collect($errors)->first(fn ($e) => str_contains($e, "id={$lastId}"));
        expect($hashError)->not->toBeNull("Expected chain errors for row id={$lastId} with forged previous_hash");
    });

});

// ---------------------------------------------------------------------------
// Area 3 — App-level immutability guard (ORM path)
// ---------------------------------------------------------------------------

describe('Area 3 — App-level immutability guard (booted() hooks)', function () {

    function seedOneLog(): FinanceAuditLog
    {
        $logger = app(FinanceAuditLogger::class);
        $er = ExpenseRequest::create([
            'request_number' => 'ER-GUARD-'.uniqid(),
            'employee_id' => Employee::factory()->create()->id,
            'category' => 'misc',
            'title' => 'Guard test',
            'amount' => '50000.00',
            'currency' => 'IDR',
            'expense_date' => '2026-05-01',
            'payment_mode' => 'employee_reimbursement',
            'status' => 'draft',
        ]);

        return $logger->log($er, 'expense_request.draft_created', null);
    }

    it('$log->update() THROWS RuntimeException with the append-only update message', function () {
        $log = seedOneLog();

        expect(fn () => $log->update(['reason' => 'attempted ORM mutation']))
            ->toThrow(
                \RuntimeException::class,
                'Finance audit logs are append-only and cannot be updated.'
            );
    });

    it('$log->save() after field mutation THROWS RuntimeException with the append-only update message', function () {
        $log = seedOneLog();
        $log->reason = 'mutated via property';

        expect(fn () => $log->save())
            ->toThrow(
                \RuntimeException::class,
                'Finance audit logs are append-only and cannot be updated.'
            );
    });

    it('$log->delete() THROWS RuntimeException with the append-only delete message', function () {
        $log = seedOneLog();

        expect(fn () => $log->delete())
            ->toThrow(
                \RuntimeException::class,
                'Finance audit logs are append-only and cannot be deleted.'
            );
    });

    it('raw DB::table() update does NOT throw (attacker path — booted() guard is app-level only)', function () {
        $log = seedOneLog();

        // This must not throw — confirms the residual: DB-level writes bypass the guard.
        DB::table('finance_audit_logs')
            ->where('id', $log->id)
            ->update(['reason' => 'raw bypass — no exception']);

        $updated = DB::table('finance_audit_logs')->where('id', $log->id)->value('reason');
        expect($updated)->toBe('raw bypass — no exception');
    });

    it('raw DB::table() delete does NOT throw (attacker path — booted() guard is app-level only)', function () {
        $log = seedOneLog();

        DB::table('finance_audit_logs')->where('id', $log->id)->delete();

        expect(FinanceAuditLog::find($log->id))->toBeNull();
    });

});
