<div class="flex h-[calc(100vh-8rem)] rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" style="font-family: Inter, system-ui, sans-serif;">
    {{-- Column 1: Notification List (Master) --}}
    <div class="flex w-[420px] flex-shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        {{-- Header with Title --}}
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h2>
                @if ($this->unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        type="button"
                        class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        {{-- Search & Filter --}}
        <div class="border-b border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            {{-- Search Input --}}
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search notifications..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400"
                >
            </div>

            {{-- Filter Tabs --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @php
                    $tabs = [
                        'all' => 'View all',
                        'projects' => 'Projects',
                        'tasks' => 'Tasks',
                        'feedback' => 'Feedback',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <button
                        type="button"
                        wire:click="switchTab('{{ $key }}')"
                        class="{{ $activeTab === $key
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                        }} rounded-lg px-4 py-2 text-sm font-medium transition-all"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Notification List --}}
        <div class="flex-1 overflow-y-auto">
            @forelse ($this->notifications as $notification)
                @php
                    $isSelected = $selectedId === $notification->id;
                    $typeConfig = match($notification->type) {
                        'comment_added', 'comment_updated' => ['color' => 'bg-blue-500', 'bgLight' => 'bg-blue-50', 'textColor' => 'text-blue-700'],
                        'project_assigned' => ['color' => 'bg-emerald-500', 'bgLight' => 'bg-emerald-50', 'textColor' => 'text-emerald-700'],
                        'project_removed' => ['color' => 'bg-gray-500', 'bgLight' => 'bg-gray-50', 'textColor' => 'text-gray-700'],
                        'ticket_overdue', 'project_overdue' => ['color' => 'bg-red-500', 'bgLight' => 'bg-red-50', 'textColor' => 'text-red-700'],
                        'ticket_nearing_overdue', 'project_nearing_overdue' => ['color' => 'bg-amber-500', 'bgLight' => 'bg-amber-50', 'textColor' => 'text-amber-700'],
                        default => ['color' => 'bg-gray-500', 'bgLight' => 'bg-gray-50', 'textColor' => 'text-gray-700'],
                    };
                    $typeIcons = match($notification->type) {
                        'comment_added', 'comment_updated' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z',
                        'project_assigned' => 'M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z',
                        'project_removed' => 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z',
                        'ticket_overdue', 'project_overdue' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
                        'ticket_nearing_overdue', 'project_nearing_overdue' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                        default => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
                    };
                    $isUrgent = in_array($notification->type, ['ticket_overdue', 'project_overdue']);
                @endphp
                <button
                    type="button"
                    wire:click="selectNotification({{ $notification->id }})"
                    class="{{ $isSelected
                        ? 'border-l-4 border-l-blue-600 bg-blue-50/50 dark:bg-blue-950/30'
                        : 'border-l-4 border-l-transparent hover:bg-gray-50 dark:hover:bg-gray-800'
                    }} w-full border-b border-gray-100 px-5 py-4 text-left transition-all dark:border-gray-800"
                >
                    <div class="flex gap-4">
                        {{-- Status Icon --}}
                        <div class="flex-shrink-0">
                            <div class="{{ $typeConfig['color'] }} flex h-10 w-10 items-center justify-center rounded-full text-white shadow-sm">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcons }}" />
                                </svg>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <p class="{{ $notification->isUnread() ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300' }} text-sm">
                                    {{ $notification->actor['name'] ?? 'System' }}
                                </p>
                                <span class="flex-shrink-0 text-xs text-gray-400">
                                    {{ $notification->relative_time }}
                                </span>
                            </div>
                            <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ $notification->human_message }}
                            </p>

                            {{-- Tags --}}
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if ($notification->data['project_name'] ?? null)
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-500/30">
                                        {{ $notification->data['project_name'] }}
                                    </span>
                                @endif
                                @if ($isUrgent)
                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-500/30">
                                        Urgent
                                    </span>
                                @endif
                                @if ($notification->isUnread())
                                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                @endif
                            </div>
                        </div>
                    </div>
                </button>
            @empty
                <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </div>
                    <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">No notifications</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You're all caught up!</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Column 2: Notification Detail (Detail) --}}
    <div class="flex flex-1 flex-col bg-gray-50 dark:bg-gray-950">
        @if ($this->selectedNotification)
            @php
                $selected = $this->selectedNotification;
                $detailTypeConfig = match($selected->type) {
                    'comment_added', 'comment_updated' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'text' => 'text-blue-800 dark:text-blue-300', 'border' => 'border-blue-200 dark:border-blue-800', 'label' => 'Feedback'],
                    'project_assigned' => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'text' => 'text-emerald-800 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-800', 'label' => 'Project Update'],
                    'project_removed' => ['bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-800 dark:text-gray-300', 'border' => 'border-gray-200 dark:border-gray-700', 'label' => 'Project Update'],
                    'ticket_overdue', 'project_overdue' => ['bg' => 'bg-red-50 dark:bg-red-900/20', 'text' => 'text-red-800 dark:text-red-300', 'border' => 'border-red-200 dark:border-red-800', 'label' => 'Overdue Alert'],
                    'ticket_nearing_overdue', 'project_nearing_overdue' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'text' => 'text-amber-800 dark:text-amber-300', 'border' => 'border-amber-200 dark:border-amber-800', 'label' => 'Due Soon'],
                    default => ['bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-800 dark:text-gray-300', 'border' => 'border-gray-200 dark:border-gray-700', 'label' => 'Notification'],
                };
            @endphp

            {{-- Detail Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ $selected->created_at->format('M j, Y') }}</span>
                    <span class="text-gray-300 dark:text-gray-600">•</span>
                    <span class="{{ $detailTypeConfig['bg'] }} {{ $detailTypeConfig['text'] }} rounded-full px-3 py-1 text-xs font-medium">
                        {{ $detailTypeConfig['label'] }}
                    </span>
                    @if ($selected->data['project_name'] ?? null)
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $selected->data['project_name'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-1">
                    <button
                        wire:click="archiveNotification({{ $selected->id }})"
                        type="button"
                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        title="Archive"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </button>
                    <button
                        wire:click="deleteNotification({{ $selected->id }})"
                        type="button"
                        class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                        title="Delete"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Detail Content --}}
            <div class="flex-1 overflow-y-auto p-6">
                <div class="mx-auto max-w-3xl">
                    {{-- Title --}}
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $selected->human_message }}
                    </h1>

                    {{-- Status Banner --}}
                    <div class="mt-6 flex items-start gap-4 rounded-lg border {{ $detailTypeConfig['border'] }} {{ $detailTypeConfig['bg'] }} p-5">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 {{ $detailTypeConfig['text'] }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold {{ $detailTypeConfig['text'] }}">{{ $detailTypeConfig['label'] }}</p>
                            <p class="mt-1 text-sm {{ $detailTypeConfig['text'] }} opacity-80">{{ $selected->message }}</p>
                        </div>
                    </div>

                    {{-- Details Section --}}
                    <div class="mt-8">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Details</h2>
                        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <div class="space-y-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                <p>{{ $selected->message }}</p>

                                @if ($selected->data['ticket_name'] ?? null)
                                    <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Ticket:</span>
                                        <span class="text-blue-600 dark:text-blue-400">{{ $selected->data['ticket_name'] }}</span>
                                    </div>
                                @endif

                                @if ($selected->data['project_name'] ?? null)
                                    <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Project:</span>
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ $selected->data['project_name'] }}</span>
                                    </div>
                                @endif

                                @if ($selected->actor['name'] ?? null)
                                    <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">From:</span>
                                        <span>{{ $selected->actor['name'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Next Steps / Actions --}}
                    @if (count($selected->action_buttons) > 0)
                        <div class="mt-8">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Next Steps</h2>
                            <div class="mt-4 space-y-3">
                                @foreach ($selected->action_buttons as $index => $action)
                                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-600">
                                        <div class="flex items-center gap-4">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ $index + 1 }}
                                            </span>
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $action['label'] }}</span>
                                        </div>
                                        <a
                                            href="{{ $action['url'] }}"
                                            class="flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                        >
                                            Go
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                @if ($selected->isUnread())
                    <button
                        wire:click="markAsRead({{ $selected->id }})"
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Mark as Read
                    </button>
                @endif
                @if (count($selected->action_buttons) > 0)
                    <a
                        href="{{ $selected->action_buttons[0]['url'] }}"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                    >
                        {{ $selected->action_buttons[0]['label'] }}
                    </a>
                @endif
            </div>
        @else
            {{-- Empty State --}}
            <div class="flex flex-1 flex-col items-center justify-center bg-white text-center dark:bg-gray-900">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z" />
                    </svg>
                </div>
                <h3 class="mt-6 text-xl font-semibold text-gray-900 dark:text-white">Select a notification</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Choose a notification from the list to view details</p>
            </div>
        @endif
    </div>
</div>
