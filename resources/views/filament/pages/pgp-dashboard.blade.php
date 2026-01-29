<x-filament-panels::page>
    @php
        $summary = $this->getPgpSummary();
        $trainings = $this->getApprovedTrainings();
    @endphp

    {{-- Quarter Header --}}
    <div class="mb-6">
        <div class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100 dark:bg-primary-900">
            <x-heroicon-o-calendar class="w-4 h-4 mr-2 text-primary-600 dark:text-primary-400" />
            <span class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ $this->getQuarterLabel() }}</span>
        </div>
    </div>

    {{-- Stats Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Current Points --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-star class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Current PGP Points</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['currentPoints'] }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Target Points --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-warning-100 dark:bg-warning-900">
                    <x-heroicon-o-flag class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Target Points</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['targetPoints'] }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Projected Reward --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-success-100 dark:bg-success-900">
                    <x-heroicon-o-banknotes class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Projected Reward</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->formatRupiah($summary['projectedReward']) }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Progress Section --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-chart-bar class="w-5 h-5" />
                Progress Toward Target
            </div>
        </x-slot>

        <div class="space-y-4">
            {{-- Progress Bar --}}
            <div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-700 dark:text-gray-300">{{ $summary['currentPoints'] }} / {{ $summary['targetPoints'] }} PGP</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($summary['progressPercentage'], 1) }}%</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                    <div
                        class="h-4 rounded-full transition-all duration-500 {{ $summary['progressPercentage'] >= 100 ? 'bg-success-600' : 'bg-primary-600' }}"
                        style="width: {{ min($summary['progressPercentage'], 100) }}%"
                    ></div>
                </div>
            </div>

            {{-- Status Message --}}
            <div class="p-4 rounded-lg {{ $summary['progressPercentage'] >= 100 ? 'bg-success-50 dark:bg-success-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                @if($summary['progressPercentage'] >= 100)
                    <div class="flex items-center gap-2 text-success-700 dark:text-success-400">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                        <span class="font-medium">Congratulations! You've reached the quarterly target!</span>
                    </div>
                @elseif($summary['currentPoints'] > 0)
                    <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                        <x-heroicon-o-arrow-trending-up class="w-5 h-5" />
                        <span>You need <strong>{{ $summary['targetPoints'] - $summary['currentPoints'] }}</strong> more points to reach the target.</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-information-circle class="w-5 h-5" />
                        <span>Complete training programs to earn PGP points this quarter.</span>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>

    {{-- Reward Information --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-gift class="w-5 h-5" />
                Reward Information
            </div>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Base Reward (150 PGP)</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->formatRupiah(150000) }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Incremental Reward (per 35 PGP)</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->formatRupiah(35000) }}</p>
            </div>
        </div>

        <div class="mt-4 p-4 bg-info-50 dark:bg-info-900/20 rounded-lg">
            <p class="text-sm text-info-700 dark:text-info-300">
                <strong>How it works:</strong> Earn 150 PGP points to receive the base reward of Rp150,000.
                For every additional 35 points beyond the target, you earn an extra Rp35,000.
            </p>
        </div>
    </x-filament::section>

    {{-- Approved Trainings Table --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-academic-cap class="w-5 h-5" />
                Approved Trainings ({{ $this->getQuarterLabel() }})
            </div>
        </x-slot>

        @if($trainings->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-3 px-2 text-gray-600 dark:text-gray-400">Training Name</th>
                            <th class="text-left py-3 px-2 text-gray-600 dark:text-gray-400">Provider</th>
                            <th class="text-center py-3 px-2 text-gray-600 dark:text-gray-400">Type</th>
                            <th class="text-center py-3 px-2 text-gray-600 dark:text-gray-400">PGP Earned</th>
                            <th class="text-center py-3 px-2 text-gray-600 dark:text-gray-400">Approved Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trainings as $training)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-3 px-2 text-gray-900 dark:text-white font-medium">
                                    {{ $training->training_name }}
                                </td>
                                <td class="py-3 px-2 text-gray-700 dark:text-gray-300">
                                    {{ $training->provider ?? '-' }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $training->isFreeTraining() ? 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' : 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' }}">
                                        {{ ucfirst($training->training_type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                        +{{ $training->pgp_points }} PGP
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-center text-gray-700 dark:text-gray-300">
                                    {{ $training->approved_at?->format('d M Y') ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <td colspan="3" class="py-3 px-2 text-right font-medium text-gray-700 dark:text-gray-300">
                                Total PGP Earned:
                            </td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-primary-600 text-white">
                                    {{ $summary['currentPoints'] }} PGP
                                </span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <x-heroicon-o-academic-cap class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-4" />
                <p class="text-gray-500 dark:text-gray-400 mb-2">No approved trainings this quarter</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    Complete training programs and get them approved to earn PGP points.
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
