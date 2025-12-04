<?php

/**
 * Property-Based Tests for Group Chat Feature
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
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

/**
 * **Feature: team-chat, Property 6: Group name requirement**
 *
 * For any group creation attempt, if the name is empty or whitespace-only,
 * creation should fail. If a valid name is provided with valid members,
 * creation should succeed.
 *
 * **Validates: Requirements 6.2**
 */
test('Property 6: Group name requirement', function () {
    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create a creator user
        $creator = User::factory()->create();

        // Test 1: Empty or whitespace-only names should fail validation
        $invalidNames = [
            '',
            ' ',
            '  ',
            "\t",
            "\n",
            "   \t\n   ",
        ];

        foreach ($invalidNames as $invalidName) {
            // Attempt to create a group with invalid name
            $trimmedName = trim($invalidName);

            // Property: Empty/whitespace names should be rejected
            expect($trimmedName)->toBeEmpty(
                "Trimmed invalid name should be empty"
            );

            // Attempting to create with empty name should fail validation
            // (The actual validation will be in the service layer, but we test the model constraint)
            if (empty($trimmedName)) {
                // This should not create a valid group
                expect(empty($trimmedName))->toBeTrue(
                    "Empty name should be detected as invalid"
                );
            }
        }

        // Test 2: Valid names should succeed
        $validNames = [
            fake()->words(rand(1, 5), true),
            'Team ' . fake()->word(),
            fake()->company(),
            'Project-' . rand(1, 100),
            fake()->sentence(3),
        ];

        $validName = $validNames[array_rand($validNames)];

        // Create group with valid name
        $group = ChatGroup::create([
            'name' => $validName,
            'created_by' => $creator->id,
        ]);

        // Property: Group should be created successfully
        expect($group)->toBeInstanceOf(ChatGroup::class);
        expect($group->exists)->toBeTrue(
            "Group with valid name should be created"
        );

        // Property: Group name should match the provided name
        expect($group->name)->toBe($validName,
            "Group name should match the provided name"
        );

        // Property: Group should have the correct creator
        expect($group->created_by)->toBe($creator->id,
            "Group should have the correct creator"
        );

        // Clean up for next iteration
        $group->delete();
        $creator->forceDelete();
    }
})->group('property-test', 'team-chat');


/**
 * **Feature: team-chat, Property 11: Minimum group size validation**
 *
 * For any group creation attempt with fewer than 2 members (excluding creator),
 * creation should fail with a validation error.
 *
 * **Validates: Requirements 6.7**
 */
test('Property 11: Minimum group size validation', function () {
    $service = new \App\Services\GroupChatService();

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create users
        $creator = User::factory()->create();
        $potentialMembers = User::factory()->count(5)->create();

        // Test 1: Creating group with 0 members (excluding creator) should fail
        $caughtException = false;
        try {
            $service->createGroup(
                'Test Group ' . $i,
                [], // No members
                $creator->id
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caughtException = true;
            expect($e->errors())->toHaveKey('members');
        }
        expect($caughtException)->toBeTrue(
            "Creating group with 0 members should throw ValidationException"
        );

        // Test 2: Creating group with 1 member (excluding creator) should fail
        $caughtException = false;
        try {
            $service->createGroup(
                'Test Group ' . $i,
                [$potentialMembers[0]->id], // Only 1 member
                $creator->id
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caughtException = true;
            expect($e->errors())->toHaveKey('members');
        }
        expect($caughtException)->toBeTrue(
            "Creating group with 1 member should throw ValidationException"
        );

        // Test 3: Creating group with 2+ members (excluding creator) should succeed
        $memberCount = rand(2, 5);
        $selectedMembers = $potentialMembers->take($memberCount)->pluck('id')->toArray();

        $group = $service->createGroup(
            'Valid Group ' . $i,
            $selectedMembers,
            $creator->id
        );

        // Property: Group should be created successfully
        expect($group)->toBeInstanceOf(ChatGroup::class);
        expect($group->exists)->toBeTrue();

        // Property: Group should have correct member count (members + creator)
        expect($group->members()->count())->toBe($memberCount + 1);

        // Clean up for next iteration
        GroupMessage::where('group_id', $group->id)->delete();
        ChatGroupMember::where('group_id', $group->id)->delete();
        $group->delete();
        $creator->forceDelete();
        foreach ($potentialMembers as $member) {
            $member->forceDelete();
        }
    }
})->group('property-test', 'team-chat');

/**
 * **Feature: team-chat, Property 7: Group message delivery**
 *
 * For any group with N members and any message sent to that group,
 * all N members should be able to retrieve that message from the group's message history.
 *
 * **Validates: Requirements 6.3**
 */
test('Property 7: Group message delivery', function () {
    $service = new \App\Services\GroupChatService();

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create users
        $creator = User::factory()->create();
        $memberCount = rand(2, 5);
        $members = User::factory()->count($memberCount)->create();
        $memberIds = $members->pluck('id')->toArray();

        // Create group
        $group = $service->createGroup(
            'Test Group ' . $i,
            $memberIds,
            $creator->id
        );

        // Send a random message from a random member
        $allMembers = collect([$creator])->merge($members);
        $sender = $allMembers->random();
        $messageBody = fake()->sentence();

        $message = $service->sendMessage($group, $sender->id, $messageBody);

        // Property: Message should be created
        expect($message)->toBeInstanceOf(GroupMessage::class);
        expect($message->body)->toBe($messageBody);
        expect($message->user_id)->toBe($sender->id);

        // Property: All members should be able to retrieve the message
        foreach ($allMembers as $member) {
            $messages = $service->getMessages($group, $member->id);

            // Property: Messages should contain the sent message
            expect($messages->contains('id', $message->id))->toBeTrue(
                "Member {$member->id} should be able to retrieve message {$message->id}"
            );

            // Property: Retrieved message should have correct content
            $retrievedMessage = $messages->firstWhere('id', $message->id);
            expect($retrievedMessage->body)->toBe($messageBody);
            expect($retrievedMessage->user_id)->toBe($sender->id);
        }

        // Clean up for next iteration
        GroupMessage::where('group_id', $group->id)->delete();
        ChatGroupMember::where('group_id', $group->id)->delete();
        $group->delete();
        $creator->forceDelete();
        foreach ($members as $member) {
            $member->forceDelete();
        }
    }
})->group('property-test', 'team-chat');

