<div
    class="relative"
    x-data="{ open: @entangle('open') }"
    x-on:click.away="open = false"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        x-on:click="open = !open"
    >
        <x-filament::icon icon="heroicon-o-bell" class="h-5 w-5" />
        @if ($this->unreadCount > 0)
            <span
                class="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-pink-500 px-1 text-[0.6rem] font-semibold text-white"
            >
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        class="notification-panel absolute right-2 top-full z-50 mt-3 w-[min(420px,calc(100vw-1.5rem))] rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <div>
                <p class="text-base font-semibold text-slate-900 dark:text-white">Notifications</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Stay updated with tasks and mentions</p>
            </div>
            <button
                wire:click="markAllAsRead"
                type="button"
                class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
            >
                Mark all as read
            </button>
        </div>

        <div class="flex items-center gap-2 px-5 pt-3">
            @php
                $tabs = [
                    'all' => 'All Notifications',
                    'unread' => 'Unread',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="switchTab('{{ $key }}')"
                    @class([
                        'flex-1 rounded-full px-4 py-2 text-xs font-semibold transition',
                        'bg-blue-600 text-white shadow' => $activeTab === $key,
                        'bg-slate-50 text-slate-500 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-slate-100' => $activeTab !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="max-h-[420px] overflow-y-auto px-5 py-3">
            @forelse ($this->notifications as $notification)
                <div
                    @class([
                        'group relative flex gap-3 rounded-2xl p-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/60',
                        'bg-blue-50/60 dark:bg-slate-800/40' => $notification->isUnread(),
                    ])
                >
                    <div class="flex-shrink-0">
                        <div class="relative">
                            <img
                                src="{{ $notification->actor['avatar'] ?? $notification->user?->avatar_url }}"
                                alt="{{ $notification->actor['name'] ?? $notification->user?->name }}"
                                class="h-12 w-12 rounded-full object-cover"
                                onerror="this.src='{{ $notification->user?->avatar_url }}'"
                            >
                            <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full border-2 border-white bg-emerald-400 dark:border-slate-900"></span>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="flex items-center gap-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $notification->actor['name'] ?? $notification->user?->name ?? 'System' }}
                                    @if ($notification->actor['verified'] ?? false)
                                        <x-filament::icon icon="heroicon-s-check-badge" class="h-4 w-4 text-sky-500" />
                                    @endif
                                </p>
                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                    {{ $notification->human_message }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $notification->relative_time }}
                                </p>
                                @if ($notification->isUnread())
                                    <span class="mt-1 inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($notification->action_buttons as $action)
                                <a
                                    href="{{ $action['url'] }}"
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-white transition"
                                    @class([
                                        'bg-slate-900 hover:bg-slate-800' => $action['style'] === 'primary',
                                        'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-700 dark:text-white' => $action['style'] !== 'primary',
                                    ])
                                >
                                    {{ $action['label'] }}
                                </a>
                            @endforeach

                            @if ($notification->isUnread())
                                <button
                                    wire:click="markAsRead({{ $notification->id }})"
                                    type="button"
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400"
                                >
                                    Mark as read
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-2xl bg-slate-50 py-14 text-center dark:bg-slate-800/50">
                    <x-filament::icon icon="heroicon-o-bell" class="h-8 w-8 text-slate-300" />
                    <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-300">You're all caught up!</p>
                    <p class="text-xs text-slate-500">No new notifications right now.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

