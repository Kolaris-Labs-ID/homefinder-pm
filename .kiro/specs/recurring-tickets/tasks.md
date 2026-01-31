# Implementation Plan: Multi-Department Recurring Tickets

## Overview

This implementation plan breaks down the recurring tickets feature into discrete coding tasks. Each task builds incrementally on previous work, ensuring no orphaned code. The feature uses Laravel 12, Filament 3.2, and Pest PHP for testing.

## Tasks

- [x]   1. Create database migrations and models
    - [x] 1.1 Create migration for ticket_templates table
        - Define columns: id, name, description, project_id, frequency (enum), day_value, lead_time_days, initial_status_id, priority_id, is_active, created_by, timestamps
        - Add foreign key constraints to projects, ticket_statuses, ticket_priorities, users
        - Add index on [is_active, frequency]
        - _Requirements: 1.1, 6.1_

    - [x] 1.2 Create migration for ticket_template_assignees pivot table
        - Define columns: id, ticket_template_id, user_id, timestamps
        - Add foreign key constraints with cascade delete
        - Add unique constraint on [ticket_template_id, user_id]
        - _Requirements: 5.1_

    - [x] 1.3 Create migration for ticket_template_generations table
        - Define columns: id, ticket_template_id, ticket_id, target_date, generated_at, timestamps
        - Add foreign key constraints with cascade delete
        - Add unique constraint on [ticket_template_id, target_date]
        - Add index on target_date
        - _Requirements: 7.1_

    - [x] 1.4 Create TicketFrequency enum
        - Define Weekly and Monthly cases with string values
        - Add label() method for display
        - Add dayValueLabel() method for human-readable day names
        - _Requirements: 1.1_

    - [x] 1.5 Create TicketTemplate model
        - Define fillable fields, casts, and relationships (project, initialStatus, priority, creator, assignees, generations)
        - Add getNextTargetDate(), getNextGenerationDate(), shouldGenerateToday(), hasGeneratedForPeriod() methods
        - _Requirements: 1.1, 1.6, 2.2_

    - [x] 1.6 Create TicketTemplateGeneration model
        - Define fillable fields, casts, and relationships (template, ticket)
        - _Requirements: 7.1, 7.2_

- [x]   2. Implement RecurringTicketService
    - [x] 2.1 Create RecurringTicketService class with date calculation methods
        - Implement calculateNextTargetDate() dispatcher method
        - Implement calculateWeeklyTargetDate() for weekly frequency
        - Implement calculateMonthlyTargetDate() for monthly frequency with day overflow handling
        - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.2, 4.3_

    - [x] 2.2 Write property tests for weekly date calculation
        - **Property 5: Weekly Target Date Calculation**
        - **Validates: Requirements 3.3**

    - [x] 2.3 Write property tests for monthly date calculation
        - **Property 6: Monthly Target Date Calculation**
        - **Validates: Requirements 4.2, 4.3**

    - [x] 2.4 Implement shouldGenerateToday() method
        - Check if today + lead_time_days equals target_date
        - Return boolean indicating if generation should occur
        - _Requirements: 2.2_

    - [x] 2.5 Implement generateTicket() method
        - Create ticket with copied fields (name, description, project_id, priority_id)
        - Set due_date to target_date
        - Set ticket_status_id to initial_status_id (with fallback)
        - Assign valid project member assignees
        - Create TicketTemplateGeneration record
        - _Requirements: 2.3, 2.4, 2.5, 2.6, 5.2, 6.3, 7.2_

    - [x] 2.6 Write property tests for ticket data copying
        - **Property 3: Ticket Data Copying**
        - **Validates: Requirements 2.3, 2.4, 2.5, 2.6**

    - [x] 2.7 Implement processAllTemplates() method
        - Query active templates
        - Check shouldGenerateToday() for each
        - Skip if already generated for period
        - Generate tickets and return summary
        - _Requirements: 2.1, 2.8_

    - [x] 2.8 Write property tests for generation idempotence
        - **Property 4: Generation Idempotence**
        - **Validates: Requirements 2.8, 7.2**

- [x]   3. Checkpoint - Ensure core service tests pass
    - Ensure all tests pass, ask the user if questions arise.