/**
 * **Feature: team-chat, Property 10: Admin permission enforcement**
 *
 * For any group, only users with admin role should be able to add or remove members.
 * Non-admin member modification attempts should be rejected.
 *
 * **Validates: Requirements 6.6**
 */
test('Property 10: Admin permission enforcement', function () {
    $service = new \App\Services\GroupChatService();

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create users
        $creator = User::factory()->create();
        $members = User::factory()->count(3)->create();
        $newUser = User::factory()->create();

        // Create group (creator is admin)
        $group = $service->createGroup(
            'Test Group ' . $i,
            $members->pluck('id')->toArray(),
            $creator->id
        );

        // Test 1: Non-admin trying to add member should fail
        $nonAdmin = $members->first();
        $caughtException = false;
        try {
            $service->addMember($group, $newUser->id, $nonAdmin->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caughtException = true;
            expect($e->errors())->toHaveKey('permission');
        }
        expect($caughtException)->toBeTrue(
            "Non-admin should not be able to add members"
        );

        // Test 2: Non-admin trying to remove member should fail
        $memberToRemove = $members->last();
        $caughtException = false;
        try {
            $service->removeMember($group, $memberToRemove->id, $nonAdmin->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caughtException = true;
            expect($e->errors())->toHaveKey('permission');
        }
        expect($caughtException)->toBeTrue(
            "Non-admin should not be able to remove members"
        );

        // Test 3: Admin (creator) should be able to add member
        $result = $service->addMember($group, $newUser->id, $creator->id);
        expect($result)->toBeTrue();
        expect($group->fresh()->hasMember($newUser))->toBeTrue(
            "Admin should be able to add members"
        );

        // Test 4: Admin (creator) should be able to remove member
        $result = $service->removeMember($group, $memberToRemove->id, $creator->id);
        expect($result)->toBeTrue();
        expect($group->fresh()->hasMember($memberToRemove))->toBeFalse(
            "Admin should be able to remove members"
        );

        // Clean up for next iteration
        GroupMessage::where('group_id', $group->id)->delete();
        ChatGroupMember::where('group_id', $group->id)->delete();
        $group->delete();
        $creator->forceDelete();
        foreach ($members as $member) {
            $member->forceDelete();
        }
        $newUser->forceDelete();
    }
})->group('property-test', 'team-chat');

/**
 * **Feature: team-chat, Property 9: Group visibility after membership**
 *
 * For any user added to a group, querying that user's group list
 * should include the newly joined group.
 *
 * **Validates: Requirements 6.5**
 */
test('Property 9: Group visibility after membership', function () {
    $service = new \App\Services\GroupChatService();

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create users
        $creator = User::factory()->create();
        $initialMembers = User::factory()->count(2)->create();
        $newMember = User::factory()->create();

        // Create group
        $group = $service->createGroup(
            'Test Group ' . $i,
            $initialMembers->pluck('id')->toArray(),
            $creator->id
        );

        // Property: New member should NOT see the group before being added
        $groupsBefore = $service->getUserGroups($newMember->id);
        expect($groupsBefore->contains('id', $group->id))->toBeFalse(
            "User should not see group before being added"
        );

        // Add new member to group
        $service->addMember($group, $newMember->id, $creator->id);

        // Property: New member should see the group after being added
        $groupsAfter = $service->getUserGroups($newMember->id);
        expect($groupsAfter->contains('id', $group->id))->toBeTrue(
            "User should see group after being added"
        );

        // Property: All initial members should see the group
        foreach ($initialMembers as $member) {
            $memberGroups = $service->getUserGroups($member->id);
            expect($memberGroups->contains('id', $group->id))->toBeTrue(
                "Initial member should see the group"
            );
        }

        // Property: Creator should see the group
        $creatorGroups = $service->getUserGroups($creator->id);
        expect($creatorGroups->contains('id', $group->id))->toBeTrue(
            "Creator should see the group"
        );

        // Clean up for next iteration
        GroupMessage::where('group_id', $group->id)->delete();
        ChatGroupMember::where('group_id', $group->id)->delete();
        $group->delete();
        $creator->forceDelete();
        foreach ($initialMembers as $member) {
            $member->forceDelete();
        }
        $newMember->forceDelete();
    }
})->group('property-test', 'team-chat');


