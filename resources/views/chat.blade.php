<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Chat - {{ config('app.name') }}</title>

    {{-- Use compiled Tailwind / app assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-screen">

        <!-- Main area -->
        <main class="flex-1 flex flex-col">
            <!-- Top bar -->
            <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                <div class="flex items-center gap-2">
                    <button class="inline-flex lg:hidden items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="flex items-baseline gap-2">
                        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Chat</h1>
                        <span class="hidden text-sm font-medium text-slate-400 sm:inline">Project conversations</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button class="hidden sm:inline-flex items-center justify-center rounded-full border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="M16 16L20 20"></path>
                        </svg>
                    </button>
                    <button class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </button>
                    <button class="relative inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-tr from-amber-400 to-pink-500 text-white text-sm font-semibold tracking-tight">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                    </button>
                </div>
            </header>

            <!-- Content -->
            <section class="flex-1 flex min-h-0">
                <!-- Conversation list -->
                <div class="w-full max-w-md border-r border-slate-200 bg-white flex flex-col">
                    <!-- Tabs -->
                    <div class="px-4 sm:px-6 pt-4 border-b border-slate-100">
                        <div class="flex gap-6 text-sm font-medium">
                            <button class="relative pb-3 text-slate-900">
                                <span>Chats</span>
                                <span class="absolute inset-x-0 -bottom-0.5 h-0.5 rounded-full bg-blue-600"></span>
                            </button>
                            <button class="pb-3 text-slate-400 hover:text-slate-600">Group Chat</button>
                            <button class="pb-3 text-slate-400 hover:text-slate-600">Contacts</button>
                        </div>
                    </div>

                    <!-- Filter / search -->
                    <div class="px-4 sm:px-6 py-3 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-500">All Messages</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">232</span>
                        </div>
                        <button class="inline-flex items-center justify-center rounded-full p-1.5 text-slate-400 hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="5" r="1"></circle>
                                <circle cx="12" cy="12" r="1"></circle>
                                <circle cx="12" cy="19" r="1"></circle>
                            </svg>
                        </button>
                    </div>

                    <div class="px-4 sm:px-6 py-3 border-b border-slate-100">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="11" cy="11" r="6"></circle>
                                    <path d="M16 16L20 20"></path>
                                </svg>
                            </span>
                            <input type="text" placeholder="Search..." class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-8 pr-3 py-2 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/10">
                        </div>
                    </div>

                    <!-- List -->
                    <div class="flex-1 overflow-y-auto">
                        <ul class="divide-y divide-slate-100 text-sm">
                            <!-- Item active -->
                            <li class="bg-blue-50/60">
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Alexis Sears</span>
                                            <span class="text-xs font-medium text-slate-400">09:43pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-blue-500">typing...</p>
                                    </div>
                                </button>
                            </li>

                            <!-- Other items -->
                            <li>
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-slate-50">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://images.pexels.com/photos/614810/pexels-photo-614810.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Guy Hawkins</span>
                                            <span class="text-xs font-medium text-slate-400">09:15pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-slate-500">I will send the document...</p>
                                    </div>
                                </button>
                            </li>

                            <li>
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-slate-50">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://images.pexels.com/photos/733872/pexels-photo-733872.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Esther Howard</span>
                                            <span class="text-xs font-medium text-slate-400">09:35pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-slate-500">Great! Keep up the...</p>
                                    </div>
                                </button>
                            </li>

                            <li>
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-slate-50">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://images.pexels.com/photos/2379005/pexels-photo-2379005.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Robert Fox</span>
                                            <span class="text-xs font-medium text-slate-400">10:07pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-slate-500">I suggest to start...</p>
                                    </div>
                                </button>
                            </li>

                            <li>
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-slate-50">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://hoirqrkdgbmvpwutwuwj.supabase.co/storage/v1/object/public/assets/assets/917d6f93-fb36-439a-8c48-884b67b35381_1600w.jpg" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Jane Cooper</span>
                                            <span class="text-xs font-medium text-slate-400">10:12pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-slate-500">I will send the document...</p>
                                    </div>
                                </button>
                            </li>

                            <li>
                                <button class="flex w-full items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-slate-50">
                                    <div class="relative h-9 w-9 flex-shrink-0">
                                        <img src="https://images.pexels.com/photos/1181686/pexels-photo-1181686.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-9 w-9 rounded-full object-cover">
                                        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="truncate text-sm font-semibold text-slate-900 tracking-tight">Ralph Edwards</span>
                                            <span class="text-xs font-medium text-slate-400">10:24pm</span>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-medium text-slate-500">We need to kick...</p>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Conversation panel -->
                <div class="flex-1 flex flex-col bg-slate-50">
                    <!-- Conversation header -->
                    <div class="border-b border-slate-200 bg-white px-4 sm:px-6 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold tracking-tight text-slate-900">Alexis Sears</span>
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                </div>
                                <p class="text-sm font-medium text-slate-400">Online</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 sm:gap-3">
                            <!-- lucide:phone -->
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.88 19.88 0 0 1-8.63-3.07A19.5 19.5 0 0 1 3.15 9.81 19.88 19.88 0 0 1 .08 1.18 2 2 0 0 1 2 0h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L6.1 8.05a16 16 0 0 0 6.86 6.86l1.41-1.16a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </button>
                            <!-- lucide:video -->
                            <button class="hidden sm:inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M15 10l4.553-2.276A1 1 0 0 1 21 8.618v6.764a1 1 0 0 1-1.447.894L15 14"></path>
                                    <rect x="3" y="6" width="12" height="12" rx="2"></rect>
                                </svg>
                            </button>
                            <!-- lucide:more-vertical -->
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="5" r="1"></circle>
                                    <circle cx="12" cy="12" r="1"></circle>
                                    <circle cx="12" cy="19" r="1"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 overflow-y-auto px-3 sm:px-8 py-4 sm:py-6 space-y-4 text-sm font-medium">
                        <!-- Left message -->
                        <div class="flex items-start gap-3 max-w-xl">
                            <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="mt-0.5 h-8 w-8 rounded-full object-cover">
                            <div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-semibold tracking-tight text-slate-900">Alexis Sears</span>
                                    <span class="text-xs font-medium text-slate-400">09:43pm</span>
                                </div>
                                <div class="mt-1 inline-flex rounded-2xl rounded-tl-sm bg-white px-3 py-2 shadow-sm border border-slate-100 text-slate-700">
                                    Hi Anna, Landing design status?
                                </div>
                            </div>
                        </div>

                        <!-- Right message -->
                        <div class="flex justify-end">
                            <div class="flex items-start gap-3 max-w-xl">
                                <div class="text-right">
                                    <div class="flex items-baseline justify-end gap-2">
                                        <span class="text-xs font-medium text-slate-400">09:44pm</span>
                                        <span class="text-xs font-semibold tracking-tight text-slate-900">You</span>
                                    </div>
                                    <div class="mt-1 inline-flex rounded-2xl rounded-tr-sm bg-blue-50 px-3 py-2 text-slate-800 shadow-sm border border-blue-100">
                                        Design sent in email. Please check it.
                                    </div>
                                </div>
                                <img src="https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="mt-0.5 h-8 w-8 rounded-full object-cover">
                            </div>
                        </div>

                        <!-- File message from Alexis -->
                        <div class="flex items-start gap-3 max-w-xl">
                            <img src="https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="mt-0.5 h-8 w-8 rounded-full object-cover">
                            <div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-semibold tracking-tight text-slate-900">Alexis Sears</span>
                                    <span class="text-xs font-medium text-slate-400">09:48pm</span>
                                </div>
                                <button class="mt-1 inline-flex items-center gap-2 rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-slate-700 shadow-sm border border-slate-100">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-50 text-slate-500">
                                        <!-- lucide:file -->
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <path d="M14 2v6h6"></path>
                                        </svg>
                                    </div>
                                    <div class="flex flex-col items-start">
                                        <span class="text-xs font-semibold tracking-tight text-slate-900">Landing Suggestion.pdf</span>
                                        <span class="text-xs font-medium text-slate-400">420 KB</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Right reply -->
                        <div class="flex justify-end">
                            <div class="flex items-start gap-3 max-w-xl">
                                <div class="text-right">
                                    <div class="flex items-baseline justify-end gap-2">
                                        <span class="text-xs font-medium text-slate-400">09:52pm</span>
                                        <span class="text-xs font-semibold tracking-tight text-slate-900">You</span>
                                    </div>
                                    <div class="mt-1 inline-flex rounded-2xl rounded-tr-sm bg-blue-50 px-3 py-2 text-slate-800 shadow-sm border border-blue-100">
                                        Okay James, will update the designs.
                                    </div>
                                </div>
                                <img src="https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="mt-0.5 h-8 w-8 rounded-full object-cover">
                            </div>
                        </div>

                        <!-- Last message typing -->
                        <div class="flex justify-end">
                            <div class="flex items-end gap-3 max-w-xs">
                                <div class="text-right">
                                    <div class="flex items-baseline justify-end gap-2">
                                        <span class="text-xs font-medium text-slate-400">09:52pm</span>
                                        <span class="text-xs font-semibold tracking-tight text-slate-900">You</span>
                                    </div>
                                    <div class="mt-1 inline-flex items-center gap-2 rounded-2xl rounded-tr-sm bg-blue-50 px-3 py-1.5 text-slate-500 shadow-sm border border-blue-100">
                                        <span class="h-1 w-1 rounded-full bg-slate-400"></span>
                                        <span class="h-1 w-1 rounded-full bg-slate-400"></span>
                                        <span class="h-1 w-1 rounded-full bg-slate-400"></span>
                                    </div>
                                </div>
                                <img src="https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&amp;cs=tinysrgb&amp;w=80" alt="" class="h-8 w-8 rounded-full object-cover">
                            </div>
                        </div>
                    </div>

                    <!-- Composer -->
                    <div class="border-t border-slate-200 bg-white px-3 sm:px-6 py-3">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <button class="hidden sm:inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
                                <!-- lucide:plus -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M12 5v14"></path>
                                    <path d="M5 12h14"></path>
                                </svg>
                            </button>
                            <div class="flex-1">
                                <div class="flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-1.5">
                                    <input type="text" placeholder="Type your message..." class="flex-1 bg-transparent text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none">
                                    <div class="flex items-center gap-1.5 text-slate-400">
                                        <!-- lucide:paperclip -->
                                        <button class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-slate-100">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M21.44 11.05 12.53 19.96a5 5 0 1 1-7.07-7.07l8.48-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.49a2 2 0 0 1-2.83-2.83l7.78-7.78"></path>
                                            </svg>
                                        </button>
                                        <!-- lucide:smile -->
                                        <button class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-slate-100">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <path d="M8 15s1.5 2 4 2 4-2 4-2"></path>
                                                <path d="M9 9h.01"></path>
                                                <path d="M15 9h.01"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- lucide:send -->
                            <button class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm hover:bg-blue-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M22 2 11 13"></path>
                                    <path d="M22 2 15 22 11 13 2 9 22 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>

