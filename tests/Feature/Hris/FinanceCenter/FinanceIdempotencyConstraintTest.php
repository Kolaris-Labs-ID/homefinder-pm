<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BatchPaymentTestFactory;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function erBase(Employee $employee, array $overrides = []): array
{
    return array_merge([
        'request_number' => 'ER-'.uniqid(),
        'employee_id' => $employee->id,
        'category' => 'transport',
        'title' => 'Test ER',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'expense_date' => '2026-05-01',
        'payment_mode' => 'employee_reimbursement',
        'payee_name' => 'Test Employee',
        'status' => 'draft',
    ], $overrides);
}

function prBase(User $creator, string $sourceType, int $sourceId, array $overrides = []): array
{
    return array_merge([
        'payment_request_number' => 'PR-'.uniqid(),
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'created_by' => $creator->id,
        'payee_name' => 'Test Payee',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'status' => PaymentRequest::STATUS_DRAFT,
    ], $overrides);
}

function bundleBase(User $creator, array $overrides = []): array
{
    return array_merge([
        'bundle_number' => 'PB-'.uniqid(),
        'status' => PaymentBundle::STATUS_DRAFT,
        'bundle_version' => 1,
        'total_amount' => '0.00',
        'currency' => 'IDR',
        'created_by' => $creator->id,
    ], $overrides);
}

