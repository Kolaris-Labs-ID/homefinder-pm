<?php

use App\Filament\Resources\AttendanceLogResource\Pages\ViewAttendanceLog;
use App\Models\Hris\AttendanceLog;
use Filament\Infolists\Infolist;

it('opens check-in photo in a modal instead of linking to the storage URL', function () {
    $photoUrl = 'https://example.supabase.co/storage/v1/object/public/attendance-photos/check-in.jpg';
    $attendanceLog = AttendanceLog::factory()->create([
        'check_in_photo_path' => $photoUrl,
        'check_out_photo_path' => null,
    ]);

    $infolist = app(ViewAttendanceLog::class)->infolist(
        Infolist::make()
            ->name('infolist')
            ->record($attendanceLog)
    );

    $photoEntry = $infolist
        ->getComponent('check_in_photo_path');
    $photoAction = $photoEntry->getAction('viewCheckInPhoto');

    expect($photoEntry->getUrl())
        ->toBeNull()
        ->and($photoAction)
        ->not->toBeNull();

    $modalContent = $photoAction
        ->getModalContent()
        ->render();

    expect($modalContent)
        ->toContain('max-h-[75vh]')
        ->toContain('src="'.$photoUrl.'"');
});
