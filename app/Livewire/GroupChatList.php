<?php

namespace App\Livewire;

use App\Models\ChatGroup;
use App\Services\GroupChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GroupChatList extends Component
{
    public ?int $selectedGroupId = null;

    protected $listeners = [
        'group-created' => '$refresh',
        'group-updated' => '$refresh',
        'group-message-sent' => '$refresh',
        'refresh-group-list' => '$refresh',
    ];

    /**
     * Get the Echo listeners for real-time updates.
     * This method returns an array of channel listeners for all groups the user belongs to.
     */
    public function getListeners(): array
    {
        $listeners = [
            'group-created' => '$refresh',
            'group-updated' => '$refresh',
            'group-message-sent' => '$refresh',
            'refresh-group-list' => '$refresh',
        ];

        // Add Echo listeners for each group the user belongs to
        $userId = Auth::id();
        if ($userId) {
            $groups = app(GroupChatService::class)->getUserGroups($userId);
            foreach ($groups as $group) {
                $listeners["echo-private:group-chat.{$group->id},message.sent"] = 'handleBroadcastMessage';
            }
        }

        return $listeners;
    }

    /**
     * Handle incoming broadcast message from any group.
     */
    public function handleBroadcastMessage(array $payload): void
    {
        // Refresh the group list to update unread counts
        // The $refresh will recalculate all unread counts
    }

    public function selectGroup(int $groupId): void
    {
        $this->selectedGroupId = $groupId;
        $this->dispatch('group-selected', groupId: $groupId);
    }

    #[Computed]
    public function groups(): Collection
    {
        $userId = Auth::id();

        if (!$userId) {
            return collect();
        }

        return app(GroupChatService::class)->getUserGroupsWithUnreadCounts($userId);
    }

    #[Computed]
    public function totalUnreadCount(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            return 0;
        }

        return app(GroupChatService::class)->getUnreadCount($userId);
    }

    public function render(): View
    {
        return view('livewire.group-chat-list');
    }
}
