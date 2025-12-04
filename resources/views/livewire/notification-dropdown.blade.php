<div
    class="relative"
    x-data="{ open: false }"
    x-on:click.away="open = false"
    x-on:keydown.escape.window="open = false"
>
    {{-- Bell Button --}}
    <button
        type="button"
        class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
        x-on:click="open = !open"
        aria-label="Notifications"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900">
                {{ $this->unreadCount > 99 ? '99' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute left-0 top-full z-50 mt-2 w-[480px] origin-top-left overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
    >
        {{-- Header --}}
        <div class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                </div>
                <div class="flex items-center gap-2">
                    @if ($this->unreadCount > 0)
                        <button
                            wire:click="markAllAsRead"
                            type="button"
                            class="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Mark all as read
                        </button>
                    @endif
                    <button
                        x-on:click="open = false"
                        type="button"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-6 border-b border-gray-200 bg-white px-6 dark:border-gray-700 dark:bg-gray-900">
            <button
                type="button"
                wire:click="switchTab('all')"
                class="{{ $activeTab === 'all' ? 'border-b-2 border-indigo-600 text-gray-900 dark:text-white' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }} pb-3 pt-2 text-sm font-medium transition"
            >
                For you
                @if ($this->notifications->count() > 0)
                    <span class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ $this->notifications->count() }}
                    </span>
                @endif
            </button>
            <button
                type="button"
                wire:click="switchTab('unread')"
                class="{{ $activeTab === 'unread' ? 'border-b-2 border-indigo-600 text-gray-900 dark:text-white' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }} pb-3 pt-2 text-sm font-medium transition"
            >
                Unread
                @if ($this->unreadCount > 0)
                    <span class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ $this->unreadCount }}
                    </span>
                @endif
            </button>
        </div>

        {{-- Notification List --}}
        <div class="max-h-[600px] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
            @forelse ($this->notifications as $notification)
                @php
                    $typeColors = match($notification->type) {
                        'comment_added', 'comment_updated' => 'bg-blue-500',
                        'project_assigned' => 'bg-emerald-500',
                        'project_removed' => 'bg-gray-500',
                        'ticket_overdue', 'project_overdue' => 'bg-red-500',
                        'ticket_nearing_overdue', 'project_nearing_overdue' => 'bg-amber-500',
                        default => 'bg-gray-500',
                    };
                    $typeIcons = match($notification->type) {
                        'comment_added', 'comment_updated' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z',
                        'project_assigned' => 'M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z',
                        'project_removed' => 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z',
                        'ticket_overdue', 'project_overdue' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
                        'ticket_nearing_overdue', 'project_nearing_overdue' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                        default => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
                    };
                @endphp
                <div class="{{ $notification->isUnread() ? 'bg-blue-50/50 dark:bg-blue-950/20' : 'bg-white dark:bg-gray-900' }} px-6 py-4 transition hover:bg-gray-50 dark:hover:bg-gray-800">
                    <div class="flex gap-4">
                        {{-- Icon --}}
                        <div class="flex-shrink-0">
                            <div class="{{ $typeColors }} flex h-10 w-10 items-center justify-center rounded-full text-white shadow-sm">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcons }}" />
                                </svg>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $notification->actor['name'] ?? 'System' }}
                                        </p>
                                        @if ($notification->isUnread())
                                            <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                        {{ $notification->human_message }}
                                    </p>
                                </div>
                                <span class="flex-shrink-0 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $notification->relative_time }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            @if (count($notification->action_buttons) > 0 || $notification->isUnread())
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    @foreach ($notification->action_buttons as $action)
                                        <a
                                            href="{{ $action['url'] }}"
                                            class="{{ $action['style'] === 'primary' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' }} inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                        >
                                            {{ $action['label'] }}
                                        </a>
                                    @endforeach

                                    @if ($notification->isUnread())
                                        <button
                                            wire:click="markAsRead({{ $notification->id }})"
                                            type="button"
                                            class="text-xs font-medium text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                        >
                                            Mark as read
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </div>
                    <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">All caught up!</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No {{ $activeTab === 'unread' ? 'unread ' : '' }}notifications</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if ($this->notifications->isNotEmpty())
            <div class="border-t border-gray-200 bg-white px-6 py-3 dark:border-gray-700 dark:bg-gray-900">
                <a
                    href="{{ route('filament.admin.resources.notifications.index') }}"
                    class="flex items-center justify-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    View All Notifications
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</div>
