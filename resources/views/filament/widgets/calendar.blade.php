<x-filament-widgets::widget>
    <x-filament::section>
        <x-filament::section.heading>
            My Calendar
        </x-filament::section.heading>
        
        <x-filament::section.description>
            View your projects, epics, and tickets in one place
        </x-filament::section.description>

        <div class="mt-6 calendar-wrapper" wire:ignore>
            <div id="calendar-widget-{{ $this->getId() }}" class="calendar-container"></div>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #3b82f6;"></div>
                <span class="text-gray-600 dark:text-gray-400">Projects</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #8b5cf6;"></div>
                <span class="text-gray-600 dark:text-gray-400">Epics</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #10b981;"></div>
                <span class="text-gray-600 dark:text-gray-400">Completed</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #ef4444;"></div>
                <span class="text-gray-600 dark:text-gray-400">Overdue</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #f59e0b;"></div>
                <span class="text-gray-600 dark:text-gray-400">In Progress</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded" style="background-color: #6b7280;"></div>
                <span class="text-gray-600 dark:text-gray-400">Pending</span>
            </div>
        </div>
    </x-filament::section>

    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

        <script>
            function initializeCalendar() {
                const widgetId = '{{ $this->getId() }}';
                const calendarEl = document.getElementById('calendar-widget-' + widgetId);
                
                if (!calendarEl) {
                    return;
                }

                // Clean up existing calendar if it exists
                if (window['calendar_' + widgetId]) {
                    try {
                        window['calendar_' + widgetId].destroy();
                        window['calendar_' + widgetId] = null;
                    } catch (e) {
                        console.warn('Error destroying existing calendar:', e);
                    }
                }

                const events = @json($this->getCalendarEvents());
                
                // Check if FullCalendar is available
                if (typeof FullCalendar === 'undefined') {
                    console.error('FullCalendar library not loaded');
                    return;
                }
                
                // Initialize calendar with responsive configuration
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
                    events: events,
                    editable: false,
                    selectable: false,
                    height: 'auto',
                    eventClick: function(info) {
                        if (info.event.extendedProps.url) {
                            window.location.href = info.event.extendedProps.url;
                        }
                    },
                    eventDidMount: function(info) {
                        // Add tooltip with event details
                        const props = info.event.extendedProps;
                        let tooltip = `<div class="p-2 max-w-xs">`;
                        tooltip += `<strong class="block mb-1">${info.event.title}</strong>`;
                        
                        if (props.description) {
                            tooltip += `<p class="text-xs text-gray-600 mb-1">${props.description.substring(0, 100)}${props.description.length > 100 ? '...' : ''}</p>`;
                        }
                        
                        if (props.type === 'ticket') {
                            tooltip += `<div class="text-xs mt-1">`;
                            tooltip += `<span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700 mr-1">${props.status}</span>`;
                            tooltip += `<span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700">${props.priority}</span>`;
                            tooltip += `</div>`;
                        } else if (props.type === 'epic') {
                            tooltip += `<div class="text-xs mt-1">`;
                            if (props.project) {
                                tooltip += `<span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700">${props.project}</span>`;
                            }
                            tooltip += `</div>`;
                        }
                        
                        tooltip += `</div>`;
                        
                        // Use Filament's tooltip system if available, otherwise use title attribute
                        info.el.setAttribute('title', tooltip.replace(/<[^>]*>/g, ''));
                        info.el.setAttribute('data-tooltip', tooltip);
                    },
                    // Responsive breakpoints
                    windowResize: function() {
                        if (window.innerWidth < 768) {
                            calendar.changeView('listWeek');
                        } else {
                            if (calendar.view.type === 'listWeek') {
                                calendar.changeView('dayGridMonth');
                            }
                        }
                    },
                });
                
                calendar.render();

                // Store calendar instance for potential cleanup
                window['calendar_' + widgetId] = calendar;
            }

            // Wait for FullCalendar to be available
            function waitForFullCalendar(callback, maxAttempts = 100) {
                let attempts = 0;
                const check = () => {
                    attempts++;
                    if (typeof FullCalendar !== 'undefined' && FullCalendar.Calendar) {
                        callback();
                    } else if (attempts < maxAttempts) {
                        setTimeout(check, 100);
                    } else {
                        console.error('FullCalendar library not available after ' + maxAttempts + ' attempts');
                        const widgetId = '{{ $this->getId() }}';
                        const calendarEl = document.getElementById('calendar-widget-' + widgetId);
                        if (calendarEl) {
                            calendarEl.innerHTML = '<div class="p-4 text-center text-gray-500">Calendar library failed to load. Please refresh the page.</div>';
                        }
                    }
                };
                check();
            }

            // Initialize calendar
            function initCalendar() {
                waitForFullCalendar(() => {
                    initializeCalendar();
                });
            }

            // Initialize on DOMContentLoaded (first load)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCalendar);
            } else {
                // DOM already loaded, wait a bit for scripts
                setTimeout(initCalendar, 100);
            }
            
            // Initialize on Livewire load (SPA navigation)
            document.addEventListener('livewire:load', () => {
                setTimeout(initCalendar, 200);
            });
            
            document.addEventListener('livewire:init', () => {
                setTimeout(initCalendar, 200);
            });
            
            // Re-initialize after Livewire updates
            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', ({ el, component }) => {
                    if (el && el.querySelector && el.querySelector('#calendar-widget-{{ $this->getId() }}')) {
                        setTimeout(initCalendar, 200);
                    }
                });
            }
            
            // Also listen for Livewire updated event
            document.addEventListener('livewire:updated', () => {
                setTimeout(initCalendar, 200);
            });
        </script>

        <style>
            /* Calendar wrapper with responsive height - prevents breaking on scroll */
            .calendar-wrapper {
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                position: relative;
                contain: layout style paint;
                min-height: 500px;
                box-sizing: border-box;
            }

            .calendar-container {
                min-height: 500px;
                max-height: 800px;
                width: 100%;
                max-width: 100%;
                position: relative;
                display: block;
                box-sizing: border-box;
            }

            /* Responsive adjustments */
            @media (max-width: 1024px) {
                .calendar-container {
                    min-height: 450px;
                    max-height: 700px;
                }
            }

            @media (max-width: 768px) {
                .calendar-wrapper {
                    min-height: 400px;
                }
                
                .calendar-container {
                    min-height: 400px;
                    max-height: 600px;
                }
            }

            @media (max-width: 640px) {
                .calendar-wrapper {
                    min-height: 350px;
                }
                
                .calendar-container {
                    min-height: 350px;
                    max-height: 500px;
                }
            }

            /* Customize calendar colors to match Filament theme */
            .ec {
                --ec-bg-color: rgb(var(--gray-50));
                --ec-text-color: rgb(var(--gray-900));
                --ec-border-color: rgb(var(--gray-200));
            }

            .dark .ec {
                --ec-bg-color: rgb(var(--gray-900));
                --ec-text-color: rgb(var(--gray-100));
                --ec-border-color: rgb(var(--gray-700));
            }

            /* Ensure calendar doesn't break layout on scroll - critical for C-level dashboards */
            .calendar-wrapper .ec {
                position: relative !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                min-width: 0 !important;
            }

            /* Calendar table responsive */
            .calendar-wrapper .ec .ec-view {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                box-sizing: border-box !important;
            }

            /* Prevent calendar from expanding beyond container */
            .fi-section-content {
                overflow: visible;
                position: relative;
            }

            /* Fix for calendar buttons in dark mode */
            .dark .ec-button {
                color: rgb(var(--gray-100));
            }

            .dark .ec-button:hover {
                background-color: rgb(var(--gray-800));
            }

            /* Ensure calendar events are visible */
            .ec-event {
                cursor: pointer;
                border-radius: 4px;
                will-change: transform;
            }

            .ec-event:hover {
                opacity: 0.9;
                transform: scale(1.02);
                transition: all 0.2s ease;
            }

            /* Fix for calendar header - remove sticky to prevent layout issues */
            .ec-header {
                padding: 1rem;
                position: relative;
                z-index: 10;
                background-color: rgb(var(--gray-50));
            }

            .dark .ec-header {
                background-color: rgb(var(--gray-900));
            }

            /* Responsive calendar toolbar */
            @media (max-width: 1024px) {
                .ec-header-toolbar {
                    flex-wrap: wrap;
                    gap: 0.5rem;
                }
            }

            @media (max-width: 768px) {
                .ec-header-toolbar {
                    flex-direction: column;
                    gap: 0.5rem;
                }

                .ec-header-toolbar .ec-button-group {
                    width: 100%;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .ec-header-toolbar .ec-button {
                    font-size: 0.875rem;
                    padding: 0.375rem 0.75rem;
                }
            }

            @media (max-width: 640px) {
                .ec-header-toolbar {
                    flex-direction: column;
                    gap: 0.5rem;
                }

                .ec-header-toolbar .ec-button-group {
                    width: 100%;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .ec-header-toolbar .ec-button {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                }

                .ec-header-toolbar .ec-title {
                    font-size: 1rem;
                    text-align: center;
                }
            }

            /* Ensure widget container doesn't break on scroll */
            [wire\:id*="calendar"] {
                contain: layout style paint;
                position: relative;
                width: 100%;
            }

            /* Fix for Filament widget wrapper - prevent overflow issues */
            .fi-widget {
                overflow: visible;
                position: relative;
                width: 100%;
                box-sizing: border-box;
            }

            /* Fix for Filament section to prevent layout breaking */
            .fi-section {
                position: relative;
                width: 100%;
                box-sizing: border-box;
            }

            /* Ensure calendar table doesn't overflow */
            .ec-view {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                box-sizing: border-box !important;
            }

            /* Fix calendar day cells */
            .ec-day {
                min-width: 0 !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            /* Responsive calendar cells */
            @media (max-width: 768px) {
                .ec-dayGridMonth .ec-day {
                    min-width: 40px;
                }
            }

            @media (max-width: 640px) {
                .ec-dayGridMonth .ec-day {
                    min-width: 35px;
                    font-size: 0.75rem;
                }
            }
        </style>
    @endpush

    @push('styles')
        <style>
            /* Global Dashboard Layout Fixes */
            
            /* Ensure all widgets have consistent sizing */
            .fi-widgets-container {
                display: grid;
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 1.5rem;
                width: 100%;
                box-sizing: border-box;
            }

            /* Fix widget card sizing */
            .fi-widget {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                position: relative;
                contain: layout style paint;
            }

            /* Prevent widgets from breaking on scroll */
            .fi-section {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                position: relative;
                overflow: visible;
            }

            /* Fix section content overflow */
            .fi-section-content {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                position: relative;
                overflow: visible;
            }

            /* Ensure tables don't break layout */
            .fi-ta-table {
                width: 100%;
                max-width: 100%;
                table-layout: auto;
            }

            /* Fix chart containers */
            .fi-widget > div {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            /* Prevent horizontal scroll on dashboard */
            .fi-main {
                overflow-x: hidden;
                width: 100%;
            }

            /* Fix for stats overview widgets */
            .fi-stats-overview-widget {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }

            /* Ensure consistent widget heights where needed */
            .fi-widget[data-widget-type="chart"],
            .fi-widget[data-widget-type="table"] {
                min-height: 300px;
            }

            /* Fix for responsive grid - removed duplicate styles, handled in app.css */
        </style>
    @endpush
</x-filament-widgets::widget>

