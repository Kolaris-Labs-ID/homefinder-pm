<x-filament-panels::page>
    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
        @php
            $stats = $this->getApprovalStatistics();
        @endphp

        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="rounded-full bg-warning-100 p-3 dark:bg-warning-500/20">
                    <x-heroicon-o-clock class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['pending'] }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="rounded-full bg-success-100 p-3 dark:bg-success-500/20">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['approved'] }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="rounded-full bg-danger-100 p-3 dark:bg-danger-500/20">
                    <x-heroicon-o-x-circle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rejected</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['rejected'] }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Pending Approvals Table --}}
    <x-filament::section>
        <x-slot name="heading">
            Pending Approvals
        </x-slot>
        <x-slot name="description">
            Items waiting for your approval action
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
