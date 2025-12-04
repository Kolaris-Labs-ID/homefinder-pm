<?php

namespace App\Livewire;

use App\Models\ChatGroup;
use App\Models\User;
use App\Services\GroupChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ManageGroupModal extends Component
{
    public bool $isOpen = false;
    public ?int $groupId = null;
    public string $searchQuery = '';

    #[On('open-manage-group-modal')]
    public function openModal(int $groupId): void
    {
        $this->groupId = $groupId;
        $this->searchQuery = '';
        $this->resetValidation();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->groupId = null;
        $this->searchQuery = '';
        $this->resetValidation();
    }

    public function addMember(int $userId): void
    {
        $adminId = Auth::id();
        if (!$adminId || !$this->groupId) {
            return;
        }

        $group = ChatGroup::find($this->groupId);
        if (!$group) {
            return;
        }

        try {
            app(GroupChatService::class)->addMember($group, $userId, $adminId);
            $this->dispatch('group-updated');
            $this->dispatch('refresh-group-list');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    session()->flash('error', $message);
                }
            }
        }
    }

    public function removeMember(int $userId): void
    {
        $adminId = Auth::id();
        if (!$adminId || !$this->groupId) {
            return;
        }

        $group = ChatGroup::find($this->groupId);
        if (!$group) {
            return;
        }

        try {
            app(GroupChatService::class)->removeMember($group, $userId, $adminId);
            $this->dispatch('group-updated');
            $this->dispatch('refresh-group-list');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    session()->flash('error', $message);
                }
            }
        }
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
    public function members(): Collection
    {
        $group = $this->group;
        if (!$group) {
            return collect();
        }

        return $group->members()->orderBy('name')->get();
    }

    #[Computed]
    public function availableUsers(): Collection
    {
        $group = $this->group;
        if (!$group) {
            return collect();
        }

        $memberIds = $group->members()->pluck('users.id')->toArray();

        $query = User::query()
            ->whereNotIn('id', $memberIds)
            ->orderBy('name');

        if (!empty($this->searchQuery)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                    ->orWhere('email', 'like', '%' . $this->searchQuery . '%');
            });
        }

        return $query->limit(10)->get();
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
        return view('livewire.manage-group-modal');
    }
}
