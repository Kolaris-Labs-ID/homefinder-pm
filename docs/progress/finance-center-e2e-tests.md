# Finance Center Hardening — E2E Test Baseline

Branch: `test/finance-center-hardening`  
Date: 2026-06-03

---

## Baseline

### Backend (partnershub-pm)

Command: `./vendor/bin/pest --compact`

```
Tests:    2465 deprecated, 1 failed (129320 assertions)
Duration: ~384s
```

**Note on compact output:** Pest compact mode renders passing tests as `!` and failures as `⨯`. The deprecation warnings are PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` notices — pre-existing noise, unrelated to logic.

Command: `./vendor/bin/pint --test` (dry-run)

Result: Reports style issues in ~several legacy files (e.g. `tests/Unit/Hris/Pph21CalculationServiceTest.php`, `tests/Unit/Hris/SalaryStructureModelTest.php`, `tests/Unit/ProjectTest.php`, `tests/Unit/TicketTest.php`, `tests/Unit/UserTest.php`). These are pre-existing legacy style issues (ordered_imports, no_whitespace_in_blank_line, single_blank_line_at_eof, new_with_parentheses). Exit code non-zero but no app-logic failures.

### Mobile (partnershub-app/frontend)

Command used: `npm test -- --testPathPattern=expense --passWithNoTests`

```
PASS __tests__/expenseErrors.test.ts
PASS __tests__/expenseSubmitter.test.ts
PASS __tests__/expenseApi.test.ts (7.154 s)

