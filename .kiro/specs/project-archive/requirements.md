# Requirements Document

## Introduction

This feature adds project archiving capabilities to the Project Management system. Users can archive completed or inactive projects to declutter the main project list while preserving project data. Projects can be marked as "done" manually or automatically when all tickets and epics are completed. Archived projects are displayed in a separate tab or section for easy access and can be restored if needed.

## Glossary

-   **Project**: A container for organizing tickets, epics, and team members with defined start and end dates
-   **Archive**: The action of moving a project to a separate storage area, removing it from the active project list while preserving all data
-   **Done Status**: A project state indicating all work has been completed, either set manually by the user or automatically by the system
-   **Active Project**: A project that is not archived and appears in the main project list
-   **Archived Project**: A project that has been moved to the archive section and is no longer visible in the main project list
-   **Restore**: The action of moving an archived project back to the active project list
-   **Auto-Archive Eligibility**: A project state where all tickets have completed status and no active epics remain

## Requirements

### Requirement 1

**User Story:** As a project manager, I want to archive completed projects, so that I can keep my active project list clean and focused on current work.

#### Acceptance Criteria

1. WHEN a user clicks the archive button on a project THEN the System SHALL move the project to the archived state and remove it from the active project list
2. WHEN a project is archived THEN the System SHALL record the archive timestamp and the user who performed the action
3. WHEN viewing the project list THEN the System SHALL display only active (non-archived) projects by default
4. WHEN a user navigates to the archived tab THEN the System SHALL display all archived projects with their archive date

### Requirement 2

**User Story:** As a project manager, I want to manually mark a project as "done", so that I can indicate project completion regardless of ticket status.

#### Acceptance Criteria

1. WHEN a user clicks the "Mark as Done" action on a project THEN the System SHALL update the project status to done and record the completion timestamp
2. WHEN a project is marked as done THEN the System SHALL display a visual indicator showing the done status in the project list
3. WHEN a user clicks "Unmark Done" on a done project THEN the System SHALL remove the done status and clear the completion timestamp

### Requirement 3

**User Story:** As a project manager, I want projects to be automatically marked as done when all work is completed, so that project status stays accurate without manual intervention.

#### Acceptance Criteria

1. WHEN all tickets in a project have a completed status AND no active epics remain THEN the System SHALL automatically mark the project as done
2. WHEN a new ticket is added to a done project THEN the System SHALL automatically remove the done status
3. WHEN an existing ticket in a done project is reopened THEN the System SHALL automatically remove the done status
4. WHEN a project has zero tickets and zero epics THEN the System SHALL consider the project eligible for done status only if manually marked

### Requirement 4

**User Story:** As a project manager, I want to restore archived projects, so that I can reactivate projects that need additional work.

#### Acceptance Criteria

1. WHEN a user clicks the restore button on an archived project THEN the System SHALL move the project back to the active state
2. WHEN a project is restored THEN the System SHALL clear the archive timestamp and archived_by fields
3. WHEN a project is restored THEN the System SHALL preserve all project data including tickets, epics, and members

### Requirement 5

**User Story:** As a user, I want to view archived projects in a separate section, so that I can easily distinguish between active and archived projects.

#### Acceptance Criteria

1. WHEN viewing the projects page THEN the System SHALL display tabs for "Active" and "Archived" projects
2. WHEN the archived tab is selected THEN the System SHALL display archived projects with columns for name, archive date, and archived by user
3. WHEN searching or filtering projects THEN the System SHALL apply filters only to the currently selected tab (active or archived)
4. WHEN counting projects in navigation THEN the System SHALL display the count of active projects only

### Requirement 6

**User Story:** As a system administrator, I want archive and restore actions to be permission-controlled, so that only authorized users can manage project lifecycle.

#### Acceptance Criteria

1. WHEN a user without archive permission attempts to archive a project THEN the System SHALL deny the action and display an authorization error
2. WHEN a user without restore permission attempts to restore a project THEN the System SHALL deny the action and display an authorization error
3. WHEN displaying archive/restore buttons THEN the System SHALL hide buttons from users without the corresponding permissions