function bundleItemBase(int $bundleId, string $sourceType, int $sourceId, string $status, array $overrides = []): array
{
    return array_merge([
        'payment_bundle_id' => $bundleId,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'payee_name' => 'Payee',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'source_snapshot_json' => 'placeholder',
        'source_hash' => hash('sha256', 'placeholder'),
        'status' => $status,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Area 1 — ExpenseRequest: unique(employee_id, client_request_id)
// ---------------------------------------------------------------------------

describe('Area 1 — ExpenseRequest client_request_id uniqueness', function () {
    it('the unique index expense_requests_employee_id_client_request_id_unique exists in the schema', function () {
        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='expense_requests'"))
            ->pluck('name')
            ->toArray();

        expect($indexes)->toContain('expense_requests_employee_id_client_request_id_unique');
    });

    it('duplicate (employee_id, client_request_id) insert below the service layer throws QueryException', function () {
        $employee = Employee::factory()->create();
        $base = erBase($employee, ['client_request_id' => 'CRI-DUPLICATE-001']);

        ExpenseRequest::create($base);

        expect(fn () => ExpenseRequest::create(array_merge($base, [
            'request_number' => 'ER-'.uniqid(), // different PK-unique field
        ])))->toThrow(QueryException::class);
    });

    it('different employee with the same client_request_id is allowed (cross-employee uniqueness is intentionally NOT enforced)', function () {
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $clientId = 'CRI-CROSS-EMPLOYEE-001';

        ExpenseRequest::create(erBase($employeeA, ['client_request_id' => $clientId]));
        // Must not throw — different employee is a distinct composite key value
        $second = ExpenseRequest::create(erBase($employeeB, [
            'client_request_id' => $clientId,
            'request_number' => 'ER-'.uniqid(),
        ]));

        expect($second->exists)->toBeTrue();
    });

    it('null client_request_id does not participate in the uniqueness constraint (two nulls are allowed)', function () {
        $employee = Employee::factory()->create();

        ExpenseRequest::create(erBase($employee, ['client_request_id' => null]));
        // SQLite treats NULL != NULL in unique indexes, so a second NULL row is always fine
        $second = ExpenseRequest::create(erBase($employee, [
            'client_request_id' => null,
            'request_number' => 'ER-'.uniqid(),
        ]));

        expect($second->exists)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Area 2 — ER → PR: one active PR per ER source (DB-LEVEL + APP-LEVEL)
// ---------------------------------------------------------------------------
// BUG-1 RESOLVED: partial unique index payment_requests_active_source_unique
// enforces one active PR per (source_type, source_id) at the DB layer.
// Manual PRs (source_id IS NULL) are exempt — multiple active manual PRs allowed.
// ---------------------------------------------------------------------------

describe('Area 2 — ER→PR one-active-per-ER (DB-LEVEL via payment_requests_active_source_unique)', function () {
    it('the partial unique index payment_requests_active_source_unique exists on payment_requests', function () {
        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='payment_requests'"))
            ->pluck('name')
            ->toArray();

        expect($indexes)->toContain('payment_requests_active_source_unique');
    });

    it('two active PRs for the same ER source are BLOCKED at the DB layer (QueryException)', function () {
        $employee = Employee::factory()->create();
        $er = ExpenseRequest::create(erBase($employee));
        $creator = User::factory()->create();

        $base = prBase($creator, PaymentRequest::SOURCE_EXPENSE_REQUEST, $er->id);

        PaymentRequest::create($base);

        expect(fn () => PaymentRequest::create(array_merge($base, [
            'payment_request_number' => 'PR-'.uniqid(),
        ])))->toThrow(QueryException::class);
    });

    it('the service-level guard rejects a duplicate active PR for the same ER', function () {
        $employee = Employee::factory()->create();
        $er = ExpenseRequest::create(erBase($employee, ['status' => ExpenseRequest::STATUS_FINANCE_VERIFIED]));
        $creator = User::factory()->create();

        $service = app(\App\Services\Hris\PaymentRequestService::class);

        $service->createFromExpenseRequest($er, $creator);

        // ER is now in pr_created status after first call; verify the service blocks the second
        expect(fn () => $service->createFromExpenseRequest($er->fresh(), $creator))
            ->toThrow(\RuntimeException::class, 'Only a finance-verified expense request can become a payment request.');
    });

    it('the service alreadyActive guard fires when an active PR already exists for a still-verified ER', function () {
        // Directly exercises the lockForUpdate "alreadyActive" race-defense guard:
        // ER stays finance_verified while an active PR already points at it.
        $employee = Employee::factory()->create();
        $er = ExpenseRequest::create(erBase($employee, ['status' => ExpenseRequest::STATUS_FINANCE_VERIFIED]));
        $creator = User::factory()->create();

        // Insert the first active PR directly so the ER status is NOT advanced to pr_created.
        PaymentRequest::create(prBase($creator, PaymentRequest::SOURCE_EXPENSE_REQUEST, $er->id, [
            'status' => PaymentRequest::STATUS_DRAFT,
        ]));

        $service = app(\App\Services\Hris\PaymentRequestService::class);

        expect(fn () => $service->createFromExpenseRequest($er->fresh(), $creator))
            ->toThrow(\RuntimeException::class, 'This expense request already has an active payment request.');
    });

    it('multiple active MANUAL PRs are allowed (manual PRs have null source_id and are exempt from the index)', function () {
        // Footgun guard: manual PRs must never be blocked by the index.
        // source_id IS NULL → generated column / WHERE predicate excludes them → no UNIQUE conflict.
        $creator = User::factory()->create();

        $pr1 = PaymentRequest::create([
            'payment_request_number' => 'PR-'.uniqid(),
            'source_type' => PaymentRequest::SOURCE_MANUAL,
            'source_id' => null,
            'created_by' => $creator->id,
            'payee_name' => 'Manual Payee 1',
            'amount' => '500000.00',
            'currency' => 'IDR',
            'status' => PaymentRequest::STATUS_DRAFT,
        ]);

        $pr2 = PaymentRequest::create([
            'payment_request_number' => 'PR-'.uniqid(),
            'source_type' => PaymentRequest::SOURCE_MANUAL,
            'source_id' => null,
            'created_by' => $creator->id,
            'payee_name' => 'Manual Payee 2',
            'amount' => '750000.00',
            'currency' => 'IDR',
            'status' => PaymentRequest::STATUS_DRAFT,
        ]);

        $pr3 = PaymentRequest::create([
            'payment_request_number' => 'PR-'.uniqid(),
            'source_type' => PaymentRequest::SOURCE_MANUAL,
            'source_id' => null,
            'created_by' => $creator->id,
            'payee_name' => 'Manual Payee 3',
            'amount' => '250000.00',
            'currency' => 'IDR',
            'status' => PaymentRequest::STATUS_SUBMITTED_TO_COO,
        ]);

        expect($pr1->exists)->toBeTrue();
        expect($pr2->exists)->toBeTrue();
        expect($pr3->exists)->toBeTrue();
        expect(
            PaymentRequest::where('source_type', PaymentRequest::SOURCE_MANUAL)
                ->whereNull('source_id')
                ->count()
        )->toBeGreaterThanOrEqual(3);
    });

    it('a second active PR is allowed for the same source after the first becomes inactive (voided)', function () {
        $employee = Employee::factory()->create();
        $er = ExpenseRequest::create(erBase($employee));
        $creator = User::factory()->create();

        $base = prBase($creator, PaymentRequest::SOURCE_EXPENSE_REQUEST, $er->id);

        // First PR — active
        $pr1 = PaymentRequest::create($base);
        // Make it inactive (voided) — now outside the index predicate
        $pr1->update(['status' => PaymentRequest::STATUS_VOIDED]);

        // Second PR — should be allowed because pr1 is voided (inactive)
        $pr2 = PaymentRequest::create(array_merge($base, [
            'payment_request_number' => 'PR-'.uniqid(),
        ]));

        expect($pr2->exists)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Area 3 — PaymentBundle items: partial unique index on active sources
// ---------------------------------------------------------------------------

describe('Area 3 — PaymentBundle active-source partial unique index', function () {
    it('the partial unique index payment_bundle_items_active_source_unique exists', function () {
        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='payment_bundle_items'"))
            ->pluck('name')
            ->toArray();

        expect($indexes)->toContain('payment_bundle_items_active_source_unique');
    });

    it('a PR source in payment_bundled status cannot be inserted into a second active bundle', function () {
        $creator = User::factory()->create();
        $pr = PaymentRequest::create(prBase($creator, PaymentRequest::SOURCE_MANUAL, 0, [
            'source_id' => null,
        ]));

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED // active status — triggers index
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        )))->toThrow(QueryException::class);
    });

    it('a PR source in payment_authorized status cannot be inserted into a second active bundle', function () {
        $creator = User::factory()->create();
        $pr = PaymentRequest::create(prBase($creator, PaymentRequest::SOURCE_MANUAL, 0, ['source_id' => null]));

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED
        )))->toThrow(QueryException::class);
    });

    it('a PR source in payment_recording status cannot be inserted into a second active bundle', function () {
        $creator = User::factory()->create();
        $pr = PaymentRequest::create(prBase($creator, PaymentRequest::SOURCE_MANUAL, 0, ['source_id' => null]));

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_RECORDING
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            $pr->id,
            PaymentBundleItem::PAYMENT_STATUS_RECORDING
        )))->toThrow(QueryException::class);
    });

    it('a weekly-allowance batch source in payment_bundled status cannot be inserted into two active bundles', function () {
        $creator = User::factory()->create();
        $employee = BatchPaymentTestFactory::employee();
        $wab = BatchPaymentTestFactory::weeklyAllowance($employee);

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        )))->toThrow(QueryException::class);
    });

    it('an overtime batch source in payment_bundled status cannot be inserted into two active bundles', function () {
        $creator = User::factory()->create();
        $employee = BatchPaymentTestFactory::employee();
        $overtime = BatchPaymentTestFactory::overtime($employee);

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH,
            $overtime->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH,
            $overtime->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        )))->toThrow(QueryException::class);
    });

    it('a late-pay batch source in payment_bundled status cannot be inserted into two active bundles', function () {
        $creator = User::factory()->create();
        $employee = BatchPaymentTestFactory::employee();
        $latePay = BatchPaymentTestFactory::latePay($employee);

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH,
            $latePay->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH,
            $latePay->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        )))->toThrow(QueryException::class);
    });

    it('a source with payment_released status (inactive) does NOT trigger the partial index (re-add is allowed)', function () {
        $creator = User::factory()->create();
        $employee = BatchPaymentTestFactory::employee();
        $wab = BatchPaymentTestFactory::weeklyAllowance($employee);

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        // First item is released — an inactive status not covered by the partial index
        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_RELEASED
        ));

        // Second item is active — should be allowed because the released row is outside the index
        $inserted = DB::table('payment_bundle_items')->insertGetId(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect($inserted)->toBeGreaterThan(0);
    });

    it('same source can be in two bundles if one is paid (paid is outside the partial index)', function () {
        $creator = User::factory()->create();
        $employee = BatchPaymentTestFactory::employee();
        $wab = BatchPaymentTestFactory::weeklyAllowance($employee);

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_PAID // terminal/inactive — outside index
        ));

        $inserted = DB::table('payment_bundle_items')->insertGetId(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            $wab->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect($inserted)->toBeGreaterThan(0);
    });
});