Test Suites: 3 passed, 3 total
Tests:       23 passed, 23 total
Time:        8.519 s
```

Command: `npx tsc --noEmit` (`npm run typecheck` does not exist)

Result: **Pre-existing errors** — 12 errors in `__tests__/widgets.test.ts` (missing module `../widgets/attendance` / `../widgets/attendance-home`), `app/login.tsx` (2 implicit `any` params), `components/DashboardSkeleton.tsx` (style type mismatch), `components/ui/Skeleton.tsx` (null), `services/liveActivityService.ts` (missing widget module + null). These are all pre-existing, unrelated to finance.

Command: `npm run lint`

Result: **1 error, 78 warnings** — exit code 1. The single error is not in expense/finance code; it is pre-existing. 78 warnings are React Hook exhaustive-deps and unused variable warnings across the codebase.

---

## Pre-existing failures

### Backend
1. `Tests\Feature\Hris\SalaryStructurePropertyTest > Property 2 Edge… Should return the most recent past structure`
   - File: `tests/Feature/Hris/SalaryStructurePropertyTest.php:529`
   - Failure: `Failed asserting that 9 is identical to 8.` — a property-based test flake on salary structure ordering. **Unrelated to finance center.**

### Mobile
- No test failures (23/23 pass for expense tests; full suite not run).
- TypeScript: 12 pre-existing errors all in widget/login/skeleton files, not finance.
- Lint: 1 pre-existing error (not in finance code).

---

## Existing coverage

### Backend — Finance-related test files

| File | Purpose |
|------|---------|
| `tests/Feature/Api/Hris/ExpenseRequestApiTest.php` | API endpoint tests for ER CRUD + lifecycle |
| `tests/Feature/Api/Hris/ExpenseRequestPaymentStatusTest.php` | API payment status fields on ER |
| `tests/Feature/Hris/ExpenseRequestAuditTest.php` | Audit log written on ER lifecycle events |
| `tests/Feature/Hris/ExpenseRequestIdempotencyTest.php` | client_request_id duplicate suppression |
| `tests/Feature/Hris/ExpenseRequestLifecycleTest.php` | Full ER state machine (draft→submitted→verified→pr_created→closed) |
| `tests/Feature/Hris/ExpenseToPaymentRequestConversionTest.php` | ER→PR conversion, record-only guard |
| `tests/Feature/Hris/PaymentRequestApprovalSnapshotTest.php` | Immutable COO snapshot fields + hash |
| `tests/Feature/Hris/PaymentRequestLifecycleTest.php` | PR state machine (draft→submitted_to_coo→approved→paid) |
| `tests/Feature/Hris/PaymentRequestLineBatchReadyTest.php` | PR line batch-ready status |
| `tests/Feature/Hris/PaymentRequestReconciliationTest.php` | Reconciliation flow |
| `tests/Feature/Hris/PaymentRequestReversalTest.php` | Reversal guard |
| `tests/Feature/Hris/PaymentBundleAuditTest.php` | Audit log on bundle events |
| `tests/Feature/Hris/PaymentBundleCanonicalHashTest.php` | Canonical hash determinism |
| `tests/Feature/Hris/PaymentBundleFinanceUiTest.php` | Filament UI for bundle |
| `tests/Feature/Hris/PaymentBundlePaymentExecutionSnapshotTest.php` | Payment instructions from frozen snapshot |
| `tests/Feature/Hris/PaymentBundlePaymentProofTest.php` | markPaid + proof upload |
| `tests/Feature/Hris/PaymentBundlePdfGenerationTest.php` | PDF generation (unsigned) |
| `tests/Feature/Hris/PaymentBundleReverseTransitionTest.php` | Reverse-transition guards |
| `tests/Feature/Hris/PaymentBundleRolePermissionTest.php` | Role→permission grants |
| `tests/Feature/Hris/PaymentBundleServiceTest.php` | PaymentBundleService unit tests |
| `tests/Feature/Hris/PaymentBundleSignatureCaptureTest.php` | Signature capture + token consumption |
| `tests/Feature/Hris/PaymentBundleSignatureWorkflowTest.php` | Sequential signing F1→F2→Exec |
| `tests/Feature/Hris/PaymentBundleSignedPdfTest.php` | Signed PDF generation on authorization |
| `tests/Feature/Hris/PaymentBundleSignerAssignmentTest.php` | Signer assignment validation |
| `tests/Feature/Hris/PaymentBundleSignerOverrideTest.php` | reassignSigners before first capture |
| `tests/Feature/Hris/PaymentBundleSigningLinkTest.php` | Token lifecycle (expiry, revoke, one-time-use) |
| `tests/Feature/Hris/PaymentBundleVerificationPageTest.php` | Public verify page + hash_check |
| `tests/Feature/Hris/FinanceAuditLogTest.php` | Chain hash integrity + append-only |
| `tests/Feature/Hris/FinanceAuditLogResourceTest.php` | Filament resource for audit log |
| `tests/Feature/Hris/FinanceAttachmentAuditTest.php` | Attachment audit events |
| `tests/Feature/Hris/FinanceAttachmentDuplicateDetectionTest.php` | Duplicate file upload guard |
| `tests/Feature/Hris/FinanceAttachmentSecurityTest.php` | Private storage, signed URLs |
| `tests/Feature/Hris/FinanceCenterQueryScopeTest.php` | Role-scoped visibility on ER/PR |
| `tests/Feature/Hris/FinanceFilamentAccessTest.php` | Filament page/resource access by role |
| `tests/Feature/Hris/FinanceFilamentActionTest.php` | Filament action gating |
| `tests/Feature/Hris/FinanceResourceViewTest.php` | Filament resource view columns |
| `tests/Feature/Hris/FinanceRolePermissionTest.php` | FinanceRolePermissionSeeder grants |
| `tests/Feature/Hris/BatchPaymentActiveUniquenessTest.php` | One active batch per week uniqueness |
| `tests/Feature/Hris/BatchPaymentAuditTest.php` | Audit events on batch payment |
| `tests/Feature/Hris/BatchPaymentBundleAuthorizationTest.php` | Batch→bundle authorization flow |
| `tests/Feature/Hris/BatchPaymentBundleMarkPaidTest.php` | markPaid cascades to batch items |
| `tests/Feature/Hris/BatchPaymentBundlePdfTest.php` | PDF for batch bundles |
| `tests/Feature/Hris/BatchPaymentBundleReverseTransitionTest.php` | Batch bundle reverse-transition |
| `tests/Feature/Hris/BatchPaymentBundleServiceTest.php` | BatchPaymentBundleService methods |
| `tests/Feature/Hris/BatchPaymentCanonicalHashRegressionTest.php` | Canonical hash regression for batch payloads |
| `tests/Feature/Hris/BatchPaymentFinanceCenterTest.php` | Finance center view of batch payments |
| `tests/Feature/Hris/BatchPaymentNoFloatGuardTest.php` | No float arithmetic in batch amounts |
| `tests/Feature/Hris/BatchPaymentSensitiveDataTest.php` | Sensitive data not leaked in batch |
| `tests/Feature/Hris/BatchPaymentSnapshotSchemaTest.php` | Batch snapshot schema validation |
| `tests/Feature/Hris/BatchPaymentSourceAdapterTest.php` | BatchPaymentBundleService source adapter |
| `tests/Feature/Hris/BatchPaymentSourceStateTest.php` | Batch source state transitions |
| `tests/Feature/Hris/WeeklyAllowanceBatchResourceTest.php` | Filament resource for WAB |
| `tests/Feature/Hris/WeeklyAllowancePaymentBundleTest.php` | WAB→bundle integration |
| `tests/Feature/Hris/WeeklyAllowanceWorkflowTest.php` | WAB approval workflow |
| `tests/Feature/Hris/OvertimePayPaymentBundleTest.php` | OvertimePayBatch→bundle integration |
| `tests/Feature/Hris/OvertimePayServiceTest.php` | OvertimePay calculation service |
| `tests/Feature/Hris/LatePayCompensationPaymentBundleTest.php` | LatePayCompensationBatch→bundle |
| `tests/Feature/Hris/LatePayCompensationServiceTest.php` | LatePayCompensation calculation |
| `tests/Feature/Hris/PaymentAuthorizationSettingsFilamentTest.php` | Filament settings page |
| `tests/Feature/Hris/PaymentAuthorizationSettingsServiceTest.php` | Settings service |
| `tests/Feature/Hris/PaymentAuthorizationSettingsTest.php` | Settings model/persistence |
| `tests/Support/BatchPaymentTestFactory.php` | Shared factory helpers for batch payment tests |

### Mobile — Expense-related test files

| File | Purpose |
|------|---------|
| `__tests__/expenseApi.test.ts` | API layer: axios calls for expense CRUD (mocked) |
| `__tests__/expenseSubmitter.test.ts` | Submit flow: idempotency key, retry logic |
| `__tests__/expenseErrors.test.ts` | Error categorization for expense API responses |

---

## E2E tooling

- **Maestro**: ABSENT (no `.maestro/` directory, no `*.maestro` files, not in `package.json`)
- **Detox**: ABSENT (not in `package.json`)
- No mobile E2E tooling currently configured.

---

## Codebase API map

### 1. ExpenseRequest

**Model**: `app/Models/Hris/ExpenseRequest.php`

**Status constants:**
- `STATUS_DRAFT = 'draft'`
- `STATUS_SUBMITTED = 'submitted'`
- `STATUS_UNDER_FINANCE_REVIEW = 'under_finance_review'`
- `STATUS_NEEDS_REVISION = 'needs_revision'`
- `STATUS_FINANCE_VERIFIED = 'finance_verified'`
- `STATUS_PR_CREATED = 'pr_created'`
- `STATUS_RECORD_ONLY_CLOSED = 'record_only_closed'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_CANCELLED = 'cancelled'`
- `STATUS_CLOSED = 'closed'`

**Payment mode constants:**
- `MODE_EMPLOYEE_REIMBURSEMENT`, `MODE_VENDOR_PAYMENT`, `MODE_COMPANY_PAID_RECORD_ONLY`, `MODE_OTHER`

**Service**: `app/Services/Hris/ExpenseRequestService.php`

Public methods:
- `create(Employee $employee, array $data, ?User $actor): ExpenseRequest` — idempotent on `(employee_id, client_request_id)`
- `submit(ExpenseRequest $er, ?User $actor): void` — requires `hasEvidence()` guard
- `verify(ExpenseRequest $er, User $financeUser, ?string $notes): void`
- `reject(ExpenseRequest $er, User $financeUser, string $reason): void`
- `requestRevision(ExpenseRequest $er, User $financeUser, string $note): void`
- `revise(ExpenseRequest $er, array $data, ?User $actor): void`
- `updateDetails(ExpenseRequest $er, array $data): void` — touching SENSITIVE fields while verified resets review
- `closeAsRecordOnly(ExpenseRequest $er, ?User $actor): void`
- `cancel(ExpenseRequest $er, ?User $actor): void`

**`client_request_id` idempotency**: DB column `client_request_id` (varchar 64, nullable) + composite unique index `['employee_id', 'client_request_id']` (migration `2026_06_03_000000_add_client_request_id_to_expense_requests_table.php`). Service does pre-check lookup then catches `QueryException` on concurrent race → re-fetches winner. Private helper: `findByClientRequestId(Employee, string): ?ExpenseRequest`.

**Sensitive fields** (reset finance review on update): `['amount', 'payee_name', 'payee_bank_name', 'payee_bank_account_number']`

**Bank account**: stored as `payee_bank_account_number_encrypted` (Laravel `'encrypted'` cast) + `payee_bank_account_last4` in clear. Field hidden from serialization.

---

### 2. PaymentRequest

**Model**: `app/Models/Hris/PaymentRequest.php`

**Status constants:**
- `STATUS_DRAFT = 'draft'`
- `STATUS_SUBMITTED_TO_COO = 'submitted_to_coo'`
- `STATUS_APPROVED_BY_COO = 'approved_by_coo'`
- `STATUS_APPROVED_FOR_BUNDLE = 'approved_for_bundle'`
- `STATUS_IN_PAYMENT_BUNDLE = 'in_payment_bundle'`
- `STATUS_PAID = 'paid'`
- `STATUS_REJECTED_BY_COO = 'rejected_by_coo'`
- `STATUS_NEEDS_CLARIFICATION = 'needs_clarification'`
- `STATUS_SCHEDULED_FOR_PAYMENT = 'scheduled_for_payment'`
- `STATUS_PAID_PENDING_RECONCILIATION = 'paid_pending_reconciliation'`
- `STATUS_RECONCILED = 'reconciled'`
- `STATUS_RECONCILIATION_FAILED = 'reconciliation_failed'`
- `STATUS_PAYMENT_FAILED = 'payment_failed'`
- `STATUS_VOIDED = 'voided'`
- `STATUS_REVERSED = 'reversed'`
- `STATUS_CANCELLED = 'cancelled'`

**Source type constants:** `SOURCE_EXPENSE_REQUEST`, `SOURCE_PAYROLL_BATCH`, `SOURCE_WEEKLY_ALLOWANCE_BATCH`, `SOURCE_LATE_PAY_COMPENSATION`, `SOURCE_OVERTIME_PAY`, `SOURCE_MANUAL`

**Service**: `app/Services/Hris/PaymentRequestService.php`

Public methods:
- `createFromExpenseRequest(ExpenseRequest, User, array $overrides): PaymentRequest` — row-locked, one active PR per ER
- `createManual(User, array $data): PaymentRequest`
- `submitToCoo(PaymentRequest): void`
- `approveAsCoo(PaymentRequest, User $coo): void` — mints `approval_snapshot_json` + `approval_snapshot_hash`
- `rejectAsCoo(PaymentRequest, User, string $reason): void`
- `requestClarification(PaymentRequest, User, string $note): void`
- `updateApprovedDetails(PaymentRequest, array $data): void` — touching SENSITIVE fields voids approval
- `addLine(PaymentRequest, array $data): PaymentRequestLine`
- `markLinePaid(PaymentRequestLine, string $reference): void`
- `markLineFailed(PaymentRequestLine, string $reason): void`
- `markPaid(PaymentRequest, User, string $reference, FinanceAttachment $proof): void`
- `reconcile(PaymentRequest, User, array $observed): void`
- `void(PaymentRequest, User, string $reason): void`
- `reverse(PaymentRequest, User, string $reason): void`
- `buildSnapshot(PaymentRequest): array` — public; fields: payment_request_number, source_type/id, amount/currency, payee_name, bank_name, bank_account_last4, encrypted_bank_account_reference (sha256 of full account), attachment_hashes, finance_notes, withholding, created_by, submitted_to_coo_at, coo_approved_by/at
- `hashSnapshot(array $snapshot): string` — ksort recursive, json_encode, sha256

**Approval snapshot fields**: `approval_snapshot_json` (array cast) + `approval_snapshot_hash` (string). Minted by `approveAsCoo`; voided (set to null) by `updateApprovedDetails` when sensitive fields are touched.

---

### 3. PaymentBundle

**Model**: `app/Models/Hris/PaymentBundle.php`

**Status constants:**
- `STATUS_DRAFT = 'draft'`
- `STATUS_SUBMITTED_FOR_SIGNATURE = 'submitted_for_signature'`
- `STATUS_PENDING_FINANCE_SIGNATURE_1 = 'pending_finance_signature_1'`
- `STATUS_PENDING_FINANCE_SIGNATURE_2 = 'pending_finance_signature_2'`
- `STATUS_PENDING_EXECUTIVE_SIGNATURE = 'pending_executive_signature'`
- `STATUS_AUTHORIZED_FOR_PAYMENT = 'authorized_for_payment'`
- `STATUS_PAYMENT_RECORDING = 'payment_recording'`
- `STATUS_PAID = 'paid'`
- `STATUS_PAYMENT_PROOF_REJECTED = 'payment_proof_rejected'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_VOIDED = 'voided'`
- `STATUS_CANCELLED = 'cancelled'`

Static helper: `PaymentBundle::signaturePendingStatuses(): array` returns the 4 pending-signature statuses.

**`PaymentBundleSignatureSlot` enum** (`app/Enums/PaymentBundleSignatureSlot.php`):
- `FINANCE_SIGNER_1 = 'finance_signer_1'` (order 1, permission `payment_bundles.sign.finance`)
- `FINANCE_SIGNER_2 = 'finance_signer_2'` (order 2, permission `payment_bundles.sign.finance`)
- `EXECUTIVE_SIGNER = 'executive_signer'` (order 3, permission `payment_bundles.sign.executive`)

**Signing order**: strictly sequential F1 → F2 → Exec. Each slot maps to an expected bundle status via `expectedBundleStatusFor()` in `PaymentBundleSignatureService`.

**`PaymentBundleService`** (`app/Services/Hris/PaymentBundleService.php`) public methods:
- `create(User, array $attrs): PaymentBundle`
- `addPaymentRequest(PaymentBundle, PaymentRequest, User): PaymentBundleItem` — row-locked, checks `approved_by_coo` or `approved_for_bundle` status
- `addWeeklyAllowanceBatch(PaymentBundle, WeeklyAllowanceBatch, User): PaymentBundleItem` — delegates to `BatchPaymentBundleService::addSource`
- `removeItem(PaymentBundleItem, User): void`
- `cancel(PaymentBundle, User): PaymentBundle`
- `void(PaymentBundle, User, string $reason): PaymentBundle`
- `rejectSignature(PaymentBundleSignature, User, string $reason): PaymentBundle`
- `submitForSignature(PaymentBundle, array $assignments, User): PaymentBundle` — validates SoD + per-slot permission, creates 3 signature rows, freezes canonical hash + unsigned PDF, moves to `pending_finance_signature_1`
- `reassignSigners(PaymentBundle, array $assignments, User): PaymentBundle` — requires `payment_bundles.manage_signers`, blocked once any slot is SIGNED

**SoD rules in `resolveAndValidateAssignment`**:
- F1 ≠ F2 (different people)
- Exec ≠ F1 and Exec ≠ F2
- On high-value bundles (≥ `config('finance.payment_bundle_high_value_threshold')`): bundle creator ≠ Exec
- Each slot's assigned user must hold `$slot->requiredPermission()`

**`PaymentBundleSignatureService`** (`app/Services/Hris/PaymentBundleSignatureService.php`) public methods:
- `createSigningLink(PaymentBundleSignature, User, int $ttlHours = 72): string` — stores hashed token (sha256), sets expiry, returns `URL::signedRoute`
- `tokenStatus(PaymentBundleSignature, string $token): 'valid'|'mismatch'|'expired'|'used'|'revoked'|'missing'`
- `assertTokenValid(PaymentBundleSignature, string $token): void`
- `consumeToken(PaymentBundleSignature): void` — idempotent one-time-use stamp
- `revokeToken(PaymentBundleSignature): void`
- `submitSignature(PaymentBundleSignature, User, string $imageDataUri, array $context): PaymentBundleSignature` — locks bundle + sig, calls `assertSignableNow()`, validates PNG ink, stores image, writes `signature_hash`, calls `advanceAfterSignature()`, triggers `generateSignedPdf` on final authorization
- `assertSignableNow(PaymentBundle, PaymentBundleSignature, User): void` — enforces: slot is PENDING, bundle status = expected for slot, signer hasn't already signed another slot on this bundle (sign-time SoD), signer is the assigned_user_id (or holds required_permission if unassigned)
- `advanceAfterSignature(PaymentBundle): void` — state machine: F1 signed → pending_finance_signature_2, F2 signed → pending_executive_signature, Exec signed → authorized_for_payment + calls `BatchPaymentBundleService::markBundleAuthorized`

**Token expiry enforcement**: DB `token_expires_at` field (checked in `tokenStatus`). The signed URL itself is non-expiring (HMAC tamper-proof only); the DB token_expires_at is the authoritative expiry gate.

**Out-of-order signing blocked by**: `assertSignableNow` compares `bundle->status` to `expectedBundleStatusFor(sig->signature_slot)` — mismatched statuses throw "It is not this slot's turn to sign."

**`PaymentBundlePaymentService`** (`app/Services/Hris/PaymentBundlePaymentService.php`) public methods:
- `paymentInstructions(PaymentBundle): array` — internal; reads from frozen `source_snapshot_json` only
- `revealPaymentInstructions(PaymentBundle, User): array` — audited reveal (logs `batch_bank_detail_revealed`)
- `detectDrift(PaymentBundleItem): bool` — rebuilds live snapshot, compares hash to `source_hash`
- `beginPaymentRecording(PaymentBundle, User): array` — moves bundle to `payment_recording`, calls `assertNoSourceDrift`, returns revealed instructions
- `markPaid(PaymentBundle, User, UploadedFile $proof, ?string $reference, ?string $notes): PaymentBundle` — high-value SoD: payer ∉ finance signers; requires `signed_pdf_attachment_id`
- `rejectPaymentProof(PaymentBundle, User, string $reason): PaymentBundle`

**`PaymentBundleDocumentService`** (`app/Services/Hris/PaymentBundleDocumentService.php`) public methods:
- `renderHtml(PaymentBundle, array $signatures = []): string` — Blade view `finance.payment-bundles.authorization`, embeds QR code via `endroid/qr-code`
- `generateUnsignedPdf(PaymentBundle, User): FinanceAttachment` — calls `canonical->store()`, renders Blade, uses `barryvdh/laravel-dompdf` (NOT FPDI), stores as `ROLE_PAYMENT_BUNDLE_UNSIGNED_PDF`
- `generateSignedPdf(PaymentBundle, User): FinanceAttachment` — idempotent; loads signature images from private storage, embeds as base64, renders same Blade, stores as `ROLE_PAYMENT_BUNDLE_SIGNED_PDF`; stores `signed_payload_hash = canonical_payload_hash`

---

### 4. Canonical hash

**Service**: `app/Services/Hris/CanonicalPayloadHashService.php`

Public methods:
- `build(PaymentBundle): array` — constructs canonical payload array
- `hash(array $payload): string` — ksort recursive + `json_encode(JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)` → sha256
- `generate(PaymentBundle): array` — returns `['payload' => ..., 'hash' => ...]`
- `store(PaymentBundle): string` — persists `canonical_payload_json` + `canonical_payload_hash` on the bundle row; audits `payment_bundle.payload_hash_generated`

**Canonical payload fields** (in `build`):
- `bundle_number`, `bundle_version` (int), `currency`, `total_amount` (money string)
- `required_signature_slots` (array of slot values for assigned slots)
- `assigned_signer_ids` (map slot.value → user_id int or null)
- `items` (sorted by `source_type ASC, source_id ASC`): each item has `source_type`, `source_id` (int), `source_hash`, `payee_name`, `bank_last4`, `amount` (money string), `currency`

**Decimal/null/ordering normalization**:
- Money: `number_format((float)$v, 2, '.', '')` — note: uses float cast internally (potential precision concern at extreme values)
- Keys: `ksortRecursive` (ksort at every level)
- Items: ordered by `source_type ASC, source_id ASC` before embedding (DB-level sort)
- JSON: `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`

---

### 5. Audit

**Model**: `app/Models/Hris/FinanceAuditLog.php`

**Hash chain**: `previous_hash` (nullable, sha256 of predecessor row) + `current_hash` (sha256 of this row's payload + `previous_hash`). Built by `FinanceAuditLogger`.

**Chain construction** (`FinanceAuditLogger::log`): locks the current head row (`lockForUpdate()->orderByDesc('id')->first()`) to prevent concurrent chain forks; then computes `chainHash(row, previousHash)`.

**`chainHash`** hashes: `auditable_type`, `auditable_id`, `event_type`, `actor_id`, `actor_role`, `before_snapshot`, `after_snapshot`, `reason`, `ip_address`, `user_agent`, `created_at` (ISO 8601), `previous_hash` — all ksorted before JSON encoding.

**Chain-validation method**: `FinanceAuditLogger::recompute(FinanceAuditLog): string` — recomputes `chainHash` from stored row fields for comparison. No walk-chain method exists in the service; tests must implement chain-walk themselves.

**Immutability enforcement** — app-level only (no DB triggers):
- `static::updating(fn() => throw new RuntimeException('Finance audit logs are append-only and cannot be updated.'))`
- `static::deleting(fn() => throw new RuntimeException('Finance audit logs are append-only and cannot be deleted.'))`
Both in `FinanceAuditLog::booted()`.

**Service**: `app/Services/Hris/FinanceAuditLogger.php`

Public methods:
- `log(Model $auditable, string $eventType, ?User $actor, array $opts): FinanceAuditLog`
- `recompute(FinanceAuditLog): string`

---

### 6. Batch sources

**WeeklyAllowanceBatch**: `app/Models/Hris/WeeklyAllowanceBatch.php` — implements `PayableBatchSource`. Statuses: `draft`, `calculated`, `pending_finance`, `rejected`, `paid`, `eligible_for_bundle`, `in_payment_bundle`.

**OvertimePayBatch**: `app/Models/Hris/OvertimePayBatch.php` — implements `PayableBatchSource`. Statuses: `draft`, `calculated`, `pending_finance`, `rejected`, `paid`. **No** `eligible_for_bundle` / `in_payment_bundle` constants on the model (managed via `PaymentBundleItem` source_type `SOURCE_OVERTIME_PAY_BATCH`).

**LatePayCompensationBatch**: `app/Models/Hris/LatePayCompensationBatch.php` — implements `PayableBatchSource`. Statuses: `draft`, `calculated`, `pending_finance`, `rejected`, `paid`. **No** `eligible_for_bundle` / `in_payment_bundle` constants on the model.

**How a batch becomes a bundle source**: `BatchPaymentBundleService::addSource(PaymentBundle, PayableBatchSource, User): PaymentBundleItem` — called from `PaymentBundleService::addWeeklyAllowanceBatch`. The service uses the `PayableBatchSource` contract (interface). Batch items get a `PaymentBundleItem` row with source_type = `SOURCE_WEEKLY_ALLOWANCE_BATCH` / `SOURCE_OVERTIME_PAY_BATCH` / `SOURCE_LATE_PAY_COMPENSATION_BATCH`. The batch model itself is updated to the `in_payment_bundle` equivalent state via `BatchPaymentBundleService`.

**`BatchPaymentBundleService`** public methods: `addSource`, `releaseItem`, `markBundleAuthorized`, `markBundleRecording`, `markItemPaid`, `assertNoSourceDrift`, `itemHasDrift`.

---

### 7. Roles/permissions

**Two seeders** (both guard `'web'`):

**`FinanceRolePermissionSeeder`** (`database/seeders/FinanceRolePermissionSeeder.php`):

Roles and their permissions:
- `finance_reviewer`: `expense_requests.view`, `.view_all`, `.review`, `.verify`, `.reject`, `.request_revision`; `finance_center.view`, `.view_payroll_placeholders`; `finance_attachments.download`
- `finance_creator`: `expense_requests.view`, `.view_all`; `payment_requests.view`, `.view_all`, `.create`, `.submit_for_approval`, `.void`; `finance_center.view`, `.view_sensitive_payment_data`, `.view_payroll_placeholders`; `finance_attachments.download`
- `finance_executor`: `payment_requests.view`, `.view_all`, `.mark_paid`; `finance_center.view`, `.view_sensitive_payment_data`, `.view_payroll_placeholders`; `finance_attachments.upload`, `.download`, `.delete_own_unlocked`
- `finance_reconciler`: `payment_requests.view`, `.view_all`, `.reconcile`; `finance_center.view`, `.view_sensitive_payment_data`, `.view_payroll_placeholders`; `finance_attachments.upload`, `.download`
- `COO`: `payment_requests.view`, `.view_all`, `.approve`, `.reject`, `.request_clarification`; `finance_center.view`, `.view_sensitive_payment_data`

**`PaymentBundleRolePermissionSeeder`** (`database/seeders/PaymentBundleRolePermissionSeeder.php`):

Roles:
- `finance_creator`: `.view`, `.create`, `.update_draft`, `.submit_for_signature`, `.manage_signers`, `.cancel`, `.void`, `.download_pdf`, `.verify_document`
- `finance_signer`: `.view`, `.sign.finance`, `.reject_signature`, `.download_pdf`, `.verify_document`
- `executive_signer`: `.view`, `.sign.executive`, `.reject_signature`, `.download_pdf`, `.verify_document`
- `finance_executor`: `.view`, `.mark_paid`, `.reject_payment_proof`, `.download_pdf`, `.verify_document`

Guard name: `'web'` (Spatie `web` guard — NOT Sanctum). API controllers use Sanctum tokens but Filament pages use web-guard roles/permissions.

---

### 8. Public verification page

**Controller**: `app/Http/Controllers/VerifyPaymentDocumentController.php`  
**Route**: `GET /verify/payment-document/{code}` → `VerifyPaymentDocumentController::show`  
**Auth**: none (public, no middleware)

Data exposed in response view:
- `found` (bool)
- `bundle_number`, `status_raw`, `status_label`, `is_finalized` (bool)
- `display_date` (authorized_at ?? paid_at ?? submitted_at, formatted `d M Y H:i T`)
- `canonical_hash` (the `canonical_payload_hash` string)
- `hash_check` (`'match'|'mismatch'|null`) — only if `?hash=` query param is provided

Does NOT expose: items, payees, amounts, signer identities, bank details.

---

### 9. PDF

**Library**: `barryvdh/laravel-dompdf` ^3.1. Facade: `Barryvdh\DomPDF\Facade\Pdf`. Called as `Pdf::loadHtml($html)->output()`.

**Blade view**: `finance.payment-bundles.authorization` (path: `resources/views/finance/payment-bundles/authorization.blade.php`)

**Not FPDI**: confirmed — FPDI is not in `composer.json`.

**No PDF-text-extraction tooling**: `smalot/pdfparser` is not in `composer.json` or `composer.lock`.

**QR code**: `endroid/qr-code` ^6.1, embedded as PNG data URI in the Blade template.

---

## Planned new test files

```
tests/Support/FinanceCenterFlowFactory.php
tests/Feature/Hris/FinanceCenter/FinanceIdempotencyConstraintTest.php
tests/Feature/Hris/FinanceCenter/ExpenseRequestSubmitFlowTest.php
tests/Feature/Hris/FinanceCenter/ExpenseRequestFinanceReviewTest.php
tests/Feature/Hris/FinanceCenter/ExpenseToPaymentRequestFlowTest.php
tests/Feature/Hris/FinanceCenter/ManualPaymentRequestFlowTest.php
tests/Feature/Hris/FinanceCenter/PaymentBundleCanonicalHashDeterminismTest.php
tests/Feature/Hris/FinanceCenter/PaymentBundleLifecycleE2ETest.php
tests/Feature/Hris/FinanceCenter/PaymentBundleSegregationOfDutiesTest.php
tests/Feature/Hris/FinanceCenter/FinanceStateMachineBlockListTest.php
tests/Feature/Hris/FinanceCenter/BatchPaymentBundleIntegrationTest.php
tests/Feature/Hris/FinanceCenter/FinanceMoneyIntegrityTest.php
tests/Feature/Hris/FinanceCenter/PaymentBundlePdfOutputSafetyTest.php
tests/Feature/Hris/FinanceCenter/FinanceSensitiveDataProtectionTest.php
tests/Feature/Hris/FinanceCenter/FinanceAuditTrailIntegrityTest.php
tests/Feature/Filament/FinanceCenter/ExpenseRequestResourceAuthorizationTest.php
tests/Feature/Filament/FinanceCenter/PaymentRequestResourceAuthorizationTest.php
tests/Feature/Filament/FinanceCenter/PaymentBundleResourceAuthorizationTest.php
tests/Feature/Filament/FinanceCenter/PaymentAuthorizationSettingsAuthorizationTest.php
tests/Fixtures/payment_bundle_canonical_payload_v1.json
tests/Fixtures/payment_bundle_canonical_payload_v1.sha256
(mobile) __tests__/expenseApi.test.ts (extend)
(mobile) __tests__/expense-request-submit.test.tsx
(mobile) __tests__/expense-history.test.tsx
```

---

## Bug log

### BUG-1 — ER→PR one-active-per-source enforced at app layer only (no DB unique index)

**Detected in:** Phase 1 (`FinanceIdempotencyConstraintTest`, Area 2)  
**Table:** `payment_requests`  
**Gap:** No unique or partial unique index on `(source_type, source_id)`. The "one active PR per ER" rule is enforced solely by `PaymentRequestService::createFromExpenseRequest` via a `whereNotIn(status, INACTIVE_STATUSES)->exists()` check under a `lockForUpdate()` transaction.  
**Race risk:** A concurrent request that reaches the exists-check simultaneously (before either commits) can bypass the guard and double-insert two active PRs for the same ER source. The `lockForUpdate()` mitigates but does not eliminate this on all MySQL isolation levels.  
**Recommended fix:** Add a partial/conditional unique index on `payment_requests (source_type, source_id)` WHERE status NOT IN (rejected_by_coo, voided, cancelled, reversed) — using a MySQL generated column or a PostgreSQL/SQLite WHERE clause, matching the migration pattern already used in `payment_bundle_items`.  
**Tests proving the gap:** "two active PRs for the same ER source CAN be inserted at the DB layer (proves gap is app-level only)" — confirms a direct `PaymentRequest::create()` bypass succeeds.

### BUG-2 — NULL `client_request_id` gives no idempotency protection

**Detected in:** Phase 1 (`FinanceIdempotencyConstraintTest`, Area 1)
**Table:** `expense_requests`
**Gap:** The unique index `(employee_id, client_request_id)` does not dedupe rows where `client_request_id` is NULL — SQL UNIQUE treats NULL ≠ NULL (true on both SQLite and MySQL). Older/legacy mobile clients that omit `client_request_id` therefore receive no server-side double-submit protection; the same employee can create multiple identical ERs.
**Severity:** Low — current mobile client always sends `client_request_id` (Phase 17 verifies this). Risk is limited to legacy clients or direct API callers.
**Recommended fix:** Make `client_request_id` required (NOT NULL) for the create endpoint, or generate a server-side fallback key when absent.
**Tests proving the gap:** "null client_request_id rows are allowed (NULL ≠ NULL)" in Area 1.

### BUG-4 — `ExpenseRequestService` review methods lacked state guards (service/API path exploitable) — RESOLVED

**Detected in:** Phase 3 (`ExpenseRequestFinanceReviewTest`, illegal-transition block-list tests)
**Service:** `app/Services/Hris/ExpenseRequestService.php` — `verify()`, `reject()`, `requestRevision()`
**Gap:** All three review methods had no source-state validation. Only the Filament `canReview()` UI gate prevented invalid transitions; the service layer (and by extension the API path) was unguarded. An ER in status `finance_verified`, `rejected`, `cancelled`, `record_only_closed`, or `draft` could be re-verified, re-rejected, or have revision requested against it — silently overwriting status.
**Fix:** Added an `in_array` guard at the top of each method, mirroring `ExpenseRequestResource::canReview()` exactly:
- Allowed states: `STATUS_SUBMITTED` and `STATUS_UNDER_FINANCE_REVIEW`
- Exception messages: `'Only a submitted expense request can be verified.'`, `'Only a submitted expense request can be rejected.'`, `'Only a submitted expense request can have a revision requested.'`
**Throws:** `\RuntimeException` — consistent with existing codebase style (see `submit()`, `revise()`, `closeAsRecordOnly()`, and `PaymentRequestService`).
**Tests:** 5 bug-gate tests in `tests/Feature/Hris/FinanceCenter/ExpenseRequestFinanceReviewTest.php` — `->group('bug')` removed, now pass as normal tests.
**Full suite impact:** Zero regressions. Suite: `Tests: 2570 deprecated (130480 assertions)` — no failures.
**Commit:** fix(expense): guard ExpenseRequestService verify/reject/requestRevision against invalid source states (BUG-4)

### BUG-3 — `CanonicalPayloadHashService::money()` uses a `(float)` cast (precision loss above 2^53)

**Detected in:** Phase 6 (`PaymentBundleCanonicalHashDeterminismTest`)
**Service:** `app/Services/Hris/CanonicalPayloadHashService.php` — `money()` does `number_format((float) $v, 2, '.', '')`.
**Gap:** The `(float)` cast loses integer precision above 2^53 (9,007,199,254,740,992). Empirically confirmed: `"9007199254740993.00"` canonicalizes to `"9007199254740992.00"` — a 1-IDR drift — which would change/destabilize the canonical hash for such amounts.
**Severity:** Negligible in practice — 9 quadrillion IDR is not a real payment amount; values up to 100 billion IDR are stable. But it is a correctness smell in money handling and violates the "no float in money" rule.
**Recommended fix (Phase 11):** replace the `(float)` cast with arbitrary-precision normalization, e.g. `bcadd($v, '0', 2)`.
**Tests:** large-IDR determinism case in `PaymentBundleCanonicalHashDeterminismTest` (with `// NOTE: (float) cast — see BUG / Phase 11`)

