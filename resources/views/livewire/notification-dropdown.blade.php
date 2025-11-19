<div
    class="relative"
    x-data="{
        open: @entangle('open'),
        dropdownStyle: '',
        dropdownWidth: 420,
        positionDropdown() {
            const button = $refs.bellButton;
            const dropdown = $refs.dropdown;
            if (!button || !dropdown) return;
            
            const buttonRect = button.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const margin = 12; // 0.75rem
            
            // Calculate width
            const maxWidth = Math.min(this.dropdownWidth, viewportWidth - (margin * 2));
            
            // Calculate horizontal position
            let left = buttonRect.right - maxWidth;
            if (left < margin) {
                left = margin;
            }
            if (left + maxWidth > viewportWidth - margin) {
                left = viewportWidth - maxWidth - margin;
            }
            
            // Calculate vertical position
            let top = buttonRect.bottom + 12; // mt-3
            
            // Check if dropdown would overflow bottom of viewport
            const dropdownHeight = dropdown.offsetHeight || 500; // estimate if not rendered
            if (top + dropdownHeight > viewportHeight - margin) {
                // Position above the button if it would overflow
                top = buttonRect.top - dropdownHeight - 12;
                if (top < margin) {
                    top = margin; // Fallback to top of viewport
                }
            }
            
            this.dropdownStyle = `position: fixed; left: ${left}px; top: ${top}px; width: ${maxWidth}px;`;
        }
    }"
    x-init="$watch('open', value => { if (value) { $nextTick(() => positionDropdown()) } })"
    x-on:resize.window.debounce.100ms="if (open) positionDropdown()"
    x-on:click.away="open = false"
    x-on:keydown.escape.window="open = false"
>
    <button
        x-ref="bellButton"
        type="button"
        class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        x-on:click="open = !open"
    >
        <x-filament::icon icon="heroicon-o-bell" class="h-5 w-5" />
        @if ($this->unreadCount > 0)
            <span
                class="absolute top-1 right-1 inline-flex h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"
            ></span>
        @endif
    </button>

    <div
        x-ref="dropdown"
        x-cloak
        x-show="open"
        x-transition.opacity
        :style="dropdownStyle"
        class="notification-panel z-50 rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 dark:border-slate-700">
            <div>
                <p class="text-lg font-bold text-slate-900 dark:text-white">Notifications</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Stay updated with tasks and mentions</p>
            </div>
            <button
                wire:click="markAllAsRead"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-transparent px-3 py-1.5 text-xs font-medium text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
            >
                <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4" />
                Mark all as read
            </button>
        </div>

        <div class="px-6 pt-4">
            <div class="flex items-center gap-6 border-b border-slate-200 dark:border-slate-700">
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
                            'relative pb-3 text-sm font-semibold transition',
                            'text-slate-900 dark:text-white after:absolute after:left-0 after:bottom-0 after:h-0.5 after:w-full after:rounded-full after:bg-blue-600' => $activeTab === $key,
                            'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $activeTab !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="max-h-[420px] overflow-y-auto px-6 py-4">
            @forelse ($this->notifications as $notification)
                <div
                    @class([
                        'group relative flex gap-4 rounded-xl p-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/60',
                        'bg-blue-50/50 dark:bg-slate-800/30' => $notification->isUnread(),
                    ])
                >
                    <div class="flex-shrink-0">
                        <div class="relative">
                            <img
                                src="{{ $notification->actor['avatar'] ?? $notification->user?->avatar_url }}"
                                alt="{{ $notification->actor['name'] ?? $notification->user?->name }}"
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-700"
                                onerror="this.src='{{ $notification->user?->avatar_url }}'"
                            >
                            <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full border-2 border-white bg-emerald-400 dark:border-slate-900"></span>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $notification->actor['name'] ?? $notification->user?->name ?? 'System' }}
                                    @if ($notification->actor['verified'] ?? false)
                                        <x-filament::icon icon="heroicon-s-check-badge" class="h-4 w-4 flex-shrink-0 text-blue-500" />
                                    @endif
                                </p>
                                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                    {{ $notification->human_message }}
                                </p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $notification->relative_time }}
                                </p>
                                @if ($notification->isUnread())
                                    <span class="mt-1.5 inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @foreach ($notification->action_buttons as $action)
                                <a
                                    href="{{ $action['url'] }}"
                                    class="inline-flex items-center rounded-full px-4 py-1.5 text-xs font-semibold transition"
                                    @class([
                                        'bg-slate-900 text-white hover:bg-slate-800 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700' => $action['style'] === 'primary',
                                        'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' => $action['style'] !== 'primary',
                                    ])
                                >
                                    {{ $action['label'] }}
                                </a>
                            @endforeach

                            @if ($notification->isUnread())
                                <button
                                    wire:click="markAsRead({{ $notification->id }})"
                                    type="button"
                                    class="inline-flex items-center rounded-full bg-transparent px-4 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                                >
                                    Mark as read
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-xl bg-slate-50 py-16 text-center dark:bg-slate-800/50">
                    <x-filament::icon icon="heroicon-o-bell" class="h-10 w-10 text-slate-300 dark:text-slate-600" />
                    <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-300">You're all caught up!</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No new notifications right now.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

