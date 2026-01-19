@php
    $typeConfig = match($notification->type) {
        'comment_added', 'comment_updated' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'text' => 'text-blue-700 dark:text-blue-300', 'border' => 'border-blue-200 dark:border-blue-800', 'label' => 'Feedback', 'icon' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z'],
        'project_assigned' => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'text' => 'text-emerald-700 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-800', 'label' => 'Project Assigned', 'icon' => 'M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z'],
        'project_removed' => ['bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-700 dark:text-gray-300', 'border' => 'border-gray-200 dark:border-gray-700', 'label' => 'Project Update', 'icon' => 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z'],
        'ticket_overdue', 'project_overdue' => ['bg' => 'bg-red-50 dark:bg-red-900/20', 'text' => 'text-red-700 dark:text-red-300', 'border' => 'border-red-200 dark:border-red-800', 'label' => 'Overdue Alert', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
        'ticket_nearing_overdue', 'project_nearing_overdue' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'text' => 'text-amber-700 dark:text-amber-300', 'border' => 'border-amber-200 dark:border-amber-800', 'label' => 'Due Soon', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
        'approval_pending' => ['bg' => 'bg-orange-50 dark:bg-orange-900/20', 'text' => 'text-orange-700 dark:text-orange-300', 'border' => 'border-orange-200 dark:border-orange-800', 'label' => 'Approval Pending', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'approval_approved' => ['bg' => 'bg-green-50 dark:bg-green-900/20', 'text' => 'text-green-700 dark:text-green-300', 'border' => 'border-green-200 dark:border-green-800', 'label' => 'Approved', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'approval_rejected' => ['bg' => 'bg-red-50 dark:bg-red-900/20', 'text' => 'text-red-700 dark:text-red-300', 'border' => 'border-red-200 dark:border-red-800', 'label' => 'Rejected', 'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        default => ['bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-700 dark:text-gray-300', 'border' => 'border-gray-200 dark:border-gray-700', 'label' => 'Notification', 'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
    };
@endphp

<div class="space-y-6">
    {{-- Type Badge --}}
    <div class="flex items-center gap-3">
        <span class="{{ $typeConfig['bg'] }} {{ $typeConfig['text'] }} inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeConfig['icon'] }}" />
            </svg>
            {{ $typeConfig['label'] }}
        </span>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ $notification->created_at->format('M j, Y \a\t g:i A') }}
        </span>
    </div>

    {{-- Full Message --}}
    <div class="rounded-lg border {{ $typeConfig['border'] }} {{ $typeConfig['bg'] }} p-4">
        <p class="text-base leading-relaxed {{ $typeConfig['text'] }}">
            {{ $notification->message }}
        </p>
    </div>

    {{-- Metadata --}}
    <div class="space-y-3">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Details</h4>
        <div class="rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                @if ($notification->actor['name'] ?? null)
                    <div class="flex justify-between px-4 py-3">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">From</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $notification->actor['name'] }}</dd>
                    </div>
                @endif

                @if ($notification->data['project_name'] ?? null)
                    <div class="flex justify-between px-4 py-3">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Project</dt>
                        <dd class="text-sm">
                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-500/30">
                                {{ $notification->data['project_name'] }}
                            </span>
                        </dd>
                    </div>
                @endif

                @if ($notification->data['ticket_name'] ?? null)
                    <div class="flex justify-between px-4 py-3">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticket</dt>
                        <dd class="text-sm">
                            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-500/30">
                                {{ $notification->data['ticket_name'] }}
                            </span>
                        </dd>
                    </div>
                @endif

                <div class="flex justify-between px-4 py-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="text-sm">
                        @if ($notification->isUnread())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                Unread
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Read
                            </span>
                        @endif
                    </dd>
                </div>

                <div class="flex justify-between px-4 py-3">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Received</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $notification->relative_time }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Action Buttons --}}
    @if (count($notification->action_buttons) > 0)
        <div class="space-y-3">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Quick Actions</h4>
            <div class="flex flex-wrap gap-2">
                @foreach ($notification->action_buttons as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="{{ $action['style'] === 'primary' 
                            ? 'bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600' 
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' 
                        }} inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    >
                        {{ $action['label'] }}
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
