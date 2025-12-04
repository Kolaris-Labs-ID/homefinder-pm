# Implementation Plan

-   [x] 1. Install and configure Filament Chatify Integration plugin

    -   [x] 1.1 Install the package and run setup commands
        -   Run `composer require monzer/filament-chatify-integration`
        -   Run `php artisan filament-chatify-integration:install`
        -   Run `php artisan migrate`
        -   Run `php artisan filament:assets`
        -   _Requirements: 1.1, 1.2, 1.3, 1.4_
    -   [x] 1.2 Create custom Chat page extending the plugin
        -   Create `app/Filament/Pages/Chat.php` extending `Monzer\FilamentChatifyIntegration\Pages\Chatify`
        -   Configure slug, navigation label, icon, and group
        -   _Requirements: 3.1, 3.2_
    -   [x] 1.3 Register ChatifyPlugin in AdminPanelProvider
        -   Add `ChatifyPlugin::make()->customPage(Chat::class)` to plugins array
        -   Remove existing placeholder NavigationItem for chat
        -   _Requirements: 3.1, 3.2, 4.1, 4.2_
    -   [x] 1.4 Write integration test for chat page access
        -   Test authenticated user can access chat page
        -   Test unauthenticated user is redirected to login
        -   _Requirements: 4.1_

-   [x] 2. Checkpoint - Verify direct messaging works

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 3. Create group chat database structure

    -   [x] 3.1 Create migration for chat_groups table
        -   Add columns: id, name, description, avatar, created_by, timestamps
        -   Add foreign key to users table
        -   _Requirements: 6.1, 6.2_
    -   [x] 3.2 Create migration for chat_group_members table
        -   Add columns: id, group_id, user_id, role (enum: admin/member), joined_at, last_read_at
        -   Add foreign keys and unique constraint on group_id + user_id
        -   _Requirements: 6.5, 6.6_
    -   [x] 3.3 Create migration for chat_group_messages table
        -   Add columns: id, group_id, user_id, body, attachment, timestamps
        -   Add foreign keys to chat_groups and users
        -   _Requirements: 6.3, 6.4_
    -   [x] 3.4 Run migrations
        -   Execute `php artisan migrate`
        -   _Requirements: 6.1, 6.2, 6.3_

-   [x] 4. Create group chat Eloquent models

    -   [x] 4.1 Create ChatGroup model
        -   Define fillable fields, relationships (members, messages, creator, admins)
        -   Add helper methods for membership checks
        -   _Requirements: 6.1, 6.5, 6.6_
    -   [x] 4.2 Create ChatGroupMember pivot model
        -   Define table, casts for datetime fields
        -   Add scope for admins
        -   _Requirements: 6.5, 6.6_
    -   [x] 4.3 Create GroupMessage model
        -   Define fillable fields, relationships (group, sender)
        -   _Requirements: 6.3, 6.4_
    -   [x] 4.4 Write property test for group name requirement
        -   **Property 6: Group name requirement**
        -   **Validates: Requirements 6.2**
    -   [x] 4.5 Write unit tests for model relationships
        -   Test ChatGroup relationships
        -   Test GroupMessage relationships
        -   _Requirements: 6.1, 6.3_

-   [x] 5. Implement GroupChatService

    -   [x] 5.1 Create GroupChatService class
        -   Implement createGroup method with validation
        -   Implement addMember and removeMember with admin checks
        -   Implement sendMessage method
        -   _Requirements: 6.1, 6.2, 6.3, 6.6, 6.7_
    -   [x] 5.2 Implement message retrieval and read status
        -   Implement getMessages method
        -   Implement markAsRead method
        -   Implement getUnreadCount method
        -   _Requirements: 6.3, 2.2, 2.3_
    -   [x] 5.3 Implement getUserGroups method
        -   Return all groups a user belongs to
        -   _Requirements: 6.5_
    -   [x] 5.4 Write property test for minimum group size validation
        -   **Property 11: Minimum group size validation**
        -   **Validates: Requirements 6.7**
    -   [x] 5.5 Write property test for group message delivery
        -   **Property 7: Group message delivery**
        -   **Validates: Requirements 6.3**
    -   [x] 5.6 Write property test for admin permission enforcement
        -   **Property 10: Admin permission enforcement**
        -   **Validates: Requirements 6.6**
    -   [x] 5.7 Write property test for group visibility after membership
        -   **Property 9: Group visibility after membership**
        -   **Validates: Requirements 6.5**

