@php
    $isImage = str_starts_with((string) $mimeType, 'image/');
    $isPdf = $mimeType === 'application/pdf';
@endphp

<div class="flex justify-center">
    @if (! $url)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Evidence is not available.
        </p>
    @elseif ($isImage)
        <img
            src="{{ $url }}"
            alt="{{ $filename }}"
            class="max-h-[75vh] w-full rounded-lg border border-gray-200 object-contain shadow-sm dark:border-gray-700"
        />
    @elseif ($isPdf)
        <iframe
            src="{{ $url }}"
            title="{{ $filename }}"
            class="h-[75vh] w-full rounded-lg border border-gray-200 shadow-sm dark:border-gray-700"
        ></iframe>
    @else
        <div class="w-full rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
            <p class="font-medium text-gray-950 dark:text-white">{{ $filename }}</p>
            <p class="mt-1 text-gray-500 dark:text-gray-400">
                Preview is not available for this file type.
            </p>
            <a
                href="{{ $url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
            >
                Open evidence
            </a>
        </div>
    @endif
</div>
