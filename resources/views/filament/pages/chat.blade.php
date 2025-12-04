<x-filament-panels::page>
    <div x-data="{ activeTab: 'direct' }" class="h-[calc(100vh-12rem)]">
        {{-- Tab Navigation --}}
        <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button
                    type="button"
                    @click="activeTab = 'direct'"
                    :class="activeTab === 'direct' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors"
                >
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-left-ellipsis class="h-5 w-5" />
                        Direct Messages
                    </span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'group'"
                    :class="activeTab === 'group' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                    class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors"
                >
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-user-group class="h-5 w-5" />
                        Group Chats
                    </span>
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="h-full">
            {{-- Direct Messages Tab --}}
            <div x-show="activeTab === 'direct'" x-cloak class="h-full">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 h-full overflow-hidden">
                    @include('vendor.Chatify.pages.app', [
                        'id' => 0,
                        'messengerColor' => Auth::user()->messenger_color ?: \Chatify\Facades\ChatifyMessenger::getFallbackColor(),
                        'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark'
                    ])
                </div>
            </div>

            {{-- Group Chats Tab --}}
            <div x-show="activeTab === 'group'" x-cloak class="h-full">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 h-full overflow-hidden">
                    <div class="flex h-full">
                        {{-- Group List Sidebar --}}
                        <div class="w-80 flex-shrink-0 border-r border-gray-200 dark:border-gray-700">
                            @livewire('group-chat-list')
                        </div>

                        {{-- Group Conversation Area --}}
                        <div class="flex-1">
                            @livewire('group-chat-conversation')
                        </div>
                    </div>
                </div>

                {{-- Modals --}}
                @livewire('create-group-modal')
                @livewire('manage-group-modal')
            </div>
        </div>
    </div>
</x-filament-panels::page>
