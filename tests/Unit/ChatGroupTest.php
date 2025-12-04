<?php

/**
 * Unit Tests for ChatGroup and GroupMessage Models
 *
 * These tests verify the model relationships and helper methods
 * for the group chat feature.
 */

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\GroupMessage;
use App\Models\User;

beforeEach(function () {
    // Clean up before each test
    GroupMessage::query()->delete();
    ChatGroupMember::query()->delete();
    ChatGroup::query()->delete();
});

describe('ChatGroup Model', function () {
    test('can create a chat group with required fields', function () {
        $creator = User::factory()->create();

        $group = ChatGroup::create([
            'name' => 'Test Group',
            'created_by' => $creator->id,
        ]);

        expect($group)->toBeInstanceOf(ChatGroup::class);
        expect($group->name)->toBe('Test Group');
        expect($group->created_by)->toBe($creator->id);
    });

    test('has creator relationship', function () {
        $creator = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        expect($group->creator)->toBeInstanceOf(User::class);
        expect($group->creator->id)->toBe($creator->id);
    });

    test('has members relationship', function () {
        $creator = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        // Add members via pivot table
        $group->members()->attach($member1->id, ['role' => 'admin', 'joined_at' => now()]);
        $group->members()->attach($member2->id, ['role' => 'member', 'joined_at' => now()]);

        expect($group->members)->toHaveCount(2);
        expect($group->members->pluck('id')->toArray())->toContain($member1->id, $member2->id);
    });

    test('has admins relationship', function () {
        $creator = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        // Add admin and regular member
        $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        expect($group->admins)->toHaveCount(1);
        expect($group->admins->first()->id)->toBe($admin->id);
    });

    test('has messages relationship', function () {
        $creator = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        // Create messages
        GroupMessage::factory()->count(3)->create([
            'group_id' => $group->id,
            'user_id' => $creator->id,
        ]);

        expect($group->messages)->toHaveCount(3);
    });

    test('hasMember helper method works correctly', function () {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        expect($group->hasMember($member))->toBeTrue();
        expect($group->hasMember($nonMember))->toBeFalse();
    });

    test('isAdmin helper method works correctly', function () {
        $creator = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        expect($group->isAdmin($admin))->toBeTrue();
        expect($group->isAdmin($member))->toBeFalse();
    });

    test('member_count attribute works correctly', function () {
        $creator = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        // Add 3 members
        $members = User::factory()->count(3)->create();
        foreach ($members as $member) {
            $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }

        expect($group->member_count)->toBe(3);
    });
});

describe('GroupMessage Model', function () {
    test('can create a group message with required fields', function () {
        $creator = User::factory()->create();
        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'user_id' => $creator->id,
            'body' => 'Hello, world!',
        ]);

        expect($message)->toBeInstanceOf(GroupMessage::class);
        expect($message->body)->toBe('Hello, world!');
        expect($message->group_id)->toBe($group->id);
        expect($message->user_id)->toBe($creator->id);
    });

    test('has group relationship', function () {
        $creator = User::factory()->create();
        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $message = GroupMessage::factory()->create([
            'group_id' => $group->id,
            'user_id' => $creator->id,
        ]);

        expect($message->group)->toBeInstanceOf(ChatGroup::class);
        expect($message->group->id)->toBe($group->id);
    });

    test('has sender relationship', function () {
        $creator = User::factory()->create();
        $sender = User::factory()->create();
        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $message = GroupMessage::factory()->create([
            'group_id' => $group->id,
            'user_id' => $sender->id,
        ]);

        expect($message->sender)->toBeInstanceOf(User::class);
        expect($message->sender->id)->toBe($sender->id);
    });
});

describe('ChatGroupMember Pivot Model', function () {
    test('has correct casts for datetime fields', function () {
        $creator = User::factory()->create();
        $member = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $group->members()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
            'last_read_at' => now(),
        ]);

        $pivot = $group->members()->where('user_id', $member->id)->first()->pivot;

        expect($pivot->joined_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($pivot->last_read_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    test('admins scope filters correctly', function () {
        $creator = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $group = ChatGroup::factory()->create([
            'created_by' => $creator->id,
        ]);

        $group->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $adminMembers = ChatGroupMember::where('group_id', $group->id)->admins()->get();

        expect($adminMembers)->toHaveCount(1);
        expect($adminMembers->first()->user_id)->toBe($admin->id);
    });
});
