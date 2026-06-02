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

## Model / table

`payment_authorization_settings` (migration `2026_06_04_000001`), one singleton row.
Model `App\Models\Hris\PaymentAuthorizationSetting`; `current()` returns the row, creating it
with safe defaults if absent (`firstOrCreate`). Columns:

| column | type | default |
|---|---|---|
| `finance_signer_1_user_id` | nullable FK users (nullOnDelete) | — |
| `finance_signer_2_user_id` | nullable FK users (nullOnDelete) | — |
| `executive_signer_user_id` | nullable FK users (nullOnDelete) | — |
| `require_two_finance_signatures` | boolean | `true` |
| `require_executive_signature` | boolean | `true` |
| `high_value_threshold` | decimal(15,2) (cast `decimal:2` → string) | `10000000.00` |
| `allow_creator_to_sign` | boolean | `false` |
| `allow_executor_to_sign` | boolean | `false` |

Relations: `financeSigner1()`, `financeSigner2()`, `executiveSigner()` → `User`.

## Permissions (pre-existing, seeded by `PaymentBundleRolePermissionSeeder`)

- `payment_bundles.sign.finance` — finance signer slots (roles: `finance_signer`).
- `payment_bundles.sign.executive` — executive slot (role: `executive_signer`).
- `payment_bundles.manage_signers` — edit settings + override signers (roles: `finance_creator`, `super_admin`).

## Settings validation — `PaymentAuthorizationSettingsService`

`validate()` (throws human-readable `RuntimeException`): Finance Signer 1 required; Finance Signer 2
required iff `require_two_finance_signatures`; Executive required iff `require_executive_signature`;
each *set* signer must hold the slot's required permission; F1 ≠ F2; Executive ≠ F1/F2;
`high_value_threshold` ≥ 0 (compared with `bccomp`). `isComplete()` = `validate()` doesn't throw.
`assignments()` = slot→userId map of the set slots.

## Settings page — `App\Filament\Pages\PaymentAuthorizationSettings`

Finance Center → Payment Authorization Settings (`navigationSort` 10). `canAccess()` = `super_admin`
OR `payment_bundles.manage_signers` (web guard). Signer selects are filtered to the matching
permission; `save()` validates via the service BEFORE persisting (danger notification + no write on
failure).

## Signer assignment flow (`PaymentBundleService::submitForSignature`)

1. Authorization: a non-`manage_signers` actor's caller-supplied signers are **replaced** by the
   configured defaults (server-side; the disabled submit-form selects are UI-only). A `manage_signers`
   actor's explicit assignments are honored (override). With no complete default configured yet,
   explicit assignments are still honored (bootstrap).
2. If relying on defaults and they are incomplete → throws *"Payment Authorization Settings are
   incomplete. Please assign Finance Signer 1, Finance Signer 2, and Executive Signer before
   submitting this bundle."*
3. All resolved signers (including settings-sourced) are validated by the existing
   `resolveAndValidateAssignment` (per-slot permission, distinctness, high-value-creator).
4. Signature rows are created with `assigned_user_id` frozen per bundle — later settings changes do
   **not** mutate already-submitted bundles.

## Override + signing enforcement

- `reassignSigners(bundle, assignments, actor)`: gated by `payment_bundles.manage_signers` (service-level
  AND Filament action); allowed only while the bundle is signature-pending
  (`PaymentBundle::signaturePendingStatuses()`) and **no** slot is signed yet; revalidates; reissues
  signing links; throws *"Signers cannot be changed after the first signature has been captured."*
  otherwise.
- `PaymentBundleSignatureService::assertSignableNow`: if a slot has an `assigned_user_id`, only that
  user may sign (*"You are not the assigned signer for this slot."*); if null, falls back to the
  slot's `required_permission`. Existing slot-pending / turn-order / segregation-of-duties checks are
  retained.

## Audit events (hash-chained `FinanceAuditLogger`)

