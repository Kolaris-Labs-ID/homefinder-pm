<?php

namespace App\Livewire;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class NotificationCenter extends Component
{
    #[Url]
    public string $activeTab = 'all';

    #[Url]
    public ?int $selectedId = null;

    public string $search = '';

    protected $listeners = [
        'refresh-notifications' => '$refresh',
    ];

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['all', 'projects', 'tasks', 'feedback'], true) ? $tab : 'all';
        $this->selectedId = null;
    }

    public function selectNotification(int $id): void
    {
        $this->selectedId = $id;

        // Auto mark as read when selected
        $notification = Notification::find($id);
        if ($notification && $notification->isUnread() && $notification->user_id === Auth::id()) {
            app(NotificationService::class)->markAsRead($id, Auth::id());
        }
    }

    public function markAsRead(int $notificationId): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        app(NotificationService::class)->markAsRead($notificationId, $userId);
        $this->dispatch('refresh-notifications');
    }

    public function markAllAsRead(): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        app(NotificationService::class)->markAllAsRead($userId);
        $this->dispatch('refresh-notifications');
    }

    public function deleteNotification(int $id): void
    {
        $notification = Notification::find($id);
        if ($notification && $notification->user_id === Auth::id()) {
            $notification->delete();

            if ($this->selectedId === $id) {
                $this->selectedId = null;
            }

            $this->dispatch('refresh-notifications');
        }
    }

    public function archiveNotification(int $id): void
    {
        // For now, archive = mark as read
        $this->markAsRead($id);
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = Auth::user();
        return $user ? $user->unreadNotifications()->count() : 0;
    }

    #[Computed]
    public function notifications(): Collection
    {
        $userId = Auth::id();
        if (! $userId) {
            return collect();
        }

        $query = Notification::query()
            ->where('user_id', $userId)
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('message', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%");
            });
        }

        // Filter by tab/type
        if ($this->activeTab === 'projects') {
            $query->whereIn('type', ['project_assigned', 'project_removed', 'project_overdue', 'project_nearing_overdue']);
        } elseif ($this->activeTab === 'tasks') {
            $query->whereIn('type', ['ticket_overdue', 'ticket_nearing_overdue']);
        } elseif ($this->activeTab === 'feedback') {
            $query->whereIn('type', ['comment_added', 'comment_updated']);
        }

        return $query->limit(50)->get();
    }

    #[Computed]
    public function selectedNotification(): ?Notification
    {
        if (! $this->selectedId) {
            return null;
        }

        return Notification::with(['user'])->find($this->selectedId);
    }

    public function render(): View
    {
        return view('livewire.notification-center');
    }
}
