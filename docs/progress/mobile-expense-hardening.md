# Mobile Expense Request — Hardening

Goal: stop duplicate Expense Requests from the mobile submit flow and give the
employee clear loading / success / error feedback. No Finance Center, PaymentBundle,
reconciliation, or gateway behaviour was changed.

## Phase 0 — Scan (existing flow)

Backend (`partnershub-pm`, on `main`):

- `POST /api/hris/expense-requests` → `ExpenseRequestController@store` → `ExpenseRequestService::create()`
  → **always** `ExpenseRequest::create()`. Only unique key was the generated `request_number`.
- Mobile submit is 3 calls: create draft → upload evidence → submit. No idempotency anywhere.
- **Duplicate risk: a repeated `store` (double tap / retry) inserted a second ER.**

Mobile (`partnershub-app/frontend`, on `main`):

- `app/expense-request.tsx` `handleSubmit` guarded **only** by `disabled={submitting}` (React state).
  A same-frame double tap enters `handleSubmit` twice before the button disables → two `create` calls.
- Errors/success surfaced via raw `Alert.alert` with the raw server `message`.
- No retry/resume: a created draft id was lost on failure, so a retry re-created.
- Already present and reused: `toastManager` + `ToastProvider` (mounted in `app/_layout.tsx`),
  `expo-crypto` (`Crypto.randomUUID`), jest (`ts-jest`, node env), `@testing-library/react-native`.
- `app/expense-history.tsx` already refetches on focus, shows human-readable status badges,
  payment status, revision/rejection notes, and an empty state → the "history UX" phase was already satisfied.

## What changed

### Backend — idempotency on `(employee_id, client_request_id)`

- Migration `2026_06_03_000000_add_client_request_id_to_expense_requests_table`:
  nullable `client_request_id` (64) + `unique(employee_id, client_request_id)`.
  NULL keys stay distinct in MySQL/SQLite, so existing web/Filament creates are unaffected.
- `ExpenseRequest`: `client_request_id` fillable.
- `ExpenseRequestService::create()`: short-circuits a replay (same employee+key → existing ER);
  wraps the insert in a `QueryException` catch that re-fetches the winner if a concurrent insert lost the unique race.
- `ExpenseRequestController@store`: accepts `client_request_id` (nullable), returns **201** for a fresh
  draft and **200** when an idempotent replay returns the existing ER (`wasRecentlyCreated`).
- Decision: key is **optional** (backward-compatible). Mobile always sends one.

### Mobile — submit lock, idempotency, errors, UI

- `services/expenseSubmitter.ts` (`createExpenseSubmitter`, dependency-injected, React-free):
  synchronous `inFlight` lock (set before the first `await`) + persisted `clientRequestId` /
  `createdId` / `evidenceUploaded`. Double taps are ignored; a lost create response retries against
  the same key; a mid-chain failure resumes instead of restarting. Returns `{ok}` / `{ignored}` / `{error}`.
- `utils/expenseErrors.ts` (`getExpenseRequestErrorMessage`): maps network / 401 / 422-evidence /
  422-amount / 422-generic / 5xx / non-axios throws to calm human-readable lines — never a raw exception.
- `services/api.ts`: optional `client_request_id` on `CreateExpenseRequestInput`.
- `app/expense-request.tsx`: consumes the submitter; step labels (Creating… / Uploading… /
  Submitting…), success toast + navigate to history, inline error banner + error toast, form data kept
  on failure. Re-laid-out into spaced card sections (Expense details / Payment / Evidence / Notes) with a
  sticky safe-area footer CTA.

## Tests

- Backend `tests/Feature/Hris/ExpenseRequestIdempotencyTest.php` (4): replay→same ER (200),
  no-key still creates, different employees may reuse a key, no duplicate across a full create+upload+submit replay.
  `./vendor/bin/pest --filter='Expense'` → 46 passed / 0 failed (111 assertions).
- Mobile `__tests__/expenseSubmitter.test.ts` (6): double-tap creates once, key in payload, step order,
  retry-no-duplicate, lost-create-response reuse, human-readable failure. `expenseErrors.test.ts` (8).
  `expenseApi.test.ts` (+1, key forwarded). `npx jest expense` → 23 passed.
- `npx tsc --noEmit` clean on touched files; `eslint` clean; `pint` clean.

## Before / after

| | Before | After |
|---|---|---|
| Double tap | 2 ERs possible | 1 ER (sync lock + DB unique) |
| Retry after timeout | new ER each retry | same ER via reused key |
| Feedback | raw `Alert` w/ server text | step progress + success toast + human-readable errors |
| Form on error | lost on some paths | preserved |
| Form UI | flat list of inputs | card sections, spacing, sticky CTA |

## Known limitations

- Editing a field after a failed attempt then retrying reuses the first draft (same key/id);
  it does not create a revised second draft. Acceptable for the duplicate-prevention goal.
- Re-upload on retry can store a second evidence attachment server-side (deduped by hash warning);
  it never creates a duplicate ER.
- Mobile UI was typechecked/linted but not visually run on a device/simulator in this pass.
- Backend amount stays an integer-rupiah `number` in the request payload (response is a decimal string);
  unchanged to avoid breaking existing API tests.
