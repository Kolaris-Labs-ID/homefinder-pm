<div class="min-h-screen bg-gray-50">
    <!-- Flash Messages -->
    @if (session('message'))
        <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transition-all duration-300"
            id="success-message">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg transition-all duration-300"
            id="error-message">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-4 sm:py-6 gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate">{{ $project->name }}</h1>
                <p class="text-gray-600 mt-1 text-sm sm:text-base">{{ config('app.name') }} - External Dashboard</p>
            </div>
            <div class="flex items-center space-x-3 sm:space-x-4 flex-shrink-0">
                <!-- Refresh Button -->
                <button wire:click="refreshData" wire:loading.attr="disabled"
                    class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 sm:space-x-2">
                    <svg wire:loading.remove class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                    <svg wire:loading class="w-4 h-4 animate-spin" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                    <span wire:loading.remove class="hidden sm:inline">Refresh</span>
                    <span wire:loading class="hidden sm:inline">Refreshing...</span>
                </button>

                <!-- Logout Button -->
                <button wire:click="logout"
                    class="bg-red-600 hover:bg-red-700 text-white px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center space-x-1 sm:space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                        </path>
                    </svg>
                    <span class="hidden sm:inline">Logout</span>
                </button>
            </div>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Project Stats Overview (Always visible) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Team</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $projectStats['total_team'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Progress</p>
                        <div class="flex items-center space-x-2">
                            <p
                                class="text-2xl font-bold {{ $projectStats['progress_percentage'] >= 100 ? 'text-green-600' : ($projectStats['progress_percentage'] >= 75 ? 'text-blue-600' : ($projectStats['progress_percentage'] >= 50 ? 'text-yellow-600' : 'text-gray-900')) }}">
                                {{ $projectStats['progress_percentage'] ?? 0 }}%
                            </p>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-gradient-to-r {{ $projectStats['progress_percentage'] >= 100 ? 'from-green-400 to-green-600' : ($projectStats['progress_percentage'] >= 75 ? 'from-blue-400 to-blue-600' : ($projectStats['progress_percentage'] >= 50 ? 'from-yellow-400 to-yellow-600' : 'from-gray-400 to-gray-600')) }} h-2 rounded-full transition-all duration-300"
                                style="width: {{ min($projectStats['progress_percentage'] ?? 0, 100) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-50 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Remaining Days</p>
                        <p
                            class="text-2xl font-bold {{ $projectStats['remaining_days'] !== null && $projectStats['remaining_days'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $projectStats['remaining_days'] ?? 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-50 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Tasks</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $projectStats['total_tickets'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Layout Container -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm" id="dashboard-tabs">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <!-- Desktop/Tablet Tab Navigation -->
                <nav class="hidden sm:flex space-x-8 px-4 lg:px-6" aria-label="Tabs">
                    <button
                        class="tab-button py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-200"
                        data-tab="tasks" onclick="switchTab('tasks')">
                        <span class="flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                </path>
                            </svg>
                            <span class="hidden md:inline">Project Tasks</span>
                            <span class="md:hidden">Tasks</span>
                        </span>
                    </button>
                    <button
                        class="tab-button py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-200"
                        data-tab="timeline" onclick="switchTab('timeline')">
                        <span class="flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z"></path>
                            </svg>
                            <span>Timeline</span>
                        </span>
                    </button>
                    <button
                        class="tab-button py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-200"
                        data-tab="activity" onclick="switchTab('activity')">
                        <span class="flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="hidden md:inline">Recent Activity</span>
                            <span class="md:hidden">Activity</span>
                        </span>
                    </button>
                </nav>

                <!-- Mobile Tab Navigation (Dropdown) -->
                <div class="sm:hidden px-4 py-3">
                    <div class="relative">
                        <button type="button" onclick="toggleMobileTabDropdown()"
                            class="w-full bg-white border border-gray-300 rounded-md px-4 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            id="mobile-tab-button">
                            <span class="flex items-center justify-between">
                                <span class="flex items-center space-x-2" id="mobile-tab-selected">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
                                    </svg>
                                    <span>Project Tasks</span>
                                </span>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </span>
                        </button>

                        <div id="mobile-tab-dropdown"
                            class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-10">
                            <div class="py-1">
                                <button onclick="switchMobileTab('tasks')"
                                    class="mobile-tab-option w-full px-4 py-2 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                    data-tab="tasks">
                                    <span class="flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                            </path>
                                        </svg>
                                        <span>Project Tasks</span>
                                    </span>
                                </button>
                                <button onclick="switchMobileTab('timeline')"
                                    class="mobile-tab-option w-full px-4 py-2 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                    data-tab="timeline">
                                    <span class="flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                        <span>Timeline</span>
                                    </span>
                                </button>
                                <button onclick="switchMobileTab('activity')"
                                    class="mobile-tab-option w-full px-4 py-2 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                    data-tab="activity">
                                    <span class="flex items-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        <span>Recent Activity</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content-container">
                <!-- Tasks Tab -->
                <div id="tasks-tab" class="tab-content">
                    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-col gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Project Tasks</h3>
                                <p class="text-sm text-gray-600">All tasks in this project (ordered by creation date)
                                </p>
                            </div>

                            <!-- Filters -->
                            <div class="flex flex-col gap-3">
                                <!-- Search -->
                                <div class="relative">
                                    <input type="text" wire:model.live.debounce.300ms="searchTerm"
                                        placeholder="Search tasks..."
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Filters Row -->
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <!-- Status Filter -->
                                    <select wire:model.live="selectedStatus"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                        <option value="">All Status</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>

                                    <!-- Clear Filters -->
                                    @if ($selectedStatus || $searchTerm)
                                        <button wire:click="clearFilters"
                                            onclick="setTimeout(() => switchTab(getCurrentTab()), 150)"
                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200 whitespace-nowrap">
                                            Clear Filters
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Container -->
                    <div class="overflow-x-auto">
                        <!-- Desktop Table -->
                        <table class="hidden sm:table min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Code</th>
                                    <th
                                        class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Task Name</th>
                                    <th
                                        class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Due Date</th>
                                    <th
                                        class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($this->tickets as $ticket)
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                                        <td
                                            class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $ticket->uuid }}
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 text-sm text-gray-900">
                                            <div class="font-medium">{{ $ticket->name }}</div>
                                            @if ($ticket->description)
                                                <div class="text-gray-500 text-xs mt-1 truncate max-w-xs">
                                                    {{ Str::limit($ticket->description, 100) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if ($ticket->due_date)
                                                <span
                                                    class="{{ \Carbon\Carbon::parse($ticket->due_date)->isPast() ? 'text-red-600 font-medium' : '' }}">
                                                    {{ \Carbon\Carbon::parse($ticket->due_date)->format('M d, Y') }}
                                                </span>
                                                @if (\Carbon\Carbon::parse($ticket->due_date)->isPast())
                                                    <div class="text-red-500 text-xs">Overdue</div>
                                                @endif
                                            @else
                                                <span class="text-gray-400">No due date</span>
                                            @endif
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white"
                                                style="background-color: {{ $ticket->status->color ?? '#6B7280' }}">
                                                {{ $ticket->status->name ?? 'Unknown' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 lg:px-6 py-12 text-center">
                                            <div class="text-gray-500">
                                                <svg class="w-12 h-12 mx-auto mb-4" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                                    </path>
                                                </svg>
                                                <p class="text-lg font-medium">No tasks found</p>
                                                <p class="text-sm">Try adjusting your search or filter criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <!-- Mobile Card View -->
                        <div class="sm:hidden">
                            @forelse($this->tickets as $ticket)
                                <div
                                    class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-150">
                                    <div class="space-y-3">
                                        <!-- Header Row -->
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-medium text-gray-900 truncate">{{ $ticket->name }}
                                                </h4>
                                                <p class="text-sm text-gray-600 font-mono">{{ $ticket->uuid }}</p>
                                            </div>
                                            <span
                                                class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white flex-shrink-0"
                                                style="background-color: {{ $ticket->status->color ?? '#6B7280' }}">
                                                {{ $ticket->status->name ?? 'Unknown' }}
                                            </span>
                                        </div>

                                        <!-- Description -->
                                        @if ($ticket->description)
                                            <p class="text-sm text-gray-600 line-clamp-2">
                                                {{ Str::limit($ticket->description, 120) }}
                                            </p>
                                        @endif

                                        <!-- Due Date -->
                                        <div class="flex items-center text-sm">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                            @if ($ticket->due_date)
                                                <span
                                                    class="{{ \Carbon\Carbon::parse($ticket->due_date)->isPast() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                                    Due:
                                                    {{ \Carbon\Carbon::parse($ticket->due_date)->format('M d, Y') }}
                                                    @if (\Carbon\Carbon::parse($ticket->due_date)->isPast())
                                                        <span class="text-red-500 text-xs ml-1">(Overdue)</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-gray-400">No due date</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8 text-center">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
                                    </svg>
                                    <p class="text-lg font-medium text-gray-900">No tasks found</p>
                                    <p class="text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if ($this->tickets->hasPages())
                        <div class="px-4 lg:px-6 py-4 border-t border-gray-200" id="tasks-pagination-section">
                            {{ $this->tickets->links() }}
                        </div>
                    @endif
                </div>

                <!-- Timeline Tab -->
                <div id="timeline-tab" class="tab-content hidden">
                    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Ticket Timeline</h2>
                                <p class="text-sm text-gray-600 sm:hidden">Swipe horizontally to navigate timeline</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Export to Excel Button -->
                                <button onclick="exportGanttToExcel()"
                                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    <span class="hidden sm:inline">Export Weekly Timeline</span>
                                    <span class="sm:hidden">Weekly</span>
                                </button>

                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span class="hidden sm:inline">Read Only View</span>
                                    <span class="sm:hidden">Read Only</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- dhtmlxGantt Container -->
                    <div class="w-full" wire:ignore>
                        @if (count($this->ganttData['data']) > 0)
                            <div id="gantt_here" class="timeline-container" style="width:100%; height:500px;"></div>

                            <!-- Status Legend -->
                            <div class="px-4 lg:px-6 py-4 border-t border-gray-200 bg-gray-50">
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Status Legend:</h4>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-wrap gap-3">
                                            @foreach ($statuses as $status)
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3 h-3 rounded flex-shrink-0"
                                                        style="background-color: {{ $status->color ?? '#6B7280' }}">
                                                    </div>
                                                    <span
                                                        class="text-sm text-gray-600 truncate">{{ $status->name }}</span>
                                                </div>
                                            @endforeach
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 rounded bg-red-500 flex-shrink-0"></div>
                                                <span class="text-sm text-gray-600">Overdue</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 border-t pt-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="hidden sm:inline">Hover over bars for more details, click to
                                                view full ticket information</span>
                                            <span class="sm:hidden">Tap bars for ticket details</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4 px-4">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z" />
                                </svg>
                                <div class="text-center">
                                    <h3 class="text-lg font-medium">No tickets with due dates</h3>
                                    <p class="text-sm">Add due dates to tickets to see the timeline</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Activity Tab -->
                <div id="activity-tab" class="tab-content hidden">
                    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                            <p class="text-sm text-gray-600">Latest updates and changes in the project</p>
                        </div>
                    </div>
                    <div class="p-4 lg:p-6">
                        @if ($this->recentActivities->count() > 0)
                            <!-- Desktop Activity List -->
                            <div class="hidden sm:block space-y-4">
                                @foreach ($this->recentActivities as $activity)
                                    <div
                                        class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors duration-150">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900">
                                                <span
                                                    class="font-medium">{{ $activity->ticket->name ?? 'Unknown Task' }}</span>
                                                @if ($activity->status)
                                                    moved to <span class="font-medium"
                                                        style="color: {{ $activity->status->color ?? '#6B7280' }}">{{ $activity->status->name }}</span>
                                                @else
                                                    was updated
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $activity->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Mobile Activity List -->
                            <div class="sm:hidden space-y-3">
                                @foreach ($this->recentActivities as $activity)
                                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0">
                                                <div
                                                    class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-gray-600" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-900 font-medium leading-tight">
                                                    {{ $activity->ticket->name ?? 'Unknown Task' }}
                                                </p>
                                                @if ($activity->status)
                                                    <p class="text-sm text-gray-600 mt-1">
                                                        Moved to <span
                                                            class="font-medium px-2 py-0.5 rounded text-xs text-white"
                                                            style="background-color: {{ $activity->status->color ?? '#6B7280' }}">{{ $activity->status->name }}</span>
                                                    </p>
                                                @else
                                                    <p class="text-sm text-gray-600 mt-1">Task was updated</p>
                                                @endif
                                                <p class="text-xs text-gray-500 mt-2">
                                                    {{ $activity->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Recent Activities Pagination -->
                            @if ($this->recentActivities->hasPages())
                                <div class="mt-6 pt-4 border-t border-gray-200">
                                    {{ $this->recentActivities->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <div>
                                    <p class="text-lg font-medium text-gray-900">No recent activity</p>
                                    <p class="text-sm text-gray-500">Activities will appear here when tasks are updated
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>


            </div>
        </div>

        <!-- Ticket Detail Modal -->
        <div id="ticket-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <!-- Background overlay -->
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity duration-300 ease-out opacity-0"
                    id="modal-backdrop" onclick="closeTicketModal()"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all duration-300 ease-out scale-95 opacity-0 sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full w-full max-w-sm mx-auto"
                    id="modal-panel">

                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 sm:px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 sm:w-10 h-8 sm:h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                        <svg class="h-5 sm:h-6 w-5 sm:w-6 text-white" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                            </path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base sm:text-lg leading-6 font-semibold text-white truncate"
                                        id="modal-title">
                                        Ticket Details
                                    </h3>
                                    <p class="text-blue-100 text-xs sm:text-sm truncate" id="ticket-subtitle">Loading
                                        ticket
                                        information...</p>
                                </div>
                            </div>
                            <button type="button" onclick="closeTicketModal()"
                                class="ml-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg p-2 text-white hover:text-gray-100 transition-colors duration-200 flex-shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="bg-white px-4 sm:px-6 py-4 sm:py-6 max-h-96 sm:max-h-none overflow-y-auto">
                        <!-- Loading State -->
                        <div id="modal-loading" class="flex items-center justify-center py-8 sm:py-12">
                            <div class="flex flex-col items-center space-y-4">
                                <div class="relative">
                                    <div
                                        class="animate-spin rounded-full h-8 sm:h-12 w-8 sm:w-12 border-3 sm:border-4 border-blue-200">
                                    </div>
                                    <div
                                        class="animate-spin rounded-full h-8 sm:h-12 w-8 sm:w-12 border-3 sm:border-4 border-blue-600 border-t-transparent absolute top-0 left-0">
                                    </div>
                                </div>
                                <p class="text-gray-600 font-medium text-sm sm:text-base">Loading ticket details...</p>
                            </div>
                        </div>

                        <!-- Ticket Content -->
                        <div id="modal-content" class="hidden">
                            <div class="space-y-4 sm:space-y-6">
                                <!-- Ticket Header Info -->
                                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                                    <div class="grid grid-cols-1 gap-3 sm:gap-4">
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Ticket
                                                Code</label>
                                            <p id="ticket-code"
                                                class="text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded-md border break-all">
                                            </p>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                                            <span id="ticket-status"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ticket Title & Description -->
                                <div class="space-y-3 sm:space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Title</label>
                                        <h4 id="ticket-name"
                                            class="text-base sm:text-lg font-semibold text-gray-900 leading-tight break-words">
                                        </h4>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                        <div id="ticket-description"
                                            class="text-sm sm:text-base text-gray-700 bg-gray-50 rounded-lg p-3 sm:p-4 min-h-[60px] whitespace-pre-wrap break-words">
                                        </div>
                                    </div>
                                </div>

                                <!-- Details Grid -->
                                <div class="space-y-4 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-6">
                                    <!-- Priority & Dates -->
                                    <div class="space-y-3 sm:space-y-4">
                                        <div>
                                            <label
                                                class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
                                            <span id="ticket-priority"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800"></span>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Start
                                                Date</label>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                                <p id="ticket-start-date" class="text-gray-900 text-sm break-words">
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Due
                                                Date</label>
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <p id="ticket-due-date" class="text-gray-900 text-sm break-words"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Progress & Assignees -->
                                    <div class="space-y-3 sm:space-y-4">
                                        <div>
                                            <label
                                                class="block text-sm font-semibold text-gray-700 mb-2">Progress</label>
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span id="ticket-progress-text"
                                                        class="text-sm font-medium text-gray-700"></span>
                                                    <span id="ticket-progress-percentage"
                                                        class="text-sm font-semibold text-gray-900"></span>
                                                </div>
                                                <div
                                                    class="w-full bg-gray-200 rounded-full h-2 sm:h-3 overflow-hidden">
                                                    <div id="ticket-progress-bar"
                                                        class="h-2 sm:h-3 rounded-full transition-all duration-500 ease-out transform origin-left">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Error State -->
                        <div id="modal-error" class="hidden text-center py-8 sm:py-12">
                            <div class="flex flex-col items-center space-y-4">
                                <div
                                    class="w-12 sm:w-16 h-12 sm:h-16 bg-red-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 sm:w-8 h-6 sm:h-8 text-red-600" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-1">Unable to Load
                                        Ticket</h3>
                                    <p id="modal-error-message" class="text-gray-600 text-sm"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css" type="text/css">
    <style>
        /* Gantt chart custom styles */
        .gantt_task_line.overdue {
            background-color: #ef4444 !important;
        }

        .gantt_task_progress.overdue {
            background-color: #dc2626 !important;
        }

        /* Gantt container styling */
        #gantt_here {
            border-radius: 0 0 0.75rem 0.75rem;
        }

        /* Custom gantt grid styling */
        .gantt_grid_scale,
        .gantt_grid_head_cell {
            background-color: #f9fafb !important;
            border-color: #e5e7eb !important;
        }

        .gantt_row {
            border-color: #e5e7eb !important;
        }

        .gantt_row:hover {
            background-color: #f3f4f6 !important;
        }

        /* Vertical grid lines for timeline (day/step separators) */
        /* Use pseudo-elements so we don't break gantt internals */
        .gantt_scale_cell,
        .gantt_task_cell {
            position: relative;
        }

        .gantt_scale_cell::after,
        .gantt_task_cell::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            /* line on the right edge of each cell */
            width: 1px;
            height: 100%;
            background-color: rgba(229, 231, 235, 0.95);
            /* Tailwind gray-200 */
            pointer-events: none;
        }

        /* Avoid showing an extra line at the far right of the whole chart */
        .gantt_scale_row .gantt_scale_cell:last-child::after,
        .gantt_light .gantt_task_row .gantt_task_cell:last-child::after,
        .gantt_task_row .gantt_task_cell:last-child::after {
            display: none;
        }

        /* Slight darker separator for major scale (months) if needed */
        .gantt_scale_cell.gantt_major::after {
            background-color: rgba(156, 163, 175, 0.95);
            /* Tailwind gray-400 */
            width: 1px;
        }

        /* Ensure lines remain visible when zooming/scrolling */
        #gantt_here .gantt_light .gantt_task_cell::after,
        #gantt_here .gantt_grid_head_cell::after {
            z-index: 2;
        }

        /* Status-based task styling for export */
        .status_todo {
            background: #9CA3AF !important;
        }

        .status_in_progress {
            background: #3B82F6 !important;
        }

        .status_review {
            background: #F59E0B !important;
        }

        .status_done {
            background: #10B981 !important;
        }

        .status_cancelled {
            background: #EF4444 !important;
        }

        .status_overdue {
            background: #DC2626 !important;
        }

        /* Weekend highlighting for timeline */
        .weekend {
            background-color: #F3F4F6 !important;
        }

        /* Today marker line styling */
        .gantt_marker.today {
            background-color: #EF4444 !important;
            /* Red color for today line */
            opacity: 0.8;
            z-index: 10;
        }

        .gantt_marker.today .gantt_marker_content {
            background-color: #EF4444 !important;
            color: white !important;
            font-weight: bold;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }

        /* Tab Layout Styles */
        .tab-button {
            border-color: transparent;
            color: #6b7280;
        }

        .tab-button:hover {
            color: #374151;
            border-color: #d1d5db;
        }

        .tab-button.active {
            color: #2563eb;
            border-color: #2563eb;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Smooth tab transitions */
        .tab-content-container {
            min-height: 400px;
        }

        /* Prevent scroll jump on pagination */
        .tab-content {
            scroll-margin-top: 100px;
        }

        /* Ensure gantt chart renders correctly in tab */
        #timeline-tab.active #gantt_here {
            visibility: visible;
        }

        #timeline-tab:not(.active) #gantt_here {
            visibility: hidden;
        }

        /* Mobile responsive gantt */
        .timeline-container {
            min-height: 400px;
        }

        @media (max-width: 640px) {
            .timeline-container {
                height: 400px !important;
                font-size: 12px;
            }

            /* Adjust gantt grid for mobile */
            .gantt_grid_scale,
            .gantt_grid_head_cell {
                font-size: 11px !important;
            }

            .gantt_task_cell,
            .gantt_cell {
                font-size: 11px !important;
            }
        }

        /* Mobile dropdown styles */
        .mobile-tab-option.active {
            background-color: #eff6ff;
            color: #2563eb;
        }

        /* Line clamping for mobile */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Loading states */
        .pagination-loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .pagination-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Cache refresh button animation */
        .refresh-loading {
            animation: spin 1s linear infinite;
        }

        /* Modal animations */
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal backdrop blur effect */
        #ticket-modal .fixed.inset-0 {
            backdrop-filter: blur(4px);
        }

        /* Progress bar animations */
        #ticket-progress-bar {
            transform-origin: left;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Assignee card hover effects */
        .assignee-card {
            transition: all 0.2s ease-in-out;
        }

        .assignee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Status badge pulse animation */
        .status-pulse {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        /* Loading spinner improvements */
        .spinner-double {
            position: relative;
        }

        .spinner-double::before,
        .spinner-double::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: currentColor;
            animation: spin 1s linear infinite;
        }

        .spinner-double::before {
            width: 100%;
            height: 100%;
            border-top-color: rgba(59, 130, 246, 0.3);
            animation-duration: 1.5s;
        }

        .spinner-double::after {
            width: 80%;
            height: 80%;
            top: 10%;
            left: 10%;
            border-top-color: #3b82f6;
            animation-duration: 0.8s;
            animation-direction: reverse;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Auto-hide notifications
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('success-message');
            const errorMessage = document.getElementById('error-message');

            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 300);
                }, 3000);
            }

            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    setTimeout(() => errorMessage.remove(), 300);
                }, 5000);
            }
        });

        // Tab Management with persistence across Livewire updates
        let currentActiveTab = sessionStorage.getItem('currentActiveTab') || 'tasks';
        let tabScrollPositions = {};

        // Maintain tab state across page interactions
        function saveCurrentTab(tabName) {
            currentActiveTab = tabName;
            sessionStorage.setItem('currentActiveTab', tabName);
        }

        function getCurrentTab() {
            return sessionStorage.getItem('currentActiveTab') || 'tasks';
        }

        // Mobile tab dropdown functions
        function toggleMobileTabDropdown() {
            const dropdown = document.getElementById('mobile-tab-dropdown');
            dropdown.classList.toggle('hidden');

            // Close dropdown when clicking outside
            if (!dropdown.classList.contains('hidden')) {
                setTimeout(() => {
                    document.addEventListener('click', closeMobileDropdownOnClickOutside);
                }, 10);
            }
        }

        function closeMobileDropdownOnClickOutside(event) {
            const dropdown = document.getElementById('mobile-tab-dropdown');
            const button = document.getElementById('mobile-tab-button');

            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
                document.removeEventListener('click', closeMobileDropdownOnClickOutside);
            }
        }

        function switchMobileTab(tabName) {
            // Update mobile dropdown selection
            updateMobileTabSelection(tabName);

            // Close dropdown
            document.getElementById('mobile-tab-dropdown').classList.add('hidden');
            document.removeEventListener('click', closeMobileDropdownOnClickOutside);

            // Switch to the tab
            switchTab(tabName);
        }

        function updateMobileTabSelection(tabName) {
            const selectedContainer = document.getElementById('mobile-tab-selected');
            const tabData = {
                'tasks': {
                    icon: 'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                    label: 'Project Tasks'
                },
                'timeline': {
                    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z',
                    label: 'Timeline'
                },
                'activity': {
                    icon: 'M13 10V3L4 14h7v7l9-11h-7z',
                    label: 'Recent Activity'
                },
                'status': {
                    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    label: 'Project Status'
                }
            };

            const tab = tabData[tabName];
            if (tab) {
                selectedContainer.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${tab.icon}"></path>
                    </svg>
                    <span>${tab.label}</span>
                `;
            }

            // Update active state in dropdown options
            document.querySelectorAll('.mobile-tab-option').forEach(option => {
                option.classList.remove('active');
                if (option.dataset.tab === tabName) {
                    option.classList.add('active');
                }
            });
        }


        function switchTab(tabName) {
            // Save current scroll position for current tab
            if (currentActiveTab) {
                tabScrollPositions[currentActiveTab] = window.pageYOffset;
            }

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
                tab.classList.add('hidden');
            });

            // Remove active class from all tab buttons (desktop)
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab
            const selectedTab = document.getElementById(tabName + '-tab');
            const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);

            if (selectedTab && selectedButton) {
                selectedTab.classList.add('active');
                selectedTab.classList.remove('hidden');
                selectedButton.classList.add('active');

                // Handle gantt chart rendering when timeline tab is shown
                if (tabName === 'timeline') {
                    console.log('Switching to timeline tab, initializing gantt...');
                    setTimeout(() => {
                        initializeGanttSafely();
                    }, 150);
                }

                // Handle status chart rendering when status tab is shown
                if (tabName === 'status') {
                    console.log('Switching to status tab, initializing chart...');
                    setTimeout(() => {
                        initializeStatusChart();
                    }, 150);
                }

                // Save the current tab state
                saveCurrentTab(tabName);

                // Update mobile dropdown if on mobile
                if (window.innerWidth < 640) {
                    updateMobileTabSelection(tabName);
                }

                // Restore scroll position for this tab (or stay at current position)
                if (tabScrollPositions[tabName] !== undefined) {
                    setTimeout(() => {
                        window.scrollTo({
                            top: tabScrollPositions[tabName],
                            behavior: 'smooth'
                        });
                    }, 50);
                }
            }
        }

        // Initialize tabs on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM ready, setting up tabs...');

            // Get saved tab or default to 'tasks'
            const savedTab = getCurrentTab();
            switchTab(savedTab);

            // Setup Livewire listeners first
            if (typeof Livewire !== 'undefined') {
                setupLivewireListeners();
            } else {
                document.addEventListener('livewire:init', setupLivewireListeners);
            }
        });

        // Handle Livewire component updates (filtering, pagination, etc.)
        document.addEventListener('livewire:morph', function() {
            console.log('Livewire component morphed, restoring tab state...');

            // Get current saved tab state
            const savedTab = getCurrentTab();

            // Restore tab state after Livewire updates
            setTimeout(() => {
                switchTab(savedTab);
                console.log('Tab state restored to:', savedTab);
            }, 50);
        });

        // Handle Livewire navigation after component updates (fallback)
        document.addEventListener('livewire:navigated', function() {
            console.log('Livewire navigated, maintaining tab state...');

            // Restore tab state after navigation
            setTimeout(() => {
                // Maintain current tab or default to 'tasks'
                const activeTab = getCurrentTab();
                switchTab(activeTab);

                // Reinitialize gantt if needed
                if (window.ganttState.initialized) {
                    try {
                        if (typeof gantt !== 'undefined' && gantt.clearAll) {
                            gantt.clearAll();
                        }
                    } catch (e) {
                        console.warn('Error clearing gantt:', e);
                    }
                    window.ganttState.initialized = false;
                }

                setTimeout(() => {
                    initializeGanttSafely();
                }, 100);
            }, 100);
        });

        // Prevent scroll jump on pagination and maintain tab state
        function handlePaginationClick(event) {
            // Save current scroll position
            const currentScrollPos = window.pageYOffset;

            // Save current tab before pagination
            const currentTab = getCurrentTab();

            // After Livewire processes the pagination
            setTimeout(() => {
                // Restore tab state first
                switchTab(currentTab);

                // Then maintain scroll position
                setTimeout(() => {
                    window.scrollTo({
                        top: currentScrollPos,
                        behavior: 'instant'
                    });
                }, 50);
            }, 100);
        }

        // Attach pagination handlers
        document.addEventListener('click', function(e) {
            if (e.target.closest('nav[role="navigation"]') || e.target.closest('.pagination')) {
                handlePaginationClick(e);
            }
        });

        // Handle filter changes to maintain tab state
        document.addEventListener('change', function(e) {
            if (e.target.matches('select[wire\\:model\\.live]') || e.target.matches('input[wire\\:model\\.live]')) {
                console.log('Filter changed, maintaining tab state...');
                const currentTab = getCurrentTab();

                // Save tab state before filter triggers Livewire update
                setTimeout(() => {
                    switchTab(currentTab);
                }, 150);
            }
        });

        // Handle search input changes
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[wire\\:model\\.live\\.debounce]')) {
                console.log('Search input changed, will maintain tab state...');
                const currentTab = getCurrentTab();

                // Debounced input will trigger Livewire update, maintain tab state
                setTimeout(() => {
                    switchTab(currentTab);
                }, 500); // Match debounce timing
            }
        });

        // Gantt Chart Code (existing)
        window.ganttState = window.ganttState || {
            initialized: false,
            currentProjectId: null
        };

        // Global gantt data cache for modal
        window.ganttTicketsCache = {};

        function getGanttData() {
            return @json($this->ganttData ?? ['data' => [], 'links' => []]);
        }

        // Cache ticket details from gantt data
        function cacheTicketDetails() {
            const ganttData = getGanttData();
            window.ganttTicketsCache = {};

            if (ganttData.data && Array.isArray(ganttData.data)) {
                ganttData.data.forEach(task => {
                    if (task.ticket_details) {
                        window.ganttTicketsCache[task.id] = task.ticket_details;
                    }
                });
            }
            console.log('Cached ticket details for', Object.keys(window.ganttTicketsCache).length, 'tickets');
        }

        function waitForGantt(callback, maxAttempts = 50) {
            let attempts = 0;

            function check() {
                attempts++;
                if (typeof gantt !== 'undefined' && gantt.init) {
                    callback();
                } else if (attempts < maxAttempts) {
                    setTimeout(check, 100);
                } else {
                    console.error('dhtmlxGantt failed to load after', maxAttempts * 100, 'ms');
                    showErrorMessage('Failed to load Gantt library');
                }
            }
            check();
        }

        function waitForContainer(callback, maxAttempts = 30) {
            let attempts = 0;

            function check() {
                attempts++;
                const container = document.getElementById('gantt_here');
                if (container && container.offsetParent !== null) {
                    callback();
                } else if (attempts < maxAttempts) {
                    setTimeout(check, 100);
                } else {
                    console.error('Gantt container not found or not visible after', maxAttempts * 100, 'ms');
                    showErrorMessage('Gantt container not available');
                }
            }
            check();
        }

        function showErrorMessage(message = 'Error loading timeline') {
            const container = document.getElementById('gantt_here');
            if (container) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium">${message}</h3>
                        <p class="text-sm">Please refresh the page or contact support</p>
                        <button onclick="location.reload()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Refresh Page
                        </button>
                    </div>
                `;
            }
        }

        function initializeGanttSafely() {
            // Check if we're on timeline tab
            const timelineTab = document.getElementById('timeline-tab');
            if (!timelineTab || !timelineTab.classList.contains('active')) {
                console.log('Timeline tab not active, skipping gantt initialization');
                return;
            }

            waitForContainer(() => {
                waitForGantt(() => {
                    initializeGantt();
                });
            });
        }

        function initializeGantt() {
            try {
                const ganttData = getGanttData();
                console.log('ðŸ”„ Initializing gantt with data:', ganttData.data.length, 'tasks');

                if (!ganttData.data || ganttData.data.length === 0) {
                    console.log('âŒ No gantt data available');
                    return;
                }

                // Cache ticket details for modal usage
                cacheTicketDetails();

                const container = document.getElementById('gantt_here');
                if (!container) {
                    console.error('âŒ Gantt container not found');
                    throw new Error('Gantt container not found');
                }

                if (typeof gantt === 'undefined' || !gantt.init) {
                    throw new Error('âŒ dhtmlxGantt library not properly loaded');
                }

                try {
                    // âœ¨ Enable export plugin for Excel functionality and marker for today line
                    gantt.plugins({
                        export_api: true,
                        marker: true
                    });
                    console.log('âœ… Export API and Marker plugins enabled');

                    // ðŸŽ¨ Configure task class template for status-based styling
                    gantt.templates.task_class = function(start, end, task) {
                        console.log('ðŸŽ¨ Applying task class for task:', task.text, 'status:', task.status);

                        let statusClass = '';
                        if (task.is_overdue) {
                            statusClass = 'status_overdue';
                        } else if (task.status) {
                            // Convert status name to CSS class
                            const status = task.status.toLowerCase()
                                .replace(/\s+/g, '_')
                                .replace(/[^a-z0-9_]/g, '');
                            statusClass = `status_${status}`;
                        }

                        console.log('ðŸŽ¨ Applied CSS class:', statusClass, 'for task:', task.text);
                        return statusClass;
                    };

                    // ðŸ—“ï¸ Configure timeline cell class for weekend highlighting
                    gantt.templates.timeline_cell_class = function(task, date) {
                        if (date.getDay() == 0 || date.getDay() == 6) {
                            return "weekend";
                        }
                        return "";
                    };

                    gantt.config.date_format = "%Y-%m-%d %H:%i";
                    gantt.config.xml_date = "%Y-%m-%d %H:%i";

                    // Responsive gantt configuration
                    const isMobile = window.innerWidth < 640;
                    const isTablet = window.innerWidth < 1024;

                    gantt.config.scales = [{
                            unit: "month",
                            step: 1,
                            format: isMobile ? "%M %y" : "%F %Y"
                        },
                        {
                            unit: "day",
                            step: isMobile ? 2 : 1,
                            format: "%j"
                        }
                    ];

                    gantt.config.readonly = true;
                    gantt.config.drag_move = false;
                    gantt.config.drag_resize = false;
                    gantt.config.drag_progress = false;
                    gantt.config.drag_links = false;

                    // Responsive grid and sizing
                    gantt.config.grid_width = isMobile ? 200 : (isTablet ? 280 : 350);
                    gantt.config.row_height = isMobile ? 35 : 40;
                    gantt.config.task_height = isMobile ? 28 : 32;
                    gantt.config.bar_height = isMobile ? 20 : 24;

                    // Responsive columns
                    if (isMobile) {
                        gantt.config.columns = [{
                                name: "text",
                                label: "Task",
                                width: 140,
                                tree: true
                            },
                            {
                                name: "status",
                                label: "Status",
                                width: 60,
                                align: "center"
                            }
                        ];
                    } else {
                        gantt.config.columns = [{
                                name: "text",
                                label: "Task Name",
                                width: isTablet ? 160 : 200,
                                tree: true
                            },
                            {
                                name: "status",
                                label: "Status",
                                width: isTablet ? 80 : 100,
                                align: "center"
                            },
                            {
                                name: "duration",
                                label: "Duration",
                                width: 50,
                                align: "center"
                            }
                        ];
                    }

                    gantt.templates.task_class = function(start, end, task) {
                        return task.is_overdue ? "overdue" : "";
                    };

                    gantt.templates.tooltip_text = function(start, end, task) {
                        if (isMobile) {
                            return `<b>${task.text}</b><br/>
                                    <b>Status:</b> ${task.status}<br/>
                                    <b>Progress:</b> ${Math.round(task.progress * 100)}%<br/>
                                    ${task.is_overdue ? '<b style="color: #ef4444;">âš ï¸ OVERDUE</b><br/>' : ''}
                                    <i>Tap for details</i>`;
                        } else {
                            return `<b>Task:</b> ${task.text}<br/>
                                    <b>Status:</b> ${task.status}<br/>
                                    <b>Duration:</b> ${task.duration} day(s)<br/>
                                    <b>Progress:</b> ${Math.round(task.progress * 100)}%<br/>
                                    <b>Start:</b> ${gantt.templates.tooltip_date_format(start)}<br/>
                                    <b>End:</b> ${gantt.templates.tooltip_date_format(end)}
                                    ${task.is_overdue ? '<br/><b style="color: #ef4444;">âš ï¸ OVERDUE</b>' : ''}
                                    <br/><i>Click to view details</i>`;
                        }
                    };

                    // Add click event handler for task bars
                    gantt.attachEvent("onTaskClick", function(id, e) {
                        console.log('Task clicked:', id);
                        showTicketModal(id);
                        return false; // Prevent default gantt behavior
                    });

                } catch (configError) {
                    console.error('Error configuring gantt:', configError);
                    throw new Error('Failed to configure Gantt chart');
                }

                try {
                    if (!window.ganttState.initialized) {
                        gantt.init("gantt_here");
                        window.ganttState.initialized = true;
                        console.log('Gantt initialized for the first time');
                    }
                } catch (initError) {
                    console.error('Error initializing gantt:', initError);
                    throw new Error('Failed to initialize Gantt chart');
                }

                try {
                    gantt.clearAll();

                    if (!Array.isArray(ganttData.data)) {
                        throw new Error('Invalid gantt data format: data must be an array');
                    }

                    const processedData = {
                        data: ganttData.data.map(task => {
                            const convertDate = (dateStr) => {
                                if (!dateStr) return dateStr;
                                try {
                                    const parts = dateStr.split(' ');
                                    const datePart = parts[0];
                                    const timePart = parts[1] || '00:00';
                                    const [day, month, year] = datePart.split('-');
                                    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} ${timePart}`;
                                } catch (e) {
                                    console.warn('Error converting date:', dateStr, e);
                                    return dateStr;
                                }
                            };

                            // Extract status from ticket details
                            const statusName = task.ticket_details?.status?.name || task.status || 'Unknown';
                            const isOverdue = task.is_overdue || task.ticket_details?.is_overdue || false;
                            const progressPercentage = task.ticket_details?.progress_percentage || task
                                .progress * 100 || 0;

                            console.log('ðŸŽ¯ Processing task:', task.text, 'Status:', statusName, 'Overdue:',
                                isOverdue);

                            return {
                                ...task,
                                start_date: convertDate(task.start_date),
                                end_date: convertDate(task.end_date),
                                status: statusName, // Ensure status is available at root level for templates
                                is_overdue: isOverdue,
                                progress: progressPercentage /
                                    100, // dhtmlx expects 0-1 range for gantt display
                                progress_percent: Math.round(progressPercentage) +
                                    '%' // Formatted percentage for Excel export
                            };
                        }),
                        links: ganttData.links || []
                    };

                    for (let i = 0; i < processedData.data.length; i++) {
                        const task = processedData.data[i];
                        if (!task.id || !task.text || !task.start_date || !task.end_date) {
                            console.warn('Invalid task data at index', i, task);
                            continue;
                        }
                    }

                    gantt.parse(processedData);

                    // âœ¨ Add today marker line
                    const today = new Date();
                    gantt.addMarker({
                        start_date: today,
                        css: "today",
                        text: "Today"
                    });

                    console.log('dhtmlxGantt initialized successfully with', processedData.data.length,
                        'tasks and today marker');

                } catch (parseError) {
                    console.error('Error parsing gantt data:', parseError);
                    throw new Error('Failed to load Gantt data');
                }

            } catch (error) {
                console.error('Error initializing dhtmlxGantt:', error);
                showErrorMessage(error.message || 'Error loading timeline');
            }
        }

        function setupLivewireListeners() {
            // Handle manual refresh button ONLY (not page reload)
            Livewire.on('data-refreshed', () => {
                console.log('Manual refresh button clicked, maintaining tab state...');
                const currentTab = getCurrentTab();

                // After refresh completes, restore tab state
                setTimeout(() => {
                    switchTab(currentTab);
                    console.log('Tab state restored to:', currentTab);
                }, 150);
            });

            // Handle gantt refresh
            Livewire.on('refreshGanttData', () => {
                console.log('Refreshing gantt chart...');
                setTimeout(() => {
                    // Clear existing cache before reinitializing
                    window.ganttTicketsCache = {};
                    initializeGanttSafely();
                }, 200);
            });

            // Handle timeline switch
            Livewire.on('switch-to-timeline', () => {
                console.log('Livewire: Switching to timeline...');
                setTimeout(() => {
                    initializeGanttSafely();
                }, 300);
            });

            // Handle pagination updates - maintain tab state
            Livewire.on('pagination-updated', () => {
                console.log('Pagination updated, maintaining tab state');
                const currentTab = getCurrentTab();
                setTimeout(() => {
                    switchTab(currentTab);
                }, 50);
            });

            // Listen for any wire:navigate events to maintain tab state
            Livewire.hook('morph.updated', ({
                el,
                component
            }) => {
                console.log('Livewire component updated, maintaining tab state...');
                const currentTab = getCurrentTab();
                setTimeout(() => {
                    switchTab(currentTab);
                }, 25);
            });
        }

        // Modal Functions - Optimized without Livewire API calls
        function showTicketModal(ticketId) {
            console.log('Opening modal for ticket:', ticketId);

            // Get cached ticket details
            const ticketDetails = window.ganttTicketsCache[ticketId];

            if (!ticketDetails) {
                console.error('Ticket details not found in cache for ID:', ticketId);
                showModalError('Ticket details not available');
                return;
            }

            // Show modal with animation
            const modal = document.getElementById('ticket-modal');
            const backdrop = document.getElementById('modal-backdrop');
            const panel = document.getElementById('modal-panel');

            modal.classList.remove('hidden');

            // Trigger animations
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('scale-95', 'opacity-0');
                panel.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Show loading briefly for smooth UX
            showModalLoading();

            // Populate with cached data after brief delay
            setTimeout(() => {
                populateTicketModal(ticketDetails);
            }, 300);
        }

        function showModalLoading() {
            document.getElementById('modal-loading').classList.remove('hidden');
            document.getElementById('modal-content').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('ticket-subtitle').textContent = 'Loading ticket information...';
        }

        function populateTicketModal(ticket) {
            // Hide loading
            document.getElementById('modal-loading').classList.add('hidden');
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('modal-content').classList.remove('hidden');

            // Update subtitle
            document.getElementById('ticket-subtitle').textContent = `Code: ${ticket.uuid}`;

            // Populate basic fields
            document.getElementById('ticket-code').textContent = ticket.uuid;
            document.getElementById('ticket-name').textContent = ticket.name;
            document.getElementById('ticket-description').textContent = ticket.description;

            // Status badge with animation
            const statusElement = document.getElementById('ticket-status');
            statusElement.textContent = ticket.status.name;
            statusElement.style.backgroundColor = ticket.status.color;
            statusElement.classList.add('animate-pulse');
            setTimeout(() => statusElement.classList.remove('animate-pulse'), 500);

            // Priority badge
            const priorityElement = document.getElementById('ticket-priority');
            priorityElement.textContent = ticket.priority.name;
            if (ticket.priority.color && ticket.priority.color !== '#6B7280') {
                priorityElement.style.backgroundColor = ticket.priority.color;
                priorityElement.style.color = '#ffffff';
            }

            // Dates
            document.getElementById('ticket-start-date').textContent = ticket.start_date;
            const dueDateElement = document.getElementById('ticket-due-date');
            dueDateElement.textContent = ticket.due_date;
            dueDateElement.classList.remove('text-red-600', 'font-medium');

            if (ticket.is_overdue) {
                dueDateElement.classList.add('text-red-600', 'font-semibold');
                dueDateElement.innerHTML +=
                    ' <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full ml-2">OVERDUE</span>';
            }

            // Progress with smooth animation
            const progressBar = document.getElementById('ticket-progress-bar');
            const progressText = document.getElementById('ticket-progress-text');
            const progressPercentage = document.getElementById('ticket-progress-percentage');

            // Reset progress bar
            progressBar.style.width = '0%';

            // Animate progress
            setTimeout(() => {
                progressBar.style.width = ticket.progress_percentage + '%';
                progressText.textContent = getProgressLabel(ticket.progress_percentage);
                progressPercentage.textContent = ticket.progress_percentage + '%';

                // Progress color based on percentage
                if (ticket.progress_percentage >= 100) {
                    progressBar.className =
                        'h-3 rounded-full transition-all duration-500 ease-out transform origin-left bg-gradient-to-r from-green-400 to-green-600';
                } else if (ticket.progress_percentage >= 75) {
                    progressBar.className =
                        'h-3 rounded-full transition-all duration-500 ease-out transform origin-left bg-gradient-to-r from-blue-400 to-blue-600';
                } else if (ticket.progress_percentage >= 50) {
                    progressBar.className =
                        'h-3 rounded-full transition-all duration-500 ease-out transform origin-left bg-gradient-to-r from-yellow-400 to-yellow-600';
                } else if (ticket.progress_percentage >= 25) {
                    progressBar.className =
                        'h-3 rounded-full transition-all duration-500 ease-out transform origin-left bg-gradient-to-r from-orange-400 to-orange-600';
                } else {
                    progressBar.className =
                        'h-3 rounded-full transition-all duration-500 ease-out transform origin-left bg-gradient-to-r from-gray-400 to-gray-500';
                }
            }, 100);


        }

        function showModalError(message) {
            document.getElementById('modal-loading').classList.add('hidden');
            document.getElementById('modal-content').classList.add('hidden');
            document.getElementById('modal-error').classList.remove('hidden');
            document.getElementById('modal-error-message').textContent = message;
            document.getElementById('ticket-subtitle').textContent = 'Error loading ticket';

            // Show modal if not already visible
            const modal = document.getElementById('ticket-modal');
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                const backdrop = document.getElementById('modal-backdrop');
                const panel = document.getElementById('modal-panel');

                setTimeout(() => {
                    backdrop.classList.remove('opacity-0');
                    backdrop.classList.add('opacity-100');
                    panel.classList.remove('scale-95', 'opacity-0');
                    panel.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
        }

        function closeTicketModal() {
            const modal = document.getElementById('ticket-modal');
            const backdrop = document.getElementById('modal-backdrop');
            const panel = document.getElementById('modal-panel');

            // Animate out
            backdrop.classList.add('opacity-0');
            backdrop.classList.remove('opacity-100');
            panel.classList.add('scale-95', 'opacity-0');
            panel.classList.remove('scale-100', 'opacity-100');

            // Hide modal after animation
            setTimeout(() => {
                modal.classList.add('hidden');
                // Reset modal state
                showModalLoading();
            }, 300);
        }

        // Helper functions
        function getProgressLabel(percentage) {
            if (percentage >= 100) return 'Completed';
            if (percentage >= 75) return 'Nearly Done';
            if (percentage >= 50) return 'In Progress';
            if (percentage >= 25) return 'Getting Started';
            return 'Not Started';
        }

        function getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        }

        function generateAvatarColor(name) {
            const colors = [
                '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
                '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
                '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
                '#ec4899', '#f43f5e'
            ];

            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }

            return colors[Math.abs(hash) % colors.length];
        }

        // ðŸ“Š Export Gantt Chart to Excel using Weekly Timeline Format
        function exportGanttToExcel() {
            try {
                console.log('ðŸš€ Starting Gantt Excel export with weekly headers...');

                // Check if gantt is initialized and has data
                if (typeof gantt === 'undefined' || !gantt.exportToExcel) {
                    console.error('âŒ Gantt export API not available');
                    showNotification('Export functionality not available. Please refresh the page.', 'error');
                    return;
                }

                // Check if we have data
                const ganttData = getGanttData();
                if (!ganttData.data || ganttData.data.length === 0) {
                    console.error('âŒ No gantt data to export');
                    showNotification('No timeline data available to export', 'error');
                    return;
                }

                // Show loading indicator
                const exportButton = document.querySelector('button[onclick="exportGanttToExcel()"]');
                const originalText = exportButton.innerHTML;
                exportButton.innerHTML = `
                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="hidden sm:inline">Exporting...</span>
                    <span class="sm:hidden">...</span>
                `;
                exportButton.disabled = true;

                console.log('ðŸ“Š Exporting', ganttData.data.length, 'tasks to Excel...');

                // Configure export options with visual styling, cell colors, and weekly scale
                const exportOptions = {
                    format: "xlsx", // ðŸ“‹ Explicitly set Excel format (.xlsx)
                    visual: "base-colors",
                    cellColors: true,
                    name: `{{ $project->name ?? 'Project' }}_Gantt_Timeline_${new Date().toISOString().split('T')[0]}.xlsx`,
                    raw: false,
                    start: null, // Let gantt determine the optimal range
                    end: null,
                    scale: "week", // ðŸ“… Use weekly scale instead of daily
                    header: {
                        title: "{{ $project->name ?? 'Project' }} - Gantt Chart Timeline (Weekly View)",
                        subtitle: `Generated on ${new Date().toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}`
                    },
                    columns: [{
                            id: "text",
                            header: "Task Name",
                            width: 200
                        },
                        {
                            id: "start_date",
                            header: "Start Date",
                            width: 100
                        },
                        {
                            id: "end_date",
                            header: "End Date",
                            width: 100
                        },
                        {
                            id: "duration",
                            header: "Duration (Days)",
                            width: 80
                        },
                        {
                            id: "status",
                            header: "Status",
                            width: 100
                        },
                        {
                            id: "progress_percent",
                            header: "Progress",
                            width: 80
                        }
                    ]
                };

                console.log('ðŸ“‹ Export options configured with weekly scale:', exportOptions);

                // Log sample data for debugging
                if (ganttData.data.length > 0) {
                    console.log('ðŸ“„ Sample task data for export:');
                    console.log('- Task Name:', ganttData.data[0].text);
                    console.log('- Status:', ganttData.data[0].status);
                    console.log('- Progress:', ganttData.data[0].progress_percent);
                    console.log('- Start Date:', ganttData.data[0].start_date);
                    console.log('- End Date:', ganttData.data[0].end_date);
                }

                // Perform the export with weekly headers
                gantt.exportToExcel(exportOptions);

                console.log('âœ… Excel export with weekly headers initiated successfully');

                // Show success message and restore button
                setTimeout(() => {
                    exportButton.innerHTML = originalText;
                    exportButton.disabled = false;
                    showNotification('Timeline exported to Excel successfully!', 'success');
                    console.log('ðŸŽ‰ Export completed successfully');
                }, 2000);

            } catch (error) {
                console.error('ðŸ’¥ Error exporting gantt chart:', error);
                showNotification('Failed to export timeline: ' + error.message, 'error');

                // Restore button
                const exportButton = document.querySelector('button[onclick="exportGanttToExcel()"]');
                if (exportButton) {
                    exportButton.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="hidden sm:inline">Export Weekly Timeline</span>
                        <span class="sm:hidden">Weekly</span>
                    `;
                    exportButton.disabled = false;
                }
            }
        }

        // ï¿½ Show notification helper function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 
                type === 'error' ? 'bg-red-500 text-white' : 
                'bg-blue-500 text-white'
            }`;

            const icon = type === 'success' ?
                `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>` :
                type === 'error' ?
                `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>` :
                `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>`;

            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    ${icon}
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTicketModal();
            }
        });
    </script>
@endpush
