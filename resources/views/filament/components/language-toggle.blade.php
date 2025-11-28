@if(auth()->check())
    <form method="POST" action="{{ route('locale.switch') }}" class="flex items-center gap-2">
        @csrf
        <button
            type="submit"
            name="locale"
            value="en"
            @class([
                'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                'bg-primary-600 text-white' => app()->getLocale() === 'en',
                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => app()->getLocale() !== 'en',
            ])
        >
            EN
        </button>
        <button
            type="submit"
            name="locale"
            value="id"
            @class([
                'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                'bg-primary-600 text-white' => app()->getLocale() === 'id',
                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => app()->getLocale() !== 'id',
            ])
        >
            ID
        </button>
    </form>
@endif


