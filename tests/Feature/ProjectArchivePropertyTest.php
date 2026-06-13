<?php

/**
 * Property-Based Tests for Project Archive Feature
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Epic;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\ProjectArchiveService;

beforeEach(function () {
    // Clean up before each test
    Project::query()->delete();
});

/**
 * **Feature: project-archive, Property 2: Active scope excludes archived projects**
 *
 * For any collection of projects containing both archived and non-archived projects,
 * the active scope SHALL return only projects where archived_at is null.
 *
 * **Validates: Requirements 1.3**
 */
test('Property 2: Active scope excludes archived projects', function () {
    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of active and archived projects
        $activeCount = rand(0, 3);
        $archivedCount = rand(0, 3);

        // Create active projects (not archived)
        Project::factory()->count($activeCount)->create([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        // Create archived projects with a single archiver
        if ($archivedCount > 0) {
            $archiver = User::factory()->create();
            Project::factory()->count($archivedCount)->create([
                'archived_at' => now()->subDays(rand(1, 30)),
                'archived_by' => $archiver->id,
            ]);
        }

        // Query using active scope
        $result = Project::active()->get();

        // Property: All returned projects must have archived_at = null
        foreach ($result as $project) {
            expect($project->archived_at)->toBeNull(
                'Active scope returned a project with archived_at set'
            );
        }

        // Property: Count of active scope results should equal count of non-archived projects
        $expectedActiveCount = Project::whereNull('archived_at')->count();
        expect($result->count())->toBe($expectedActiveCount);

        // Clean up for next iteration
        Project::query()->delete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 3: Archived scope includes only archived projects**
 *
 * For any collection of projects containing both archived and non-archived projects,
 * the archived scope SHALL return only projects where archived_at is not null.
 *
 * **Validates: Requirements 1.4**
 */
test('Property 3: Archived scope includes only archived projects', function () {
    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of active and archived projects
        $activeCount = rand(0, 3);
        $archivedCount = rand(0, 3);

        // Create active projects (not archived)
        Project::factory()->count($activeCount)->create([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        // Create archived projects with a single archiver
        if ($archivedCount > 0) {
            $archiver = User::factory()->create();
            Project::factory()->count($archivedCount)->create([
                'archived_at' => now()->subDays(rand(1, 30)),
                'archived_by' => $archiver->id,
            ]);
        }

        // Query using archived scope
        $result = Project::archived()->get();

        // Property: All returned projects must have archived_at NOT null
        foreach ($result as $project) {
            expect($project->archived_at)->not->toBeNull(
                'Archived scope returned a project without archived_at set'
            );
        }

        // Property: Count of archived scope results should equal count of archived projects
        $expectedArchivedCount = Project::whereNotNull('archived_at')->count();
        expect($result->count())->toBe($expectedArchivedCount);

        // Clean up for next iteration
        Project::query()->delete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 1: Archive sets state and metadata correctly**
 *
 * For any project and any user, when the archive action is performed,
 * the project's archived_at field SHALL be set to the current timestamp
 * AND the archived_by field SHALL reference the user who performed the action.
 *
 * **Validates: Requirements 1.1, 1.2**
 */
test('Property 1: Archive sets state and metadata correctly', function () {
    $service = new ProjectArchiveService;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a random project (not archived)
        $project = Project::factory()->create([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        // Create a random user to perform the archive
        $user = User::factory()->create();

        // Perform archive action
        $service->archive($project, $user);

        // Refresh project from database
        $project->refresh();

        // Property: archived_at must be set (not null)
        expect($project->archived_at)->not->toBeNull(
            'Archive action did not set archived_at'
        );

        // Property: archived_at must be a recent timestamp (within last minute)
        expect($project->archived_at->diffInSeconds(now()))->toBeLessThan(60,
            'archived_at timestamp is not recent'
        );

        // Property: archived_by must reference the user who performed the action
        expect($project->archived_by)->toBe($user->id,
            'archived_by does not reference the correct user'
        );

        // Property: is_archived accessor should return true
        expect($project->is_archived)->toBeTrue(
            'is_archived accessor should return true after archiving'
        );

        // Clean up for next iteration
        $project->forceDelete();
        $user->forceDelete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 4: Mark as done sets completion timestamp**
 *
 * For any project, when the markAsDone action is performed,
 * the project's done_at field SHALL be set to the current timestamp.
 *
 * **Validates: Requirements 2.1**
 */
test('Property 4: Mark as done sets completion timestamp', function () {
    $service = new ProjectArchiveService;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a random project (not done)
        $project = Project::factory()->create([
            'done_at' => null,
            'is_auto_done' => false,
        ]);

        // Randomly decide if this is auto or manual done
        $isAuto = (bool) rand(0, 1);

        // Perform mark as done action
        $service->markAsDone($project, $isAuto);

        // Refresh project from database
        $project->refresh();

        // Property: done_at must be set (not null)
        expect($project->done_at)->not->toBeNull(
            'markAsDone action did not set done_at'
        );

        // Property: done_at must be a recent timestamp (within last minute)
        expect($project->done_at->diffInSeconds(now()))->toBeLessThan(60,
            'done_at timestamp is not recent'
        );

        // Property: is_auto_done should match the isAuto parameter
        expect($project->is_auto_done)->toBe($isAuto,
            'is_auto_done does not match the isAuto parameter'
        );

        // Property: is_done accessor should return true
        expect($project->is_done)->toBeTrue(
            'is_done accessor should return true after marking as done'
        );

        // Clean up for next iteration
        $project->forceDelete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 5: Unmark done clears completion state**
 *
 * For any project that is marked as done, when the unmarkDone action is performed,
 * the project's done_at field SHALL be null.
 *
 * **Validates: Requirements 2.3**
 */
test('Property 5: Unmark done clears completion state', function () {
    $service = new ProjectArchiveService;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a random project that is marked as done
        $isAuto = (bool) rand(0, 1);
        $project = Project::factory()->create([
            'done_at' => now()->subDays(rand(1, 30)),
            'is_auto_done' => $isAuto,
        ]);

        // Verify project is done before unmark
        expect($project->is_done)->toBeTrue(
            'Project should be done before unmarkDone'
        );

        // Perform unmark done action
        $service->unmarkDone($project);

        // Refresh project from database
        $project->refresh();

        // Property: done_at must be null after unmarkDone
        expect($project->done_at)->toBeNull(
            'unmarkDone action did not clear done_at'
        );

        // Property: is_auto_done must be false after unmarkDone
        expect($project->is_auto_done)->toBeFalse(
            'unmarkDone action did not clear is_auto_done'
        );

        // Property: is_done accessor should return false
        expect($project->is_done)->toBeFalse(
            'is_done accessor should return false after unmarking as done'
        );

        // Clean up for next iteration
        $project->forceDelete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 10: Restore clears archive state**
 *
 * For any archived project, when the restore action is performed,
 * the project's archived_at and archived_by fields SHALL be null.
 *
 * **Validates: Requirements 4.1, 4.2**
 */
test('Property 10: Restore clears archive state', function () {
    $service = new ProjectArchiveService;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a user who archived the project
        $archiver = User::factory()->create();

        // Create a random archived project
        $project = Project::factory()->create([
            'archived_at' => now()->subDays(rand(1, 30)),
            'archived_by' => $archiver->id,
        ]);

        // Verify project is archived before restore
        expect($project->is_archived)->toBeTrue(
            'Project should be archived before restore'
        );

        // Perform restore action
        $service->restore($project);

        // Refresh project from database
        $project->refresh();

        // Property: archived_at must be null after restore
        expect($project->archived_at)->toBeNull(
            'restore action did not clear archived_at'
        );

        // Property: archived_by must be null after restore
        expect($project->archived_by)->toBeNull(
            'restore action did not clear archived_by'
        );

        // Property: is_archived accessor should return false
        expect($project->is_archived)->toBeFalse(
            'is_archived accessor should return false after restoring'
        );

        // Clean up for next iteration
        $project->forceDelete();
        $archiver->forceDelete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 11: Restore preserves related data**
 *
 * For any archived project with tickets, epics, and members,
 * when the restore action is performed, the count of tickets, epics,
 * and members SHALL remain unchanged.
 *
 * **Validates: Requirements 4.3**
 */
test('Property 11: Restore preserves related data', function () {
    $service = new ProjectArchiveService;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a user who archived the project
        $archiver = User::factory()->create();

        // Create a random archived project
        $project = Project::factory()->create([
            'archived_at' => now()->subDays(rand(1, 30)),
            'archived_by' => $archiver->id,
        ]);

        // Create random number of related data
        $ticketCount = rand(0, 3);
        $epicCount = rand(0, 2);
        $memberCount = rand(0, 3);

        // Create ticket status for the project
        $status = TicketStatus::factory()->create([
            'project_id' => $project->id,
        ]);

        // Create tickets
        if ($ticketCount > 0) {
            Ticket::factory()->count($ticketCount)->create([
                'project_id' => $project->id,
                'ticket_status_id' => $status->id,
            ]);
        }

        // Create epics
        if ($epicCount > 0) {
            Epic::factory()->count($epicCount)->create([
                'project_id' => $project->id,
            ]);
        }

        // Create members
        if ($memberCount > 0) {
            $members = User::factory()->count($memberCount)->create();
            $project->members()->attach($members->pluck('id'));
        }

        // Record counts before restore
        $ticketCountBefore = $project->tickets()->count();
        $epicCountBefore = $project->epics()->count();
        $memberCountBefore = $project->members()->count();

        // Perform restore action
        $service->restore($project);

        // Refresh project from database
        $project->refresh();

        // Property: ticket count must remain unchanged
        expect($project->tickets()->count())->toBe($ticketCountBefore,
            'Restore action changed ticket count'
        );

        // Property: epic count must remain unchanged
        expect($project->epics()->count())->toBe($epicCountBefore,
            'Restore action changed epic count'
        );

        // Property: member count must remain unchanged
        expect($project->members()->count())->toBe($memberCountBefore,
            'Restore action changed member count'
        );

        // Clean up for next iteration
        $project->tickets()->delete();
        $project->epics()->delete();
        $project->members()->detach();
        $status->delete();
        $project->forceDelete();
        $archiver->forceDelete();
        if (isset($members)) {
            User::whereIn('id', $members->pluck('id'))->delete();
        }
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 13: Permission enforcement for archive/restore**
 *
 * For any user without the archive_project permission, attempting to archive a project
 * SHALL be denied. For any user without the restore_archived_project permission,
 * attempting to restore a project SHALL be denied.
 *
 * **Validates: Requirements 6.1, 6.2**
 */
test('Property 13: Permission enforcement for archive/restore', function () {
    $policy = new \App\Policies\ProjectPolicy;

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a project
        $project = Project::factory()->create();

        // Create users with different permission configurations
        // User without any permissions
        $userWithoutPermissions = User::factory()->create();

        // User with archive_project permission
        $userWithArchivePermission = User::factory()->create();
        $archivePermission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'archive_project',
            'guard_name' => 'web',
        ]);
        $archiveRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'archive_role_'.$i,
            'guard_name' => 'web',
        ]);
        $archiveRole->givePermissionTo($archivePermission);
        $userWithArchivePermission->assignRole($archiveRole);

        // User with restore_archived_project permission
        $userWithRestorePermission = User::factory()->create();
        $restorePermission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'restore_archived_project',
            'guard_name' => 'web',
        ]);
        $restoreRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'restore_role_'.$i,
            'guard_name' => 'web',
        ]);
        $restoreRole->givePermissionTo($restorePermission);
        $userWithRestorePermission->assignRole($restoreRole);

        // Property: User without archive_project permission cannot archive
        expect($policy->archive($userWithoutPermissions, $project))->toBeFalse(
            'User without archive_project permission should not be able to archive'
        );

        // Property: User with archive_project permission can archive
        expect($policy->archive($userWithArchivePermission, $project))->toBeTrue(
            'User with archive_project permission should be able to archive'
        );

        // Property: User without restore_archived_project permission cannot restore
        expect($policy->restoreArchived($userWithoutPermissions, $project))->toBeFalse(
            'User without restore_archived_project permission should not be able to restore'
        );

        // Property: User with restore_archived_project permission can restore
        expect($policy->restoreArchived($userWithRestorePermission, $project))->toBeTrue(
            'User with restore_archived_project permission should be able to restore'
        );

        // Clean up for next iteration
        $userWithoutPermissions->forceDelete();
        $userWithArchivePermission->forceDelete();
        $userWithRestorePermission->forceDelete();
        $archiveRole->delete();
        $restoreRole->delete();
        $project->forceDelete();
    }
})->group('property-test', 'project-archive');

/**
 * **Feature: project-archive, Property 12: Active project count excludes archived**
 *
 * For any collection of projects, the count of active projects SHALL equal
 * the total count minus the count of archived projects.
 *
 * **Validates: Requirements 5.4**
 */
test('Property 12: Active project count excludes archived', function () {
    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of active and archived projects
        $activeCount = rand(0, 5);
        $archivedCount = rand(0, 5);

        // Create active projects (not archived)
        Project::factory()->count($activeCount)->create([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        // Create archived projects with a single archiver
        if ($archivedCount > 0) {
            $archiver = User::factory()->create();
            Project::factory()->count($archivedCount)->create([
                'archived_at' => now()->subDays(rand(1, 30)),
                'archived_by' => $archiver->id,
            ]);
        }

        // Get total count
        $totalCount = Project::count();

        // Get active count using the scope
        $activeProjectCount = Project::active()->count();

        // Get archived count using the scope
        $archivedProjectCount = Project::archived()->count();

        // Property: Active count should equal total minus archived
        expect($activeProjectCount)->toBe($totalCount - $archivedProjectCount,
            'Active project count should equal total count minus archived count'
        );

        // Property: Active count should match the expected active count
        expect($activeProjectCount)->toBe($activeCount,
            'Active project count should match the number of non-archived projects created'
        );

        // Property: Archived count should match the expected archived count
        expect($archivedProjectCount)->toBe($archivedCount,
            'Archived project count should match the number of archived projects created'
        );

        // Property: Sum of active and archived should equal total
        expect($activeProjectCount + $archivedProjectCount)->toBe($totalCount,
            'Sum of active and archived counts should equal total count'
        );

        // Clean up for next iteration
        Project::query()->delete();
        if (isset($archiver)) {
            $archiver->forceDelete();
            unset($archiver);
        }
    }
})->group('property-test', 'project-archive');
