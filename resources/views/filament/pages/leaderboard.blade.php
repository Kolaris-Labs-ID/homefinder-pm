<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Controls -->        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <!-- Time Range Selector -->
            <div class="fi-ta-actions flex shrink-0 items-center gap-3">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="timeRange">
                        <option value="7days">Last 7 Days</option>
                        <option value="30days">Last 30 Days</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            
            <!-- Top Count Selector -->
            <div class="fi-ta-actions flex shrink-0 items-center gap-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Show Top:</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="topCount">
                        <option value="5">Top 5</option>
                        <option value="10">Top 10</option>
                        <option value="20">Top 20</option>
                        <option value="50">Top 50</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <!-- Leaderboard -->        
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <!-- Section Header -->
            <div class="fi-section-header flex items-center gap-3 overflow-hidden px-6 py-4">
                <div class="fi-section-header-wrapper flex flex-1 items-center gap-3">
                    <div class="grid flex-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            🏆 Contribution Leaderboard
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->getTimeRangeLabel() }} - Top {{ $topCount }} Contributors
                        </p>
                    </div>
                </div>
            </div>

            <!-- Section Content -->
            <div class="fi-section-content p-6">
                @php
                    $leaderboardData = $this->getLeaderboardData();
                @endphp

                @forelse($leaderboardData as $entry)
                    <div class="mb-4 last:mb-0">
                        <!-- Mobile-First Responsive Card Layout -->
                        <div class="rounded-lg bg-white p-4 sm:p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10 hover:shadow-md transition-shadow">
                            <!-- Mobile Layout: Vertical Stack -->
                            <div class="block sm:hidden">
                                <!-- Mobile: Rank + User Info -->
                                <div class="flex items-center gap-3 mb-4">
                                    <!-- Rank Badge -->
                                    <div class="flex-shrink-0">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $this->getRankBadgeColor($entry['rank']) }} font-bold text-sm">
                                            <span class="text-lg">{{ $this->getRankIcon($entry['rank']) }}</span>
                                        </div>
                                        <div class="text-center mt-1">
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">#{{ $entry['rank'] }}</span>
                                        </div>
                                    </div>

                                    <!-- User Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            @php
                                                $hash = substr(md5($entry['user']->name), 0, 6);
                                                $r = hexdec(substr($hash, 0, 2));
                                                $g = hexdec(substr($hash, 2, 2));
                                                $b = hexdec(substr($hash, 4, 2));
                                                $avatarColor = "rgb({$r}, {$g}, {$b})";
                                            @endphp
                                            <div class="fi-avatar flex items-center justify-center text-white font-medium rounded-full h-8 w-8" style="background-color: {{ $avatarColor }}">
                                                <span class="text-xs">
                                                    {{ strtoupper(substr($entry['user']->name, 0, 2)) }}
                                                </span>
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                                    {{ $entry['user']->name }}
                                                </h4>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Total Score: <span class="font-bold text-blue-600 dark:text-blue-400">{{ number_format($entry['total_score']) }}</span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Mobile: Stats in 2x2 Grid -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['tickets_created']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Tickets</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['status_changes']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Updates</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['comments_made']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Comments</p>
                                    </div>
                                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['active_days']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Active Days</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Layout: Horizontal (Hidden on Mobile) -->
                            <div class="hidden sm:flex items-center justify-between gap-6">
                                <!-- Left Side: Rank + User Info -->
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <!-- Rank Badge -->
                                    <div class="flex-shrink-0">
                                        <div class="flex items-center justify-center w-12 h-12 rounded-full {{ $this->getRankBadgeColor($entry['rank']) }} font-bold text-lg">
                                            <span class="text-xl">{{ $this->getRankIcon($entry['rank']) }}</span>
                                        </div>
                                        <div class="text-center mt-1">
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">#{{ $entry['rank'] }}</span>
                                        </div>
                                    </div>

                                    <!-- User Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3 mb-2">
                                            @php
                                                $hash = substr(md5($entry['user']->name), 0, 6);
                                                $r = hexdec(substr($hash, 0, 2));
                                                $g = hexdec(substr($hash, 2, 2));
                                                $b = hexdec(substr($hash, 4, 2));
                                                $avatarColor = "rgb({$r}, {$g}, {$b})";
                                            @endphp
                                            <div class="fi-avatar flex items-center justify-center text-white font-medium rounded-full h-10 w-10" style="background-color: {{ $avatarColor }}">
                                                <span class="text-sm">
                                                    {{ strtoupper(substr($entry['user']->name, 0, 2)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                                    {{ $entry['user']->name }}
                                                </h4>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    Total Score: <span class="font-bold text-blue-600 dark:text-blue-400 text-lg">{{ number_format($entry['total_score']) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Side: Stats in Horizontal Layout -->
                                <div class="flex items-center gap-6 flex-shrink-0">
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['tickets_created']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Tickets</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['status_changes']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Updates</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['comments_made']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Comments</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($entry['stats']['active_days']) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">Active Days</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <div class="text-gray-400 dark:text-gray-600 text-4xl mb-4">🏆</div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Contributors Found</h3>
                        <p class="text-gray-500 dark:text-gray-400">No user activity found for the selected time period.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Updated Scoring Information -->
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-3 overflow-hidden px-6 py-4">
                <div class="fi-section-header-wrapper flex flex-1 items-center gap-3">
                    <div class="grid flex-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            📊 Scoring System
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            How contribution scores are calculated
                        </p>
                    </div>
                </div>
            </div>
            <div class="fi-section-content p-6">
                <!-- Formula Explanation -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📐 Score Calculation Formula:</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-mono">
                        Total Score = (Status Changes × 5) + (Tickets Created × 2) + (Comments Made × 2) + (Active Days × 1)
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>