### BUG-5 — `PaymentRequestService::updateApprovedDetails` has no post-payment state guard — RESOLVED

**Detected in:** Phase 5 (`ManualPaymentRequestFlowTest`)
**Service:** `app/Services/Hris/PaymentRequestService.php::updateApprovedDetails`
**Fix:** Added `EDITABLE_STATUSES` constant (`draft`, `needs_clarification`, `submitted_to_coo`, `approved_by_coo`, `rejected_by_coo`, `approved_for_bundle`) and an `in_array` guard at the top of `updateApprovedDetails`. States not in that list (`in_payment_bundle`, `scheduled_for_payment`, `paid`, `paid_pending_reconciliation`, `reconciled`, `reconciliation_failed`, `payment_failed`, `voided`, `reversed`, `cancelled`) now throw `\RuntimeException('A payment request can no longer be edited once it is in a payment or terminal state.')`.
**UI gate mirrored:** `PaymentRequestResource` has no edit page (`canCreate()=false`, pages: index+view only) and no form action gated by editable states — the service is the sole enforcement point. The allowed-state set mirrors the pre-payment lifecycle analogous to BUG-4's `canReview()` pattern.
**Tests:** 4 BUG-A characterization tests flipped to `->toThrow(\RuntimeException::class, '...')` in `ManualPaymentRequestFlowTest`. "allowed on draft" and "allowed/voids on approved_by_coo" tests unchanged (still pass).
**Commit:** fix(finance): BUG-5/6/7 PR edit+amount guards + BUG-8b reimbursement amount-as-string

