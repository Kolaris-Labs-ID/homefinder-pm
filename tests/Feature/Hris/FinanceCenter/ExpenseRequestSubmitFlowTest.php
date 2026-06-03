<?php

/**
 * Phase 2 — ER submit flow (gap-fill over existing ER tests).
 *
 * Coverage classification — ALL Phase 2 candidates already covered:
 *
 * - draft → evidence → submit          : ExpenseRequestLifecycleTest / ExpenseRequestApiTest
 * - submit without evidence rejected   : ExpenseRequestLifecycleTest "refuses to submit an ER without evidence"
 * - decimal-safe amount                : ExpenseRequestLifecycleTest "creates a draft with...decimal-safe amount"
 * - encryption-at-rest + last4 + decrypted accessor : ExpenseRequestLifecycleTest "encrypts the payee bank account number..."
 * - client_request_id dedupe           : ExpenseRequestIdempotencyTest (4 tests)
 * - audit event on submit              : ExpenseRequestAuditTest "logs expense_request.submitted on submit"
 *
 * Net-new cases: NONE for Phase 2 — all candidates were present in pre-existing tests.
 * File kept as required documentation artifact.
 *
 * @see tests/Feature/Hris/ExpenseRequestLifecycleTest.php
 * @see tests/Feature/Hris/ExpenseRequestIdempotencyTest.php
 * @see tests/Feature/Hris/ExpenseRequestAuditTest.php
 * @see tests/Feature/Api/Hris/ExpenseRequestApiTest.php
 */

// No tests needed — all Phase 2 candidates already covered; see docblock above.
