# Kanban-Based Agile Project Management System
## Tutorial for Team Members & Users

This guide will help you effectively use the Kanban-based Agile project management system as a team member or regular user.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding the Dashboard](#understanding-the-dashboard)
3. [Working with Projects](#working-with-projects)
4. [Using the Kanban Board](#using-the-kanban-board)
5. [Creating and Managing Tickets](#creating-and-managing-tickets)
6. [Ticket Assignment and Updates](#ticket-assignment-and-updates)
7. [Epics Overview](#epics-overview)
8. [Daily Todos Management](#daily-todos-management)
9. [Analytics and Tracking](#analytics-and-tracking)
10. [Timeline Views](#timeline-views)
11. [Best Practices](#best-practices)

---

## Getting Started

### Accessing the System

1. Log in to the admin panel at `http://your-domain.com/admin`
2. You'll see the dashboard with an overview of your work
3. Navigate using the left sidebar menu

### Understanding Your Permissions

As a regular user, you can:
- View projects you're a member of
- Create tickets in those projects
- Update tickets assigned to you or created by you
- Move tickets on the Kanban board (if you have permission)
- Add comments to tickets
- View your own contributions and statistics

**Note**: You can only see and work on projects where you've been added as a team member.

---

## Understanding the Dashboard

### Your Dashboard Overview

When you log in, you'll see several key metrics:

- **My Assigned Tickets**: Number of tickets currently assigned to you
- **Projects I'm In**: Number of projects where you're a team member
- **My Created Tickets**: Tickets you've created
- **Recent Activity**: Latest updates on tickets you can access

### Dashboard Widgets

The dashboard may include:
- Charts showing ticket trends
- Recent activity feed
- Quick access to your assigned tickets

---

## Working with Projects

### Viewing Your Projects

1. Navigate to **Projects** in the navigation menu
2. You'll see a list of all projects where you're a team member
3. Click on a project to view details

### Project Information

Each project shows:
- Project name and description
- Start and end dates
- Team members
- Project notes (if any)

### Understanding Project Membership

- You can only see projects you've been added to by a Super Admin or Admin
- If you don't see a project you should have access to, contact your project manager or HR
- Project members are managed by administrators

---

## Using the Kanban Board

### Accessing the Kanban Board

1. Navigate to **Project Board** in the "Project Management" menu
2. Select a project from the dropdown at the top
3. The Kanban board displays with status columns (e.g., "To Do", "In Progress", "Done")

### Understanding the Board Layout

The board is organized into **columns** representing ticket statuses:
- Each column shows tickets in that status
- Tickets are displayed as **cards** with key information
- Columns are ordered from left to right showing workflow progression

### Ticket Cards Information

Each ticket card shows:
- **Ticket ID** (UUID): Unique identifier (e.g., "PROJ-ABC123")
- **Ticket Name**: The title of the ticket
- **Priority Badge**: Color-coded priority level
- **Due Date**: Deadline (if set)
- **Assignees**: Who's working on the ticket
- **View Button**: Click the eye icon to see full details

### View-Only vs. Interactive Mode

**View-Only Mode**: 
- If you don't have permission to move tickets, you'll see a "View Only Mode" banner
- You can view all tickets but cannot drag and drop them
- Contact your administrator if you need move permissions

**Interactive Mode**:
- You can move tickets between columns (if assigned to you or you created them)
- Drag tickets with your mouse (desktop) or long-press and move (mobile)

### Moving Tickets on the Board

**Desktop (Mouse)**:
1. Click and hold on a ticket card
2. Drag it to a different status column
3. Release to drop
4. The ticket status updates automatically

**Mobile (Touch)**:
1. Long-press (hold for ~500ms) on a ticket card
2. Drag it to a different column
3. Release to drop
4. Status updates automatically

**Note**: You can only move tickets that are:
- Assigned to you, OR
- Created by you

If you can't move a ticket, you may not have the necessary permissions.

### Sorting Tickets in Columns

Each column header has a menu (three dots icon):
1. Click the menu icon
2. Choose a sort option:
   - **Date created (newest first)**: Most recent tickets on top
   - **Date created (oldest first)**: Oldest tickets first
   - **Card name (alphabetically)**: Alphabetical order
   - **Due date**: Overdue tickets appear first
   - **Priority**: Higher priority first

### Filtering the Board

**Filter by User**:
1. Click **"Filter by User"** button (top right)
2. Select one or more team members
3. The board shows only tickets assigned to those users
4. Clear selections to show all tickets again

**Use Cases**:
- See only your assigned tickets
- Check what teammates are working on
- Focus on specific team members' workload

### Refreshing the Board

- Click **"Refresh Board"** button to reload ticket data
- Useful if you notice outdated information
- Other users' changes will appear after refresh

---

## Creating and Managing Tickets

### Creating a New Ticket

**From the Board**:
1. Select a project on the Kanban board
2. Click **"New Ticket"** button (top right)
3. Fill in the ticket form (opens in new tab)

**From Tickets Menu**:
1. Navigate to **Tickets**
2. Click **"New Ticket"**
3. Fill in the form

### Ticket Form Fields

**Required Fields**:
- **Project**: Select the project (you'll only see projects you're a member of)
- **Name**: Clear, descriptive ticket title
- **Status**: Initial status (usually set automatically)

**Optional but Recommended**:
- **Description**: Detailed description of the work
  - Supports rich text formatting
  - Include acceptance criteria
  - Add relevant links or references
- **Priority**: Set urgency level (High, Medium, Low)
- **Epic**: Group related tickets together
- **Assignees**: Assign to yourself or teammates
  - You can assign multiple people
  - Only project members can be assigned
- **Start Date**: When work should begin
- **Due Date**: Target completion date

### Ticket Naming Best Practices

**Good Examples**:
- "Implement user login functionality"
- "Fix mobile menu display issue"
- "Create API endpoint for user profiles"

**Avoid**:
- "Bug fix"
- "Task 1"
- "Update"

### Editing Your Tickets

**You can edit tickets if**:
- You created the ticket, OR
- You're assigned to the ticket

**To Edit**:
1. Find the ticket (on board or in tickets list)
2. Click the ticket or use the **View** button
3. Click **"Edit"**
4. Update fields as needed
5. Click **"Save"**

**Fields You Can Update**:
- Name and description
- Status
- Priority
- Epic assignment
- Assignees
- Due dates
- Start dates

### Understanding Ticket Statuses

Statuses represent workflow stages. Common statuses:
- **To Do**: New ticket, not started
- **In Progress**: Actively being worked on
- **Review**: Completed, awaiting review/approval
- **Done**: Completed and verified ✓

Move tickets through statuses as work progresses.

---

## Ticket Assignment and Updates

### Understanding Assignments

- **Multi-user assignment**: Tickets can have multiple assignees
- You'll see all assignees on the ticket card
- All assignees can view and update the ticket

### When You're Assigned

- The ticket appears in your dashboard statistics
- You can see it in "My Assigned Tickets"
- You receive notifications (if enabled)
- You can move the ticket on the board

### Updating Ticket Status

**On the Kanban Board**:
- Drag the ticket to a new column
- Status updates automatically

**In Ticket Details**:
1. Open ticket details
2. Click **Edit**
3. Change the status dropdown
4. Click **Save**

### Adding Comments

Comments are great for:
- Asking questions
- Providing updates
- Sharing progress
- Collaborative discussions

**To Add a Comment**:
1. Open ticket details (click ticket card or use view button)
2. Scroll to **Comments** section
3. Type your comment in the text box
4. Click **"Post Comment"**
5. Your comment appears with timestamp and your name

**Best Practices**:
- Be clear and concise
- Use comments for updates rather than status changes
- Tag teammates if needed (mention by name)
- Respond to questions promptly

### Ticket History

- Every status change is automatically recorded
- View the **History** tab in ticket details
- See who changed what and when
- Track complete ticket lifecycle

---

## Epics Overview

### What Are Epics?

Epics are large initiatives that group related tickets together. They help organize work by feature or objective.

### Viewing Epics

1. Navigate to **Epics Overview** in Project Management menu
2. Select a project from the dropdown
3. See all epics for that project

### Epic Information

Each epic shows:
- **Epic Name**: The initiative name
- **Date Range**: Start and end dates
- **Ticket Count**: How many tickets belong to this epic
- **Tickets List**: All tickets in the epic (when expanded)

### Using Epics

1. **Plan Work**: Group related tickets into epics
2. **Track Progress**: See how many tickets are completed per epic
3. **Organize Features**: Use epics to represent features or releases
4. **View Context**: Understand how your ticket fits into larger goals

### Expanding/Collapsing Epics

- Click on an epic row to expand and see its tickets
- Click again to collapse
- View ticket details by clicking "View" link on any ticket

---

## Daily Todos Management

### Overview

Daily Todos help you track your personal daily tasks and to-dos. As a regular employee, you can create, view, update, and delete your own daily todos. You cannot see other employees' todos.

**Note**: If you're a Head Division, you can see todos from employees in your division. Management roles (Super Admin, Admin, CEO, COO, CTO, HR) can see all employees' todos.

### Accessing Daily Todos

1. Navigate to **Daily Todos** in the navigation menu (if visible)
2. You'll see a list of your personal daily todos
3. Use filters to organize your view

### Creating a Daily Todo

**From Daily Todos Menu**:
1. Navigate to **Daily Todos**
2. Click **"New To-Do"** button (top right, or use keyboard shortcut Ctrl/Cmd + C)
3. Fill in the form:
   - **Date**: Select the date for this todo (defaults to today)
   - **Title**: Clear, descriptive title (required)
     - Example: "Review pull request #123"
     - Example: "Complete user authentication ticket"
   - **Description**: Detailed description (optional)
     - Add context, links, or notes
   - **Status**: Initial status
     - **Pending**: Not started yet
     - **In Progress**: Currently working on it
     - **Done**: Completed
4. Click **"Create"**

**From Dashboard Widget** (if available):
- Some dashboards include a Daily Todo widget
- Click **"New To-Do"** directly from the widget
- Fill in the quick form

### Understanding Todo Statuses

**Pending** (Gray):
- Todo is created but not started
- Default status for new todos
- Use for planning future work

**In Progress** (Yellow):
- You've started working on this todo
- Update status when you begin working
- Shows active work

**Done** (Green):
- Todo is completed
- Mark when you finish the task
- Helps track completion rates

### Managing Your Daily Todos

**Viewing Your Todos**:

The Daily Todos list shows:
- **Date**: When the todo is scheduled
- **Title**: Todo title
- **Description**: Brief description preview
- **Status**: Current status with color coding

**Filters Available**:
- **Date Filter**: Focus on specific dates
- **Today's Todos**: Quick filter to see only today's tasks
- **Status Filter**: Filter by Pending, In Progress, or Done
- **Date Range**: View todos for a specific period

**Editing a Todo**:

1. Navigate to **Daily Todos**
2. Find the todo you want to edit
3. Click on it or use the **Edit** action
4. Update any fields:
   - Title
   - Description
   - Date
   - Status
5. Click **"Save"**

**Changing Status**:

**Quick Status Updates**:
- Edit the todo and change the status dropdown
- Or update status as you work:
  - Start working → Change to "In Progress"
  - Complete task → Change to "Done"

### Deleting Your Todos

1. Open the todo you want to delete
2. Click **"Delete"** button
3. Confirm deletion

**Note**: You can only delete your own todos. Management roles can delete any employee's todos.

### Daily Todo Workflow

**Morning Routine**:
1. Review your daily todos
2. Update statuses from "Pending" to "In Progress" as you start work
3. Create new todos for the day if needed
4. Plan your day based on todo priorities

**During Work**:
1. Keep statuses updated
2. Change to "Done" when tasks are completed
3. Add new todos as they come up
4. Update descriptions if details change

**End of Day**:
1. Review all your todos
2. Mark completed items as "Done"
3. Update incomplete items appropriately
4. Create todos for tomorrow if needed

### Daily Todos Dashboard Widget

If available, the dashboard widget shows:
- Your recent daily todos
- Quick status overview
- Quick-add form for new todos
- Filter options

**Use It To**:
- Start your day with a clear task list
- Quickly add todos as they come up
- Monitor your completion progress
- Stay organized throughout the day

### Best Practices for Daily Todos

**Creating Effective Todos**:
- ✅ Use clear, actionable titles
- ✅ Include relevant details in descriptions
- ✅ Set realistic dates
- ✅ Link todos to project tickets when relevant

**Examples of Good Todo Titles**:
- ✅ "Review PR #123 for authentication feature"
- ✅ "Update API documentation for user endpoint"
- ✅ "Follow up with client about requirements"
- ✅ "Test login functionality on mobile devices"

**Avoid Vague Titles**:
- ❌ "Work on project"
- ❌ "Do stuff"
- ❌ "Task"

**Status Management**:
- Keep statuses current (don't leave todos in "Pending" for days)
- Update to "In Progress" when you start
- Mark as "Done" when complete (don't forget!)
- Delete old completed todos if they're no longer relevant

**Daily Planning**:
1. **Start of Day**: Review todos, plan priorities
2. **Throughout Day**: Update statuses as work progresses
3. **End of Day**: Review, mark completed, plan tomorrow

**Integration with Project Work**:
- Create todos linked to project tickets
- Use todos for daily standup preparation
- Track daily progress alongside project milestones
- Reference project ticket IDs in todo descriptions

---

## Analytics and Tracking

### Your Personal Statistics

Navigate to **User Contributions** in Analytics menu to see:
- **Daily Activity Chart**: Your activity over time
- **Ticket Assignments**: Tickets assigned to you
- **Ticket Completions**: Tickets you've completed
- **Time Range**: Filter by 1 month, 3 months, 6 months, or 1 year

**Note**: You can only view your own contributions, not other team members' data.

### Leaderboard

1. Navigate to **Leaderboard** in Project Management menu
2. Select a project
3. See team rankings based on:
   - Tickets completed
   - Tickets created
   - Overall activity

**Use It To**:
- See team performance
- Get motivated by healthy competition
- Identify top contributors

---

## Timeline Views

### Project Timeline

1. Navigate to **Project Timeline** in Project Management menu
2. View a Gantt-style timeline showing:
   - Project start and end dates
   - Project progress bars
   - Visual representation of project duration

**Use It To**:
- Understand project schedules
- See multiple projects at once
- Identify project overlaps

### Ticket Timeline

1. Navigate to **Ticket Timeline** in Project Management menu
2. Select a project
3. View tickets plotted on a timeline based on:
   - Start dates
   - Due dates
   - Status progression

**Use It To**:
- See ticket schedules
- Identify conflicts or bottlenecks
- Plan work based on due dates
- Visualize ticket durations

---

## Best Practices

### Daily Workflow

1. **Start Your Day**:
   - Check dashboard for assigned tickets
   - Review your daily todos and plan priorities
   - Review any new comments or updates
   - Plan your day based on priorities

2. **During Work**:
   - Move tickets to "In Progress" when you start
   - Update daily todo statuses as you work
   - Update ticket status as work progresses
   - Add comments for important updates
   - Update due dates if timelines change

3. **End of Day**:
   - Mark completed daily todos as "Done"
   - Update ticket statuses to reflect current state
   - Add comments summarizing progress
   - Move completed tickets to "Done" status
   - Create todos for tomorrow if needed

### Ticket Management Best Practices

**Creating Tickets**:
- Use clear, descriptive names
- Include detailed descriptions
- Set appropriate priorities
- Assign yourself if you'll work on it
- Set realistic due dates

**Updating Tickets**:
- Keep status current (don't leave tickets in "In Progress" for weeks)
- Update regularly with comments
- Move tickets when status changes
- Don't forget to move to "Done" when complete

**Collaboration**:
- Use comments for questions and updates
- Tag teammates in comments when needed
- Respond to comments promptly
- Keep discussions professional and clear

### Kanban Board Best Practices

**Managing Your Work**:
- **Limit WIP**: Don't have too many "In Progress" tickets
- **Complete Before Starting**: Finish current tickets before moving to new ones
- **Use Priorities**: Focus on high-priority tickets first
- **Keep Board Updated**: Move tickets as work progresses

**Visual Organization**:
- Use priority colors to identify urgent work
- Sort columns by due date to see deadlines
- Filter by your name to see only your tickets
- Review the board regularly

### Communication Guidelines

**Comments**:
- Be clear and concise
- Include relevant details
- Ask questions if unclear
- Update teammates on progress
- Use professional language

**Status Updates**:
- Move tickets through statuses promptly
- Don't leave tickets stagnant
- Use "Review" status when work is complete but needs verification
- Mark as "Done" only when truly complete

### Productivity Tips

1. **Filter Your View**: Use user filter to see only your tickets
2. **Sort by Due Date**: Prioritize work with approaching deadlines
3. **Use Epics**: Understand how your work fits into larger goals
4. **Check Timeline**: Plan ahead using timeline views
5. **Regular Updates**: Keep tickets current to help team coordination

### Common Mistakes to Avoid

❌ **Leaving tickets in "In Progress" forever**
- ✅ Move tickets to appropriate statuses as work progresses

❌ **Not updating due dates**
- ✅ Update due dates when timelines change

❌ **Forgetting to add comments**
- ✅ Communicate progress through comments

❌ **Creating vague ticket names**
- ✅ Use clear, descriptive names

❌ **Not assigning tickets**
- ✅ Assign tickets to yourself or teammates

❌ **Ignoring notifications**
- ✅ Check and respond to ticket updates

---

## Troubleshooting

### I Can't See a Project

- **Cause**: You're not a member of that project
- **Solution**: Contact your project manager or HR to be added as a member

### I Can't Move Tickets on the Board

- **Cause**: You don't have permission or the ticket isn't assigned to you
- **Solution**: 
  - Verify the ticket is assigned to you or you created it
  - Contact your administrator if you need move permissions
  - Note: You'll see "View Only Mode" banner if you don't have permission

### I Can't Edit a Ticket

- **Cause**: You're not the creator or assignee
- **Solution**: Ask to be assigned to the ticket, or contact the ticket creator

### Tickets Don't Appear

- **Cause**: Filters may be applied
- **Solution**: 
  - Clear user filters on the board
  - Check that you're looking at the correct project
  - Refresh the board

### I Don't See Expected Statuses

- **Cause**: Statuses are configured per project
- **Solution**: Contact your project administrator to add needed statuses

---

## Quick Reference

### Navigation Menu (User View)

```
Dashboard
├── Your Statistics
└── Recent Activity

Project Management
├── Projects (your projects only)
├── Project Board (Kanban)
├── Epics Overview
├── Leaderboard
├── Project Timeline
└── Ticket Timeline

Analytics
└── User Contributions (your data only)

Daily Todos
└── Your personal daily todos

Tickets
└── All accessible tickets
```

### Common Actions

| Action | How To |
|--------|--------|
| View Board | Project Board → Select Project |
| Create Ticket | Board: "New Ticket" button OR Tickets → "New Ticket" |
| Update Status | Drag ticket to new column OR Edit ticket |
| Add Comment | Open ticket → Comments section |
| Filter Board | Click "Filter by User" → Select users |
| Sort Column | Click three dots menu on column header |
| View Epic | Epics Overview → Select project → Expand epic |
| Create Daily Todo | Daily Todos → "New To-Do" OR use widget |
| Update Todo Status | Edit todo → Change status dropdown |

### Status Workflow

```
To Do → In Progress → Review → Done
```

### Keyboard Tips

- Use browser search (Ctrl+F / Cmd+F) to find tickets
- Use tab key to navigate form fields quickly
- Use browser back button to return to board

---

## Getting Help

- **Questions about Projects**: Contact your project manager
- **Access Issues**: Contact HR or system administrator
- **Technical Problems**: Contact IT support
- **Training**: Ask your team lead or HR for training sessions

---

## Summary

As a team member, you have access to:
- ✅ Kanban board for visual workflow management
- ✅ Ticket creation and management
- ✅ Status updates via drag-and-drop
- ✅ Comments for collaboration
- ✅ Daily Todos for personal task tracking
- ✅ Personal analytics and contributions
- ✅ Timeline views for planning
- ✅ Epic overview for context

**Remember**: Keep tickets updated, communicate through comments, move tickets through statuses as work progresses, and track your daily todos to stay organized!

---

**Last Updated**: [Current Date]
**Version**: 1.1

### Changelog

**Version 1.1**:
- Added Daily Todos Management section
- Updated daily workflow best practices
- Enhanced quick reference table with Daily Todo actions

**Version 1.0**:
- Initial tutorial release

