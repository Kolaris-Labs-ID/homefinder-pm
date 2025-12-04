<div class="flex h-full flex-col bg-white dark:bg-gray-900">
    @if ($this->group)
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                {{-- Group Avatar --}}
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
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->group->name }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->group->member_count }} {{ Str::plural('member', $this->group->member_count) }}
                    </p>
                </div>
            </div>
            @if ($this->isAdmin)
                <button
                    type="button"
                    x-on:click="$dispatch('open-manage-group-modal', { groupId: {{ $this->group->id }} })"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    title="Manage Group"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            @endif
        </div>

        {{-- Messages --}}
        <div
            class="flex-1 overflow-y-auto p-4 space-y-4"
            x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
            x-init="scrollToBottom()"
            x-on:group-message-sent.window="$nextTick(() => scrollToBottom())"
            wire:poll.5s
        >
            @forelse ($this->messages as $message)
                @php
                    $isOwnMessage = $message->user_id === auth()->id();
                @endphp
                <div class="{{ $isOwnMessage ? 'flex justify-end' : 'flex justify-start' }}">
                    <div class="{{ $isOwnMessage ? 'max-w-[70%]' : 'max-w-[70%] flex gap-2' }}">
                        @unless ($isOwnMessage)
                            {{-- Sender Avatar --}}
                            <div class="flex-shrink-0">
                                <img
                                    src="{{ $message->sender->avatar_url }}"
                                    alt="{{ $message->sender->name }}"
                                    class="h-8 w-8 rounded-full object-cover"
                                />
                            </div>
                        @endunless
                        <div>
                            @unless ($isOwnMessage)
                                {{-- Sender Name --}}
                                <p class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ $message->sender->name }}
                                </p>
                            @endunless
                            {{-- Message Bubble --}}
                            <div class="{{ $isOwnMessage ? 'bg-indigo-600 text-white rounded-2xl rounded-br-md' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white rounded-2xl rounded-bl-md' }} px-4 py-2">
                                <p class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            </div>
                            {{-- Timestamp --}}
                            <p class="{{ $isOwnMessage ? 'text-right' : '' }} mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                                {{ $message->created_at->format('M j, g:i A') }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">No messages yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Start the conversation!</p>
                </div>
            @endforelse
        </div>

        {{-- Message Input --}}
        <div class="border-t border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <form wire:submit="sendMessage" class="flex items-end gap-2">
                <div class="flex-1">
                    <textarea
                        wire:model="messageBody"
                        placeholder="Type a message..."
                        rows="1"
                        class="block w-full resize-none rounded-xl border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400"
                        x-data
                        x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage() }"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                    ></textarea>
                    @error('messageBody')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <button
                    type="submit"
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    wire:loading.attr="disabled"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </form>
        </div>
    @else
        {{-- No Group Selected --}}
        <div class="flex h-full flex-col items-center justify-center text-center p-8">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <svg class="h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
            </div>
            <p class="mt-4 text-base font-medium text-gray-900 dark:text-white">Select a group chat</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a group from the list to start chatting</p>
        </div>
    @endif
</div>
