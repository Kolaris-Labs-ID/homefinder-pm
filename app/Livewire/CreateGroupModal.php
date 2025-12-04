<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\GroupChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateGroupModal extends Component
{
    public bool $isOpen = false;
    public string $groupName = '';
    public array $selectedMembers = [];
    public string $searchQuery = '';

    protected function rules(): array
    {
        return [
            'groupName' => 'required|string|min:1|max:255',
            'selectedMembers' => 'required|array|min:2',
            'selectedMembers.*' => 'exists:users,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'groupName.required' => 'Group name is required.',
            'selectedMembers.required' => 'Please select at least 2 members.',
            'selectedMembers.min' => 'A group must have at least 2 members (excluding yourself).',
        ];
    }

    #[On('open-create-group-modal')]
    public function openModal(): void
    {
        $this->reset(['groupName', 'selectedMembers', 'searchQuery']);
        $this->resetValidation();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['groupName', 'selectedMembers', 'searchQuery']);
        $this->resetValidation();
    }

    public function toggleMember(int $userId): void
    {
        if (in_array($userId, $this->selectedMembers)) {
            $this->selectedMembers = array_values(array_diff($this->selectedMembers, [$userId]));
        } else {
            $this->selectedMembers[] = $userId;
        }
    }

    public function createGroup(): void
    {
        $this->validate();

        $userId = Auth::id();
        if (!$userId) {
            return;
        }

        try {
            app(GroupChatService::class)->createGroup(
                $this->groupName,
                $this->selectedMembers,
                $userId
            );

            $this->closeModal();
            $this->dispatch('group-created');
            $this->dispatch('refresh-group-list');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    #[Computed]
    public function availableUsers(): Collection
    {
        $currentUserId = Auth::id();

        $query = User::query()
            ->where('id', '!=', $currentUserId)
            ->orderBy('name');

        if (!empty($this->searchQuery)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                    ->orWhere('email', 'like', '%' . $this->searchQuery . '%');
            });
        }

        return $query->limit(20)->get();
    }

    #[Computed]
    public function selectedUsers(): Collection
    {
        if (empty($this->selectedMembers)) {
            return collect();
        }

        return User::whereIn('id', $this->selectedMembers)->get();
    }

    public function render(): View
    {
        return view('livewire.create-group-modal');
    }
}
