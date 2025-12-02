<div
    class="relative"
    x-data="{
        open: false,
        dropdownStyle: '',
        dropdownWidth: 420,
        init() {
            this.$watch('open', value => {
                if (value) {
                    this.$nextTick(() => {
                        setTimeout(() => this.positionDropdown(), 10);
                    });
                }
            });
        },
        positionDropdown() {
            const button = this.$refs.bellButton;
            const dropdown = this.$refs.dropdown;
            if (!button || !dropdown) return;

            const buttonRect = button.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const margin = 12;
            const verticalOffset = 12;

            const availableLeftSpace = Math.max(buttonRect.right - margin, margin);
            let width = Math.min(this.dropdownWidth, availableLeftSpace);
            let right = Math.max(0, viewportWidth - buttonRect.right);

            const fallbackWidth = Math.min(this.dropdownWidth, viewportWidth - (margin * 2));

            if (availableLeftSpace < 260) {
                width = fallbackWidth;
                right = margin;
            }

            let top = buttonRect.bottom + verticalOffset;
            const dropdownHeight = dropdown.offsetHeight || 500;

            if (top + dropdownHeight > viewportHeight - margin) {
                top = buttonRect.top - dropdownHeight - 12;
                if (top < margin) {
                    top = margin;
                }
            }

            this.dropdownStyle = `position: fixed; right: ${right}px; top: ${top}px; width: ${width}px;`;
        },
        closeDropdown() {
            this.open = false;
        }
    }"
    x-on:resize.window.debounce.100ms="if (open) positionDropdown()"
    x-on:click.away="closeDropdown()"
    x-on:keydown.escape.window="closeDropdown()"
    wire:ignore.self
>
    {{-- Bell Button --}}
    <button
        x-ref="bellButton"
        type="button"
        class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-slate-50 to-slate-100 text-slate-600 shadow-sm ring-1 ring-slate-200/80 transition-all duration-200 hover:from-white hover:to-slate-50 hover:text-slate-900 hover:shadow-md hover:ring-slate-300 dark:from-slate-800 dark:to-slate-900 dark:text-slate-300 dark:ring-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-800 dark:hover:text-white"
        x-on:click="open = !open"
        aria-label="Notifications"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-rose-600 text-[10px] font-bold text-white shadow-lg ring-2 ring-white dark:ring-slate-900">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-ref="dropdown"
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        :style="dropdownStyle"
        class="z-50 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl shadow-slate-200/50 dark:border-slate-700/80 dark:bg-slate-900 dark:shadow-black/30"
    >
        {{-- Header --}}
        <div class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-5 py-4 dark:border-slate-800">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'30\' height=\'30\' viewBox=\'0 0 30 30\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z\' fill=\'rgba(255,255,255,0.07)\'/%3E%3C/svg%3E')] opacity-50"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-white">Notifications</h3>
                    <p class="mt-0.5 text-xs text-white/70">Stay updated with your tasks</p>
                </div>
                @if ($this->unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-sm transition hover:bg-white/30"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-slate-100 bg-slate-50/50 px-5 dark:border-slate-800 dark:bg-slate-800/50">
            <div class="flex items-center gap-1">
                @php
                    $tabs = [
                        'all' => ['label' => 'All', 'icon' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z'],
                        'unread' => ['label' => 'Unread', 'icon' => 'M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z'],
                    ];
                @endphp
                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        wire:click="switchTab('{{ $key }}')"
                        @class([
                            'relative flex items-center gap-2 px-4 py-3 text-sm font-medium transition-all duration-200',
                            'text-indigo-600 dark:text-indigo-400' => $activeTab === $key,
                            'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $activeTab !== $key,
                        ])
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" />
                        </svg>
                        {{ $tab['label'] }}
                        @if ($key === 'unread' && $this->unreadCount > 0)
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
                                {{ $this->unreadCount }}
                            </span>
                        @endif
                        @if ($activeTab === $key)
                            <span class="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Notification List --}}
        <div class="max-h-[400px] overflow-y-auto">
            @forelse ($this->notifications as $notification)
                @php
                    $typeConfig = match($notification->type) {
                        'comment_added', 'comment_updated' => ['color' => 'blue', 'icon' => 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
                        'project_assigned' => ['color' => 'emerald', 'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'],
                        'project_removed' => ['color' => 'slate', 'icon' => 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z'],
                        'ticket_overdue', 'project_overdue' => ['color' => 'red', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
                        'ticket_nearing_overdue', 'project_nearing_overdue' => ['color' => 'amber', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                        default => ['color' => 'slate', 'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
                    };
                    $colorClasses = match($typeConfig['color']) {
                        'blue' => 'from-blue-500 to-indigo-600 ring-blue-100 dark:ring-blue-900/30',
                        'emerald' => 'from-emerald-500 to-teal-600 ring-emerald-100 dark:ring-emerald-900/30',
                        'red' => 'from-red-500 to-rose-600 ring-red-100 dark:ring-red-900/30',
                        'amber' => 'from-amber-500 to-orange-600 ring-amber-100 dark:ring-amber-900/30',
                        default => 'from-slate-500 to-slate-600 ring-slate-100 dark:ring-slate-700',
                    };
                    $bgClasses = match($typeConfig['color']) {
                        'blue' => 'bg-blue-50/50 dark:bg-blue-950/20',
                        'emerald' => 'bg-emerald-50/50 dark:bg-emerald-950/20',
                        'red' => 'bg-red-50/50 dark:bg-red-950/20',
                        'amber' => 'bg-amber-50/50 dark:bg-amber-950/20',
                        default => 'bg-slate-50/50 dark:bg-slate-800/20',
                    };
                @endphp
                <div
                    @class([
                        'group relative border-b border-slate-100 px-5 py-4 transition-all duration-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50',
                        $bgClasses => $notification->isUnread(),
                    ])
                >
                    <div class="flex gap-4">
                        {{-- Icon --}}
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br {{ $colorClasses }} text-white shadow-lg ring-4">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeConfig['icon'] }}" />
                                </svg>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $notification->actor['name'] ?? 'System' }}
                                        @if ($notification->isUnread())
                                            <span class="inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                        {{ $notification->human_message }}
                                    </p>
                                </div>
                                <span class="flex-shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $notification->relative_time }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @foreach ($notification->action_buttons as $action)
                                    <a
                                        href="{{ $action['url'] }}"
                                        @class([
                                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                                            'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-200 hover:shadow-lg hover:shadow-indigo-300 dark:shadow-indigo-900/30' => $action['style'] === 'primary',
                                            'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' => $action['style'] !== 'primary',
                                        ])
                                    >
                                        {{ $action['label'] }}
                                        @if ($action['style'] === 'primary')
                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        @endif
                                    </a>
                                @endforeach

                                @if ($notification->isUnread())
                                    <button
                                        wire:click="markAsRead({{ $notification->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        Mark as read
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700">
                        <svg class="h-8 w-8 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-300">You're all caught up!</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No {{ $activeTab === 'unread' ? 'unread ' : '' }}notifications</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if ($this->notifications->isNotEmpty())
            <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/50">
                <a
                    href="{{ route('filament.admin.resources.notifications.index') }}"
                    class="flex items-center justify-center gap-2 text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    View all notifications
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</div>
