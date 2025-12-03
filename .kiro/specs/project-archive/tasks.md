# Implementation Plan

-   [x] 1. Create database migration for archive fields

    -   Add `archived_at` (timestamp, nullable) column to projects table
    -   Add `archived_by` (foreign key to users, nullable) column to projects table
    -   Add `done_at` (timestamp, nullable) column to projects table
    -   Add `is_auto_done` (boolean, default false) column to projects table
    -   _Requirements: 1.1, 1.2, 2.1, 3.1_

-   [x] 2. Extend Project model with archive functionality

    -   [x] 2.1 Add new fillable fields and casts for archive columns
        -   Add `archived_at`, `archived_by`, `done_at`, `is_auto_done` to fillable array
        -   Add datetime casts for `archived_at` and `done_at`
        -   Add boolean cast for `is_auto_done`
        -   _Requirements: 1.1, 1.2, 2.1_
    -   [x] 2.2 Add computed accessors and relationships
        -   Add `getIsArchivedAttribute()` accessor
        -   Add `getIsDoneAttribute()` accessor
        -   Add `archivedBy()` BelongsTo relationship
        -   _Requirements: 1.1, 2.1_
    -   [x] 2.3 Add query scopes for filtering
        -   Add `scopeActive()` to filter non-archived projects
        -   Add `scopeArchived()` to filter archived projects
        -   _Requirements: 1.3, 1.4_
    -   [x] 2.4 Add helper methods for completion checking
        -   Add `allTicketsCompleted()` method
        -   Add `hasActiveEpics()` method
        -   _Requirements: 3.1_
    -   [x] 2.5 Write property test for active scope
        -   **Property 2: Active scope excludes archived projects**
        -   **Validates: Requirements 1.3**
    -   [x] 2.6 Write property test for archived scope
        -   **Property 3: Archived scope includes only archived projects**
        -   **Validates: Requirements 1.4**

-   [x] 3. Create ProjectArchiveService

    -   [x] 3.1 Implement archive method
        -   Set `archived_at` to current timestamp
        -   Set `archived_by` to the user performing the action
        -   _Requirements: 1.1, 1.2_
    -   [x] 3.2 Implement restore method
        -   Clear `archived_at` and `archived_by` fields
        -   _Requirements: 4.1, 4.2_
    -   [x] 3.3 Implement markAsDone method
        -   Set `done_at` to current timestamp
        -   Accept `isAuto` parameter to set `is_auto_done`
        -   _Requirements: 2.1, 3.1_
    -   [x] 3.4 Implement unmarkDone method
        -   Clear `done_at` and `is_auto_done` fields
        -   _Requirements: 2.3_
    -   [x] 3.5 Implement checkAndUpdateDoneStatus method
        -   Check if all tickets are completed
        -   Auto-mark as done if eligible
        -   Remove done status if tickets are incomplete
        -   _Requirements: 3.1, 3.2, 3.3, 3.4_
    -   [x] 3.6 Write property test for archive sets state and metadata
        -   **Property 1: Archive sets state and metadata correctly**
        -   **Validates: Requirements 1.1, 1.2**
    -   [x] 3.7 Write property test for mark as done
        -   **Property 4: Mark as done sets completion timestamp**
        -   **Validates: Requirements 2.1**
    -   [x] 3.8 Write property test for unmark done
        -   **Property 5: Unmark done clears completion state**
        -   **Validates: Requirements 2.3**
    -   [x] 3.9 Write property test for restore clears archive state
        -   **Property 10: Restore clears archive state**
        -   **Validates: Requirements 4.1, 4.2**
    -   [x] 3.10 Write property test for restore preserves data
        -   **Property 11: Restore preserves related data**
        -   **Validates: Requirements 4.3**

-   [x] 4. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 5. Create TicketObserver for auto-done detection

    -   [x] 5.1 Create TicketObserver class
        -   Implement `saved()` method to check project done status
        -   Implement `deleted()` method to check project done status
        -   _Requirements: 3.1, 3.2, 3.3_
    -   [x] 5.2 Register observer in AppServiceProvider
        -   Register TicketObserver for Ticket model
        -   _Requirements: 3.1_
    -   [x] 5.3 Write property test for auto-done triggers
        -   **Property 6: Auto-done triggers when all tickets completed**
        -   **Validates: Requirements 3.1**
    -   [x] 5.4 Write property test for adding ticket removes auto-done
        -   **Property 7: Adding ticket removes auto-done status**
        -   **Validates: Requirements 3.2**
    -   [x] 5.5 Write property test for reopening ticket removes auto-done
        -   **Property 8: Reopening ticket removes auto-done status**
        -   **Validates: Requirements 3.3**
    -   [x] 5.6 Write property test for empty projects
        -   **Property 9: Empty projects do not auto-complete**
        -   **Validates: Requirements 3.4**

-   [x] 6. Extend ProjectPolicy with archive permissions

    -   [x] 6.1 Add archive policy method
        -   Check for `archive_project` permission
        -   _Requirements: 6.1_
    -   [x] 6.2 Add restoreArchived policy method
        -   Check for `restore_archived_project` permission
        -   _Requirements: 6.2_
    -   [x] 6.3 Write property test for permission enforcement
        -   **Property 13: Permission enforcement for archive/restore**
        -   **Validates: Requirements 6.1, 6.2**

-   [x] 7. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 8. Update ProjectResource with tabs and actions

    -   [x] 8.1 Add tabs for Active and Archived projects
        -   Implement `getTabs()` method in ListProjects page
        -   Add "Active" tab with `active` scope (default)
        -   Add "Archived" tab with `archived` scope
        -   _Requirements: 5.1, 5.2_
    -   [x] 8.2 Add Archive action button
        -   Create Archive action visible for active projects
        -   Call ProjectArchiveService::archive()
        -   Show success notification
        -   _Requirements: 1.1, 6.3_
    -   [x] 8.3 Add Restore action button
        -   Create Restore action visible for archived projects
        -   Call ProjectArchiveService::restore()
        -   Show success notification
        -   _Requirements: 4.1, 6.3_
    -   [x] 8.4 Add Mark as Done / Unmark Done action
        -   Create toggle action for done status
        -   Call appropriate service method based on current state
        -   _Requirements: 2.1, 2.3_
    -   [x] 8.5 Add done status badge to table
        -   Display "Done" badge when project is done
        -   Show different color for auto-done vs manual done
        -   _Requirements: 2.2_
    -   [x] 8.6 Update table columns for archived tab
        -   Show archive date column
        -   Show archived by user column
        -   _Requirements: 5.2_
    -   [x] 8.7 Write property test for active project count
        -   **Property 12: Active project count excludes archived**
        -   **Validates: Requirements 5.4**

-   [x] 9. Update navigation badge count

    -   Modify getNavigationBadge() to count only active projects
    -   _Requirements: 5.4_

-   [x] 10. Final Checkpoint - Ensure all tests pass
    -   Ensure all tests pass, ask the user if questions arise.
