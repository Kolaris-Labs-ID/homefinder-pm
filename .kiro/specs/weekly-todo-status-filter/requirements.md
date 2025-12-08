# Requirements Document

## Introduction

This feature improves the UX of the Weekly To-Do List Overview widget by replacing the static "Status" column header with an interactive dropdown filter. Currently, users must open the filters panel to filter by status, which is not intuitive. The new design allows users to filter todos by status (Pending, In Progress, Done, or All) directly from the column header, providing a more streamlined and accessible filtering experience.

## Glossary

- **Weekly_Todo_Widget**: The dashboard widget that displays an overview of weekly to-do items for management users
- **Status_Filter_Dropdown**: An interactive dropdown component in the column header that allows filtering by todo status
- **Status_Values**: The three possible states of a todo item: pending, in_progress, done

## Requirements

### Requirement 1

**User Story:** As a manager, I want to filter weekly todos by status directly from the column header, so that I can quickly view items in a specific state without opening the filters panel.

#### Acceptance Criteria

1. WHEN the Weekly_Todo_Widget loads THEN the Status column header SHALL display a dropdown selector showing "Status" as the default label
2. WHEN a user clicks the Status column header dropdown THEN the Weekly_Todo_Widget SHALL display options: "All", "Pending", "In Progress", "Done"
3. WHEN a user selects a status option from the dropdown THEN the Weekly_Todo_Widget SHALL filter the table to show only todos matching that status
4. WHEN a user selects "All" from the dropdown THEN the Weekly_Todo_Widget SHALL display todos of all statuses
5. WHEN a status filter is active THEN the dropdown label SHALL indicate the selected filter value

### Requirement 2

**User Story:** As a manager, I want the status filter to persist during my session, so that I don't lose my filter selection when the widget refreshes.

#### Acceptance Criteria

1. WHEN the widget polls for updates (every 30 seconds) THEN the Weekly_Todo_Widget SHALL maintain the current status filter selection
2. WHEN a user navigates away and returns to the dashboard THEN the Weekly_Todo_Widget SHALL reset to showing all statuses (default state)

### Requirement 3

**User Story:** As a manager, I want clear visual feedback when a status filter is applied, so that I know which filter is currently active.

#### Acceptance Criteria

1. WHEN a specific status filter is selected THEN the dropdown SHALL display the selected status name instead of "Status"
2. WHEN "All" is selected or no filter is applied THEN the dropdown SHALL display "Status" as the label
