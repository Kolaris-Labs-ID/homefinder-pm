<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-shield-check class="w-5 h-5" />
                Payment Authorization Settings
            </div>
        </x-slot>
        <x-slot name="description">
            Configure signers and thresholds for payment bundle authorization.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit">
                    Save Settings
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