-   [x] 6. Checkpoint - Verify group chat service

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 7. Create group chat Livewire components

    -   [x] 7.1 Create GroupChatList component
        -   Display user's groups with unread indicators
        -   Handle group selection
        -   _Requirements: 6.5, 2.2_
    -   [x] 7.2 Create GroupChatConversation component
        -   Display group messages with sender names
        -   Handle message sending
        -   Mark messages as read on view
        -   _Requirements: 6.3, 6.4, 2.3_
    -   [x] 7.3 Create CreateGroupModal component
        -   Form for group name and member selection
        -   Validation for minimum members
        -   _Requirements: 6.1, 6.2, 6.7_
    -   [x] 7.4 Create ManageGroupModal component
        -   Allow admins to add/remove members
        -   Display current members with roles
        -   _Requirements: 6.6_
    -   [x] 7.5 Write property test for group message sender attribution
        -   **Property 8: Group message sender attribution**
        -   **Validates: Requirements 6.4**

-   [x] 8. Create group chat Blade views

    -   [x] 8.1 Create group-chat-list view
        -   List groups with avatars and names
        -   Show unread message count badges
        -   _Requirements: 6.5, 2.2_
    -   [x] 8.2 Create group-chat-conversation view
        -   Message bubbles with sender names and timestamps
        -   Message input form
        -   _Requirements: 6.3, 6.4_
    -   [x] 8.3 Create create-group-modal view
        -   Group name input
        -   Multi-select for members
        -   Submit button
        -   _Requirements: 6.1, 6.2_
    -   [x] 8.4 Create manage-group-modal view
        -   Member list with remove buttons
        -   Add member input
        -   _Requirements: 6.6_

-   [x] 9. Integrate group chat into Chat page

    -   [x] 9.1 Extend Chat page to include group chat section
        -   Add tab or section for group chats alongside direct messages
        -   Wire up Livewire components
        -   _Requirements: 6.1, 6.5_
    -   [x] 9.2 Add "Create Group" button to chat interface
        -   Open CreateGroupModal on click
        -   _Requirements: 6.1_

-   [x] 10. Checkpoint - Verify group chat UI

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 11. Implement real-time updates for group chat

    -   [x] 11.1 Create GroupMessageSent event
        -   Broadcast to group channel
        -   Include message data and sender info
        -   _Requirements: 2.1, 6.3_
    -   [x] 11.2 Configure broadcasting for group channels
        -   Add channel authorization in routes/channels.php
        -   _Requirements: 2.1_
    -   [x] 11.3 Add Echo listeners to Livewire components
        -   Listen for new messages in GroupChatConversation
        -   Update unread counts in GroupChatList
        -   _Requirements: 2.1, 2.2_

-   [x] 12. Add direct message property tests

    -   [x] 12.1 Write property test for message persistence round-trip
        -   **Property 1: Message persistence round-trip**
        -   **Validates: Requirements 1.3, 1.4**
    -   [x] 12.2 Write property test for empty message rejection
        -   **Property 2: Empty message rejection**
        -   **Validates: Requirements 1.5**
    -   [x] 12.3 Write property test for unread count accuracy
        -   **Property 3: Unread count accuracy**
        -   **Validates: Requirements 2.2**
    -   [x] 12.4 Write property test for mark as read consistency
        -   **Property 4: Mark as read consistency**
        -   **Validates: Requirements 2.3**
    -   [x] 12.5 Write property test for user search filtering
        -   **Property 5: User search filtering**
        -   **Validates: Requirements 5.1**

-   [x] 13. Final Checkpoint - Make sure all tests are passing
    -   Ensure all tests pass, ask the user if questions arise.
