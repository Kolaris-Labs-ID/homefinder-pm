<?php

namespace App\Livewire;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationDropdown extends Component
{
    public bool $open = false;

    public string $activeTab = 'all';

    public int $limit = 8;

    protected $listeners = [
        'refresh-notifications' => '$refresh',
    ];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['all', 'unread'], true) ? $tab : 'all';
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

    #[Computed]
    public function unreadCount(): int
    {
        $user = Auth::user();

        if (! $user) {
            return 0;
        }

        return $user->unreadNotifications()->count();
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

        if ($this->activeTab === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->limit($this->limit)->get();
    }

    public function render(): View
    {
        return view('livewire.notification-dropdown');
    }
}

