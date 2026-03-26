# Announcement Feature - Setup Instructions

## Quick Setup

### 1. Run Migration

```bash
cd partnershub-pm
php artisan migrate
```

This creates the `announcements` table with the following structure:

-   id
-   type (news, update, blog, event, birthday)
-   title
-   description
-   date
-   icon (nullable)
-   is_active (boolean)
-   timestamps

### 2. Seed Sample Data (Optional)

```bash
php artisan db:seed --class=AnnouncementSeeder
```

This creates 7 sample announcements of different types for testing.

### 3. Access Admin Panel

1. Log in to Filament admin panel: `https://pm.partnershub.co/admin`
2. Navigate to **HRIS** → **Announcements**
3. You should see the navigation item with a badge showing active announcement count

### 4. Test Mobile App

1. Open the mobile app
2. Navigate to the Home screen
3. Scroll down to see the "Announcements" section
4. Announcements should appear with color-coded icons

## Files Created

### Backend

-   `app/Models/Hris/Announcement.php` - Model
-   `app/Http/Controllers/Api/Hris/AnnouncementController.php` - API Controller
-   `database/migrations/2025_12_25_000002_create_announcements_table.php` - Migration
-   `database/seeders/AnnouncementSeeder.php` - Seeder
-   `routes/api.php` - Added route (already updated)

### Filament Admin

-   `app/Filament/Resources/AnnouncementResource.php` - Main resource
-   `app/Filament/Resources/AnnouncementResource/Pages/ListAnnouncements.php`
-   `app/Filament/Resources/AnnouncementResource/Pages/CreateAnnouncement.php`
-   `app/Filament/Resources/AnnouncementResource/Pages/EditAnnouncement.php`
-   `app/Filament/Resources/AnnouncementResource/Pages/ViewAnnouncement.php`
-   `app/Filament/Resources/AnnouncementResource/Widgets/AnnouncementStatsWidget.php`

### Frontend (Mobile App)

-   `services/api.ts` - Added Announcement type and API method
-   `app/(tabs)/index.tsx` - Added announcement section UI

### Documentation

-   `ANNOUNCEMENT_ADMIN_GUIDE.md` - Admin user guide
-   `ANNOUNCEMENT_SETUP.md` - This file
-   `frontend/ANNOUNCEMENT_FEATURE.md` - Technical documentation

## Verification Checklist

-   [ ] Migration ran successfully
-   [ ] Seeder created sample data
-   [ ] Admin panel shows "Announcements" in HRIS menu
-   [ ] Can create new announcement via admin panel
-   [ ] Can edit/delete announcements
-   [ ] Stats widget shows correct counts
-   [ ] Mobile app displays announcements on home screen
-   [ ] Announcements show correct icons and colors
-   [ ] Pull-to-refresh updates announcements

## API Endpoint

```
GET /api/hris/announcements
Authorization: Bearer {token}
```

Response:

```json
{
    "data": [
        {
            "id": 1,
            "type": "birthday",
            "title": "Happy Birthday Sarah!",
            "description": "Wishing Sarah Johnson a wonderful birthday today!",
            "date": "2025-12-25",
            "icon": null,
            "is_active": true,
            "created_at": "2025-12-25T00:00:00.000000Z",
            "updated_at": "2025-12-25T00:00:00.000000Z"
        }
    ]
}
```

## Troubleshooting

### Migration fails

-   Check database connection
-   Ensure no existing `announcements` table
-   Run `php artisan migrate:fresh` (WARNING: drops all tables)

### Admin panel doesn't show Announcements

-   Clear cache: `php artisan cache:clear`
-   Clear config: `php artisan config:clear`
-   Check user permissions

### Mobile app doesn't show announcements

-   Check API connection
-   Verify authentication token
-   Check if announcements are marked as active
-   Check console for API errors

### Stats widget not showing

-   Clear cache: `php artisan filament:cache-components`
-   Check if widget is registered in ListAnnouncements page

## Next Steps

1. Create your first announcement via admin panel
2. Test on mobile app
3. Train HR team using `ANNOUNCEMENT_ADMIN_GUIDE.md`
4. Set up recurring tasks for birthday announcements
5. Consider implementing push notifications (future enhancement)

## Support

For issues or questions:

-   Check documentation files
-   Review Laravel logs: `storage/logs/laravel.log`
-   Check mobile app console for errors
-   Contact development team