### BUG-6 — `updateApprovedDetails` has no `source_type` guard — RECLASSIFIED: not a bug

**Detected in:** Phase 5 (`ManualPaymentRequestFlowTest`)
**Reclassification:** The `PaymentRequestResource` has NO form action, no edit page, and no gating by `source_type` anywhere. Correcting bank details or amount on an ER-sourced PR before payment is a legitimate and intended operation. No source_type guard should be added.
**Tests:** BUG-B characterization test renamed and rewritten to assert the LEGITIMATE behavior: editing an ER-sourced approved PR voids the snapshot and returns the PR to draft (same as a manual PR). Test now passes as a positive assertion.

### BUG-7 — `createManual` does not reject zero/negative amounts — RESOLVED

**Detected in:** Phase 5 (`ManualPaymentRequestFlowTest`)
**Service:** `PaymentRequestService::createManual`
**Fix:** Added guard before the DB transaction: `if (bccomp((string) $amount, '0', 2) <= 0) throw new \RuntimeException('A payment request amount must be greater than zero.')`.
**Collateral:** `PaymentRequestLineBatchReadyTest::beforeEach` used `amount => 0` as a test shortcut for batch-line container PRs; updated to `amount => 1` (the test does not assert on header amount).
**Tests:** 2 GAP-C characterization tests flipped to `->toThrow(\RuntimeException::class, '...')` in `ManualPaymentRequestFlowTest`.

