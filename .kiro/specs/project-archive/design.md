# Design Document: Project Archive Feature

## Overview

This feature extends the Project model and Filament resource to support project archiving and "done" status management. The implementation adds new database columns to track archive and done states, introduces model observers for automatic done detection, and enhances the ProjectResource with tabbed views and action buttons.

## Architecture

The feature follows the existing Laravel/Filament patterns in the codebase:

```
┌─────────────────────────────────────────────────────────────────┐
│                      Filament UI Layer                          │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  Active Tab     │  │  Archived Tab   │  │  Action Buttons │ │
│  │  (Default)      │  │                 │  │  Archive/Done   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Service Layer                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              ProjectArchiveService                       │   │
│  │  - archive(Project, User)                               │   │
│  │  - restore(Project)                                     │   │
│  │  - markAsDone(Project)                                  │   │
│  │  - unmarkDone(Project)                                  │   │
│  │  - checkAutoComplete(Project)                           │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Model Layer                                │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  Project Model  │  │  TicketObserver │  │  ProjectPolicy  │ │
│  │  + is_archived  │  │  (auto-done)    │  │  + archive()    │ │
│  │  + is_done      │  │                 │  │  + restore()    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database Layer                             │
│  projects table:                                                │
│  + archived_at (timestamp, nullable)                            │
│  + archived_by (foreign key to users, nullable)                 │
│  + done_at (timestamp, nullable)                                │
│  + is_auto_done (boolean, default false)                        │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. Database Migration

New columns for the `projects` table:

```php
Schema::table('projects', function (Blueprint $table) {
    $table->timestamp('archived_at')->nullable();
    $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('done_at')->nullable();
    $table->boolean('is_auto_done')->default(false);
});
```

### 2. Project Model Extensions

```php
// New fillable fields
protected $fillable = [
    // ... existing fields
    'archived_at',
    'archived_by',
    'done_at',
    'is_auto_done',
];

// New casts
protected $casts = [
    // ... existing casts
    'archived_at' => 'datetime',
    'done_at' => 'datetime',
    'is_auto_done' => 'boolean',
];

// Accessor for is_archived
public function getIsArchivedAttribute(): bool;

// Accessor for is_done
public function getIsDoneAttribute(): bool;

// Relationship to archiver
public function archivedBy(): BelongsTo;

// Scope for active projects
public function scopeActive(Builder $query): Builder;

// Scope for archived projects
public function scopeArchived(Builder $query): Builder;

// Check if all tickets are completed
public function allTicketsCompleted(): bool;

// Check if project has active epics (epics with incomplete tickets)
public function hasActiveEpics(): bool;
```

### 3. ProjectArchiveService

```php
namespace App\Services;

class ProjectArchiveService
{
    public function archive(Project $project, User $user): void;
    public function restore(Project $project): void;
    public function markAsDone(Project $project, bool $isAuto = false): void;
    public function unmarkDone(Project $project): void;
    public function checkAndUpdateDoneStatus(Project $project): void;
}
```

### 4. Ticket Observer

Listens to ticket status changes to trigger auto-done checks:

```php
namespace App\Observers;

class TicketObserver
{
    public function saved(Ticket $ticket): void;
    public function deleted(Ticket $ticket): void;
}
```

### 5. ProjectPolicy Extensions

```php
public function archive(User $user, Project $project): bool
{
    return $user->can('archive_project');
}

