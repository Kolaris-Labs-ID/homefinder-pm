# Kanban-Based Agile Project Management System
## Tutorial for Super Admin & HR

This comprehensive guide will help you understand and effectively use the Kanban-based Agile project management system as a Super Admin or HR administrator.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [User Management](#user-management)
3. [Project Management](#project-management)
4. [Ticket Status Configuration](#ticket-status-configuration)
5. [Ticket Priority Management](#ticket-priority-management)
6. [Epic Management](#epic-management)
7. [Kanban Board Management](#kanban-board-management)
8. [Daily Todos Management](#daily-todos-management)
9. [Analytics & Reporting](#analytics--reporting)
10. [Export & Data Management](#export--data-management)
11. [Best Practices](#best-practices)

---

## Getting Started

### Accessing the System

1. Log in to the admin panel at `http://your-domain.com/admin`
2. As a Super Admin, you have access to all features and can manage all projects, users, and tickets.

### Understanding Your Dashboard

The dashboard provides an overview of:
- **Total Projects**: All projects in the system
- **Total Tickets**: All tickets across all projects
- **My Assigned Tickets**: Tickets assigned to you personally
- **Team Members**: Total number of registered users

---

## User Management

### Creating New Users

1. Navigate to **Users** in the navigation menu
2. Click **"New User"** button
3. Fill in the required information:
   - Name
   - Email (must be unique)
   - Password
4. Click **"Create"** to save the user

### Assigning Roles

The system has multiple roles:

- **Super Admin**: Full access to all features and data
- **Admin**: Access to most features (cannot delete users)
- **CEO, COO, CTO, HR**: Management roles with broad access
- **Head Division**: Division managers who can oversee their division's work
- **Member**: Limited access - can view and update tickets they're assigned to

**Note**: Role assignments are managed through the Filament Shield plugin. You can modify role permissions if needed.

### Setting Up Divisions and Division Heads

1. Navigate to **Users** in the navigation menu
2. Edit a user you want to designate as a division head
3. Fill in the **Division** field (e.g., "Engineering", "Marketing", "Sales")
4. Enable the **Is Division Head** toggle
5. Click **"Save"**

**Key Points**:
- Division heads can see and manage all daily todos from their division members
- Each division should have a clear division head
- Users can belong to one division at a time

### Managing User Access

- Users can only see projects they are members of (unless they are Super Admin)
- Project membership is managed through the **Project Members** section in each project

---

## Project Management

### Creating a New Project

1. Navigate to **Projects** in the navigation menu
2. Click **"New Project"** button
3. Fill in the project details:
   - **Name**: Project name (e.g., "Website Redesign")
   - **Description**: Detailed project description
   - **Ticket Prefix**: Short code for ticket IDs (e.g., "WEB", "MOB"). This will appear in ticket UUIDs like "WEB-ABC123"
   - **Start Date**: Project start date (optional)
   - **End Date**: Project end date (optional)
4. Click **"Create"** to save

### Adding Team Members to Projects

1. Open a project
2. Go to the **Members** tab
3. Click **"Attach"** to add existing users
4. Select team members from the list
5. Click **"Attach"** to confirm

**Note**: Only project members can view and work on tickets within that project.

### Managing Project Settings

- **Pin Projects**: Pin important projects to show them at the top in timeline views
- **Project Notes**: Add notes or documentation directly in projects

---

## Ticket Status Configuration

### Creating Ticket Statuses

Ticket statuses represent the stages in your workflow (e.g., "To Do", "In Progress", "Review", "Done").

1. Open a project
2. Navigate to the **Ticket Statuses** tab
3. Click **"Create"**
4. Configure the status:
   - **Name**: Status name (e.g., "In Progress")
   - **Color**: Choose a color for visual identification on the Kanban board
   - **Sort Order**: Determines the column order on the board (lower numbers appear first)
   - **Is Completed**: Check this for final/done statuses
5. Click **"Create"** to save

### Best Practices for Status Configuration

- Start with simple workflows: **To Do → In Progress → Review → Done**
- Use color coding consistently (e.g., green for completed, yellow for in-progress, red for blocked)
- Mark completion statuses so they appear with a checkmark on the board
- Consider your team's actual workflow when creating statuses

### Reordering Statuses

Update the **Sort Order** field to change the column sequence on the Kanban board. Lower numbers appear on the left.

---

## Ticket Priority Management

### Creating Priorities

Priorities help teams identify urgent or important work.

1. Navigate to **Ticket Priorities** in the main menu
2. Click **"New Priority"**
3. Configure:
   - **Name**: Priority level (e.g., "High", "Medium", "Low")
   - **Color**: Color for visual identification
4. Click **"Create"**

### Priority Best Practices

- Keep priority levels simple (3-5 levels usually work best)
- Use consistent naming (e.g., "Critical", "High", "Medium", "Low")
- Color code by urgency (red for critical, orange for high, etc.)

---

## Epic Management

### What Are Epics?

Epics are large initiatives that group related tickets together. They help organize work by feature, release, or business objective.

### Creating an Epic

1. Open a project
2. Navigate to the **Epics** tab
3. Click **"Create"**
4. Fill in:
   - **Name**: Epic name (e.g., "User Authentication System")
   - **Description**: Detailed description of the epic
   - **Start Date**: When work on this epic begins (optional)
   - **End Date**: Target completion date (optional)
5. Click **"Create"**

### Assigning Tickets to Epics

- When creating or editing a ticket, select an epic from the dropdown
- Multiple tickets can belong to one epic
- View all tickets in an epic via the **Epics Overview** page

### Viewing Epics

Navigate to **Epics Overview** in the Project Management menu to see:
- All epics in a project
- Tickets grouped by epic
- Epic descriptions and date ranges

---

## Kanban Board Management

### Accessing the Kanban Board

1. Navigate to **Project Board** in the Project Management menu
2. Select a project from the dropdown
3. The Kanban board displays tickets organized by status columns

### Board Features

#### Drag and Drop

- **Super Admins** can move any ticket between status columns
- Drag tickets from one column to another to update their status
- Works on both desktop (mouse) and mobile (touch with long-press)

#### Column Sorting

Each column has a sort menu (three dots icon):
- **Date created (newest first)**: Most recently created tickets on top
- **Date created (oldest first)**: Oldest tickets first
- **Card name (alphabetically)**: Sort by ticket name
- **Due date**: Sort by due date (overdue first)
- **Priority**: Sort by priority level

#### Filtering by User

1. Click **"Filter by User"** in the header actions
2. Select one or more users
3. The board will only show tickets assigned to selected users
4. Clear the filter to see all tickets again

### Creating Tickets from the Board

1. Click **"New Ticket"** button (top right)
2. Fill in ticket details (see ticket creation section below)
3. Ticket appears on the board immediately

### Viewing Ticket Details

- Click the **eye icon** on any ticket card to view full details
- Opens in a new tab for easy reference

---

## Daily Todos Management

### Overview

The Daily Todos feature allows employees to track their daily tasks and to-dos. As a Super Admin, Admin, CEO, COO, CTO, or HR, you have full visibility into all employees' daily todos across all divisions.

### Accessing Daily Todos

1. Navigate to **Daily Todos** in the navigation menu
2. You'll see a comprehensive list of all daily todos across the organization
3. Use filters to narrow down the view

### Viewing Employee Daily Todos

**Full Management Access**:
- Super Admin, Admin, CEO, COO, CTO, and HR can see **all** daily todos from all employees
- No restrictions based on division

**Head Division Access**:
- Head Division users can only see todos from employees in their division
- They cannot see todos from other divisions

**Regular Employees**:
- Can only see and manage their own daily todos

### Creating Daily Todos for Employees

**To Create a Todo for an Employee**:

1. Navigate to **Daily Todos**
2. Click **"New To-Do"** button
3. Fill in the form:
   - **User**: Select the employee (required)
   - **Date**: Select the date for the todo
   - **Title**: Clear, descriptive title (required)
   - **Description**: Detailed description (optional)
   - **Status**: Initial status (Pending, In Progress, or Done)
4. Click **"Create"**

**Use Cases**:
- Assigning daily tasks to team members
- Creating follow-up reminders
- Setting daily goals for employees

### Managing Daily Todos

**Viewing and Editing**:

1. Navigate to **Daily Todos**
2. Browse the list or use filters:
   - **Filter by User**: See todos for specific employees
   - **Filter by Division**: See todos from a specific division
   - **Filter by Date**: Focus on specific dates
   - **Today's Todos**: Quick filter for today
   - **Filter by Status**: Pending, In Progress, or Done
3. Click on any todo to view details
4. Click **"Edit"** to modify:
   - Title and description
   - Status
   - Date
   - Assign to different user (if needed)

### Deleting Daily Todos

1. Open a daily todo
2. Click **"Delete"** button
3. Confirm deletion

**Note**: Full management roles can delete any todo. Head Division can only delete todos from their division members.

### Daily Todos Table Features

The Daily Todos table shows:
- **User**: Employee name and division
- **Date**: Todo date
- **Title**: Todo title
- **Description**: Brief description preview
- **Status**: Current status with color coding
  - **Gray**: Pending
  - **Yellow**: In Progress
  - **Green**: Done

### Dashboard Widgets for Daily Todos

**Daily Todo Overview Widget**:
- Displays a table of todos visible to your role
- Shows user, division, date, title, and status
- Includes filtering capabilities
- Updates in real-time

**Use It To**:
- Monitor team productivity
- Track daily task completion
- Identify employees who need support
- Review division workload distribution

### Division-Based Access Control

**Understanding Access Levels**:

1. **Full Management Roles** (Super Admin, Admin, CEO, COO, CTO, HR):
   - Can view, create, update, and delete todos for all employees
   - No division restrictions

2. **Head Division**:
   - Can view, create, update, and delete todos for employees in their division only
   - Cannot see todos from other divisions
   - Can see their own todos plus division members' todos

3. **Regular Employees**:
   - Can only view, create, update, and delete their own todos
   - Cannot see other employees' todos

### Best Practices for Daily Todos Management

**Setting Up Divisions**:
- Organize employees into clear divisions (Engineering, Sales, Marketing, etc.)
- Assign a responsible division head for each division
- Ensure division names are consistent across the system

**Monitoring Employee Todos**:
- Review todos regularly to understand team workload
- Use division filters to focus on specific teams
- Check status filters to see completion rates

**Supporting Employees**:
- Help employees who have too many pending todos
- Recognize employees with consistent completion
- Use todos to identify training needs

**Integration with Project Management**:
- Encourage employees to link daily todos to project tickets
- Use todos for daily standup preparation
- Track daily progress alongside project milestones

---

## Ticket Management

### Creating Tickets

1. Navigate to **Tickets** or click **"New Ticket"** from the board
2. Fill in the ticket form:
   - **Project**: Select the project
   - **Name**: Clear, descriptive ticket title
   - **Description**: Detailed description (supports rich text/HTML)
   - **Status**: Initial status (usually "To Do")
   - **Priority**: Set priority level
   - **Epic**: Optionally assign to an epic
   - **Assignees**: Select one or more team members (must be project members)
   - **Start Date**: When work should begin (optional)
   - **Due Date**: Target completion date (optional)
3. Click **"Create"**

**Note**: If you assign users who aren't project members, they'll be automatically removed and you'll receive a warning.

### Editing Tickets

1. Find the ticket (via Tickets list or Kanban board)
2. Click **"Edit"** or open ticket details
3. Update any fields
4. Click **"Save"**

### Managing Ticket Assignments

- **Multi-user assignment**: Tickets can be assigned to multiple team members
- When creating a ticket, select multiple users from the assignees dropdown
- Assignees receive notifications and can view the ticket in their dashboard

### Ticket Comments

1. Open ticket details
2. Scroll to **Comments** section
3. Type your comment
4. Click **"Post Comment"**
5. Comments are visible to all project members who can view the ticket

### Ticket History

Every status change is automatically recorded in the ticket history:
- View the **History** tab in ticket details
- See who changed the status and when
- Track all updates over time

---

## Analytics & Reporting

### Dashboard Widgets

The dashboard includes several widgets:

- **Stats Overview**: Key metrics at a glance
- **Monthly Ticket Creation Trend**: Chart showing ticket creation over time
- **Project Timeline**: Gantt-style view of projects and their timelines
- **Tickets Per Project**: Distribution of tickets across projects
- **User Statistics**: Individual user activity charts
- **Recent Activity**: Latest actions in the system

### User Contributions Page

1. Navigate to **User Contributions** in Analytics menu
2. View contribution charts for all users or select a specific user
3. See daily activity, ticket assignments, and completion trends
4. Filter by time range (1 month, 3 months, 6 months, 1 year)

**Super Admin**: Can view all users' contributions
**Regular Users**: Can only view their own contributions

### Leaderboard

1. Navigate to **Leaderboard** in the Project Management menu
2. Select a project
3. View team member rankings based on:
   - Tickets completed
   - Tickets created
   - Activity levels

### Timeline Views

#### Project Timeline

1. Navigate to **Project Timeline** in Project Management menu
2. View Gantt-style timeline of all projects
3. See project progress, durations, and deadlines
4. Filter by:
   - **Pinned Projects**: Shows only pinned projects
   - **All Projects**: Shows all projects

#### Ticket Timeline

1. Navigate to **Ticket Timeline** in Project Management menu
2. Select a project
3. View tickets on a timeline based on start and due dates
4. Identify scheduling conflicts and bottlenecks

---

## Export & Data Management

### Exporting Tickets to Excel

1. Navigate to **Project Board**
2. Select a project
3. Click **"Export Tickets"** button (Super Admin only)
4. Select columns to include in export:
   - UUID
   - Name
   - Description
   - Status
   - Priority
   - Assignees
   - Due Date
   - Created Date
   - Epic
5. Click **"Export"**
6. Excel file downloads automatically

**Use Cases**:
- Project reporting
- Client presentations
- Backup and archival
- Integration with other tools

### Importing Tickets

1. Navigate to **Tickets** resource
2. Click **"Import Tickets"** action
3. Download the template (optional)
4. Fill in the CSV/Excel file following the template
5. Upload the file
6. Review and confirm import

**Note**: Ensure project members and statuses exist before importing.

---

## Best Practices

### Project Setup Workflow

1. **Create the Project**: Set up basic project information
2. **Add Team Members**: Invite relevant team members
3. **Configure Statuses**: Set up workflow statuses (To Do, In Progress, Done, etc.)
4. **Create Priorities**: Define priority levels
5. **Create Epics** (optional): Organize work into larger initiatives
6. **Start Creating Tickets**: Begin adding work items

### Kanban Workflow Management

- **Limit Work in Progress (WIP)**: Encourage teams to complete items before starting new ones
- **Regular Board Reviews**: Review the board in team meetings
- **Clear Status Definitions**: Ensure everyone understands what each status means
- **Use Colors Effectively**: Color-code statuses for quick visual recognition

### Communication Best Practices

- **Use Comments**: Encourage team communication within ticket comments
- **Set Clear Due Dates**: Help team members prioritize work
- **Update Status Regularly**: Keep the board current by moving tickets through statuses
- **Use Epics for Planning**: Group related tickets into epics for better organization

### Team Onboarding

1. Create user accounts
2. Assign appropriate roles
3. Add users to relevant projects
4. Provide training on:
   - How to view the Kanban board
   - How to create and update tickets
   - How to use comments
   - How to filter and sort tickets

### Reporting Guidelines

- Use **User Contributions** to track individual performance
- Use **Leaderboard** for team motivation and recognition
- Export tickets regularly for backup and reporting
- Review timeline views to identify project health

### Maintenance Tasks

- **Regular Reviews**: Review and archive completed projects
- **Clean Up**: Remove unused statuses or priorities
- **User Management**: Deactivate users who no longer need access
- **Epic Management**: Close completed epics

---

## Troubleshooting

### Users Can't See Projects

- Ensure users are added as project members
- Check user role permissions
- Verify the project hasn't been deleted

### Tickets Not Moving on Board

- Check user has `update_ticket` permission
- Verify user is assigned to the ticket or is the ticket creator
- Super Admin can always move tickets

### Export Not Working

- Ensure you're logged in as Super Admin
- Check that project has tickets to export
- Verify file permissions for storage directory

### Status Columns Not Appearing

- Ensure ticket statuses are created for the project
- Check sort order settings
- Verify project is selected correctly

---

## Additional Resources

- **Filament Documentation**: For general Filament admin panel questions
- **System Documentation**: Check README.md for technical details
- **Support**: Contact your system administrator for technical support

---

## Quick Reference

### Navigation Menu Structure

```
Dashboard
├── Stats Overview
└── Various Widgets

Project Management
├── Projects
├── Project Board (Kanban)
├── Epics Overview
├── Leaderboard
├── Project Timeline
└── Ticket Timeline

Analytics
├── User Contributions
└── Various Charts

Resources
├── Tickets
├── Ticket Priorities
├── Daily Todos
├── Users
└── Notifications
```

### Keyboard Shortcuts

- Use browser search (Ctrl+F / Cmd+F) to quickly find tickets or projects
- Use tab navigation in forms for quick data entry

### Status Workflow Example

```
1. To Do (Yellow) - New tickets
2. In Progress (Blue) - Active work
3. Review (Purple) - Under review
4. Done (Green) - Completed ✓
```

---

**Last Updated**: [Current Date]
**Version**: 1.1

### Changelog

**Version 1.1**:
- Added Daily Todos Management section
- Added Division and Division Head setup instructions
- Updated navigation menu structure

**Version 1.0**:
- Initial tutorial release