- [x]   4. Implement edge case handling in service
    - [x] 4.1 Implement status fallback logic
        - When initial_status_id references deleted status, use project's first status by sort_order
        - _Requirements: 6.4_

    - [x] 4.2 Write property tests for status fallback
        - **Property 8: Status Fallback**
        - **Validates: Requirements 6.4**

    - [x] 4.3 Implement removed assignee handling
        - Filter assignees to only include current project members
        - Continue generation even if no valid assignees remain
        - _Requirements: 5.4_

    - [x] 4.4 Write property tests for removed assignee handling
        - **Property 7: Removed Assignee Handling**
        - **Validates: Requirements 5.4**

- [x]   5. Create GenerateRecurringTicketsCommand
    - [x] 5.1 Create Artisan command class
        - Define signature: tickets:generate-recurring with --dry-run option
        - Inject RecurringTicketService
        - Implement handle() method calling processAllTemplates()
        - Output progress and summary to console
        - _Requirements: 2.1, 2.7_

    - [x] 5.2 Register command in scheduler
        - Add to routes/console.php
        - Schedule daily at 00:01 Asia/Jakarta timezone
        - _Requirements: 2.1_

    - [x] 5.3 Write feature tests for command execution
        - Test successful generation
        - Test dry-run mode
        - Test with no active templates
        - _Requirements: 2.1, 2.7_

- [x]   6. Checkpoint - Ensure command and service integration works
    - Ensure all tests pass, ask the user if questions arise.

- [x]   7. Create TicketTemplatePolicy
    - [x] 7.1 Create policy class with Filament Shield integration
        - Implement viewAny, view, create, update, delete methods
        - Check permissions via $user->can()
        - _Requirements: 8.1, 8.2, 8.3, 8.4_

    - [x] 7.2 Register policy in AuthServiceProvider
        - Map TicketTemplate model to TicketTemplatePolicy
        - _Requirements: 8.1_

- [x]   8. Create TicketTemplateResource for Filament
    - [x] 8.1 Create resource class with navigation configuration
        - Set model, navigation icon, navigation group
        - Configure navigation visibility based on permissions
        - _Requirements: 1.1, 8.3_

    - [x] 8.2 Implement form schema
        - Add name, description fields
        - Add project select with relationship
        - Add frequency select (weekly/monthly)
        - Add day_value select (dynamic based on frequency)
        - Add lead_time_days number input (0-30)
        - Add initial_status select (filtered by project)
        - Add priority select
        - Add assignees multi-select (filtered by project members)
        - Add is_active toggle
        - _Requirements: 1.1, 1.2, 1.3, 5.3, 6.2_

    - [x] 8.3 Write property tests for template validation
        - **Property 1: Template Validation**
        - **Validates: Requirements 1.2, 1.3**

    - [x] 8.4 Implement table schema
        - Add columns: name, project, frequency, day_value, lead_time_days, next_generation_date, is_active
        - Add filters for project, frequency, is_active
        - Add actions: edit, delete, toggle active
        - _Requirements: 1.4, 1.5, 1.6_

    - [x] 8.5 Create GenerationHistoryRelationManager
        - Display ticket_template_generations for the template
        - Show ticket link, target_date, generated_at
        - _Requirements: 7.3_

    - [x] 8.6 Write feature tests for resource CRUD operations
        - Test create, read, update, delete operations
        - Test permission checks
        - _Requirements: 1.1, 1.4, 8.2_

- [x]   9. Checkpoint - Ensure Filament resource works correctly
    - Ensure all tests pass, ask the user if questions arise.

- [x]   10. Final integration and cleanup
    - [x] 10.1 Run shield:generate to create permissions
        - Generate view_any_ticket_template, view_ticket_template, create_ticket_template, update_ticket_template, delete_ticket_template permissions
        - _Requirements: 8.2_

    - [x] 10.2 Add TicketTemplate to shield config
        - Update config/filament-shield.php if needed
        - _Requirements: 8.1_

    - [x] 10.3 Write integration test for end-to-end flow
        - Create template via resource
        - Run generation command
        - Verify ticket created on Kanban board
        - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6_

- [x]   11. Final checkpoint - All tests pass
    - Ensure all tests pass, ask the user if questions arise.

## Notes

- All tasks are required for comprehensive implementation
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (100+ iterations each)
- Unit tests validate specific examples and edge cases
- The implementation follows existing project patterns (Services, Policies, Filament Resources)