// ---------------------------------------------------------------------------
// Area 4 — Batch source: one active bundle per source (same partial index)
// ---------------------------------------------------------------------------

describe('Area 4 — Batch source duplicate active source reference', function () {
    it('a payrun batch source in payment_bundled cannot appear in two active bundle items', function () {
        $creator = User::factory()->create();
        $payrun = BatchPaymentTestFactory::payrun();

        $bundle1 = PaymentBundle::create(bundleBase($creator));
        $bundle2 = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle1->id,
            PaymentBundleItem::SOURCE_PAYRUN_BATCH,
            $payrun->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect(fn () => DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle2->id,
            PaymentBundleItem::SOURCE_PAYRUN_BATCH,
            $payrun->id,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        )))->toThrow(QueryException::class);
    });

    it('two different source types with the same source_id are distinct and both can be bundled', function () {
        $creator = User::factory()->create();

        // Same source_id integer, but different source_type — must be treated as distinct
        $bundle = PaymentBundle::create(bundleBase($creator));

        DB::table('payment_bundle_items')->insert(bundleItemBase(
            $bundle->id,
            PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
            999,
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        $inserted = DB::table('payment_bundle_items')->insertGetId(bundleItemBase(
            $bundle->id,
            PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH,
            999, // same ID, different type
            PaymentBundleItem::PAYMENT_STATUS_BUNDLED
        ));

        expect($inserted)->toBeGreaterThan(0);
    });
});
