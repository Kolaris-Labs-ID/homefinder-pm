<x-filament-widgets::widget>
    <x-filament::section>
        <x-filament::section.heading>
            My Calendar
        </x-filament::section.heading>
        
        <x-filament::section.description>
            View your weekly todos, projects, and tickets in one place
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

    @push('scripts')
        <link href="https://cdn.jsdelivr.net/npm/@event-calendar/core@4.7.1/dist/index.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/@event-calendar/core@4.7.1/dist/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@event-calendar/day-grid@4.7.1/dist/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@event-calendar/time-grid@4.7.1/dist/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@event-calendar/list@4.7.1/dist/index.global.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const widgetId = '{{ $this->getId() }}';
                const calendarEl = document.getElementById('calendar-widget-' + widgetId);
                
                if (!calendarEl) {
                    return;
                }

                const events = @json($this->getCalendarEvents());
                
                // Initialize calendar with responsive configuration
                const calendar = new EventCalendar(calendarEl, {
                    plugins: [EventCalendarDayGrid, EventCalendarTimeGrid, EventCalendarList],
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
                    aspectRatio: 1.8,
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
                        } else if (props.type === 'weekly_todo') {
                            tooltip += `<div class="text-xs mt-1">`;
                            tooltip += `<span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700 mr-1">${props.status}</span>`;
                            if (props.user) {
                                tooltip += `<span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700">${props.user}</span>`;
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

                // Store calendar instance for potential cleanup
                window['calendar_' + widgetId] = calendar;
            });
        </script>

        <style>
            /* Calendar wrapper with responsive height - prevents breaking on scroll */
            .calendar-wrapper {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                position: relative;
                contain: layout style paint;
            }

            .calendar-container {
                min-height: 500px;
                max-height: 800px;
                width: 100%;
                position: relative;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .calendar-container {
                    min-height: 400px;
                    max-height: 600px;
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
                height: auto !important;
                max-width: 100% !important;
                overflow: visible !important;
            }

            /* Prevent calendar from expanding beyond container */
            .fi-section-content {
                overflow: visible;
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

            /* Fix for calendar header */
            .ec-header {
                padding: 1rem;
                position: sticky;
                top: 0;
                z-index: 10;
                background-color: rgb(var(--gray-50));
            }

            .dark .ec-header {
                background-color: rgb(var(--gray-900));
            }

            /* Responsive calendar toolbar */
            @media (max-width: 640px) {
                .ec-header-toolbar {
                    flex-direction: column;
                    gap: 0.5rem;
                }

                .ec-header-toolbar .ec-button-group {
                    width: 100%;
                    justify-content: center;
                }
            }

            /* Ensure widget container doesn't break on scroll */
            [wire\:id*="calendar"] {
                contain: layout style paint;
            }

            /* Fix for Filament widget wrapper */
            .fi-widget {
                overflow: visible;
            }
        </style>
    @endpush
</x-filament-widgets::widget>

