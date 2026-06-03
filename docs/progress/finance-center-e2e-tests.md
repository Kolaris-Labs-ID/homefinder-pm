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

_(empty — populated during Phase 19)_
