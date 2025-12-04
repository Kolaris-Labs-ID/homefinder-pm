<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupChatService
{
    /**
     * Create a new group chat.
     *
     * @param string $name The group name (required, non-empty)
     * @param array $memberIds Array of user IDs to add as members
     * @param int $creatorId The user ID of the group creator
     * @return ChatGroup
     * @throws ValidationException
     */
    public function createGroup(string $name, array $memberIds, int $creatorId): ChatGroup
    {
        // Validate group name is not empty or whitespace-only
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            throw ValidationException::withMessages([
                'name' => ['Group name is required and cannot be empty or whitespace-only.'],
            ]);
        }

        // Validate minimum member count (at least 2 members excluding creator)
        // Filter out the creator from memberIds if present
        $memberIdsWithoutCreator = array_filter($memberIds, fn($id) => $id !== $creatorId);
        if (count($memberIdsWithoutCreator) < 2) {
            throw ValidationException::withMessages([
                'members' => ['A group must have at least 2 members (excluding the creator).'],
            ]);
        }

        return DB::transaction(function () use ($trimmedName, $memberIds, $creatorId) {
            // Create the group
            $group = ChatGroup::create([
                'name' => $trimmedName,
                'created_by' => $creatorId,
            ]);

            // Add creator as admin
            $group->members()->attach($creatorId, [
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add other members
            foreach ($memberIds as $memberId) {
                if ($memberId !== $creatorId) {
                    $group->members()->attach($memberId, [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }

            return $group->fresh(['members', 'creator']);
        });
    }


    /**
     * Add a member to a group.
     *
     * @param ChatGroup $group The group to add the member to
     * @param int $userId The user ID to add
     * @param int $adminId The admin user ID performing the action
     * @return bool
     * @throws ValidationException
     */
    public function addMember(ChatGroup $group, int $userId, int $adminId): bool
    {
        // Verify the admin has permission
        $admin = User::find($adminId);
        if (!$admin || !$group->isAdmin($admin)) {
            throw ValidationException::withMessages([
                'permission' => ['Only group admins can add members.'],
            ]);
        }

        // Check if user is already a member
        $user = User::find($userId);
        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['User not found.'],
            ]);
        }

        if ($group->hasMember($user)) {
            throw ValidationException::withMessages([
                'user' => ['User is already a member of this group.'],
            ]);
        }

        // Add the member
        $group->members()->attach($userId, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return true;
    }

    /**
     * Remove a member from a group.
     *
     * @param ChatGroup $group The group to remove the member from
     * @param int $userId The user ID to remove
     * @param int $adminId The admin user ID performing the action
     * @return bool
     * @throws ValidationException
     */
    public function removeMember(ChatGroup $group, int $userId, int $adminId): bool
    {
        // Verify the admin has permission
        $admin = User::find($adminId);
        if (!$admin || !$group->isAdmin($admin)) {
            throw ValidationException::withMessages([
                'permission' => ['Only group admins can remove members.'],
            ]);
        }

        // Check if user is a member
        $user = User::find($userId);
        if (!$user || !$group->hasMember($user)) {
            throw ValidationException::withMessages([
                'user' => ['User is not a member of this group.'],
            ]);
        }

        // Prevent removing the last admin
        if ($group->isAdmin($user) && $group->admins()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['Cannot remove the last admin from the group.'],
            ]);
        }

        // Remove the member
        $group->members()->detach($userId);

        return true;
    }

    /**
     * Send a message to a group.
     *
     * @param ChatGroup $group The group to send the message to
     * @param int $senderId The user ID sending the message
     * @param string $body The message content
     * @param string|null $attachment Optional attachment path
     * @return GroupMessage
     * @throws ValidationException
     */
    public function sendMessage(ChatGroup $group, int $senderId, string $body, ?string $attachment = null): GroupMessage
    {
        // Validate message is not empty
        $trimmedBody = trim($body);
        if (empty($trimmedBody) && empty($attachment)) {
            throw ValidationException::withMessages([
                'body' => ['Message cannot be empty.'],
            ]);
        }

        // Verify sender is a member of the group
        $sender = User::find($senderId);
        if (!$sender || !$group->hasMember($sender)) {
            throw ValidationException::withMessages([
                'sender' => ['You must be a member of the group to send messages.'],
            ]);
        }

        // Create the message
        $message = GroupMessage::create([
            'group_id' => $group->id,
            'user_id' => $senderId,
            'body' => $trimmedBody,
            'attachment' => $attachment,
        ]);

        $message = $message->fresh(['sender', 'group']);

        // Broadcast the message to all group members
        broadcast(new GroupMessageSent($message))->toOthers();

        return $message;
    }


    /**
     * Get messages from a group.
     *
     * @param ChatGroup $group The group to get messages from
     * @param int $userId The user ID requesting messages
     * @param int $limit Maximum number of messages to return
     * @param int|null $beforeId Get messages before this ID (for pagination)
     * @return Collection
     * @throws ValidationException
     */
    public function getMessages(ChatGroup $group, int $userId, int $limit = 50, ?int $beforeId = null): Collection
    {
        // Verify user is a member of the group
        $user = User::find($userId);
        if (!$user || !$group->hasMember($user)) {
            throw ValidationException::withMessages([
                'permission' => ['You must be a member of the group to view messages.'],
            ]);
        }

        $query = $group->messages()
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit)->get()->reverse()->values();
    }

    /**
     * Mark messages as read for a user in a group.
     *
     * @param ChatGroup $group The group
     * @param int $userId The user ID
     * @return void
     */
    public function markAsRead(ChatGroup $group, int $userId): void
    {
        ChatGroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Get the total unread message count across all groups for a user.
     *
     * @param int $userId The user ID
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        $totalUnread = 0;

        // Get all groups the user is a member of
        $memberships = ChatGroupMember::where('user_id', $userId)->get();

        foreach ($memberships as $membership) {
            $query = GroupMessage::where('group_id', $membership->group_id)
                ->where('user_id', '!=', $userId); // Don't count own messages

            if ($membership->last_read_at) {
                $query->where('created_at', '>', $membership->last_read_at);
            }

            $totalUnread += $query->count();
        }

        return $totalUnread;
    }

    /**
     * Get unread message count for a specific group.
     *
     * @param ChatGroup $group The group
     * @param int $userId The user ID
     * @return int
     */
    public function getGroupUnreadCount(ChatGroup $group, int $userId): int
    {
        $membership = ChatGroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return 0;
        }

        $query = GroupMessage::where('group_id', $group->id)
            ->where('user_id', '!=', $userId); // Don't count own messages

        if ($membership->last_read_at) {
            $query->where('created_at', '>', $membership->last_read_at);
        }

        return $query->count();
    }

    /**
     * Get all groups a user belongs to.
     *
     * @param int $userId The user ID
     * @return Collection
     */
    public function getUserGroups(int $userId): Collection
    {
        return ChatGroup::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with(['creator', 'members'])
        ->orderBy('updated_at', 'desc')
        ->get();
    }

    /**
     * Get all groups a user belongs to with unread counts.
     *
     * @param int $userId The user ID
     * @return Collection
     */
    public function getUserGroupsWithUnreadCounts(int $userId): Collection
    {
        $groups = $this->getUserGroups($userId);

        return $groups->map(function ($group) use ($userId) {
            $group->unread_count = $this->getGroupUnreadCount($group, $userId);
            return $group;
        });
    }
}
