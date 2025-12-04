<div class="flex flex-col h-full" wire:poll.10s>
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Group Chats</h3>
            @if ($this->totalUnreadCount > 0)
                <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-[10px] font-bold text-white">
                    {{ $this->totalUnreadCount > 99 ? '99+' : $this->totalUnreadCount }}
                </span>
            @endif
        </div>
        <button
            type="button"
            x-on:click="$dispatch('open-create-group-modal')"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            title="Create Group"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>
    </div>

    {{-- Group List --}}
    <div class="flex-1 overflow-y-auto">
        @forelse ($this->groups as $group)
            <button
                type="button"
                wire:click="selectGroup({{ $group->id }})"
                class="{{ $selectedGroupId === $group->id ? 'bg-indigo-50 border-l-2 border-indigo-600 dark:bg-indigo-950/30' : 'border-l-2 border-transparent hover:bg-gray-50 dark:hover:bg-gray-800' }} flex w-full items-center gap-3 px-4 py-3 text-left transition"
            >
                {{-- Group Avatar --}}
                <div class="relative flex-shrink-0">
                    @if ($group->avatar)
                        <img
                            src="{{ Storage::url($group->avatar) }}"
                            alt="{{ $group->name }}"
                            class="h-10 w-10 rounded-full object-cover"
                        />
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                        </div>
                    @endif
                    {{-- Unread indicator --}}
                    @if (($group->unread_count ?? 0) > 0)
                        <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {{ $group->unread_count > 99 ? '99+' : $group->unread_count }}
                        </span>
                    @endif
                </div>

                {{-- Group Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ $group->name }}
                        </p>
                    </div>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $group->member_count }} {{ Str::plural('member', $group->member_count) }}
                    </p>
                </div>
            </button>
        @empty
            <div class="flex flex-col items-center justify-center px-4 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                </div>
                <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No group chats yet</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Create a group to start chatting</p>
                <button
                    type="button"
                    x-on:click="$dispatch('open-create-group-modal')"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-indigo-700"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Group
                </button>
            </div>
        @endforelse
    </div>
</div>
