# Batch Payment Modules — Progress

Spec: `docs/BATCH_PAYMENT_MODULE.MD`. Builds on the completed PaymentBundle / Final Payment
Authorization workflow (frozen snapshots, canonical payload hash, multi-signature, signed PDF,
payment proof, internal paid record).

- **Branch:** `main`
- **Base HEAD at scan:** `99ff3c9`
- **Engine:** Laravel 12 + Filament 3. Tests on SQLite `:memory:`; prod MySQL 8.

## Scope guardrails (held)

Batch payment modules do **not** move money. They feed approved/finalized payable batches into a
`PaymentBundle` as frozen, versioned snapshot sources. The signed bundle authorizes an internal
payment record; finance pays manually outside the system and uploads proof.

Explicitly **excluded** (and not introduced): bank reconciliation, payment gateway, bank API
transfer, automatic bank-statement matching, FPDI stamping, payroll recalculation during bundling,
anonymous public signing, partial per-item payment.

## Implementation summary

| Concern | Where |
| --- | --- |
| Source contracts | `app/Contracts/Hris/PayableBatchSource.php`, `BatchPaymentSourceAdapter.php` |
| Adapters | `app/Services/Hris/BatchPayments/{WeeklyAllowance,OvertimePay,LatePayCompensation,PayrunBatch}BatchPaymentSourceAdapter.php` |
| Orchestration | `app/Services/Hris/BatchPaymentBundleService.php` |
| Snapshot + hash | `app/Support/Finance/PaymentBundleSnapshot.php`, `MoneyString.php`, `BatchPaymentSnapshotCollection.php` |
| Payment execution / reveal | `app/Services/Hris/PaymentBundlePaymentService.php` |
| Reverse transitions | `app/Services/Hris/PaymentBundleService.php` (cancel/void/rejectSignature → release) |
| Models | `PaymentBundleItem` (+ source/payment-status consts), `WeeklyAllowanceBatch`, `OvertimePayBatch`, `LatePayCompensationBatch`, `PayrunBatch` implement `PayableBatchSource` |
| DB guard | `database/migrations/2026_06_03_000004_add_batch_payment_status_and_active_source_guard.php` |
| Finance Center UI | `app/Filament/Pages/FinanceCenter.php`, `resources/views/filament/pages/finance-center.blade.php` |
| PDF | `resources/views/finance/payment-bundles/authorization.blade.php` |

## Phase status

| Phase | Status |
| --- | --- |
| 0 Scan | ✅ (this doc) |
| 0.5 State model & invariants | ✅ |
| 1 Placeholder copy | ✅ (payroll honestly disabled; completed modules enabled) |
| 2 Weekly allowance pilot | ✅ |
| 3 Adapter contract | ✅ |
| 4 DB active-source uniqueness | ✅ (pgsql/sqlite partial unique index; mysql generated column + unique index) |
| 5 BatchPaymentBundleService | ✅ |
| 6 Decimal / no-float guards | ✅ (`BatchPaymentNoFloatGuardTest`) |
| 7 Overtime | ✅ |
| 8 Late pay | ✅ |
| 9 Payroll | ⚠️ Intentionally **blocked** at the adapter (Phase 9 gate) until frozen payroll payee bank data + year-end tax guards exist. Surfaced honestly in Finance Center. |
| 10 Payment atomicity | ✅ (bundle-level, transactional cascade) |
| 11 Bank-detail reveal path | ✅ (`revealPaymentInstructions`, audited) |
| 12 Finance Center UI | ✅ |
| 13 PDF batch sections | ✅ (safe summary, last4 only) |
| 14 Reverse transitions | ✅ |
| 15 Audit events | ✅ (see below) |
| 16 Hash regression fixture | ✅ |
| 17 Tests | ✅ |
| 18 Validation | ✅ (see below) |

## Canonical source state model (Phase 0.5)

`PaymentBundleItem` payment-status constants drive both the item and the live source:

```
payment_eligible → payment_bundled → payment_authorized → payment_recording → payment_paid
payment_bundled → payment_released            (cancel / remove before signing)
payment_authorized → payment_released         (void before payment)
payment_blocked → payment_eligible            (after missing data fixed)
```

Active (lock-protected) states: `payment_bundled`, `payment_authorized`, `payment_recording`.
A `payment_paid` source is terminal and is never released back to eligible — a paid bundle cannot be
cancelled or voided, so the release paths are unreachable for it.

## Snapshot schema (versioned)

`PaymentBundleSnapshot::batchSnapshot()` emits `snapshot_version: 1` with: `source_type`,
`source_id`, `source_number`, `source_status`, `currency` (IDR), `total_amount` (decimal string),
`payable_count`, `summary`, `payees[]` (`payee_id`, `payee_name`, `bank_name`, `bank_account_last4`,
`bank_account_encrypted_ref`, `amount`, `currency`), `protected_payees[]` (full account, kept out of
the public payload), and `source_hash`. All money is bcmath decimal strings; no floats.

