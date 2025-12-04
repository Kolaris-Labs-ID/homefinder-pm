<?php

use App\Models\ChatGroup;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Group Chat Channels
|--------------------------------------------------------------------------
|
| Authorization for group chat private channels. Users can only listen
| to group channels they are members of.
|
*/

Broadcast::channel('group-chat.{groupId}', function ($user, $groupId) {
    $group = ChatGroup::find($groupId);

    if (!$group) {
        return false;
    }

    return $group->hasMember($user);
});
