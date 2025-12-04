<div>
    {{-- Modal Backdrop --}}
    <div
        x-data="{ show: @entangle('isOpen') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
            {{-- Background overlay --}}
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity dark:bg-gray-900 dark:bg-opacity-75"
                x-on:click="$wire.closeModal()"
            ></div>

            {{-- Center modal --}}
            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            {{-- Modal panel --}}
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative inline-block w-full max-w-lg transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:align-middle"
            >
                @if ($this->group)
                    {{-- Header --}}
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if ($this->group->avatar)
                                    <img
                                        src="{{ Storage::url($this->group->avatar) }}"
                                        alt="{{ $this->group->name }}"
                                        class="h-10 w-10 rounded-full object-cover"
                                    />
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300">
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                        </svg>
                                    </div>
                                @endif
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                                        {{ $this->group->name }}
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Manage group members
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                            >
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-4">
                        {{-- Error Message --}}
                        @if (session()->has('error'))
                            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                {{ session('error') }}
                            </div>
                        @endif

                        {{-- Current Members --}}
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Members ({{ $this->members->count() }})
                            </h4>
                            <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                @foreach ($this->members as $member)
                                    @php
                                        $isAdmin = $member->pivot->role === 'admin';
                                        $isCurrentUser = $member->id === auth()->id();
                                    @endphp
                                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 last:border-b-0 dark:border-gray-700">
                                        <div class="flex items-center gap-3">
                                            <img
                                                src="{{ $member->avatar_url }}"
                                                alt="{{ $member->name }}"
                                                class="h-8 w-8 rounded-full"
                                            />
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $member->name }}
                                                        @if ($isCurrentUser)
                                                            <span class="text-xs text-gray-500">(you)</span>
                                                        @endif
                                                    </p>
                                                    @if ($isAdmin)
                                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                                            Admin
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $member->email }}
                                                </p>
                                            </div>
                                        </div>
                                        @if ($this->isAdmin && !$isCurrentUser)
                                            <button
                                                type="button"
                                                wire:click="removeMember({{ $member->id }})"
                                                wire:confirm="Are you sure you want to remove {{ $member->name }} from this group?"
                                                class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                                title="Remove member"
                                            >
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Add Members Section --}}
                        @if ($this->isAdmin)
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Add Members
                                </h4>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="searchQuery"
                                    placeholder="Search users by name or email..."
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                                />
                                @if ($this->availableUsers->count() > 0)
                                    <div class="mt-2 max-h-36 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        @foreach ($this->availableUsers as $user)
                                            <button
                                                type="button"
                                                wire:click="addMember({{ $user->id }})"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-gray-50 dark:hover:bg-gray-700"
                                            >
                                                <img
                                                    src="{{ $user->avatar_url }}"
                                                    alt="{{ $user->name }}"
                                                    class="h-8 w-8 rounded-full"
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $user->name }}
                                                    </p>
                                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $user->email }}
                                                    </p>
                                                </div>
                                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                                </svg>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif (!empty($searchQuery))
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        No users found matching "{{ $searchQuery }}"
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex justify-end">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
