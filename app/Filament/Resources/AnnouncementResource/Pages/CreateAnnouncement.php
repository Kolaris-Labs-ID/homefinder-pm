<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use App\Services\Hris\PushNotificationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Announcement created successfully';
    }

    protected function afterCreate(): void
    {
        $announcement = $this->record;

        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendAnnouncementToAll(
                '📢 ' . $announcement->title,
                $announcement->description ?? 'New announcement from management.',
                [
                    'announcement_id'   => $announcement->id,
                    'announcement_type' => $announcement->type,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send announcement push: ' . $e->getMessage(), [
                'announcement_id' => $announcement->id,
            ]);
        }
    }
}
