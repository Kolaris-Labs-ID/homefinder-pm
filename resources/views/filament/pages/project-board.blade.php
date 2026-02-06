<x-filament-panels::page>

    {{-- Project Selection - Monday.com Style Table --}}
    @if (! $selectedProject)
        <div class="pb-board" style="font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-3 mb-6">
                {{-- New Project Button --}}
                @can('create', \App\Models\Project::class)
                    <a
                        href="{{ \App\Filament\Resources\ProjectResource::getUrl('create') }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-md text-white text-sm font-medium transition-colors"
                        style="background-color: #56C078;"
                        onmouseover="this.style.backgroundColor='#4CAF6A'"
                        onmouseout="this.style.backgroundColor='#56C078'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Project
                    </a>
                @endcan

                {{-- Search --}}
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="projectSearch"
                        placeholder="Search"
                        class="pl-9 pr-4 py-2 text-sm border rounded-md bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-300 dark:focus:ring-gray-600"
                        style="border-color: #E5E7EB; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);"
                    />
                </div>

                {{-- Sort --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border rounded-md bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        style="border-color: #E5E7EB; color: #111827;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                        Sort
                    </button>
                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition
                        class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 py-1"
                        style="display: none;"
                    >
                        <button wire:click="sortProjectsBy('name')" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center justify-between">
                            Project Name
                            @if($projectSortField === 'name')
                                <svg class="w-3 h-3 {{ $projectSortDirection === 'asc' ? '' : 'rotate-180' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z"/></svg>
                            @endif
                        </button>
                        <button wire:click="sortProjectsBy('end_date')" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center justify-between">
                            Due Date
                            @if($projectSortField === 'end_date')
                                <svg class="w-3 h-3 {{ $projectSortDirection === 'asc' ? '' : 'rotate-180' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z"/></svg>
                            @endif
                        </button>
                        <button wire:click="sortProjectsBy('status')" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center justify-between">
                            Status
                            @if($projectSortField === 'status')
                                <svg class="w-3 h-3 {{ $projectSortDirection === 'asc' ? '' : 'rotate-180' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z"/></svg>
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Hide Columns --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border rounded-md bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        style="border-color: #E5E7EB; color: #111827;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>
                        Hide
                    </button>
                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition
                        class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 py-1"
                        style="display: none;"
                    >
                        @foreach(['due_date' => 'Due Date', 'status' => 'Status', 'members' => 'Members', 'progress' => 'Progress'] as $col => $label)
                            <button wire:click="toggleColumn('{{ $col }}')" @click.stop class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                <span class="w-4 h-4 border rounded flex items-center justify-center {{ in_array($col, $hiddenColumns) ? 'border-gray-300 dark:border-gray-600' : 'bg-green-500 border-green-500' }}" style="{{ !in_array($col, $hiddenColumns) ? 'background-color: #56C078; border-color: #56C078;' : '' }}">
                                    @if(!in_array($col, $hiddenColumns))
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </span>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($projects->isEmpty())
                <div class="flex flex-col items-center justify-center h-64 text-gray-500 dark:text-gray-400 gap-4">
                    <div class="flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 p-6">
                        <x-heroicon-o-folder class="w-16 h-16 text-gray-400 dark:text-gray-500" />
                    </div>
                    <h3 class="text-xl font-medium text-gray-600 dark:text-gray-300">No projects available</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        You don't have access to any projects yet.
                    </p>
                </div>
            @else
                @php $groupedProjects = $this->getGroupedProjects(); @endphp

                @forelse ($groupedProjects as $groupName => $groupProjects)
                    <div class="mb-6">
                        {{-- Group Header --}}
                        <button
                            wire:click="toggleGroup('{{ $groupName }}')"
                            class="flex items-center gap-2 mb-3 group cursor-pointer"
                        >
                            <svg
                                class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200 {{ in_array($groupName, $collapsedGroups) ? '-rotate-90' : '' }}"
                                fill="currentColor" viewBox="0 0 20 20"
                            >
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $groupName }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">({{ $groupProjects->count() }})</span>
                        </button>

                        {{-- Table --}}
                        @if (!in_array($groupName, $collapsedGroups))
                            <div class="rounded-lg border overflow-hidden" style="border-color: #E5E7EB; background-color: #F7F8FA;">
                                <div class="overflow-x-auto">
                                    <table class="w-full" style="min-width: 700px;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                                <th class="text-left py-3 px-4 text-sm font-semibold" style="color: #111827; width: 30%;">Project</th>
                                                @if(!in_array('due_date', $hiddenColumns))
                                                    <th class="text-left py-3 px-4 text-sm font-semibold" style="color: #111827; width: 18%;">
                                                        <span class="inline-flex items-center gap-1">
                                                            Due date
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </span>
                                                    </th>
                                                @endif
                                                @if(!in_array('status', $hiddenColumns))
                                                    <th class="text-center py-3 px-4 text-sm font-semibold" style="color: #111827; width: 18%;">
                                                        <span class="inline-flex items-center gap-1">
                                                            Status
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </span>
                                                    </th>
                                                @endif
                                                @if(!in_array('members', $hiddenColumns))
                                                    <th class="text-center py-3 px-4 text-sm font-semibold" style="color: #111827; width: 18%;">Member</th>
                                                @endif
                                                @if(!in_array('progress', $hiddenColumns))
                                                    <th class="text-center py-3 px-4 text-sm font-semibold" style="color: #111827; width: 12%;">Progress</th>
                                                @endif
                                                <th style="width: 40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($groupProjects as $project)
                                                @php
                                                    $status = $this->getProjectStatus($project);
                                                    $dueDateIndicator = $this->getDueDateIndicator($project);
                                                    $progress = $project->progress_percentage ?? 0;
                                                @endphp
                                                <tr
                                                    wire:click="selectProject({{ $project->id }})"
                                                    class="cursor-pointer transition-colors hover:bg-blue-50/50 dark:hover:bg-gray-700/50"
                                                    style="background-color: #FFFFFF; border-bottom: 1px solid #F7F8FA; height: 64px;"
                                                >
                                                    {{-- Project Name --}}
                                                    <td class="py-3 px-4">
                                                        <div class="flex items-center gap-3">
                                                            <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold" style="background-color: #56C078;">
                                                                {{ strtoupper(substr($project->name, 0, 1)) }}
                                                            </div>
                                                            <div class="min-w-0">
                                                                <span class="text-sm font-medium truncate block" style="color: #111827;">{{ $project->name }}</span>
                                                                @if($project->ticket_prefix)
                                                                    <span class="text-xs" style="color: #6B7280;">{{ $project->ticket_prefix }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {{-- Due Date --}}
                                                    @if(!in_array('due_date', $hiddenColumns))
                                                        <td class="py-3 px-4">
                                                            @if($project->end_date)
                                                                <div class="flex items-center gap-2">
                                                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $dueDateIndicator['dot'] }};"></span>
                                                                    <span class="text-sm" style="color: #6B7280;">{{ $project->end_date->format('d M Y') }}</span>
                                                                </div>
                                                            @else
                                                                <span class="text-sm" style="color: #9CA3AF;">No date</span>
                                                            @endif
                                                        </td>
                                                    @endif

                                                    {{-- Status Badge --}}
                                                    @if(!in_array('status', $hiddenColumns))
                                                        <td class="py-3 px-4 text-center">
                                                            <span
                                                                class="inline-flex items-center justify-center px-4 py-1.5 rounded-md text-sm font-medium whitespace-nowrap"
                                                                style="background-color: {{ $status['bg'] }}; color: {{ $status['text'] }}; min-width: 110px;"
                                                            >
                                                                {{ $status['label'] }}
                                                            </span>
                                                        </td>
                                                    @endif

                                                    {{-- Members --}}
                                                    @if(!in_array('members', $hiddenColumns))
                                                        <td class="py-3 px-4">
                                                            <div class="flex items-center justify-center">
                                                                @if($project->users && $project->users->isNotEmpty())
                                                                    <div class="flex -space-x-2">
                                                                        @foreach($project->users->take(4) as $member)
                                                                            <div
                                                                                class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-[10px] font-semibold flex-shrink-0"
                                                                                style="background-color: #E0F2FE; color: #0369A1;"
                                                                                title="{{ $member->name }}"
                                                                            >
                                                                                {{ \Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                                                            </div>
                                                                        @endforeach
                                                                        @if($project->users->count() > 4)
                                                                            <div
                                                                                class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-[10px] font-semibold flex-shrink-0"
                                                                                style="background-color: #F3F4F6; color: #6B7280;"
                                                                            >
                                                                                +{{ $project->users->count() - 4 }}
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <span class="text-xs" style="color: #9CA3AF;">-</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    @endif

                                                    {{-- Progress --}}
                                                    @if(!in_array('progress', $hiddenColumns))
                                                        <td class="py-3 px-4 text-center">
                                                            <div class="flex items-center justify-center gap-2">
                                                                <div class="w-16 h-1.5 rounded-full overflow-hidden" style="background-color: #E5E7EB;">
                                                                    <div
                                                                        class="h-full rounded-full transition-all duration-300"
                                                                        style="width: {{ min($progress, 100) }}%; background-color: {{ $progress >= 100 ? '#10B981' : ($progress >= 50 ? '#56C078' : '#F4C571') }};"
                                                                    ></div>
                                                                </div>
                                                                <span class="text-xs font-medium" style="color: #6B7280;">{{ number_format(min($progress, 100), 0) }}%</span>
                                                            </div>
                                                        </td>
                                                    @endif

                                                    {{-- Action --}}
                                                    <td class="py-3 px-2">
                                                        <div class="flex items-center justify-center">
                                                            <svg class="w-4 h-4" style="color: #9CA3AF;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-40 text-gray-500 dark:text-gray-400 gap-2">
                        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <p class="text-sm">No projects match your search.</p>
                    </div>
                @endforelse
            @endif
        </div>
    @endif

    @if($selectedProject)
        <div
            x-data="{
                draggingTicket: null,
                isTouchDevice: false,
                touchStartX: 0,
                touchStartY: 0,
                scrollStartX: 0,
                
                init() {
                    this.$nextTick(() => {
                        this.removeAllEventListeners();
                        this.attachAllEventListeners();
                        this.setupTouchScrolling();
                        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
                        
                        // Add listeners for when user returns to the board
                        this.setupPageVisibilityListener();
                    });
                },
                
                setupPageVisibilityListener() {
                    // Listen for page visibility changes (when user returns to tab)
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) {
                            // Page is visible again, reinitialize drag and drop
                            setTimeout(() => {
                                this.removeAllEventListeners();
                                this.attachAllEventListeners();
                            }, 100);
                        }
                    });
                    
                    // Listen for window focus (when user returns to window)
                    window.addEventListener('focus', () => {
                        // Window is focused again, reinitialize drag and drop
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 100);
                    });
                    
                    // Listen for popstate event (when user navigates back)
                    window.addEventListener('popstate', () => {
                        // User navigated back, reinitialize drag and drop
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 200);
                    });
                    
                    // Listen for Livewire lifecycle events
                    document.addEventListener('livewire:navigated', () => {
                        // Livewire navigated, reinitialize drag and drop
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 300);
                    });
                    
                    // Listen for Livewire load and update events
                    document.addEventListener('livewire:load', () => {
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 100);
                    });
                    
                    // Listen for Livewire update events
                    document.addEventListener('livewire:updated', () => {
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 100);
                    });
                    
                    // Listen for custom board events
                    window.addEventListener('ticket-updated', () => {
                        setTimeout(() => {
                            this.removeAllEventListeners();
                            this.attachAllEventListeners();
                        }, 150);
                    });
                    
                    // Periodic check to ensure drag and drop is working
                    setInterval(() => {
                        if (document.visibilityState === 'visible') {
                            this.ensureDragDropInitialized();
                        }
                    }, 2000);
                },
                
                ensureDragDropInitialized() {
                    // Check if any ticket cards exist but are not draggable
                    const tickets = document.querySelectorAll('.ticket-card');
                    let needsReinitialization = false;
                    
                    tickets.forEach(ticket => {
                        if (!ticket.getAttribute('draggable') || ticket.getAttribute('draggable') !== 'true') {
                            needsReinitialization = true;
                        }
                    });
                    
                    // If any tickets are not draggable, reinitialize
                    if (needsReinitialization && tickets.length > 0) {
                        this.removeAllEventListeners();
                        this.attachAllEventListeners();
                    }
                },
                
                setupTouchScrolling() {
                    const container = document.getElementById('board-container');
                    
                    container.addEventListener('touchstart', (e) => {
                        this.touchStartX = e.touches[0].clientX;
                        this.touchStartY = e.touches[0].clientY;
                        this.scrollStartX = container.scrollLeft;
                    }, { passive: true });
                    
                    container.addEventListener('touchmove', (e) => {
                        if (e.touches.length !== 1) return;
                        
                        const touchX = e.touches[0].clientX;
                        const touchY = e.touches[0].clientY;
                        
                        // Calculate both horizontal and vertical movement
                        const moveX = this.touchStartX - touchX;
                        const moveY = this.touchStartY - touchY;
                        
                        // If horizontal movement is greater than vertical movement, prevent default scrolling
                        if (Math.abs(moveX) > Math.abs(moveY)) {
                            e.preventDefault();
                            container.scrollLeft = this.scrollStartX + moveX;
                        }
                    }, { passive: false });
                },
                
                removeAllEventListeners() {
                    const tickets = document.querySelectorAll('.ticket-card');
                    tickets.forEach(ticket => {
                        ticket.removeAttribute('draggable');
                        const newTicket = ticket.cloneNode(true);
                        ticket.parentNode.replaceChild(newTicket, ticket);
                    });
                    
                    const columns = document.querySelectorAll('.status-column');
                    columns.forEach(column => {
                        const newColumn = column.cloneNode(false);
                        
                        while (column.firstChild) {
                            newColumn.appendChild(column.firstChild);
                        }
                        
                        if (column.parentNode) {
                            column.parentNode.replaceChild(newColumn, column);
                        }
                    });
                },
                
                attachAllEventListeners() {
                    // Check permission before attaching drag events
                    // If user cannot move tickets, don't attach drag and drop listeners
                    @if(!$this->canMoveTickets())
                        return; // Exit early if user doesn't have permission to move tickets
                    @endif
                    
                    const tickets = document.querySelectorAll('.ticket-card');
                    tickets.forEach(ticket => {
                        // Enable dragging only for users with proper permissions
                        ticket.setAttribute('draggable', true);
                        
                        ticket.addEventListener('dragstart', (e) => {
                            this.draggingTicket = ticket.getAttribute('data-ticket-id');
                            ticket.classList.add('opacity-50');
                            e.dataTransfer.effectAllowed = 'move';
                        });
                        
                        ticket.addEventListener('dragend', () => {
                            ticket.classList.remove('opacity-50');
                            this.draggingTicket = null;
                        });
                        
                        // Touch events for mobile drag and drop
                        let longPressTimer;
                        let isDragging = false;
                        let originalColumn;
                        
                        ticket.addEventListener('touchstart', (e) => {
                            // Only proceed if not already scrolling
                            if (isDragging) return;
                            
                            longPressTimer = setTimeout(() => {
                                originalColumn = ticket.closest('.status-column');
                                this.draggingTicket = ticket.getAttribute('data-ticket-id');
                                ticket.classList.add('opacity-50', 'relative', 'z-30');
                                isDragging = true;
                                
                                // Visual feedback
                                ticket.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
                            }, 500); // 500ms long press
                        }, { passive: true });
                        
                        ticket.addEventListener('touchmove', (e) => {
                            if (!isDragging) {
                                // If not dragging, clear the timer to prevent entering drag mode
                                clearTimeout(longPressTimer);
                                return;
                            }
                            
                            // Move the ticket with touch
                            const touch = e.touches[0];
                            const columns = document.querySelectorAll('.status-column');
                            
                            // Find column under touch position
                            let targetColumn = null;
                            columns.forEach(column => {
                                const rect = column.getBoundingClientRect();
                                if (touch.clientX >= rect.left && 
                                    touch.clientX <= rect.right && 
                                    touch.clientY >= rect.top && 
                                    touch.clientY <= rect.bottom) {
                                    targetColumn = column;
                                    column.classList.add('bg-primary-50', 'dark:bg-primary-950');
                                } else {
                                    column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                                }
                            });
                        });
                        
                        ticket.addEventListener('touchend', (e) => {
                            clearTimeout(longPressTimer);
                            
                            if (!isDragging) return;
                            
                            isDragging = false;
                            ticket.classList.remove('opacity-50', 'relative', 'z-30');
                            ticket.style.boxShadow = '';
                            
                            // Find column under final touch position
                            const touch = e.changedTouches[0];
                            const columns = document.querySelectorAll('.status-column');
                            
                            let targetColumn = null;
                            columns.forEach(column => {
                                const rect = column.getBoundingClientRect();
                                if (touch.clientX >= rect.left && 
                                    touch.clientX <= rect.right && 
                                    touch.clientY >= rect.top && 
                                    touch.clientY <= rect.bottom) {
                                    targetColumn = column;
                                }
                                column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                            });
                            
                            if (targetColumn && targetColumn !== originalColumn) {
                                const statusId = targetColumn.getAttribute('data-status-id');
                                const ticketId = this.draggingTicket;
                                
                                this.$wire.moveTicket(
                                    parseInt(ticketId), 
                                    parseInt(statusId)
                                );
                            }
                            
                            this.draggingTicket = null;
                        });
                        
                        ticket.addEventListener('touchcancel', () => {
                            clearTimeout(longPressTimer);
                            if (!isDragging) return;
                            
                            isDragging = false;
                            ticket.classList.remove('opacity-50', 'relative', 'z-30');
                            ticket.style.boxShadow = '';
                            this.draggingTicket = null;
                            
                            document.querySelectorAll('.status-column').forEach(column => {
                                column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                            });
                        });
                    });
                    
                    // Attach drag and drop listeners to columns
                    const columns = document.querySelectorAll('.status-column');
                    columns.forEach(column => {
                        column.addEventListener('dragover', (e) => {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                            column.classList.add('bg-primary-50', 'dark:bg-primary-950');
                        });
                        
                        column.addEventListener('dragleave', () => {
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                        });
                        
                        column.addEventListener('drop', (e) => {
                            e.preventDefault();
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                            
                            if (this.draggingTicket) {
                                const statusId = column.getAttribute('data-status-id');
                                const ticketId = this.draggingTicket;
                                this.draggingTicket = null;
                                
                                this.$wire.moveTicket(
                                    parseInt(ticketId), 
                                    parseInt(statusId)
                                );
                            }
                        });
                    });
                }
            }"
            x-init="init()"
            @ticket-moved.window="init()"
            @ticket-updated.window="init()"
            @refresh-board.window="init()"
            wire:key="board-container-{{ $selectedProject->id }}"
            class="relative overflow-x-auto pb-6 {{ !$this->canMoveTickets() ? 'view-only-mode' : '' }}"
            id="board-container"
        >
            {{-- Scroll indicators --}}
            <div class="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-gray-100 dark:from-gray-800 to-transparent pointer-events-none z-10"
                 x-data="{ visible: false }"
                 x-init="$nextTick(() => { 
                     const container = document.getElementById('board-container');
                     container.addEventListener('scroll', () => {
                         visible = container.scrollLeft > 20;
                     });
                 })"
                 x-show="visible"
                 x-transition.opacity
            ></div>
            
            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-gray-100 dark:from-gray-800 to-transparent pointer-events-none z-10"
                 x-data="{ visible: false }"
                 x-init="$nextTick(() => { 
                     const container = document.getElementById('board-container');
                     visible = container.scrollWidth > container.clientWidth;
                     container.addEventListener('scroll', () => {
                         visible = container.scrollLeft + container.clientWidth < container.scrollWidth - 20;
                     });
                 })"
                 x-show="visible"
                 x-transition.opacity
            ></div>

            {{-- Mobile swipe hint --}}
            <div class="md:hidden flex justify-center mb-2 text-xs text-gray-500 dark:text-gray-400 items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Swipe horizontally to view all columns</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </div>

            {{-- View Only Mode Indicator --}}
            @if(!$this->canMoveTickets())
                <div class="flex justify-center mb-4">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="text-sm font-medium">View Only Mode</span>
                        <span class="text-xs opacity-75">You can view tickets but cannot move them</span>
                    </div>
                </div>
            @endif

            <div class="inline-flex gap-4 pb-2 min-w-full">
                @foreach ($ticketStatuses as $status)
                    <div 
                        class="status-column rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50 dark:bg-gray-900"
                        style="width: calc(85vw - 2rem); min-width: 280px; max-width: 350px; height: 700px; @media (min-width: 640px) { width: calc((100vw - 6rem) / 2); height: 750px; } @media (min-width: 1024px) { width: calc((100vw - 8rem) / 3); height: 800px; } @media (min-width: 1280px) { width: calc((100vw - 10rem) / 4); height: 850px; }"
                        data-status-id="{{ $status->id }}"
                    >
                        <div 
                            class="px-4 py-3 rounded-t-xl border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
                            style="background-color: {{ $status->color ?? '#f3f4f6' }};"
                        >
                            <div class="flex items-center justify-between">
                                <h3 class="font-medium flex items-center gap-2" style="color: white; text-shadow: 0px 0px 1px rgba(0,0,0,0.5);">
                                    <span>{{ $status->name }}</span>
                                    <span class="text-sm opacity-80">{{ $status->tickets->count() }}</span>
                                    @if($status->is_completed)
                                        <div class="flex items-center justify-center w-6 h-6 bg-green-500 rounded-full border-2 border-white shadow-lg" title="Completed Status">
                                            <svg class="w-3 h-3 text-white font-bold" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </h3>
                                
                                <!-- Sort Menu Dropdown -->
                                <div class="relative" x-data="{ open: false }">
                                    <button 
                                        @click="open = !open" 
                                        @click.away="open = false"
                                        class="p-1 rounded hover:bg-black hover:bg-opacity-20 transition-colors"
                                        style="color: white;"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                        </svg>
                                    </button>
                                    
                                    <div 
                                        x-show="open" 
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        class="absolute top-8 left-0 w-52 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
                                        style="display: none; transform: translateX(-100%);"
                                    >
                                        <div class="p-2">
                                            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">Sort list</span>
                                                <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            
                                            <div class="py-1">
                                                <button 
                                                    wire:click="setSortOrder({{ $status->id }}, 'date_created_newest')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-white rounded"
                                                >
                                                    Date created (newest first)
                                                </button>
                                                <button 
                                                    wire:click="setSortOrder({{ $status->id }}, 'date_created_oldest')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-white rounded"
                                                >
                                                    Date created (oldest first)
                                                </button>
                                                <button 
                                                    wire:click="setSortOrder({{ $status->id }}, 'card_name_alphabetical')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-white rounded"
                                                >
                                                    Card name (alphabetically)
                                                </button>
                                                <button 
                                                    wire:click="setSortOrder({{ $status->id }}, 'due_date')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-white rounded"
                                                >
                                                    Due date
                                                </button>
                                                <button 
                                                    wire:click="setSortOrder({{ $status->id }}, 'priority')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-white rounded"
                                                >
                                                    Priority
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3 flex flex-col gap-3 flex-1 overflow-y-auto" style="max-height: calc(100% - 60px);" x-data="{ visibleTickets: 10, totalTickets: {{ $status->tickets->count() }} }" x-init="$nextTick(() => { $el.addEventListener('scroll', () => { if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 100 && visibleTickets < totalTickets) { visibleTickets = Math.min(visibleTickets + 10, totalTickets); } }); })">
                            @foreach ($status->tickets as $index => $ticket)
                                <div 
                                    class="ticket-card bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 cursor-move"
                                    data-ticket-id="{{ $ticket->id }}"
                                    x-show="{{ $index }} < visibleTickets"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                >
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400 px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded truncate max-w-[120px] sm:max-w-none">
                                            {{ $ticket->uuid }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            @if ($ticket->priority)
                                                <span class="text-xs px-1.5 py-0.5 rounded whitespace-nowrap text-white font-medium" style="background-color: {{ $ticket->priority->color }};">
                                                    {{ $ticket->priority->name }}
                                                </span>
                                            @endif
                                            @if ($ticket->due_date)
                                                <span class="text-xs px-1.5 py-0.5 rounded whitespace-nowrap {{ $ticket->due_date->isPast() ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' }}">
                                                    {{ $ticket->due_date->format('M d') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $ticket->name }}</h4>
                                    
                                    @if ($ticket->description)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3 line-clamp-2">
                                            {{ \Illuminate\Support\Str::limit(strip_tags($ticket->description), 100) }}
                                        </p>
                                    @endif
                                    
                                    <div class="flex justify-between items-center mt-2">
                                       @if ($ticket->assignees->isNotEmpty())
                                            <div class="flex flex-wrap gap-1 max-w-[180px]">
                                                @foreach($ticket->assignees->take(2) as $assignee)
                                                    <div class="inline-flex items-center px-2 py-1 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 gap-1">
                                                        <span class="w-4 h-4 rounded-full bg-primary-500 flex items-center justify-center text-xs text-white flex-shrink-0">
                                                            {{ substr($assignee->name, 0, 1) }}
                                                        </span>
                                                        <span class="text-xs font-medium truncate">{{ \Illuminate\Support\Str::limit($assignee->name, 8) }}</span>
                                                    </div>
                                                @endforeach
                                                @if($ticket->assignees->count() > 2)
                                                    <div class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400">
                                                        <span class="text-xs font-medium">+{{ $ticket->assignees->count() - 2 }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 dark:text-gray-500 mr-1 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="text-xs font-medium">Unassigned</span>
                                            </div>
                                        @endif
                                        
                                        <button
                                            type="button" 
                                            wire:click="showTicketDetails({{ $ticket->id }})"
                                            class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400 flex-shrink-0"
                                        >
                                            <x-heroicon-m-eye class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            
                            @if ($status->tickets->isEmpty())
                                <div class="flex items-center justify-center h-24 text-gray-500 dark:text-gray-400 text-sm italic border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                                    No tickets
                                </div>
                            @else
                                <!-- Loading indicator for more tickets -->
                                <div x-show="visibleTickets < totalTickets" class="flex items-center justify-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                                    <div class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Loading more tickets...</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
                
                @if ($ticketStatuses->isEmpty())
                    <div class="w-full flex items-center justify-center h-40 text-gray-500 dark:text-gray-400">
                        No status columns found for this project
                    </div>
                @endif
            </div>
        </div>
    @else
    @endif
</x-filament-panels::page>