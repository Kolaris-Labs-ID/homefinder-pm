<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- Header dengan Filter -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <x-filament::section.heading>
                    Project Timeline
                </x-filament::section.heading>
                
                <!-- Filter Buttons -->
                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="setFilter('pinned')"
                        :color="$filter === 'pinned' ? 'primary' : 'gray'"
                        :outlined="$filter !== 'pinned'"
                        size="sm"
                    >
                        Pinned Projects
                        <x-filament::badge
                            :color="$filter === 'pinned' ? 'primary' : 'gray'"
                            size="sm"
                            class="ml-1"
                        >
                            {{ $counts['pinned'] }}
                        </x-filament::badge>
                    </x-filament::button>
                    
                    <x-filament::button
                        wire:click="setFilter('all')"
                        :color="$filter === 'all' ? 'primary' : 'gray'"
                        :outlined="$filter !== 'all'"
                        size="sm"
                    >
                        All Projects
                        <x-filament::badge
                            :color="$filter === 'all' ? 'primary' : 'gray'"
                            size="sm"
                            class="ml-1"
                        >
                            {{ $counts['all'] }}
                        </x-filament::badge>
                    </x-filament::button>
                </div>
            </div>
            
            <!-- Project List -->
            @if(count($projects) === 0)
                <div class="flex flex-col items-center justify-center py-12">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-calendar-days class="h-6 w-6 text-gray-400" />
                    </div>
                    
                    <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                        @if($filter === 'pinned')
                            No pinned projects
                        @else
                            No projects found
                        @endif
                    </h3>
                    
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm">
                        @if($filter === 'pinned')
                            You haven't pinned any projects yet. Pin important projects to keep them easily accessible.
                        @else
                            Create a new project or check your project permissions.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($projects as $project)
                        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <!-- Project Header -->
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $project['name'] }}
                                        </h3>
                                        
                                        @php
                                            $badgeColor = match($project['status']) {
                                                'Completed' => 'success',
                                                'Overdue' => 'danger',
                                                'Approaching Deadline' => 'warning',
                                                'In Progress' => 'primary',
                                                'Not Started' => 'gray',
                                                default => 'gray'
                                            };
                                        @endphp
                                        
                                        <x-filament::badge :color="$badgeColor" size="sm">
                                            {{ $project['status'] }}
                                        </x-filament::badge>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $project['start_date'] }} - {{ $project['end_date'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $project['total_days'] }} total days
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Section -->
                            <div class="space-y-3">
                                <!-- Progress Bar -->
                                <div class="relative">
                                    <div class="h-3 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                                        @if($project['progress_percent'] > 0)
                                            <div class="h-full rounded-full transition-all duration-500 ease-in-out
                                                       {{ $project['remaining_days'] < 0 ? 'bg-red-500' : 'bg-primary-500' }}"
                                                 style="width: {{ min($project['progress_percent'], 100) }}%">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Progress Info -->
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-4">
                                        @if($project['past_days'] > 0)
                                            <div class="text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">{{ $project['past_days'] }}</span>
                                                {{ Str::plural('day', $project['past_days']) }} completed
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right">
                                        @if($project['remaining_days'] > 0)
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $project['remaining_days'] }} {{ Str::plural('day', $project['remaining_days']) }} remaining
                                            </div>
                                        @elseif($project['remaining_days'] < 0)
                                            <div class="font-medium text-red-600 dark:text-red-400">
                                                {{ abs($project['remaining_days']) }} {{ Str::plural('day', abs($project['remaining_days'])) }} overdue
                                            </div>
                                        @else
                                            <div class="font-medium text-amber-600 dark:text-amber-400">
                                                Due today
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>