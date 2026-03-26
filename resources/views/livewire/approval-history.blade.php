<div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    {{-- Header --}}
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Approval History</h3>
        </div>
    </div>

    {{-- Timeline Content --}}
    <div class="p-6">
        @if ($this->approvalSteps->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No approval history</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This item has no approval workflow configured.</p>
            </div>
        @else
            {{-- Timeline --}}
            <div class="relative">
                {{-- Timeline Line --}}
                <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>

                {{-- Timeline Items --}}
                <div class="space-y-6">
                    @foreach ($this->approvalSteps as $step)
                        @php
                            $config = $this->getStatusConfig($step->status);
                            $isProcessed = $this->isProcessed($step);
                            $approverName = $this->getApproverName($step);
                            $actedStep = $this->actedStep;
                            $isSkippedDueToParallel = $step->status === 'skipped'
                                && $actedStep !== null
                                && $actedStep->id !== $step->id;
                        @endphp

                        <div class="relative flex gap-4">
                            {{-- Timeline Dot --}}
                            <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full {{ $config['color'] }} text-white shadow-sm">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $config['icon'] }}" />
                                </svg>
                            </div>

                            {{-- Content Card --}}
                            <div class="flex-1 rounded-lg border {{ $config['borderColor'] }} {{ $config['bgLight'] }} p-4">
                                {{-- Header Row --}}
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            Step {{ $step->step_order }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $config['bgLight'] }} {{ $config['textColor'] }} ring-1 ring-inset ring-current/20">
                                            {{ $config['label'] }}
                                        </span>
                                    </div>
                                    @if ($step->approved_at)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $step->approved_at->format('M j, Y g:i A') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Approver Info --}}
                                <div class="mt-2 flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $approverName }}
                                    </span>
                                </div>

                                {{-- Notes (if any) --}}
                                @if ($step->notes)
                                    <div class="mt-3 rounded-md bg-white/50 p-3 dark:bg-gray-800/50">
                                        <div class="flex items-start gap-2">
                                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                                            </svg>
                                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                                {{ $step->notes }}
                                            </p>
                                        </div>
                                    </div>
                                @endif

                                {{-- Parallel skip notice: show who actually acted --}}
                                @if ($isSkippedDueToParallel)
                                    <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-800/50">
                                        <div class="flex items-start gap-2">
                                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                            </svg>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ ucfirst($actedStep->status) }} by
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->getApproverName($actedStep) }}</span>
                                                @if ($actedStep->approved_at)
                                                    on {{ $actedStep->approved_at->format('M j, Y g:i A') }}
                                                @endif
                                                — no action required from this approver.
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
