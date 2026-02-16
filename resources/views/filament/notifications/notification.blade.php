@php
    $color = $getIconColor() ?? 'gray';
@endphp

<x-filament-notifications::notification
    :notification="$notification"
    @class([
        'w-full max-w-md overflow-hidden rounded-xl shadow-2xl ring-2',
        // Success - Bold Green
        'bg-success-600 ring-success-700 dark:bg-success-700 dark:ring-success-600' => $color === 'success',
        // Danger - Bold Red
        'bg-danger-600 ring-danger-700 dark:bg-danger-700 dark:ring-danger-600' => $color === 'danger',
        // Warning - Bold Amber
        'bg-warning-500 ring-warning-600 dark:bg-warning-600 dark:ring-warning-500' => $color === 'warning',
        // Info - Bold Blue
        'bg-info-600 ring-info-700 dark:bg-info-700 dark:ring-info-600' => $color === 'info',
        // Primary - Bold Blue
        'bg-primary-600 ring-primary-700 dark:bg-primary-700 dark:ring-primary-600' => $color === 'primary',
        // Gray/Default
        'bg-gray-800 ring-gray-900 dark:bg-gray-700 dark:ring-gray-600' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
    ])
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:leave-end="opacity-0 scale-95"
>
    <div class="flex w-full gap-4 p-4">
        {{-- Icon --}}
        @if ($getIcon())
            <div class="flex-shrink-0 pt-0.5">
                <div @class([
                    'flex h-10 w-10 items-center justify-center rounded-full',
                    'bg-white/20 text-white' => in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                    'bg-white/10 text-gray-200' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])>
                    <x-filament::icon
                        :icon="$getIcon()"
                        class="h-5 w-5"
                    />
                </div>
            </div>
        @endif

        {{-- Content --}}
        <div class="min-w-0 flex-1 space-y-1">
            {{-- Title --}}
            @if ($getTitle())
                <h4 @class([
                    'text-sm font-bold',
                    'text-white' => in_array($color, ['success', 'danger', 'info', 'primary']),
                    'text-gray-900' => $color === 'warning',
                    'text-white' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])>
                    {{ $getTitle() }}
                </h4>
            @endif

            {{-- Body --}}
            @if ($getBody())
                <p @class([
                    'text-sm',
                    'text-white/80' => in_array($color, ['success', 'danger', 'info', 'primary']),
                    'text-gray-800/80' => $color === 'warning',
                    'text-gray-300' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])>
                    {{ $getBody() }}
                </p>
            @endif

            {{-- Actions --}}
            @if ($getActions())
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-2">
                    @foreach ($getActions() as $action)
                        {{ $action }}
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Close Button --}}
        <div class="flex-shrink-0">
            <button
                x-on:click="close"
                type="button"
                @class([
                    '-mr-1 -mt-1 rounded-lg p-1.5 transition',
                    'text-white/70 hover:bg-white/20 hover:text-white' => in_array($color, ['success', 'danger', 'info', 'primary']),
                    'text-gray-700/70 hover:bg-gray-900/10 hover:text-gray-900' => $color === 'warning',
                    'text-gray-400 hover:bg-white/10 hover:text-white' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])
            >
                <x-heroicon-m-x-mark class="h-5 w-5" />
            </button>
        </div>
    </div>
</x-filament-notifications::notification>
