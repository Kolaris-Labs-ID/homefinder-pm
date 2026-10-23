<x-filament-panels::page>
    @if ($this->hasPendingFeatures())
        <form wire:submit="save">
            {{ $this->form }}
        </form>
    @endif

    @php($locked = $this->getLockedFeatures())

    @if (count($locked))
        <x-filament::section>
            <x-slot name="heading">Locked</x-slot>
            <x-slot name="description">Set at first setup. To change one, ask your administrator to run <code>php artisan features:change</code> with a reason.</x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($locked as $row)
                    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-1 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $row['feature']->label() }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $row['feature']->description() }}</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Set {{ $row['set_at'] }} by {{ $row['set_by'] }}</p>
                        </div>
                        <x-filament::badge :color="$row['on'] ? 'success' : 'gray'">
                            {{ $row['on'] ? 'On' : 'Off' }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
