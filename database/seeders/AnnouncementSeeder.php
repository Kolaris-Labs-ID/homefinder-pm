<?php

namespace Database\Seeders;

use App\Models\Hris\Announcement;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $announcements = [
            [
                'type' => 'birthday',
                'title' => 'Happy Birthday Sarah!',
                'description' => 'Wishing Sarah Johnson a wonderful birthday today! 🎉',
                'date' => Carbon::today(),
                'is_active' => true,
            ],
            [
                'type' => 'event',
                'title' => 'Team Building Event',
                'description' => 'Join us for team building activities this Friday at 2 PM. Location: Conference Room A',
                'date' => Carbon::today()->addDays(2),
                'is_active' => true,
            ],
            [
                'type' => 'news',
                'title' => 'New Office Policy',
                'description' => 'Updated work from home policy is now effective. Check your email for details.',
                'date' => Carbon::today(),
                'is_active' => true,
            ],
            [
                'type' => 'update',
                'title' => 'System Maintenance',
                'description' => 'The HRIS system will undergo maintenance this Saturday from 10 PM to 2 AM.',
                'date' => Carbon::today()->addDays(3),
                'is_active' => true,
            ],
            [
                'type' => 'blog',
                'title' => 'Employee Spotlight: John Doe',
                'description' => 'Read about John\'s journey and achievements in our latest blog post.',
                'date' => Carbon::today()->subDays(1),
                'is_active' => true,
            ],
            [
                'type' => 'birthday',
                'title' => 'Happy Birthday Michael!',
                'description' => 'Wishing Michael Chen a fantastic birthday! 🎂',
                'date' => Carbon::today()->addDays(5),
                'is_active' => true,
            ],
            [
                'type' => 'event',
                'title' => 'Monthly Town Hall',
                'description' => 'Join our monthly town hall meeting next Monday at 10 AM via Zoom.',
                'date' => Carbon::today()->addDays(7),
                'is_active' => true,
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create($announcement);
        }
    }
}