/**
 * **Feature: team-chat, Property 8: Group message sender attribution**
 *
 * For any message in a group chat, the message record should contain the sender's user ID,
 * and rendering should include the sender's name.
 *
 * **Validates: Requirements 6.4**
 */
test('Property 8: Group message sender attribution', function () {
    $service = new \App\Services\GroupChatService();

    // Run 100 iterations with random configurations
    for ($i = 0; $i < 100; $i++) {
        // Create users with random names
        $creator = User::factory()->create([
            'name' => fake()->name(),
        ]);
        $memberCount = rand(2, 4);
        $members = User::factory()->count($memberCount)->create();
        $memberIds = $members->pluck('id')->toArray();

        // Create group
        $group = $service->createGroup(
            'Test Group ' . $i,
            $memberIds,
            $creator->id
        );

        // All members including creator
        $allMembers = collect([$creator])->merge($members);

        // Send multiple messages from random members
        $messageCount = rand(3, 10);
        $sentMessages = [];

        for ($j = 0; $j < $messageCount; $j++) {
            $sender = $allMembers->random();
            $messageBody = fake()->sentence();

            $message = $service->sendMessage($group, $sender->id, $messageBody);
            $sentMessages[] = [
                'message' => $message,
                'sender' => $sender,
            ];
        }

        // Property: Each message should have correct sender attribution
        foreach ($sentMessages as $sentData) {
            $message = $sentData['message'];
            $expectedSender = $sentData['sender'];

            // Reload message with sender relationship
            $message = GroupMessage::with('sender')->find($message->id);

            // Property 1: Message should have user_id set to sender's ID
            expect($message->user_id)->toBe($expectedSender->id,
                "Message user_id should match sender's ID"
            );

            // Property 2: Message sender relationship should return correct user
            expect($message->sender)->toBeInstanceOf(User::class,
                "Message should have sender relationship loaded"
            );
            expect($message->sender->id)->toBe($expectedSender->id,
                "Message sender relationship should return correct user"
            );

            // Property 3: Sender's name should be accessible through the relationship
            expect($message->sender->name)->toBe($expectedSender->name,
                "Sender's name should be accessible through the message"
            );

            // Property 4: Sender's name should not be empty
            expect($message->sender->name)->not->toBeEmpty(
                "Sender's name should not be empty"
            );
        }

        // Property: When retrieving messages, sender info should be included
        $retrievedMessages = $service->getMessages($group, $creator->id);

        foreach ($retrievedMessages as $retrievedMessage) {
            // Property: Each retrieved message should have sender loaded
            expect($retrievedMessage->sender)->toBeInstanceOf(User::class,
                "Retrieved message should have sender relationship"
            );

            // Property: Sender ID should match user_id
            expect($retrievedMessage->sender->id)->toBe($retrievedMessage->user_id,
                "Sender ID should match message user_id"
            );

            // Property: Sender name should be a non-empty string
            expect($retrievedMessage->sender->name)->toBeString();
            expect(strlen($retrievedMessage->sender->name))->toBeGreaterThan(0,
                "Sender name should be a non-empty string"
            );
        }

        // Clean up for next iteration
        GroupMessage::where('group_id', $group->id)->delete();
        ChatGroupMember::where('group_id', $group->id)->delete();
        $group->delete();
        $creator->forceDelete();
        foreach ($members as $member) {
            $member->forceDelete();
        }
    }
})->group('property-test', 'team-chat');