The full `source_snapshot_json` (incl. full bank account, in `protected_payees`) is stored
`encrypted:array` at rest on `PaymentBundleItem`. The PDF and public verification show last4 only.

## Hash responsibility

`canonical_payload_hash` (over per-item `bank_last4`, `amount`, `source_hash`, …) is the authoritative
signed identity; the PDF file hash is secondary. Item `source_hash` binds the frozen snapshot. The
pre-batch canonical hash format is unchanged and pinned by a golden fixture
(`BatchPaymentCanonicalHashRegressionTest`).

## DB-level active-source uniqueness (Phase 4)

`payment_bundle_items_active_source_unique` on `(source_type, source_id)` restricted to active
states. pgsql/sqlite use a partial unique index; mysql/mariadb use a `STORED` generated
`active_source_key` column + unique index. App-level guard in `BatchPaymentBundleService::addSource`
remains as a friendly first check, but the DB index is the real protection in production.

## Drift detection checkpoints

`recomputed sourceHash !== stored PaymentBundleItem.source_hash`. Enforced (blocking) at:
submit-for-signature, each signature submit, before payment recording, and before mark-paid. On
drift: emit `batch_source_drift_detected` and block — payment always executes from the frozen
snapshot, never live fields.

## Audit events

Implemented and asserted (`BatchPaymentAuditTest`): `batch_source_added_to_bundle`,
`batch_source_released_from_bundle`, **`batch_source_marked_paid`** (new this phase),
**`batch_source_blocked_missing_data`** (new this phase), `batch_source_drift_detected`,
`weekly_allowance_added_to_bundle`, `overtime_pay_added_to_bundle`, `late_pay_added_to_bundle`,
`batch_bank_detail_revealed`. Hash chain stays unbroken across the lifecycle.

`payroll_batch_added_to_bundle` is wired in `BatchPaymentBundleService::sourceAddedEvent()` but is
unreachable while the payroll adapter is gated (Phase 9). Attempting to add a payroll batch emits
`batch_source_blocked_missing_data` instead. Audit payloads carry no bank/salary detail (the
reveal/blocked tests assert the full account never appears).

## Tests added

12 Phase-17 classes (all `tests/Feature/Hris/`): `BatchPaymentSourceStateTest`,
`BatchPaymentSourceAdapterTest`, `WeeklyAllowancePaymentBundleTest`, `OvertimePayPaymentBundleTest`,
`LatePayCompensationPaymentBundleTest`, `BatchPaymentBundlePdfTest`,
`BatchPaymentBundleAuthorizationTest`, `BatchPaymentBundleReverseTransitionTest`,
`BatchPaymentFinanceCenterTest`, `BatchPaymentAuditTest`, `BatchPaymentSensitiveDataTest`,
`BatchPaymentCanonicalHashRegressionTest`. Shared builders: `tests/Support/BatchPaymentTestFactory.php`
(autoloaded, single-file-run safe). Pre-existing batch tests retained:
`BatchPaymentBundleServiceTest`, `BatchPaymentBundleMarkPaidTest`, `BatchPaymentActiveUniquenessTest`,
`BatchPaymentSnapshotSchemaTest`, `BatchPaymentNoFloatGuardTest`, `PayrunBatchPaymentBundleTest`.

## Validation (Phase 18)

- `./vendor/bin/pest --filter='BatchPayment'` — 49 passed.
- `./vendor/bin/pest --filter='PaymentBundle'` — passed (no regression).
- `./vendor/bin/pest --filter='WeeklyAllowance|Overtime|LatePay|Payrun'` — passed.
- `./vendor/bin/pest --compact` — full suite green (see commit/CI).
- `./vendor/bin/pint` — clean on new/changed files.

All tests report as PHPUnit "deprecated" because of a pre-existing PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA`
deprecation in the host environment; that is not a failure.

## Known pre-existing issues

- PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation noise on every test (host/runtime, not this work).
- `PayslipTest > returns correct earnings breakdown` was non-deterministic because
  `SalaryStructureFactory` randomizes `pph21_method`; netto/gross_up add a tunjangan_pajak to
  `gross_income`. Fixed by pinning `pph21_method = gross` in that test (determinism only, no logic
  change).
- `pint` reports style issues in unrelated pre-existing files under `tests/Unit/` — out of scope.

## Remaining risks / deferred items

- **Payroll bundling** stays gated until frozen payroll payee bank data + December/termination
  annual true-up and TER category coverage are confirmed. Re-enable by implementing
  `PayrunBatchPaymentSourceAdapter::assertEligible/buildSnapshots/sourceHash`.
- Partial per-item payment is intentionally out of MVP scope.
- "Edit after first signature" re-version flow is not built (re-version + new hash + new unsigned PDF
  + re-sign) — deferred.
