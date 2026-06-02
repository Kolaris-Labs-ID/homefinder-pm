# Payment Authorization Settings — Finance Center

Status: **in progress** (branch `worktree-feat+payment-authorization-settings`).

Goal: persist a global default set of Payment Bundle signers (Finance Signer 1, Finance
Signer 2, Executive Signer) so submitters no longer hand-pick them on every bundle, plus a
gated UI to edit them, signer preview/override before the first signature, and signing-time
enforcement of assigned signers.

---

## Phase 0 — Scan (current state, before any feature code)

The Payment Bundle signing feature is already built. Most of the originally-drafted plan
**already exists**; this feature only fills the gaps. Key findings:

### 1. Where signer rows are created
`PaymentBundleService::submitForSignature(PaymentBundle $bundle, array $assignments, User $actor)`
(`app/Services/Hris/PaymentBundleService.php:323`). It:
- locks the draft bundle, asserts DRAFT + non-empty + no source drift,
- calls `resolveAndValidateAssignment()` (`:381`) — **already enforces every validation rule
  the plan lists** (all three present, F1≠F2, EXEC≠F1/F2, high-value creator≠exec, and each
  assigned user `->can(slot->requiredPermission())`),
- creates the three `PaymentBundleSignature` rows (`:342`),
- calls `PaymentBundleSignatureService::createSigningLink($sig, $user)` (`:349`) which sets
  `assigned_user_id` + `required_permission` + token,
- generates the unsigned PDF, advances status to `pending_finance_signature_1`,
- audits `payment_bundle.submitted_for_signature` with the three signer ids in `after`.

So **per-submit assignment + full validation + `assigned_user_id` persistence already exist.**
Today the assignments come from the submitter's form — there is **no persisted default**.

### 2. How signature slots are represented
Enum `App\Enums\PaymentBundleSignatureSlot` (`finance_signer_1`, `finance_signer_2`,
`executive_signer`) with `requiredPermission()`, `isFinance()`, `order()`, `label()`,
`ordered()`. Each `payment_bundle_signatures` row stores `signature_slot`, `required_permission`,
and a nullable `assigned_user_id` (FK users, nullOnDelete) — migration
`2026_06_03_000003_create_payment_bundle_signatures_table.php:17`. The model
`PaymentBundleSignature` already has an `assignedUser()` relation (`:52`).

### 3. Which permission gates signing
- Finance slots → `payment_bundles.sign.finance`
- Executive slot → `payment_bundles.sign.executive`
(from `PaymentBundleSignatureSlot::requiredPermission()`.)
Signing authorization today: `PaymentBundleSignatureService::assertSignableNow()` (`:200`) checks
slot=pending + bundle-status-is-this-slot's-turn + signer-not-already-signed-another-slot (SoD).
It does **NOT** currently check `signer->id === assigned_user_id` → this is the Phase 7 gap.

### 4. `payment_bundles.manage_signers`
**Already exists** and is seeded — `PaymentBundleRolePermissionSeeder.php:35`, granted to
`finance_creator` and `super_admin`. All required permissions
(`sign.finance`, `sign.executive`, `manage_signers`) exist and the seeder is registered in
`HrisSeeder` (`:18`). **No seeder/permission changes needed.**

### 5. Settings persistence pattern to mirror
Dedicated settings table + Eloquent model, edited by a Filament `Page` — e.g. `PayrollSetting`
/ `AttendanceSetting` models, `PayrollApprovalConfig` page (`Page implements HasForms`, custom
blade view, `mount()`/`save*()`/`canAccess()`). **No settings package** (matches the plan's
"do not introduce a new package"). The submit UI is the `submit_for_signature` table action in
`PaymentBundleResource.php:205` — three `User::permission(...)` selects, required, not pre-filled.

### 6. Audit logger shape
`FinanceAuditLogger::log(Model $auditable, string $eventType, ?User $actor, array $opts)` —
opts support `before`, `after`, `reason` only (hash-chained, append-only). There is **no
freeform payload column**; new events must put their payload in `after` (and `before` for
changes).

---

## Re-scoped work (vs the literal plan)

| Plan phase | Reality | Action |
|---|---|---|
| 0 Scan | — | this doc |
| 1 Settings model+migration | none | **build** singleton `PaymentAuthorizationSetting` |
| 2 Permissions | already exist + seeded | **verify only** (test) |
| 3 Settings validation service | rules exist in `resolveAndValidateAssignment` | **build** `PaymentAuthorizationSettingsService` (validate the *defaults*; reuse rule shape; do not duplicate the bundle-aware validator) |
| 4 Filament settings page | none | **build** gated page + view |
| 5 Bundle uses settings | submit takes explicit assignments | **modify** `submitForSignature` to default `$assignments` from settings (additive — keep all ~35 existing callers working), block-with-message if incomplete, emit `payment_bundle_signers_assigned`; pre-fill the submit form selects from settings |
| 6 Preview + override | submit form is the pre-submit override | **add** `reassignSigners()` (post-submit, pre-first-signature) + Filament action + preview; audit `payment_bundle_signers_changed` |
| 7 Signing enforcement | `assertSignableNow` ignores `assigned_user_id` | **modify** to require signer == assigned_user when set; permission fallback when null. **Must keep all existing signature tests green.** |
| 8 Tests | — | woven per task (TDD) + full 15-scenario coverage verified at the end |
| 9 Docs | — | finalize this doc |

### Files to modify / create
- create `database/migrations/*_create_payment_authorization_settings_table.php`
- create `app/Models/Hris/PaymentAuthorizationSetting.php`
- create `app/Services/Hris/PaymentAuthorizationSettingsService.php`
- create `app/Filament/Pages/PaymentAuthorizationSettings.php` + `resources/views/filament/pages/payment-authorization-settings.blade.php`
- modify `app/Services/Hris/PaymentBundleService.php` (`submitForSignature` defaulting + `payment_bundle_signers_assigned`; add `reassignSigners`)
- modify `app/Services/Hris/PaymentBundleSignatureService.php` (`assertSignableNow` assigned-user enforcement)
- modify `app/Filament/Resources/PaymentBundleResource.php` (pre-fill submit selects from settings; add Reassign action + signer preview)

### Tests to add
`PaymentAuthorizationSettingsTest`, `PaymentAuthorizationSettingsFilamentTest`,
`PaymentBundleSignerAssignmentTest` — covering the 15 scenarios in the plan.

---

## (to be completed) model/table · defaults · validation · assignment flow · override rules · audit events · production setup
