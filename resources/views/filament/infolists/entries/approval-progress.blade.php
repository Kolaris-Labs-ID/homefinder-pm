@php
    use App\Models\Hris\PayrollWorkflowConfig;
    use App\Models\Hris\PayrollWorkflowStep;

    $record = $getRecord();
    $module = $record->getWorkflowModule();
    $config = PayrollWorkflowConfig::getActiveConfig($module);

    $steps = collect();
    if ($config) {
        $financeSteps = $config->steps()
            ->where('step_type', PayrollWorkflowStep::STEP_FINANCE)
            ->orderBy('order', 'asc')
            ->with('assigneeUser')
            ->get();

        $approvalLogs = $record->approvalLogs()
            ->whereIn('action', ['approved', 'paid'])
            ->orderBy('created_at', 'asc')
            ->with('actedBy')
            ->get();

        $currentOrder = $record->current_finance_step_order;
        $isPaid = $record->isPaid();
        $isPendingFinance = $record->status === 'pending_finance';

        foreach ($financeSteps as $index => $step) {
            $stepNumber = $index + 1;
            $approverName = $step->assigneeUser?->name ?? 'Unknown';

            $matchingLog = $approvalLogs->first(function ($log) use ($step) {
                return $log->acted_by === $step->assignee_user_id;
            });

            if ($isPaid && $matchingLog) {
                $status = $matchingLog->action === 'paid' ? 'paid' : 'approved';
                $timestamp = $matchingLog->created_at;
                $notes = $matchingLog->notes;
            } elseif ($isPendingFinance && $stepNumber < $currentOrder) {
                $status = 'approved';
                $timestamp = $matchingLog?->created_at;
                $notes = $matchingLog?->notes;
            } elseif ($isPendingFinance && $stepNumber === $currentOrder) {
                $status = 'current';
                $timestamp = null;
                $notes = null;
            } else {
                $status = 'pending';
                $timestamp = null;
                $notes = null;
            }

            $steps->push((object) [
                'step_number' => $stepNumber,
                'approver_name' => $approverName,
                'status' => $status,
                'timestamp' => $timestamp,
                'notes' => $notes,
                'is_last' => $stepNumber === $financeSteps->count(),
            ]);
        }
    }
@endphp

<div class="space-y-3">
    @forelse ($steps as $step)
        <div class="flex items-start gap-3">
            {{-- Step number indicator --}}
            <div class="flex-shrink-0 mt-0.5">
                @if ($step->status === 'approved' || $step->status === 'paid')
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-success-100 dark:bg-success-500/20">
                        <x-heroicon-m-check class="h-4 w-4 text-success-600 dark:text-success-400" />
                    </div>
                @elseif ($step->status === 'current')
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20 ring-2 ring-warning-500">
                        <span class="text-xs font-bold text-warning-600 dark:text-warning-400">{{ $step->step_number }}</span>
                    </div>
                @else
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $step->step_number }}</span>
                    </div>
                @endif
            </div>

            {{-- Step content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ $step->approver_name }}
                    </span>
                    @if ($step->is_last)
                        <span class="inline-flex items-center rounded-md bg-primary-50 px-1.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                            Final Approver
                        </span>
                    @endif
                    @if ($step->status === 'approved')
                        <x-filament::badge color="success" size="sm">Approved</x-filament::badge>
                    @elseif ($step->status === 'paid')
                        <x-filament::badge color="success" size="sm">Approved & Paid</x-filament::badge>
                    @elseif ($step->status === 'current')
                        <x-filament::badge color="warning" size="sm">Waiting</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="sm">Pending</x-filament::badge>
                    @endif
                </div>
                @if ($step->timestamp)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $step->timestamp->format('d M Y H:i') }}
                    </p>
                @endif
                @if ($step->notes)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 italic">
                        {{ $step->notes }}
                    </p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No approval workflow configured.</p>
    @endforelse
</div>
