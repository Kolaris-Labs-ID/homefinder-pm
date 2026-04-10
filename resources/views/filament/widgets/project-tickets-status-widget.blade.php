<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
                <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Project & Ticket Status
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Tickets grouped by kanban status across your projects
                </p>
            </div>

            {{-- Toggle: My Tickets / All Tickets --}}
            <div class="flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800 self-start sm:self-auto">
                <button
                    wire:click="toggleViewMode('my_tickets')"
                    @class([
                        'px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-150 focus:outline-none',
                        'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' => $viewMode === 'my_tickets',
                        'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $viewMode !== 'my_tickets',
                    ])
                >
                    My Tickets
                </button>
                <button
                    wire:click="toggleViewMode('all_tickets')"
                    @class([
                        'px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-150 focus:outline-none',
                        'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' => $viewMode === 'all_tickets',
                        'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $viewMode !== 'all_tickets',
                    ])
                >
                    All Tickets
                </button>
            </div>
        </div>

        @php
            $widgetData = $this->getWidgetData();
        @endphp

        @if ($widgetData->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <x-heroicon-o-rectangle-stack class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No active projects found</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">You are not a member of any active projects yet.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($widgetData as $row)
                    @php
                        $project = $row['project'];
                        $statuses = $row['statuses'];
                        $overdue = $row['overdue'];
                        $total = $row['total'];
                        $isExpanded = $row['is_expanded'];
                        $overdueTickets = $row['overdue_tickets'];
                    @endphp

                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-900 shadow-sm">

                        {{-- Project Row (clickable header) --}}
                        <button
                            wire:click="toggleProject({{ $project->id }})"
                            class="w-full flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150 text-left focus:outline-none"
                        >
                            {{-- Expand/Collapse Icon + Project Name --}}
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="flex-shrink-0 text-gray-400 dark:text-gray-500 transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                                <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                    {{ $project->name }}
                                </span>
                                @if ($project->ticket_prefix)
                                    <span class="flex-shrink-0 text-xs text-gray-400 dark:text-gray-500 font-mono">
                                        {{ $project->ticket_prefix }}
                                    </span>
                                @endif
                            </div>

                            {{-- Status Badges --}}
                            <div class="flex flex-wrap items-center gap-1.5 pl-6 sm:pl-0">
                                {{-- Overdue badge (always shown if > 0) --}}
                                @if ($overdue > 0)
                                    <span 
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border shadow-sm"
                                        style="background-color: #dc2626; color: white; border-color: #b91c1c;"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                        </svg>
                                        Overdue: {{ $overdue }}
                                    </span>
                                @endif

                                {{-- Per-status badges --}}
                                @foreach ($statuses as $statusItem)
                                    @if ($statusItem['count'] > 0)
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border"
                                            style="
                                                background-color: {{ $statusItem['color'] }}22;
                                                color: {{ $statusItem['color'] }};
                                                border-color: {{ $statusItem['color'] }}55;
                                            "
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                style="background-color: {{ $statusItem['color'] }}"
                                            ></span>
                                            {{ $statusItem['name'] }}: {{ $statusItem['count'] }}
                                        </span>
                                    @endif
                                @endforeach

                                {{-- Zero state --}}
                                @if ($total === 0 && $overdue === 0)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">
                                        No {{ $viewMode === 'my_tickets' ? 'assigned ' : '' }}tickets
                                    </span>
                                @endif

                                {{-- Total --}}
                                @if ($total > 0)
                                    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap">
                                        ({{ $total }} total)
                                    </span>
                                @endif
                            </div>
                        </button>

                        {{-- Expanded Detail --}}
                        @if ($isExpanded)
                            <div class="border-t border-gray-100 dark:border-gray-700/60">

                                {{-- Overdue Tickets Section --}}
                                @if ($overdueTickets->isNotEmpty())
                                    <div class="px-4 py-3 bg-red-50/50 dark:bg-red-950/20 border-l-4 border-red-500">
                                        <p class="text-xs font-bold uppercase tracking-widest mb-2 flex items-center gap-1" style="color: #b91c1c;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                            </svg>
                                            Overdue ({{ $overdueTickets->count() }})
                                        </p>
                                        <div class="space-y-1.5">
                                            @foreach ($overdueTickets as $ticket)
                                                <a
                                                    href="{{ route('filament.admin.resources.tickets.view', $ticket->id) }}"
                                                    class="flex items-start gap-2 group hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg px-2 py-1.5 -mx-2 transition-colors duration-100"
                                                >
                                                    <span class="flex-shrink-0 font-mono text-xs text-red-500 dark:text-red-400 mt-0.5">
                                                        {{ $ticket->uuid }}
                                                    </span>
                                                    <span class="flex-1 min-w-0">
                                                        <span class="text-sm text-gray-800 dark:text-gray-200 group-hover:text-red-700 dark:group-hover:text-red-300 line-clamp-1">
                                                            {{ $ticket->name }}
                                                        </span>
                                                        <span class="flex items-center gap-2 mt-0.5">
                                                            <span class="text-xs text-red-500 dark:text-red-400">
                                                                Due: {{ $ticket->due_date->format('d M Y') }}
                                                                ({{ abs($ticket->getDaysUntilDue()) }}d overdue)
                                                            </span>
                                                            @if ($ticket->status)
                                                                <span
                                                                    class="inline-block text-xs px-1.5 py-0.5 rounded"
                                                                    style="background-color: {{ $ticket->status->color ?? '#6b7280' }}22; color: {{ $ticket->status->color ?? '#6b7280' }}"
                                                                >
                                                                    {{ $ticket->status->name }}
                                                                </span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                    @if ($ticket->assignees->isNotEmpty())
                                                        <div class="flex-shrink-0 flex -space-x-1.5">
                                                            @foreach ($ticket->assignees->take(3) as $assignee)
                                                                <img
                                                                    src="{{ $assignee->avatar_url }}"
                                                                    alt="{{ $assignee->name }}"
                                                                    title="{{ $assignee->name }}"
                                                                    class="w-5 h-5 rounded-full ring-1 ring-white dark:ring-gray-900 object-cover"
                                                                />
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Tickets grouped by status --}}
                                @foreach ($statuses as $statusItem)
                                    @if ($statusItem['tickets']->isNotEmpty())
                                        <div class="px-4 py-3 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700/60' : '' }}">
                                            <p class="text-xs font-semibold uppercase tracking-wide mb-2 flex items-center gap-1.5"
                                               style="color: {{ $statusItem['color'] }}">
                                                <span
                                                    class="w-2 h-2 rounded-full flex-shrink-0"
                                                    style="background-color: {{ $statusItem['color'] }}"
                                                ></span>
                                                {{ $statusItem['name'] }} ({{ $statusItem['count'] }})
                                            </p>
                                            <div class="space-y-1.5">
                                                @foreach ($statusItem['tickets'] as $ticket)
                                                    <a
                                                        href="{{ route('filament.admin.resources.tickets.view', $ticket->id) }}"
                                                        class="flex items-start gap-2 group hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg px-2 py-1.5 -mx-2 transition-colors duration-100"
                                                    >
                                                        <span class="flex-shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                            {{ $ticket->uuid }}
                                                        </span>
                                                        <span class="flex-1 min-w-0">
                                                            <span class="text-sm text-gray-800 dark:text-gray-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 line-clamp-1">
                                                                {{ $ticket->name }}
                                                            </span>
                                                            @if ($ticket->due_date)
                                                                <span class="text-xs {{ $ticket->isOverdue() ? 'font-bold' : 'text-gray-400 dark:text-gray-500' }} block mt-0.5" style="{{ $ticket->isOverdue() ? 'color: #dc2626;' : '' }}">
                                                                    Due: {{ $ticket->due_date->format('d M Y') }}
                                                                    @if ($ticket->isOverdue())
                                                                        (overdue)
                                                                    @endif
                                                                </span>
                                                            @endif
                                                        </span>
                                                        <div class="flex-shrink-0 flex items-center gap-1.5">
                                                            @if ($ticket->priority)
                                                                <span
                                                                    class="text-xs px-1.5 py-0.5 rounded font-medium"
                                                                    style="background-color: {{ $ticket->priority->color ?? '#6b7280' }}22; color: {{ $ticket->priority->color ?? '#6b7280' }}"
                                                                >
                                                                    {{ $ticket->priority->name }}
                                                                </span>
                                                            @endif
                                                            @if ($ticket->assignees->isNotEmpty())
                                                                <div class="flex -space-x-1.5">
                                                                    @foreach ($ticket->assignees->take(3) as $assignee)
                                                                        <img
                                                                            src="{{ $assignee->avatar_url }}"
                                                                            alt="{{ $assignee->name }}"
                                                                            title="{{ $assignee->name }}"
                                                                            class="w-5 h-5 rounded-full ring-1 ring-white dark:ring-gray-900 object-cover"
                                                                        />
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </a>
                                                @endforeach

                                                @if ($statusItem['count'] > 20)
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 px-2 pt-1 italic">
                                                        Showing first 20 of {{ $statusItem['count'] }} tickets
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif ($statusItem['count'] > 0)
                                        {{-- Status exists but no loaded tickets (shouldn't happen) --}}
                                    @endif
                                @endforeach

                                @if ($total === 0 && $overdue === 0)
                                    <div class="px-4 py-4 text-center text-sm text-gray-400 dark:text-gray-500 italic">
                                        No {{ $viewMode === 'my_tickets' ? 'assigned ' : '' }}tickets in this project.
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
