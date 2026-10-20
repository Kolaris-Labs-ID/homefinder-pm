# Announcement Management - Admin Guide

## Overview

The Announcement feature allows HR and C-level users to create and manage company announcements that appear on employees' mobile app home screens.

## Accessing the Admin Panel

1. Log in to the Filament admin panel at `/admin`
2. Navigate to **HRIS** → **Announcements** in the sidebar
3. The navigation badge shows the count of active announcements

## Creating an Announcement

### Step 1: Click "New Announcement"

Click the "New Announcement" button in the top-right corner of the Announcements page.

### Step 2: Fill in the Form

#### Type (Required)

Select the announcement type:

-   **News**: Company news and important updates
-   **Update**: System updates and policy changes
-   **Blog**: Employee spotlights and blog posts
-   **Event**: Upcoming company events and meetings
-   **Birthday**: Employee birthday celebrations

#### Date (Required)

Select the announcement date. This determines when the announcement is relevant and affects sorting.

#### Title (Required)

Enter a clear, concise title (max 255 characters).

**Examples:**

-   "Happy Birthday Sarah!"
-   "Team Building Event - This Friday"
-   "New WFH Policy Effective Today"

#### Description (Required)

Provide detailed information about the announcement.

**Examples:**

-   "Wishing Sarah Johnson a wonderful birthday today! 🎉"
-   "Join us for team building activities this Friday at 2 PM. Location: Conference Room A"
-   "Updated work from home policy is now effective. Check your email for details."

#### Active Status

Toggle to control visibility:

-   **ON** (default): Announcement appears in the mobile app
-   **OFF**: Announcement is hidden from employees

### Step 3: Save

Click "Create" to publish the announcement.

## Managing Announcements

### Viewing Announcements

-   Click on any announcement row to view full details
-   Use the "View" action button for quick access

### Editing Announcements

1. Click the "Edit" button on any announcement
2. Modify the fields as needed
3. Click "Save changes"

### Deleting Announcements

1. Click the "Delete" button on any announcement
2. Confirm the deletion

### Bulk Actions

Select multiple announcements using checkboxes to:

-   **Delete**: Remove multiple announcements at once
-   **Activate**: Make multiple announcements active
-   **Deactivate**: Hide multiple announcements from the app

## Filtering and Searching

### Search

Use the search bar to find announcements by title or description.

### Filters

#### Type Filter

Filter announcements by one or more types:

-   News
-   Update
-   Blog
-   Event
-   Birthday

#### Active Status Filter

-   **All announcements**: Show both active and inactive
-   **Active only**: Show only visible announcements
-   **Inactive only**: Show only hidden announcements

#### Date Range Filter

Filter announcements by date:

-   **From**: Start date
-   **Until**: End date

## Best Practices

### Titles

-   Keep titles short and descriptive
-   Use action words for events ("Join us...", "Don't miss...")
-   Include key information (date, person's name, etc.)

### Descriptions

-   Provide enough context without being too verbose
-   Include relevant details (time, location, action required)
-   Use emojis sparingly for birthday and event announcements

### Types

-   **News**: Major company announcements, policy changes
-   **Update**: System updates, process changes
-   **Blog**: Employee stories, achievements
-   **Event**: Meetings, celebrations, activities
-   **Birthday**: Employee birthdays only

### Timing

-   Create birthday announcements in advance
-   Schedule event announcements at least 2-3 days before
-   Keep announcements active only while relevant
-   Deactivate old announcements instead of deleting (for records)

### Active Status

-   Only keep relevant announcements active
-   Deactivate past events and outdated news
-   Review and clean up announcements monthly

## Mobile App Display

Announcements appear on the employee home screen:

-   Sorted by date (newest first)
-   Maximum 5 announcements shown
-   Color-coded icons by type
-   Includes title, description, date, and type badge

### Icon Colors

-   News: Blue
-   Update: Purple
-   Blog: Pink
-   Event: Amber
-   Birthday: Red

## Tips for HR Teams

### Birthday Announcements

1. Set up a recurring task to check upcoming birthdays
2. Create birthday announcements 1 day in advance
3. Use consistent format: "Happy Birthday [Name]!"
4. Set date to the actual birthday

### Event Announcements

1. Create event announcements 3-5 days before
2. Include time, location, and any preparation needed
3. Update with reminders as the date approaches
4. Deactivate after the event concludes

### News and Updates

1. Coordinate with management before publishing
2. Ensure information is accurate and approved
3. Link to detailed resources if needed
4. Keep active until superseded by new information

## Troubleshooting

### Announcement not showing in app

-   Check if "Active" toggle is ON
-   Verify the date is not too far in the future
-   Ensure the mobile app is connected to the API
-   Check if there are more than 5 newer announcements

### Can't delete announcement

-   Check your user permissions
-   Try deactivating instead of deleting
-   Contact system administrator if issue persists

## Support

For technical issues or questions:

-   Contact IT Support
-   Email: support@your-homefinder-domain.example
-   Check the system documentation

---

**Last Updated**: December 25, 2025
