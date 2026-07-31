<?php

use App\Models\Notification;
use App\Models\User;
use Emuniq\FilamentBrowserNotifications\Support\ActionResolver;
use Illuminate\Notifications\DatabaseNotification;

it('mirrors custom notifications into Laravel database notifications for browser push', function () {
    $user = User::factory()->create();

    Notification::create([
        'user_id' => $user->id,
        'type' => 'approval_pending',
        'title' => 'Approval needed',
        'message' => 'fallback message',
        'data' => ['approvable_url' => 'https://example.test/review/1'],
    ]);

    $mirrored = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->first();

    expect($mirrored)->not->toBeNull()
        ->and($mirrored->data['title'])->toBe('Approval needed')
        ->and($mirrored->data['body'])->toBe('Request requires your approval')
        ->and(ActionResolver::resolve($mirrored->data)['url'])->toBe('https://example.test/review/1');
});
