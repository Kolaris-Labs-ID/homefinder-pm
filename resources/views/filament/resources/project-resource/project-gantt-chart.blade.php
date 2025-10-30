<x-filament-panels::page>
    <div class="space-y-6">
        
        <!-- Gantt Chart Container -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <span>Timeline View</span>
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-eye class="w-4 h-4" />
                        <span>Read Only Mode</span>
                    </div>
                </div>
            </x-slot>
            
            <!-- dhtmlxGantt Container -->
            <div class="w-full">
                @if(isset($ganttData['data']) && count($ganttData['data']) > 0)
                    @php
                        $projectCount = count($ganttData['data']);
                        $minHeight = 400;
                        $rowHeight = 40;
                        $headerHeight = 80;
                        $calculatedHeight = max($minHeight, ($projectCount * $rowHeight) + $headerHeight);
                    @endphp
                    <div id="gantt_here" style="width:100%; height:{{ $calculatedHeight }}px;"></div>
                @else
                    <div class="flex flex-col items-center justify-center h-64 text-gray-500 gap-4">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="text-lg font-medium">No project data available</h3>
                        <p class="text-sm">Add start and end dates to projects to view timeline</p>
                    </div>
                @endif
            </div>
        </x-filament::section>
        
        <!-- Legend -->
        <x-filament::section>
            <x-slot name="heading">
                Status Legend
            </x-slot>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background-color: #3b82f6;"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">In Progress</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background-color: #10b981;"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Nearly Complete</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background-color: #f59e0b;"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Approaching Deadline</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background-color: #ef4444;"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Overdue</span>
                </div>
            </div>
        </x-filament::section>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css" type="text/css">
        <style>
            .gantt_task_line.overdue {
                background-color: #ef4444 !important;
                border-color: #dc2626 !important;
            }
            .gantt_task_progress.overdue {
                background-color: #b91c1c !important;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
        <script>
            let ganttPageInitialized = false;
            let ganttData = @json($ganttData ?? ['data' => [], 'links' => []]);
            
            function waitForGantt(callback) {
                if (typeof gantt !== 'undefined') {
                    callback();
                } else {
                    setTimeout(() => waitForGantt(callback), 100);
                }
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Page DOM ready, waiting for dhtmlxGantt...');
                waitForGantt(() => {
                    console.log('dhtmlxGantt loaded, initializing...');
                    initializeGanttPage();
                });
            });
            
            document.addEventListener('livewire:navigated', function() {
                console.log('Livewire navigated, reinitializing gantt...');
                if (ganttPageInitialized) {
                    gantt.clearAll();
                    ganttPageInitialized = false;
                }
                waitForGantt(() => {
                    initializeGanttPage();
                });
            });

            function initializeGanttPage() {
                try {
                    console.log('Page dhtmlxGantt data:', ganttData);
                    
                    if (!ganttData.data || ganttData.data.length === 0) {
                        console.log('No page gantt data available');
                        return;
                    }

                    const container = document.getElementById('gantt_here');
                    if (!container) {
                        console.error('Page Gantt container not found');
                        return;
                    }
                    
                    gantt.config.date_format = "%d-%m-%Y %H:%i";
                    
                    gantt.config.scales = [
                        {unit: "year", step: 1, format: "%Y"},
                        {unit: "month", step: 1, format: "%F"}
                    ];
                    
                    gantt.config.readonly = true;
                    gantt.config.drag_move = false;
                    gantt.config.drag_resize = false;
                    gantt.config.drag_progress = false;
                    gantt.config.drag_links = false;
                    
                    gantt.config.grid_width = 350;
                    gantt.config.row_height = 40;
                    gantt.config.task_height = 32;
                    gantt.config.bar_height = 24;
                    
                    gantt.config.columns = [
                        {name: "text", label: "Project Name", width: 200, tree: true},
                        {name: "status", label: "Status", width: 100, align: "center"},
                        {name: "duration", label: "Duration", width: 50, align: "center"}
                    ];
                    
                    gantt.templates.task_class = function(start, end, task) {
                        return task.is_overdue ? "overdue" : "";
                    };
                    
                    gantt.templates.tooltip_text = function(start, end, task) {
                        return `<b>Project:</b> ${task.text}<br/>
                                <b>Status:</b> ${task.status}<br/>
                                <b>Duration:</b> ${task.duration} day(s)<br/>
                                <b>Progress:</b> ${Math.round(task.progress * 100)}%<br/>
                                <b>Start:</b> ${gantt.templates.tooltip_date_format(start)}<br/>
                                <b>End:</b> ${gantt.templates.tooltip_date_format(end)}
                                ${task.is_overdue ? '<br/><b style="color: #ef4444;">⚠️ OVERDUE</b>' : ''}`;
                    };
                    
                    if (!ganttPageInitialized) {
                        gantt.init("gantt_here");
                        ganttPageInitialized = true;
                        console.log('Gantt initialized for the first time');
                    }
                    
                    gantt.clearAll();
                    gantt.parse(ganttData);
                    
                    console.log('Page dhtmlxGantt initialized successfully with', ganttData.data.length, 'projects');
                    
                } catch (error) {
                    console.error('Error initializing Page dhtmlxGantt:', error);
                    
                    const container = document.getElementById('gantt_here');
                    if (container) {
                        container.innerHTML = `
                            <div class="flex flex-col items-center justify-center h-64 text-red-500 gap-4">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-lg font-medium">Error loading timeline</h3>
                                <p class="text-sm">Please refresh the page or contact support</p>
                                <p class="text-xs">Error: ${error.message}</p>
                            </div>
                        `;
                    }
                }
            }
        </script>
    @endpush
</x-filament-panels::page>