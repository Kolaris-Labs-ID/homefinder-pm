<div class="flex justify-center">
    @if ($url)
        <img
            src="{{ $url }}"
            alt="{{ $alt ?? 'Attendance photo' }}"
            class="max-h-[75vh] w-full rounded-lg border border-gray-200 object-contain shadow-sm dark:border-gray-700"
        />
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Photo is not available.
        </p>
    @endif
</div>