public function restoreArchived(User $user, Project $project): bool
{
    return $user->can('restore_archived_project');
}
```

### 6. ProjectResource Modifications

-   Add tabs for Active/Archived views using Filament's `getTabs()` method
-   Add Archive action button (visible for active projects)
-   Add Restore action button (visible for archived projects)
-   Add "Mark as Done" / "Unmark Done" toggle action
-   Display done status badge in table
-   Filter archived projects from default view

## Data Models

### Project Model (Extended)

| Field        | Type                 | Description                               |
| ------------ | -------------------- | ----------------------------------------- |
| archived_at  | timestamp (nullable) | When the project was archived             |
| archived_by  | foreignId (nullable) | User who archived the project             |
| done_at      | timestamp (nullable) | When the project was marked done          |
| is_auto_done | boolean              | Whether done status was set automatically |

### Computed Attributes

| Attribute   | Type | Logic                  |
| ----------- | ---- | ---------------------- |
| is_archived | bool | `archived_at !== null` |
| is_done     | bool | `done_at !== null`     |

## Correctness Properties

_A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees._

Based on the prework analysis, the following correctness properties have been identified:

### Property 1: Archive sets state and metadata correctly

_For any_ project and any user, when the archive action is performed, the project's `archived_at` field SHALL be set to the current timestamp AND the `archived_by` field SHALL reference the user who performed the action.

**Validates: Requirements 1.1, 1.2**

### Property 2: Active scope excludes archived projects

_For any_ collection of projects containing both archived and non-archived projects, the active scope SHALL return only projects where `archived_at` is null.

**Validates: Requirements 1.3**

### Property 3: Archived scope includes only archived projects

_For any_ collection of projects containing both archived and non-archived projects, the archived scope SHALL return only projects where `archived_at` is not null.

**Validates: Requirements 1.4**

### Property 4: Mark as done sets completion timestamp

_For any_ project, when the markAsDone action is performed, the project's `done_at` field SHALL be set to the current timestamp.

**Validates: Requirements 2.1**

### Property 5: Unmark done clears completion state

_For any_ project that is marked as done, when the unmarkDone action is performed, the project's `done_at` field SHALL be null.

**Validates: Requirements 2.3**

### Property 6: Auto-done triggers when all tickets completed

_For any_ project with at least one ticket, when all tickets have a completed status (via `is_completed` on TicketStatus), the project SHALL be automatically marked as done with `is_auto_done` set to true.

**Validates: Requirements 3.1**

### Property 7: Adding ticket removes auto-done status

_For any_ project that is marked as done with `is_auto_done` = true, when a new non-completed ticket is added, the project's done status SHALL be removed (`done_at` = null).

**Validates: Requirements 3.2**

### Property 8: Reopening ticket removes auto-done status

_For any_ project that is marked as done with `is_auto_done` = true, when an existing ticket's status changes from completed to non-completed, the project's done status SHALL be removed.

**Validates: Requirements 3.3**

### Property 9: Empty projects do not auto-complete

_For any_ project with zero tickets and zero epics, the auto-done check SHALL NOT mark the project as done.

**Validates: Requirements 3.4**

### Property 10: Restore clears archive state

_For any_ archived project, when the restore action is performed, the project's `archived_at` and `archived_by` fields SHALL be null.

**Validates: Requirements 4.1, 4.2**

### Property 11: Restore preserves related data

_For any_ archived project with tickets, epics, and members, when the restore action is performed, the count of tickets, epics, and members SHALL remain unchanged.

**Validates: Requirements 4.3**

### Property 12: Active project count excludes archived

_For any_ collection of projects, the count of active projects SHALL equal the total count minus the count of archived projects.

**Validates: Requirements 5.4**

### Property 13: Permission enforcement for archive/restore

_For any_ user without the `archive_project` permission, attempting to archive a project SHALL be denied. _For any_ user without the `restore_archived_project` permission, attempting to restore a project SHALL be denied.

**Validates: Requirements 6.1, 6.2**

## Error Handling

| Scenario                         | Error Type             | Handling                                           |
| -------------------------------- | ---------------------- | -------------------------------------------------- |
| Archive without permission       | AuthorizationException | Display "Unauthorized" notification                |
| Restore without permission       | AuthorizationException | Display "Unauthorized" notification                |
| Archive already archived project | ValidationException    | Display "Project is already archived" notification |
| Restore non-archived project     | ValidationException    | Display "Project is not archived" notification     |
| Database failure during archive  | QueryException         | Rollback transaction, display error notification   |

## Testing Strategy

### Property-Based Testing Library

Use **Pest PHP** with custom generators for property-based testing. Pest is already configured in the project.

### Unit Tests

-   Test `ProjectArchiveService` methods in isolation
-   Test model accessors (`is_archived`, `is_done`)
-   Test model scopes (`active`, `archived`)
-   Test policy methods (`archive`, `restoreArchived`)

### Property-Based Tests

Each correctness property will be implemented as a property-based test using Pest's data providers to generate random test cases:

-   Generate random projects with varying states
-   Generate random users with varying permissions
-   Generate random ticket configurations (completed/incomplete)
-   Verify properties hold across all generated cases

**Test Configuration:**

-   Minimum 100 iterations per property test
-   Each property test tagged with format: `**Feature: project-archive, Property {number}: {property_text}**`

### Integration Tests

-   Test archive/restore flow through Filament actions
-   Test tab switching and filtering behavior
-   Test auto-done observer triggers

### Test File Structure

```
tests/
├── Unit/
│   └── ProjectArchiveServiceTest.php
│   └── ProjectModelArchiveTest.php
├── Feature/
│   └── ProjectArchiveTest.php
│   └── ProjectArchivePropertyTest.php
```