### BUG-8 — `PaymentRequestService::varianceMetadata()` uses `(float)` cast for ratio comparison

**Detected in:** Phase 11 (`FinanceMoneyIntegrityTest` — no-float guard)
**Service:** `app/Services/Hris/PaymentRequestService.php` — `varianceMetadata()` line ~118:
`(float) bcdiv($absDiff, (string) $er->amount, 6)` used as a ratio flag against constant `0.10`.
**Gap:** The `(float)` cast is used purely for a > comparison, not for storing money. Precision impact is negligible (the ratio has 6dp of bcdiv precision before the cast). Nonetheless it violates the no-float rule in money-handling code.
**Severity:** Very low — no stored amount is affected; only the `variance_risk_flag` metadata field could in theory be miscalculated for ratios very close to 0.10 (e.g. exactly 0.1000000 at 6dp might cast to 0.09999999... or vice versa).
**Recommended fix:** replace with `bccomp(bcdiv($absDiff, (string) $er->amount, 6), '0.100000', 6) > 0`. **DEFERRED.**
**Tests:** allowlisted with `PaymentRequestService.php => 1` in the no-float guard in `FinanceMoneyIntegrityTest`.

### BUG-8b — `ReimbursementController` returns `amount` as a JSON number (float cast) — RESOLVED

