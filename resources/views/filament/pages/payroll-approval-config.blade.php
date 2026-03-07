<x-filament-panels::page>
    <div x-data="{ tab: 'monthly' }">
        <x-filament::tabs>
            <x-filament::tabs.item
                alpine-active="tab === 'monthly'"
                x-on:click="tab = 'monthly'"
            >
                Monthly Payroll
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="tab === 'weekly'"
                x-on:click="tab = 'weekly'"
            >
                Weekly Allowance
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Monthly Payroll Tab --}}
        <div x-show="tab === 'monthly'" x-cloak class="mt-4">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                        Monthly Payroll Configuration
                    </div>
                </x-slot>
                <x-slot name="description">
                    Configure the approval workflow for monthly payroll batches.
                </x-slot>

                <form wire:submit="saveMonthly">
                    {{ $this->monthlyForm }}

                    <div class="mt-6 flex justify-end">
                        <x-filament::button type="submit">
                            Save Monthly Configuration
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>

        {{-- Weekly Allowance Tab --}}
        <div x-show="tab === 'weekly'" x-cloak class="mt-4">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-calendar-days class="w-5 h-5" />
                        Weekly Allowance Configuration
                    </div>
                </x-slot>
                <x-slot name="description">
                    Configure the approval workflow for weekly allowance batches.
                </x-slot>

                <form wire:submit="saveWeekly">
                    {{ $this->weeklyForm }}

                    <div class="mt-6 flex justify-end">
                        <x-filament::button type="submit">
                            Save Weekly Configuration
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
