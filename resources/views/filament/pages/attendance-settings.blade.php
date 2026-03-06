<x-filament-panels::page>
    {{-- Settings Form --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                Attendance Settings
            </div>
        </x-slot>
        <x-slot name="description">
            Configure global attendance parameters. Changes take effect immediately.
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

    {{-- Audit History --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-clock class="w-5 h-5" />
                Change History
            </div>
        </x-slot>
        <x-slot name="description">
            Recent changes to attendance settings
        </x-slot>

        @php $auditLogs = $this->getAuditLogs(); @endphp

        @if($auditLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">User</th>
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">Field</th>
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">Old Value</th>
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">New Value</th>
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $log)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 text-gray-900 dark:text-white">{{ $log->user?->name ?? 'Unknown' }}</td>
                                <td class="py-2 text-gray-900 dark:text-white">{{ str_replace('_', ' ', ucfirst($log->field)) }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $log->old_value ?? '—' }}</td>
                                <td class="py-2 text-gray-900 dark:text-white">{{ $log->new_value }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-center py-4">No changes recorded yet</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
