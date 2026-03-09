<?php

// Feature: payroll-approval-workflow, Property 14: Approval logs are immutable

/**
 * Property-Based Test: Approval logs are immutable
 *
 * For any existing PayrollApprovalLog entry, the model should not support
 * update or delete operations. The UPDATED_AT constant should be null,
 * and attempting to modify a log entry's attributes should not persist changes.
 *
 * **Validates: Requirements 8.4**
 */

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrunBatch;
use App\Models\User;

beforeEach(function () {
    PayrollApprovalLog::query()->delete();
});

test('Property 14: Approval logs are immutable — UPDATED_AT constant is null', function () {
    expect(PayrollApprovalLog::UPDATED_AT)->toBeNull(
        'PayrollApprovalLog::UPDATED_AT should be null to prevent updated_at tracking'
    );
})->group('property-test', 'payroll-approval-workflow');

test('Property 14: Approval logs are immutable — modifying attributes does not persist changes to the database', function () {
    $actions = ['submitted', 'approved', 'rejected', 'resubmitted', 'paid'];
    $statuses = ['draft', 'calculated', 'pending_finance', 'rejected', 'paid'];

    for ($i = 0; $i < 100; $i++) {
        PayrollApprovalLog::query()->delete();

        $actor = User::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $originalAction = fake()->randomElement($actions);
        $originalPreviousStatus = fake()->randomElement($statuses);
        $originalNewStatus = fake()->randomElement($statuses);
        $originalNotes = fake()->optional()->sentence();

        // Create a log entry
        $log = PayrollApprovalLog::create([
            'batch_type' => PayrunBatch::class,
            'batch_id' => $batch->id,
            'action' => $originalAction,
            'previous_status' => $originalPreviousStatus,
            'new_status' => $originalNewStatus,
            'acted_by' => $actor->id,
            'notes' => $originalNotes,
        ]);

        $logId = $log->id;
        $originalCreatedAt = $log->created_at;

        // Attempt to modify every mutable attribute on the in-memory model and save
        $newActor = User::factory()->create();
        $newAction = fake()->randomElement(array_diff($actions, [$originalAction]));
        $newPreviousStatus = fake()->randomElement(array_diff($statuses, [$originalPreviousStatus]));
        $newNewStatus = fake()->randomElement(array_diff($statuses, [$originalNewStatus]));
        $newNotes = fake()->sentence() . ' MODIFIED';

        $log->action = $newAction;
        $log->previous_status = $newPreviousStatus;
        $log->new_status = $newNewStatus;
        $log->acted_by = $newActor->id;
        $log->notes = $newNotes;
        $log->save();

        // Reload from database and verify the row is unchanged
        $reloaded = PayrollApprovalLog::find($logId);

        expect($reloaded->action)->toBe(
            $originalAction,
            "Iteration {$i}: action should remain '{$originalAction}' after update attempt, got '{$reloaded->action}'"
        );
        expect($reloaded->previous_status)->toBe(
            $originalPreviousStatus,
            "Iteration {$i}: previous_status should remain '{$originalPreviousStatus}' after update attempt"
        );
        expect($reloaded->new_status)->toBe(
            $originalNewStatus,
            "Iteration {$i}: new_status should remain '{$originalNewStatus}' after update attempt"
        );
        expect($reloaded->acted_by)->toBe(
            $actor->id,
            "Iteration {$i}: acted_by should remain {$actor->id} after update attempt"
        );
        expect($reloaded->notes)->toBe(
            $originalNotes,
            "Iteration {$i}: notes should remain unchanged after update attempt"
        );
        expect($reloaded->batch_type)->toBe(
            PayrunBatch::class,
            "Iteration {$i}: batch_type should remain unchanged"
        );
        expect($reloaded->batch_id)->toBe(
            $batch->id,
            "Iteration {$i}: batch_id should remain unchanged"
        );
    }
})->group('property-test', 'payroll-approval-workflow');

test('Property 14: Approval logs are immutable — created_at timestamp is set on creation and does not change', function () {
    $actions = ['submitted', 'approved', 'rejected', 'resubmitted', 'paid'];
    $statuses = ['draft', 'calculated', 'pending_finance', 'rejected', 'paid'];

    for ($i = 0; $i < 100; $i++) {
        PayrollApprovalLog::query()->delete();

        $actor = User::factory()->create();
        $batch = PayrunBatch::factory()->create();

        $log = PayrollApprovalLog::create([
            'batch_type' => PayrunBatch::class,
            'batch_id' => $batch->id,
            'action' => fake()->randomElement($actions),
            'previous_status' => fake()->randomElement($statuses),
            'new_status' => fake()->randomElement($statuses),
            'acted_by' => $actor->id,
            'notes' => fake()->optional()->sentence(),
        ]);

        $originalCreatedAt = $log->created_at->toDateTimeString();

        // Verify created_at is set
        expect($log->created_at)->not->toBeNull(
            "Iteration {$i}: created_at should be set on creation"
        );

        // Verify there is no updated_at column tracked
        expect($log->updated_at)->toBeNull(
            "Iteration {$i}: updated_at should be null since UPDATED_AT constant is null"
        );

        // Reload and verify created_at hasn't changed
        $reloaded = PayrollApprovalLog::find($log->id);
        expect($reloaded->created_at->toDateTimeString())->toBe(
            $originalCreatedAt,
            "Iteration {$i}: created_at should remain unchanged after reload"
        );
    }
})->group('property-test', 'payroll-approval-workflow');