**Detected in:** Phase 11 (`FinanceMoneyIntegrityTest` — API amount-as-string test)
**Controller:** `app/Http/Controllers/Api/Hris/ReimbursementController.php`
**Fix:** Removed `(float)` cast on `$r->amount` (`index()`) and on `$reimbursement->amount` (`store()`). Eloquent's `decimal:2` cast on `Reimbursement::$casts['amount']` returns the canonical string (e.g. `"350000.00"`) directly.
**Collateral:** `ReimbursementTest` line 165 asserted `->assertJsonPath('data.amount', 500000)` — updated to `->assertJsonPath('data.amount', '500000.00')`.
**Tests:** BUG-8b test in `FinanceMoneyIntegrityTest` flipped: `is_string($rawAmount)->toBeTrue()` + `->toBeString()->toBe('350000.00')`.

### BUG-9 — `revealPaymentInstructions` has no internal permission check (defense-in-depth gap, NOT an active vuln)

**Detected in:** Phase 13 (`FinanceSensitiveDataProtectionTest`)
**Service:** `app/Services/Hris/PaymentBundlePaymentService::revealPaymentInstructions` returns the FULL decrypted bank account but performs no permission check; its docblock says it is "permission-gated at the caller."
**Exposure (verified):** The method has exactly ONE caller — `beginPaymentRecording()` (same service) — which is reachable only via the `begin_payment_recording` Filament action in `PaymentBundleResource` (line 288), gated by `visible(status === authorized_for_payment && financeCan('payment_bundles.mark_paid'))`. There is NO HTTP route or ungated action that reaches the reveal. So an unauthorized user CANNOT reach the full-bank reveal through the real UI/API. This is a **defense-in-depth gap**, not an open vulnerability.
**Secondary note:** the enforced gate is `payment_bundles.mark_paid`, while the intended sensitive-data permission is `finance_center.view_sensitive_payment_data` (docblock intent). `finance_executor` holds both, so it works today, but the permission semantics are inconsistent.
**Severity:** Low — no current exploit path; risk is a future caller added without a gate.
**Recommended fix:** add an explicit `abort_unless($actor->can('finance_center.view_sensitive_payment_data'), 403)` (or equivalent) inside `revealPaymentInstructions` for defense-in-depth, and align the action gate to the same permission. **DEFERRED — payment service + permission semantics, owner approval (bug-gate).**
**Tests:** characterization test in `FinanceSensitiveDataProtectionTest` confirms the service itself does not self-gate; the reveal IS audited (`batch_bank_detail_revealed`) and full bank is absent from audit payloads.

