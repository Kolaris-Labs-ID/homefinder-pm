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
            <div class="flex flex-col gap-6">
                @foreach ($groupedData as $group)

                    @if (! $group['is_grouped'])

                        {{-- ── Flat list (regular users) ── --}}
                        <div class="flex flex-col gap-3">
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

                    @else

                        {{-- ── Division group (C-level / superadmin) ── --}}
                        <div class="flex flex-col">

                            {{-- Division header --}}
                            <button
                                wire:click="toggleDivision('{{ $group['key'] }}')"
                                class="group flex flex-col sm:flex-row sm:items-center justify-between w-full py-2 mb-3 focus:outline-none rounded-lg px-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors -mx-2"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="p-1 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 group-hover:bg-gray-200 dark:group-hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-700 shadow-sm">
                                        <svg class="w-4 h-4 transition-transform duration-200 {{ $group['is_expanded'] ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-s-building-office-2 class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                        <span class="font-bold text-base text-gray-900 dark:text-white">
                                            {{ $group['label'] }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Aggregate badges --}}
                                <div class="flex flex-wrap items-center gap-2 mt-2 sm:mt-0 pl-10 sm:pl-0">
                                    @if ($group['total_overdue'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                            </svg>
                                            {{ $group['total_overdue'] }} overdue
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-white text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 shadow-sm">
                                        {{ $group['project_count'] }} {{ $group['project_count'] === 1 ? 'project' : 'projects' }}
                                    </span>
                                    @if ($group['total_tickets'] > 0)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-white text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 shadow-sm">
                                            {{ $group['total_tickets'] }} tickets
                                        </span>
                                    @endif
                                </div>
                            </button>

                            {{-- Expanded: project rows --}}
                            @if ($group['is_expanded'])
                                <div class="flex flex-col gap-3 pl-4 sm:pl-6 border-l-2 border-gray-100 dark:border-gray-800 ml-3 sm:ml-4 mt-1">
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
