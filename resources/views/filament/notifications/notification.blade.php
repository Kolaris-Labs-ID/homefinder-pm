@php
    $color = $getIconColor() ?? 'gray';
@endphp

<x-filament-notifications::notification
    :notification="$notification"
    @class([
        'w-full max-w-md overflow-hidden rounded-xl shadow-2xl ring-1',
        // Success - Green
        'bg-success-50 ring-success-200 dark:bg-success-950 dark:ring-success-800' => $color === 'success',
        // Danger - Red
        'bg-danger-50 ring-danger-200 dark:bg-danger-950 dark:ring-danger-800' => $color === 'danger',
        // Warning - Yellow/Amber
        'bg-warning-50 ring-warning-200 dark:bg-warning-950 dark:ring-warning-800' => $color === 'warning',
        // Info - Blue
        'bg-info-50 ring-info-200 dark:bg-info-950 dark:ring-info-800' => $color === 'info',
        // Primary - Blue
        'bg-primary-50 ring-primary-200 dark:bg-primary-950 dark:ring-primary-800' => $color === 'primary',
        // Gray/Default
        'bg-white ring-gray-200 dark:bg-gray-800 dark:ring-gray-700' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
    ])
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:leave-end="opacity-0 scale-95"
>
    {{-- Colored top border accent --}}
    <div @class([
        'h-1',
        'bg-success-500' => $color === 'success',
        'bg-danger-500' => $color === 'danger',
        'bg-warning-500' => $color === 'warning',
        'bg-info-500' => $color === 'info',
        'bg-primary-500' => $color === 'primary',
        'bg-gray-300 dark:bg-gray-600' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
    ])></div>

    <div class="flex w-full gap-4 p-4">
        {{-- Icon --}}
        @if ($getIcon())
            <div class="flex-shrink-0 pt-0.5">
                <div @class([
                    'flex h-10 w-10 items-center justify-center rounded-full',
                    'bg-success-100 text-success-600 dark:bg-success-500/20 dark:text-success-400' => $color === 'success',
                    'bg-danger-100 text-danger-600 dark:bg-danger-500/20 dark:text-danger-400' => $color === 'danger',
                    'bg-warning-100 text-warning-600 dark:bg-warning-500/20 dark:text-warning-400' => $color === 'warning',
                    'bg-info-100 text-info-600 dark:bg-info-500/20 dark:text-info-400' => $color === 'info',
                    'bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400' => $color === 'primary',
                    'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
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
                    'text-sm font-semibold',
                    'text-success-800 dark:text-success-200' => $color === 'success',
                    'text-danger-800 dark:text-danger-200' => $color === 'danger',
                    'text-warning-800 dark:text-warning-200' => $color === 'warning',
                    'text-info-800 dark:text-info-200' => $color === 'info',
                    'text-primary-800 dark:text-primary-200' => $color === 'primary',
                    'text-gray-900 dark:text-white' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])>
                    {{ $getTitle() }}
                </h4>
            @endif

            {{-- Body --}}
            @if ($getBody())
                <p @class([
                    'text-sm',
                    'text-success-700 dark:text-success-300' => $color === 'success',
                    'text-danger-700 dark:text-danger-300' => $color === 'danger',
                    'text-warning-700 dark:text-warning-300' => $color === 'warning',
                    'text-info-700 dark:text-info-300' => $color === 'info',
                    'text-primary-700 dark:text-primary-300' => $color === 'primary',
                    'text-gray-600 dark:text-gray-300' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
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
                    'text-success-500 hover:bg-success-100 hover:text-success-700 dark:hover:bg-success-800' => $color === 'success',
                    'text-danger-500 hover:bg-danger-100 hover:text-danger-700 dark:hover:bg-danger-800' => $color === 'danger',
                    'text-warning-500 hover:bg-warning-100 hover:text-warning-700 dark:hover:bg-warning-800' => $color === 'warning',
                    'text-info-500 hover:bg-info-100 hover:text-info-700 dark:hover:bg-info-800' => $color === 'info',
                    'text-primary-500 hover:bg-primary-100 hover:text-primary-700 dark:hover:bg-primary-800' => $color === 'primary',
                    'text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300' => $color === 'gray' || !in_array($color, ['success', 'danger', 'warning', 'info', 'primary']),
                ])
            >
                <x-heroicon-m-x-mark class="h-5 w-5" />
            </button>
        </div>
    </div>
</x-filament-notifications::notification>
