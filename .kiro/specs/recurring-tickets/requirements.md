# Requirements Document

## Introduction

This document defines the requirements for the Multi-Department Recurring Tickets feature. This feature enables HR, Finance, and Tax teams to automate ticket generation on the Kanban board, ensuring recurring tasks (like monthly payroll processing) are automatically created with appropriate lead time before their deadlines.

## Glossary

- **Ticket_Template**: A template record that defines a recurring ticket pattern, including frequency, timing, and assignment rules
- **Recurring_Ticket**: A ticket automatically generated from a Ticket_Template based on its schedule
- **Frequency**: The recurrence pattern - either weekly (specific day of week) or monthly (specific day of month)
- **Day_Value**: The target day for the recurring ticket - 1-7 for weekly (Monday-Sunday) or 1-31 for monthly
- **Lead_Time_Days**: Number of days before the target date when the ticket should be created
- **Target_Date**: The calculated due date for the generated ticket based on frequency and day_value
- **Generation_Command**: The Laravel Console Command that runs daily to create tickets from templates
- **Kanban_Board**: The visual project board where tickets are displayed and managed
- **Initial_Status**: The TicketStatus column where newly generated tickets are placed (e.g., "Ready to Start")

## Requirements

### Requirement 1: Ticket Template Management

**User Story:** As an administrator, I want to create and manage ticket templates, so that I can define recurring tasks for different departments.

#### Acceptance Criteria

1. THE Ticket_Template_Resource SHALL provide a form to create new ticket templates with name, description, project, frequency, day_value, lead_time_days, initial_status, and assignees
2. WHEN a user creates a ticket template, THE System SHALL validate that day_value is between 1-7 for weekly frequency or 1-31 for monthly frequency
3. WHEN a user sets lead_time_days, THE System SHALL accept values between 0 and 30 days
4. THE Ticket_Template_Resource SHALL allow editing existing templates
5. THE Ticket_Template_Resource SHALL allow enabling or disabling templates via an is_active flag
6. WHEN displaying templates, THE System SHALL show the next scheduled generation date

### Requirement 2: Ticket Generation Logic

**User Story:** As a system administrator, I want tickets to be automatically generated based on templates, so that teams receive their recurring tasks on time.

#### Acceptance Criteria

1. THE Generation_Command SHALL run daily at 00:01 to check all active templates
2. WHEN (today + lead_time_days) equals the target_date, THE Generation_Command SHALL create a new ticket from the template
3. WHEN generating a ticket, THE System SHALL copy name, description, project_id, and priority_id from the template
4. WHEN generating a ticket, THE System SHALL set the due_date to the calculated target_date
5. WHEN generating a ticket, THE System SHALL assign the ticket to the initial_status specified in the template
6. WHEN generating a ticket, THE System SHALL assign all assignees from the template to the new ticket
7. THE Generation_Command SHALL log each ticket generation with template_id, ticket_id, and timestamp
8. IF a ticket has already been generated for the current period, THEN THE Generation_Command SHALL skip that template to prevent duplicates

### Requirement 3: Weekly Frequency Calculation

**User Story:** As a user, I want to create weekly recurring tickets, so that tasks like weekly reports are automatically generated.

#### Acceptance Criteria

1. WHEN frequency is weekly and day_value is 1, THE System SHALL calculate target_date as the next Monday
2. WHEN frequency is weekly and day_value is 7, THE System SHALL calculate target_date as the next Sunday
3. WHEN calculating weekly target_date, THE System SHALL always use the upcoming occurrence of the specified day
4. WHEN today is the target day and lead_time is 0, THE System SHALL generate the ticket for today

### Requirement 4: Monthly Frequency Calculation

**User Story:** As a user, I want to create monthly recurring tickets, so that tasks like monthly payroll are automatically generated.

#### Acceptance Criteria

1. WHEN frequency is monthly and day_value is 15, THE System SHALL calculate target_date as the 15th of the current or next month
2. WHEN frequency is monthly and day_value is 31 but the month has fewer days, THE System SHALL use the last day of that month
3. WHEN the target day has already passed this month, THE System SHALL calculate target_date for the next month
4. WHEN today is the target day and lead_time is 0, THE System SHALL generate the ticket for today

### Requirement 5: Department and Team Assignment

**User Story:** As a department head, I want recurring tickets to be automatically assigned to my team, so that the right people receive the tasks.

#### Acceptance Criteria

1. THE Ticket_Template SHALL store multiple assignee user IDs
2. WHEN generating a ticket, THE System SHALL copy all assignees from the template to the new ticket
3. THE Ticket_Template_Resource SHALL provide a multi-select field for choosing assignees from project members
4. WHEN a template assignee is removed from the project, THE System SHALL still generate tickets but without that assignee

### Requirement 6: Initial Status Configuration

**User Story:** As an administrator, I want to specify which Kanban column recurring tickets appear in, so that they start in the correct workflow stage.

#### Acceptance Criteria

1. THE Ticket_Template SHALL store an initial_status_id referencing a TicketStatus
2. WHEN creating a template, THE System SHALL only show statuses belonging to the selected project
3. WHEN generating a ticket, THE System SHALL set ticket_status_id to the template's initial_status_id
4. IF the initial_status no longer exists, THEN THE System SHALL use the project's first status by sort_order

### Requirement 7: Generation History and Tracking

**User Story:** As an administrator, I want to track when tickets were generated from templates, so that I can audit the automation.

#### Acceptance Criteria

1. THE System SHALL record each ticket generation in a ticket_template_generations table
2. WHEN a ticket is generated, THE System SHALL store template_id, ticket_id, generated_at, and target_date
3. THE Ticket_Template_Resource SHALL display a history of generated tickets
4. THE System SHALL use generation history to prevent duplicate ticket creation for the same period

### Requirement 8: Access Control

**User Story:** As a system administrator, I want to control who can manage ticket templates, so that only authorized users can configure automation.

#### Acceptance Criteria

1. THE System SHALL integrate with Filament Shield for permission management
2. THE System SHALL define permissions: view_any_ticket_template, view_ticket_template, create_ticket_template, update_ticket_template, delete_ticket_template
3. WHEN a user lacks view_any_ticket_template permission, THE System SHALL hide the Ticket Templates menu item
4. THE System SHALL allow super_admin role full access to ticket template management
