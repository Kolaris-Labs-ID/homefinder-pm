# Glossary
## Kanban-Based Agile Project Management System

This glossary defines key terms, concepts, and features used in the Kanban-based Agile project management system.

---

## Table of Contents

- [A](#a)
- [B](#b)
- [C](#c)
- [D](#d)
- [E](#e)
- [F](#f)
- [G](#g)
- [H](#h)
- [I](#i)
- [K](#k)
- [L](#l)
- [M](#m)
- [P](#p)
- [R](#r)
- [S](#s)
- [T](#t)
- [U](#u)
- [W](#w)

---

## A

### **Agile**
A project management methodology that emphasizes iterative development, collaboration, and responsiveness to change. This system combines Agile principles with Kanban visualization.

### **Assignee**
A team member assigned to work on a ticket. Multiple assignees can be assigned to a single ticket, enabling collaborative work on complex tasks.

### **Assignment**
The process of assigning a ticket to one or more team members. Assignees receive notifications and can view, edit, and move assigned tickets on the Kanban board.

---

## B

### **Board** (see Kanban Board)
A visual representation of work items organized into columns representing different workflow stages.

---

## C

### **Card** (see Ticket Card)
A visual representation of a ticket on the Kanban board, displaying key information like title, priority, assignees, and due date.

### **Column** (see Status Column)
A vertical section on the Kanban board representing a specific workflow stage or status (e.g., "To Do", "In Progress", "Done").

### **Comment**
Text-based communication attached to a ticket. Comments allow team members to discuss work, ask questions, provide updates, and collaborate directly within the ticket context.

### **Completed Status**
A ticket status marked with the "Is Completed" flag, typically used for final/done states. Completed tickets display a checkmark on the board.

---

## D

### **Daily Todo**
A personal task or to-do item that employees can create, manage, and track on a daily basis. Daily todos have three statuses: Pending, In Progress, and Done.

**Statuses:**
- **Pending** (Gray): Task created but not started
- **In Progress** (Yellow): Task is actively being worked on
- **Done** (Green): Task completed

**Access Control:**
- Regular employees can only manage their own todos
- Head Division can see todos from employees in their division
- Full management roles (Super Admin, Admin, CEO, COO, CTO, HR) can see all todos

### **Dashboard**
The main page displayed after logging in, showing key metrics, widgets, and recent activity. Includes statistics like total projects, tickets, and personal assignments.

### **Description**
A detailed text field in tickets and daily todos that provides context, requirements, acceptance criteria, links, or additional information about the work item.

### **Division**
An organizational unit within the company (e.g., Engineering, Marketing, Sales). Divisions are used to group employees and enable Head Division management of team members' daily todos.

### **Division Head**
A user designated as a division manager with the ability to view and manage daily todos from employees within their division. Division heads cannot see todos from other divisions.

### **Drag and Drop**
The ability to move tickets between status columns on the Kanban board by clicking and dragging (desktop) or long-pressing and dragging (mobile). This updates the ticket status automatically.

### **Due Date**
A target completion date assigned to a ticket, helping team members prioritize work and identify deadlines. Overdue tickets can be highlighted on the board.

---

## E

### **Epic**
A large initiative or feature that groups related tickets together. Epics help organize work by feature, release, or business objective, providing a higher-level view of project progress.

**Characteristics:**
- Multiple tickets can belong to one epic
- Epics have start and end dates
- Useful for planning and tracking large features
- Visible in the Epics Overview page

---

## F

### **Filter**
A tool used to narrow down the view of tickets, todos, or other items based on specific criteria such as user, date, status, priority, or division.

### **Full Management Role**
Users with roles that provide unrestricted access: Super Admin, Admin, CEO, COO, CTO, and HR. These roles can view and manage all projects, tickets, and daily todos across all divisions.

---

## H

### **Head Division** (see Division Head)
A management role that oversees a specific division and can view/manage daily todos from division members only.

### **History**
A chronological record of changes made to a ticket, particularly status changes. Includes information about who made changes and when.

---

## I

### **Is Completed Flag**
A boolean field on ticket statuses that marks statuses as completion states. Tickets in completed statuses display a checkmark (✓) on the Kanban board.

### **In Progress**
A common ticket status indicating that work has begun on the ticket. Also used as a status for daily todos.

---

## K

### **Kanban**
A visual workflow management method that uses a board with columns representing different stages of work. Originated from lean manufacturing and adapted for software development.

**Key Principles:**
- Visualize work
- Limit work in progress (WIP)
- Manage flow
- Make process policies explicit
- Improve collaboratively

### **Kanban Board**
The visual board displaying tickets organized into columns by status. Enables drag-and-drop status updates, filtering, sorting, and real-time collaboration.

**Features:**
- Status columns (To Do, In Progress, Review, Done, etc.)
- Ticket cards showing key information
- Drag-and-drop functionality
- Column sorting options
- User filtering
- Real-time updates

---

## L

### **Leaderboard**
A ranking page showing team member performance based on metrics like tickets completed, tickets created, and overall activity levels. Filterable by project.

---

## M

### **Member** (see Team Member)
A user role with limited access, able to view and update tickets they're assigned to or have created, within projects they're members of.

### **Multi-user Assignment**
The capability to assign multiple team members to a single ticket, enabling collaborative work on complex tasks.

---

## P

### **Pending**
A status indicating that a task has been created but not yet started. Used in daily todos as the default status.

### **Priority**
A classification system that indicates the urgency or importance of a ticket. Common priority levels include:
- **Critical** / **High**: Urgent work requiring immediate attention
- **Medium**: Normal priority work
- **Low**: Non-urgent work that can be deferred

Priorities are color-coded for quick visual identification on the board.

### **Project**
A container that organizes related work, tickets, team members, and configuration. Projects have their own ticket statuses, epics, and settings.

**Key Attributes:**
- Name and description
- Ticket prefix (e.g., "WEB", "MOB") for unique ticket IDs
- Start and end dates
- Team members
- Ticket statuses
- Epics
- Pinned status (for timeline views)

### **Project Board** (see Kanban Board)
The Kanban board view for a specific project, displaying only tickets from that project.

### **Project Member**
A user who has been added to a project and can view, create, and work on tickets within that project. Access is restricted to project members unless the user is a Super Admin.

### **Project Timeline**
A Gantt-style timeline view showing all projects with their start and end dates, durations, and progress. Useful for understanding project schedules and overlaps.

---

## R

### **Review**
A common ticket status indicating that work is complete and awaiting review or approval before being marked as done.

---

## S

### **Sort Order**
A numeric field on ticket statuses that determines the column sequence on the Kanban board. Lower numbers appear on the left, higher numbers on the right.

### **Start Date**
The date when work on a ticket or epic should begin. Used for planning and timeline visualization.

### **Status** (Ticket Status)
A workflow stage that represents the current state of a ticket (e.g., "To Do", "In Progress", "Review", "Done"). Each project has its own set of statuses.

**Common Statuses:**
- **To Do**: New ticket, not started
- **In Progress**: Actively being worked on
- **Review**: Completed, awaiting review/approval
- **Done**: Completed and verified

**Status Attributes:**
- Name
- Color (for visual identification)
- Sort Order (column position)
- Is Completed flag (marks final states)

### **Status Column**
A vertical section on the Kanban board representing a specific ticket status. Tickets are organized into columns based on their current status.

### **Super Admin**
A user role with full system access, able to view and manage all projects, tickets, users, and system configuration regardless of project membership.

---

## T

### **Team Member**
A user who belongs to one or more projects and can work on tickets within those projects.

### **Ticket**
A work item, task, or user story that represents a piece of work to be completed. Tickets are the primary unit of work in the system.

**Ticket Attributes:**
- **UUID**: Unique identifier with project prefix (e.g., "WEB-ABC123")
- **Name**: Title/description of the work
- **Description**: Detailed information about the work
- **Status**: Current workflow stage
- **Priority**: Urgency level
- **Project**: Which project it belongs to
- **Epic**: Optional grouping with related tickets
- **Assignees**: Team members working on it (can be multiple)
- **Start Date**: When work should begin
- **Due Date**: Target completion date
- **Comments**: Discussion and updates
- **History**: Record of status changes

**Ticket Lifecycle:**
1. Created → assigned to "To Do" or initial status
2. Work begins → moved to "In Progress"
3. Completed → moved to "Review" or "Done"
4. Marked as done when verified complete

### **Ticket Card**
The visual representation of a ticket on the Kanban board, displaying:
- Ticket UUID
- Ticket name
- Priority badge (color-coded)
- Due date
- Assignees
- View button

### **Ticket History**
A chronological log of changes to a ticket, particularly status changes, showing who made changes and when.

### **Ticket Prefix**
A short code associated with a project (e.g., "WEB", "MOB") used to create unique ticket IDs. Results in UUIDs like "WEB-ABC123".

### **Ticket Status** (see Status)

### **Ticket Timeline**
A Gantt-style timeline view showing tickets from a selected project, plotted based on their start and due dates. Helps identify scheduling conflicts and bottlenecks.

### **Timeline View**
Visual representation of projects or tickets over time, showing durations, overlaps, and schedules. Includes Project Timeline and Ticket Timeline.

### **To Do**
A ticket status indicating that work has not yet started. Typically the first status in a workflow.

---

## U

### **User**
A person registered in the system who can be assigned to projects and tickets. Users have roles that determine their access levels and permissions.

**User Roles:**
- **Super Admin**: Full system access
- **Admin**: Broad access with some restrictions
- **CEO, COO, CTO, HR**: Management roles with broad access
- **Head Division**: Division manager with division-level access
- **Member**: Regular user with limited access

**User Attributes:**
- Name and email
- Role(s)
- Division (optional)
- Is Division Head flag
- Project memberships

### **UUID** (Unique Universal Identifier)
A unique identifier for tickets, typically formatted as "PROJECT-PREFIX-RANDOM-CODE" (e.g., "WEB-ABC123"). Ensures each ticket has a unique, trackable identifier.

---

## W

### **Widget**
A component on the dashboard that displays specific information, such as:
- Statistics overview
- Daily todo lists
- Activity charts
- Recent updates
- Team metrics

### **Workflow**
The sequence of statuses that tickets move through from creation to completion (e.g., To Do → In Progress → Review → Done).

### **WIP** (Work In Progress)
Work that has been started but not yet completed. Limiting WIP is a key Kanban principle to improve flow and reduce bottlenecks.

---

## Additional Terms

### **Acceptance Criteria**
Requirements or conditions that must be met for a ticket to be considered complete. Often included in ticket descriptions.

### **Backlog**
A collection of tickets that haven't been started yet, typically represented by the "To Do" status column.

### **Blocked**
A state where work cannot proceed due to dependencies or issues. Often handled through comments or special statuses.

### **Burndown Chart**
A chart showing progress toward completing work, typically measuring remaining work over time.

### **Filter by User**
A Kanban board feature that filters tickets to show only those assigned to selected team members.

### **Pin Project**
A feature that marks important projects to appear at the top in timeline views, making them easier to find.

### **Pull System**
A Kanban principle where work is pulled into the next stage when capacity is available, rather than being pushed forward regardless of capacity.

### **Sprint**
A time-boxed iteration in Agile development. While this system is Kanban-based (not Scrum), similar concepts may apply.

### **Stand-up** (Daily Stand-up)
A brief daily meeting where team members share progress. Daily todos can be used to prepare for stand-ups.

### **User Story**
A description of a feature from the user's perspective. Often represented as tickets in this system.

---

## Related Documentation

- [Tutorial for Super Admin & HR](TUTORIAL_SUPERADMIN.md)
- [Tutorial for Team Members & Users](TUTORIAL_USER.md)

---

**Last Updated**: [Current Date]  
**Version**: 1.0

