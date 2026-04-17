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
            $groupedData = $this->getGroupedWidgetData();
            $totalProjectCount = $groupedData->sum(fn ($g) => count($g['rows']));
        @endphp

        @if ($totalProjectCount === 0)
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <x-heroicon-o-rectangle-stack class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No active projects found</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">You are not a member of any active projects yet.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($groupedData as $group)

                    @if (! $group['is_grouped'])

                        {{-- ── Flat list (regular users) ── --}}
                        @foreach ($group['rows'] as $row)
                            @php
                                $project      = $row['project'];
                                $statuses     = $row['statuses'];
                                $overdue      = $row['overdue'];
                                $total        = $row['total'];
                                $isExpanded   = $row['is_expanded'];
                                $overdueTickets = $row['overdue_tickets'];
                            @endphp
                            @include('filament.widgets.partials.project-ticket-row')
                        @endforeach

                    @else

                        {{-- ── Division group (C-level / superadmin) ── --}}
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">

                            {{-- Division header --}}
                            <button
                                wire:click="toggleDivision('{{ $group['key'] }}')"
                                class="w-full flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors duration-150 text-left focus:outline-none"
                            >
                                {{-- Chevron --}}
                                <span class="flex-shrink-0 text-gray-400 dark:text-gray-500 transition-transform duration-200 {{ $group['is_expanded'] ? 'rotate-90' : '' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>

                                {{-- Division icon + name --}}
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <x-heroicon-s-building-office-2 class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                                    <span class="font-bold text-sm text-gray-900 dark:text-white truncate">
                                        {{ $group['label'] }}
                                    </span>
                                </div>

                                {{-- Aggregate badges --}}
                                <div class="flex flex-wrap items-center gap-1.5 flex-shrink-0">
                                    @if ($group['total_overdue'] > 0)
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border shadow-sm"
                                            style="background-color: #dc2626; color: white; border-color: #b91c1c;"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                            </svg>
                                            {{ $group['total_overdue'] }} overdue
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        {{ $group['project_count'] }} {{ $group['project_count'] === 1 ? 'project' : 'projects' }}
                                    </span>
                                    @if ($group['total_tickets'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                            {{ $group['total_tickets'] }} tickets
                                        </span>
                                    @endif
                                </div>
                            </button>

                            {{-- Expanded: project rows --}}
                            @if ($group['is_expanded'])
                                <div class="divide-y divide-gray-100 dark:divide-gray-700/60 border-t border-gray-200 dark:border-gray-700">
                                    @foreach ($group['rows'] as $row)
                                        @php
                                            $project      = $row['project'];
                                            $statuses     = $row['statuses'];
                                            $overdue      = $row['overdue'];
                                            $total        = $row['total'];
                                            $isExpanded   = $row['is_expanded'];
                                            $overdueTickets = $row['overdue_tickets'];
                                        @endphp
                                        @include('filament.widgets.partials.project-ticket-row')
                                    @endforeach
                                </div>
                            @endif

                        </div>

                    @endif

                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
