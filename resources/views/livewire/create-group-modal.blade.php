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
                {{-- Header --}}
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                            Create Group Chat
                        </h3>
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
                <form wire:submit="createGroup">
                    <div class="px-6 py-4 space-y-4">
                        {{-- Group Name --}}
                        <div>
                            <label for="groupName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Group Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="groupName"
                                wire:model="groupName"
                                placeholder="Enter group name"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                            />
                            @error('groupName')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Selected Members --}}
                        @if (count($this->selectedUsers) > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Selected Members ({{ count($this->selectedMembers) }})
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->selectedUsers as $user)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-sm font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                            <img
                                                src="{{ $user->avatar_url }}"
                                                alt="{{ $user->name }}"
                                                class="h-5 w-5 rounded-full"
                                            />
                                            {{ $user->name }}
                                            <button
                                                type="button"
                                                wire:click="toggleMember({{ $user->id }})"
                                                class="ml-1 text-indigo-500 hover:text-indigo-700 dark:text-indigo-400"
                                            >
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Member Search --}}
                        <div>
                            <label for="searchQuery" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Add Members <span class="text-red-500">*</span>
                                <span class="text-xs text-gray-500">(minimum 2)</span>
                            </label>
                            <input
                                type="text"
                                id="searchQuery"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Search users by name or email..."
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                            />
                            @error('selectedMembers')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- User List --}}
                        <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            @forelse ($this->availableUsers as $user)
                                @php
                                    $isSelected = in_array($user->id, $this->selectedMembers);
                                @endphp
                                <button
                                    type="button"
                                    wire:click="toggleMember({{ $user->id }})"
                                    class="{{ $isSelected ? 'bg-indigo-50 dark:bg-indigo-950/30' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }} flex w-full items-center gap-3 px-4 py-2.5 text-left transition"
                                >
                                    <div class="relative">
                                        <img
                                            src="{{ $user->avatar_url }}"
                                            alt="{{ $user->name }}"
                                            class="h-8 w-8 rounded-full"
                                        />
                                        @if ($isSelected)
                                            <span class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-white">
                                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $user->name }}
                                        </p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $user->email }}
                                        </p>
                                    </div>
                                </button>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @if (!empty($searchQuery))
                                            No users found matching "{{ $searchQuery }}"
                                        @else
                                            No users available
                                        @endif
                                    </p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="createGroup">Create Group</span>
                                <span wire:loading wire:target="createGroup">Creating...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