- `payment_bundle_signers_assigned` — on submit; `after_snapshot` = the three signer ids + `bundle_number`.
- `payment_bundle_signers_changed` — on reassign; `before_snapshot`/`after_snapshot` = slot→id maps.

## Tests (15 plan scenarios)

| # | scenario | file |
|---|---|---|
| 1 | singleton + safe defaults | `PaymentAuthorizationSettingsTest` |
| 2 | settings page gated by `manage_signers` | `PaymentAuthorizationSettingsFilamentTest` |
| 3 | finance signer needs `sign.finance` | `PaymentAuthorizationSettingsServiceTest` |
| 4 | executive needs `sign.executive` | `…ServiceTest` |
| 5 | F1 ≠ F2 | `…ServiceTest` |
| 6 | Executive ≠ finance signers | `…ServiceTest` |
| 7 | incomplete settings blocks submit | `PaymentBundleSignerAssignmentTest` |
| 8 | valid settings create signature rows | `…SignerAssignmentTest` |
| 9 | settings change doesn't mutate existing bundle | `…SignerAssignmentTest` |
| 10 | override before submit (manage_signers) | `…SignerAssignmentTest` (submit) + `PaymentBundleSignerOverrideTest` (reassign) |
| 11 | override after first signature blocked | `…SignerOverrideTest` |
| 12 | assigned signer can sign | `…SignerOverrideTest` |
| 13 | wrong user cannot sign assigned slot | `…SignerOverrideTest` |
| 14 | `payment_bundle_signers_assigned` audit | `…SignerAssignmentTest` |
| 15 | `payment_bundle_signers_changed` audit | `…SignerOverrideTest` |

Plus: server-side override authorization (non-manager supplied signers replaced by defaults) and the
null-`assigned_user_id` permission fallback (both paths) are covered in `…SignerAssignmentTest` /
`…SignerOverrideTest`.

## Validation commands

```bash
./vendor/bin/pest --filter='PaymentAuthorizationSettings'
./vendor/bin/pest --filter='PaymentBundleSigner'
./vendor/bin/pest --filter='PaymentBundle|BatchPaymentBundle'
./vendor/bin/pest            # full suite
./vendor/bin/pint
```

## Production setup

1. Grant `payment_bundles.sign.finance` to the Finance Signer users.
2. Grant `payment_bundles.sign.executive` to the CEO/CFO user.
3. Grant `payment_bundles.manage_signers` to the admin / finance manager.
4. Open **Finance Center → Payment Authorization Settings**.
5. Select Finance Signer 1, Finance Signer 2, Executive Signer; Save.
6. Create a Payment Bundle and submit — the signer slots are pre-filled from these defaults.
7. A `manage_signers` user can override per-bundle at submit, or via **Reassign Signers** before the
   first signature.

## Scope notes

- **Three signers required.** The signing state machine has exactly three slots
  (`PaymentBundleSignatureSlot`), so a submittable configuration needs all three set. The
  `require_two_finance_signatures` / `require_executive_signature` toggles are persisted and enforced
  for *settings completeness*, but conditionally-optional signers in the signing flow are deferred
  (this is signer configuration, not a workflow builder).
- **Web context.** `submitForSignature` / `reassignSigners` authorize via `$actor->can(...)` and are
  invoked only from the Filament web panel (no Sanctum API caller). If ever exposed via `/api`, the
  Spatie `web`-guard handling must be revisited (see [[finance-er-pr-module]] guard gotcha).
- **Reserved-for-future fields.** `allow_creator_to_sign`, `allow_executor_to_sign`, and the settings
  `high_value_threshold` are persisted, validated (threshold ≥ 0) and editable on the page, but are
  **not yet consumed** by the signing flow: the high-value creator-≠-executive rule still reads
  `config('finance.payment_bundle_high_value_threshold')`, and the `allow_*` toggles have no reader.
  They are stored for the next iteration (amount-based / relaxed-SoD rules); changing them has no
  behavioral effect today.
