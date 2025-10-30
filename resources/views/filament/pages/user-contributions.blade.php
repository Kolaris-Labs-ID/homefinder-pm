<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Time Range Selector -->
        <div class="flex justify-end">
            <div class="fi-ta-actions flex shrink-0 items-center gap-3">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="timeRange">
                        <option value="1month">Last Month</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="6months">Last 6 Months</option>
                        <option value="1year">Last Year</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <!-- Users Grid -->
        <div class="space-y-6">
            @php
                $allUsersData = $this->getUsersActivityData();
            @endphp

            @forelse($allUsersData as $userId => $userData)
                <!-- User Section -->
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <!-- Section Header -->
                    <div class="fi-section-header flex items-center gap-3 overflow-hidden px-6 py-4">
                        <div class="fi-section-header-wrapper flex flex-1 items-center gap-3">
                            <!-- User Avatar -->
                            @php
                                $hash = substr(md5($userData['user']->name), 0, 6);
                                $r = hexdec(substr($hash, 0, 2));
                                $g = hexdec(substr($hash, 2, 2));
                                $b = hexdec(substr($hash, 4, 2));
                                $avatarColor = "rgb({$r}, {$g}, {$b})";
                            @endphp
                            <div class="fi-avatar flex items-center justify-center text-white font-medium rounded-full h-10 w-10" style="background-color: {{ $avatarColor }}">
                                <span class="text-sm">
                                    {{ strtoupper(substr($userData['user']->name, 0, 2)) }}
                                </span>
                            </div>
                            
                            <div class="grid flex-1">
                                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                    {{ $userData['user']->name }}
                                </h3>
                                <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                                    {{ $this->getTimeRangeLabel() }} Activity
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Section Content -->
                    <div class="fi-section-content p-6 space-y-4">
                        <!-- Stats Row -->
                        <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-4">
                               
                                 <!-- Ticket Created -->
                                <div class="flex-1 text-center p-2 rounded-lg">
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ number_format($userData['stats']['tickets_created']) }}
                                    </p>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Tickets Created</p>
                                </div>

                                <!-- Status Changes -->
                                <div class="flex-1 text-center p-2 rounded-lg">
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ number_format($userData['stats']['status_changes']) }}
                                    </p>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Status Changes</p>
                                </div>

                                <!-- Comments Made -->
                                <div class="flex-1 text-center p-2 rounded-lg">
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ number_format($userData['stats']['comments_made']) }}
                                    </p>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Comments Made</p>
                                </div>

                                <!-- Active Days -->
                                <div class="flex-1 text-center p-2 rounded-lg">
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ number_format($userData['stats']['active_days']) }}
                                    </p>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Active Days</p>
                                </div>
                            </div>
                        </div>

                        <!-- Heatmap Section -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                    Daily Contributions
                                </h4>
                                
                                <!-- Legend -->
                                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span>Less</span>
                                    <div class="flex gap-1">
                                        <div class="h-3 w-3 rounded-sm bg-gray-200 dark:bg-gray-700" title="No activity"></div>
                                        <div class="h-3 w-3 rounded-sm" style="background-color: #9be9a8" title="Low activity (1-2)"></div>
                                        <div class="h-3 w-3 rounded-sm" style="background-color: #40c463" title="Medium activity (3-5)"></div>
                                        <div class="h-3 w-3 rounded-sm" style="background-color: #30a14e" title="High activity (6-10)"></div>
                                        <div class="h-3 w-3 rounded-sm" style="background-color: #216e39" title="Very high activity (11+)"></div>
                                    </div>
                                    <span>More</span>
                                </div>
                            </div>

                            <!-- Heatmap Container -->
                            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                <div>
                                    <div class="inline-block min-w-full">
                                        <!-- Heatmap Grid with Vertical Day Labels -->
                                        <div class="flex gap-1">
                                            <!-- Vertical Day Labels -->
                                            <div class="flex flex-col gap-1 mr-2">
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Sun</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Mon</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Tue</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Wed</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Thu</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Fri</div>
                                                <div class="h-3 w-8 flex items-center justify-end text-xs text-gray-500 dark:text-gray-400 font-medium">Sat</div>
                                            </div>
                                            
                                            <!-- Heatmap Weeks -->
                                            @foreach($this->getWeeksData() as $weekIndex => $week)
                                                <div class="flex flex-col gap-1">
                                                    @foreach($week as $day)
                                                        @php
                                                            $activityCount = $userData['activity'][$day['date']] ?? 0;
                                                            $level = $this->getActivityLevel($activityCount);
                                                            
                                                            // GitHub-like green colors
                                                            $colorStyle = match($level) {
                                                                'none' => 'background-color: #ebedf0',
                                                                'low' => 'background-color: #9be9a8',
                                                                'medium' => 'background-color: #40c463',
                                                                'high' => 'background-color: #30a14e',
                                                                'very-high' => 'background-color: #216e39',
                                                                default => 'background-color: #ebedf0'
                                                            };
                                                            
                                                            // Dark mode colors
                                                            $darkColorStyle = match($level) {
                                                                'none' => 'background-color: #161b22',
                                                                'low' => 'background-color: #0e4429',
                                                                'medium' => 'background-color: #006d32',
                                                                'high' => 'background-color: #26a641',
                                                                'very-high' => 'background-color: #39d353',
                                                                default => 'background-color: #161b22'
                                                            };
                                                        @endphp
                                                        <div 
                                                            class="contribution-square h-3 w-3 rounded-sm cursor-pointer transition-all duration-150 hover:ring-2 hover:ring-primary-500/50"
                                                            style="{{ $colorStyle }}"
                                                            data-dark-style="{{ $darkColorStyle }}"
                                                            title="{{ Carbon\Carbon::parse($day['date'])->format('M j, Y') }}: {{ $activityCount }} contributions"
                                                        ></div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            @empty
                <div class="fi-ta-empty-state px-6 py-12">
                    <div class="fi-ta-empty-state-content mx-auto grid max-w-lg justify-items-center text-center">
                        <div class="fi-ta-empty-state-icon-ctn mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-500/20">
                            <svg class="fi-ta-empty-state-icon h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                        </div>
                        <h4 class="fi-ta-empty-state-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            No contributions found
                        </h4>
                        <p class="fi-ta-empty-state-description text-sm text-gray-500 dark:text-gray-400">
                            There are no user contributions to display for the selected time period.
                        </p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.delay class="fi-modal-overlay fixed inset-0 z-40 bg-black/50">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fi-modal-window pointer-events-auto relative flex w-full max-w-md transform-gpu flex-col bg-white shadow-xl ring-1 ring-gray-950/5 transition-all dark:bg-gray-900 dark:ring-white/10 sm:rounded-xl">
                <div class="flex items-center gap-3 p-6">
                    <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Loading contributions...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Dark Mode Color Switching -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to update contribution square colors based on theme
            function updateContributionColors() {
                const isDark = document.documentElement.classList.contains('dark');
                const squares = document.querySelectorAll('.contribution-square');
                
                squares.forEach(square => {
                    if (isDark) {
                        const darkStyle = square.getAttribute('data-dark-style');
                        square.setAttribute('style', darkStyle);
                    } else {
                        const lightStyle = square.getAttribute('style').split(';')[0] + ';';
                        square.setAttribute('style', lightStyle);
                    }
                });
            }

            // Initial color update
            updateContributionColors();

            // Listen for theme changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        updateContributionColors();
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            // Update colors after Livewire updates
            document.addEventListener('livewire:navigated', updateContributionColors);
            Livewire.on('updateContributions', updateContributionColors);
        });
    </script>

    <style>
        /* Filament-style scrollbar */
        .overflow-x-auto::-webkit-scrollbar {
            height: 6px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: rgb(243 244 246);
            border-radius: 3px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: rgb(209 213 219);
            border-radius: 3px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: rgb(156 163 175);
        }
        
        .dark .overflow-x-auto::-webkit-scrollbar-track {
            background: rgb(55 65 81);
        }
        .dark .overflow-x-auto::-webkit-scrollbar-thumb {
            background: rgb(107 114 128);
        }
        .dark .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: rgb(156 163 175);
        }
    </style>
</x-filament-panels::page>