---

## Phase 11 — Money integrity test classification

**File:** `tests/Feature/Hris/FinanceCenter/FinanceMoneyIntegrityTest.php`  
**Tests:** 6 (all green)

### Already covered (not duplicated)

| Case | Covered in |
|------|-----------|
| amount stored as string `'5000000.00'` | `PaymentRequestLifecycleTest` L37 |
| bccomp total after addPaymentRequest | `PaymentBundleServiceTest` L106 |
| bccomp total after addWeeklyAllowanceBatch | `PaymentBundleServiceTest` L130 |
| multi-item sum | `PaymentBundleServiceTest` L138 |
| batch total (weekly+overtime+latepay) | `BatchPaymentBundleServiceTest` L45 |
| zero amount GAP-C / BUG-7 | `ManualPaymentRequestFlowTest` L286 |
| negative amount GAP-C / BUG-7 | `ManualPaymentRequestFlowTest` L302 |
| large amount (100B IDR) | `ManualPaymentRequestFlowTest` L259 |
| IDR display does not mutate stored value | `ManualPaymentRequestFlowTest` L266 |
| ER API returns amount as string | `ExpenseRequestApiTest` L41 |
| No-float guard for BatchPaymentBundleService + BatchPayments/* + snapshot support | `BatchPaymentNoFloatGuardTest` |

### Net-new cases

1. **Bundle total == bcadd sum of PR items** — verifies item amounts copy PR amounts exactly and sum matches bundle total via bcadd + bccomp.
2. **Bundle total == bcadd sum of batch-source items** — same check for batch (WAB) sources.
3. **Decimal scale consistency** — PR, item, bundle all carry exactly 2dp (regex check + bccomp equality).
4. **Reimbursement API amount type (BUG-8b characterization)** — documents that `/api/hris/reimbursements` currently returns amount as JSON number, not string.
5. **ER API amount regression guard** — confirms ER API still returns amount as string (the correct behavior).
6. **Extended no-float guard** — covers `PaymentBundleService`, `PaymentBundlePaymentService`, `PaymentBundleSignatureService`, `CanonicalPayloadHashService`, `PaymentRequestService`, `PaymentRequest`, `PaymentBundle`, `PaymentBundleItem`. Allowlist: `CanonicalPayloadHashService.php => 1` (BUG-3) and `PaymentRequestService.php => 1` (BUG-8). Guard would FAIL on any new float cast in these files.

### Float occurrences found

| File | Line | Code | Status |
|------|------|------|--------|
| `CanonicalPayloadHashService.php` | 108 | `number_format((float) $v, 2, '.', '')` | BUG-3 — allowlisted |
| `PaymentRequestService.php` | 118 | `(float) bcdiv($absDiff, (string) $er->amount, 6)` | BUG-8 — allowlisted |
| `ReimbursementController.php` | 37, 76 | `(float) $r->amount` | BUG-8b — not in guard scope (controller) |

---

## Residual hardening

### Audit immutability is APP-LEVEL ONLY (no DB triggers / REVOKE)

**Context (Phase 14):** `FinanceAuditLog::booted()` has `updating` and `deleting` hooks that throw `RuntimeException` — this stops Eloquent ORM mutations. However, any DB user with direct table access (a raw `DB::table()` query, a MySQL CLI session, or a compromised service bypassing Eloquent) can `UPDATE` or `DELETE` rows without triggering the guard.

**Tamper detectability:** The SHA-256 hash chain makes tampering DETECTABLE after the fact. Running a chain re-validation walk (`FinanceAuditLogger::recompute()` + `previous_hash` linkage per row) reveals:
- Any field mutation: `recompute(row) ≠ stored current_hash` for the tampered row.
- Any row deletion: the surviving row after the gap has `previous_hash` that no longer matches the prior surviving row's `current_hash`.
- Any `previous_hash` forgery: both the linkage check and the re-hash fail for that row.

**Test strategy:** `tests/Feature/Hris/FinanceCenter/FinanceAuditTrailIntegrityTest.php` (Phase 14) intentionally uses raw `DB::table()` queries to simulate the attacker path and verifies that `walkChain()` returns errors in every tamper scenario. This correctly represents the residual threat model: ORM-path mutations are blocked; raw-query mutations are detectable but not prevented.

**Recommended deferred production hardening:**
1. **DB-level append-only enforcement:** Grant the application DB user only `INSERT` + `SELECT` on `finance_audit_logs` (REVOKE UPDATE, DELETE). Prevent even super-user tampering via a `BEFORE UPDATE/DELETE` trigger that raises an error.
2. **Scheduled chain integrity audit:** Run a background job that walks the chain periodically and alerts on any broken link (tamper detection moves from manual/on-demand to automatic).
3. **External anchor:** Periodically publish the chain head's `current_hash` to an immutable external log (e.g. a signed email receipt, a blockchain timestamp, or an append-only S3 object) so hash-chain forgery requires defeating an out-of-band anchor..

---

## Phase 18 — Mobile E2E smoke (NOT added — tooling absent, deferred)

**Status:** No mobile E2E tooling exists (Phase 0 confirmed: no Maestro, no Detox in `partnershub-app/frontend`). Per the plan, new E2E tooling was NOT added without approval. The Phase 17 unit/integration tests (`expenseSubmitter.test.ts`, `expenseApi.test.ts`, `expenseErrors.test.ts` — 29 tests) cover the idempotency/double-submit logic at the submitter+api layer, which is where the duplicate-submit bug actually lived.

**Recommended Maestro setup (if/when approved):**
1. Add Maestro: `curl -Ls "https://get.maestro.mobile.dev" | bash`; create `partnershub-app/frontend/.maestro/`.
2. Flow `expense-request-submit.yaml`: launch app → login as employee → open Expense Request → fill form → attach evidence → submit → assert success message → open Expense History → assert the submitted item appears.
3. Flow `expense-request-double-submit.yaml`: fill form → tap submit twice rapidly → assert exactly one success → navigate to history → assert only ONE submitted expense exists (the real-device guarantee complementing the unit-level in-flight-guard test).
4. CI: add a Maestro Cloud / `maestro test` step gated behind a build (EAS dev client). Wire into `.eas/workflows/` per the existing CI conventions.

**Why deferred:** adding a native E2E runner + a dev-client build pipeline is infra work beyond test-hardening scope and needs owner sign-off (CI minutes, device farm). The duplicate-submit risk is already covered at the logic layer (Phase 17).
