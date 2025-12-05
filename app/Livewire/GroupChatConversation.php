<?php

namespace App\Livewire;

use App\Models\ChatGroup;
use App\Services\GroupChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class GroupChatConversation extends Component
{
    public ?int $groupId = null;
    public string $messageBody = '';

    /**
     * Get the listeners for the component.
     * We use this method instead of $listeners property to handle dynamic Echo channels.
     */
    public function getListeners(): array
    {
        $listeners = [
            'group-message-sent' => '$refresh',
        ];

        // Only add Echo listener if we have a group selected
        if ($this->groupId) {
            $listeners["echo-private:group-chat.{$this->groupId},message.sent"] = 'handleNewMessage';
        }

        return $listeners;
    }

    protected function rules(): array
    {
        return [
            'messageBody' => 'required|string|min:1',
        ];
    }

    #[On('group-selected')]
    public function loadGroup(int $groupId): void
    {
        $this->groupId = $groupId;
        $this->messageBody = '';
        $this->markMessagesAsRead();
    }

    /**
     * Handle incoming broadcast message from Echo.
     */
    public function handleNewMessage(array $payload): void
    {
        // Refresh the messages list when a new message arrives
        // The message is already in the database, so we just need to refresh
        $this->dispatch('refresh-group-list');
    }

    public function sendMessage(): void
    {
        $this->validate();

        $userId = Auth::id();
        if (!$userId || !$this->groupId) {
            return;
        }

        $group = ChatGroup::find($this->groupId);
        if (!$group) {
            return;
        }

        try {
            app(GroupChatService::class)->sendMessage($group, $userId, $this->messageBody);
            $this->messageBody = '';
            $this->dispatch('group-message-sent');
            $this->dispatch('refresh-group-list');
        } catch (ValidationException $e) {
            $this->addError('messageBody', $e->getMessage());
        }
    }

    public function markMessagesAsRead(): void
    {
        $userId = Auth::id();
        if (!$userId || !$this->groupId) {
            return;
        }

        $group = ChatGroup::find($this->groupId);
        if (!$group) {
            return;
        }

        app(GroupChatService::class)->markAsRead($group, $userId);
        $this->dispatch('refresh-group-list');
    }

    #[Computed]
    public function group(): ?ChatGroup
    {
        if (!$this->groupId) {
            return null;
        }

        return ChatGroup::with(['members', 'creator'])->find($this->groupId);
    }

    #[Computed]
    public function groupMessages(): Collection
    {
        $userId = Auth::id();
        if (!$userId || !$this->groupId) {
            return collect();
        }

        $group = ChatGroup::find($this->groupId);
        if (!$group) {
            return collect();
        }

        try {
            return app(GroupChatService::class)->getMessages($group, $userId);
        } catch (ValidationException $e) {
            return collect();
        }
    }

    #[Computed]
    public function isAdmin(): bool
    {
        $user = Auth::user();
        $group = $this->group;

        if (!$user || !$group) {
            return false;
        }

        return $group->isAdmin($user);
    }

    public function render(): View
    {
        return view('livewire.group-chat-conversation');
    }
}
