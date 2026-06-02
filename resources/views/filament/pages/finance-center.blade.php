<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Expense reviews</x-slot>
            <div class="text-3xl font-bold">{{ $this->expenseReviewQueue()->count() }}</div>
            <p class="text-sm text-gray-500">awaiting finance verification</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Payment requests</x-slot>
            <div class="text-3xl font-bold">{{ $this->paymentRequestQueue()->count() }}</div>
            <p class="text-sm text-gray-500">draft / submitted for approval / clarification</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Paid · pending reconciliation</x-slot>
            <div class="text-3xl font-bold">{{ $this->paidPendingReconciliationQueue()->count() }}</div>
            <p class="text-sm text-gray-500">self-attested, awaiting reconciliation</p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Expense review inbox</x-slot>
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($this->expenseReviewQueue()->limit(15)->get() as $er)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="font-medium">{{ $er->request_number }} — {{ $er->title }}</span>
                    <span class="tabular-nums">{{ $er->currency }} {{ number_format((float) $er->amount, 2) }}</span>
                    <span class="text-gray-500">{{ str($er->status)->headline() }}</span>
                </div>
            @empty
                <p class="py-2 text-sm text-gray-500">No expense requests awaiting review.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Paid · pending reconciliation</x-slot>
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($this->paidPendingReconciliationQueue()->limit(15)->get() as $pr)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="font-medium">{{ $pr->payment_request_number }} — {{ $pr->payee_name }}</span>
                    {{-- Sensitive: only ever the masked last4 is shown. --}}
                    <span class="text-gray-500">•••• {{ $pr->bank_account_last4 }}</span>
                    <span class="tabular-nums">{{ $pr->currency }} {{ number_format((float) $pr->amount, 2) }}</span>
                </div>
            @empty
                <p class="py-2 text-sm text-gray-500">Nothing pending reconciliation.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Batch payment modules</x-slot>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach ($this->batchPaymentModules() as $module)
                <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $module['label'] }}</span>
                        <x-filament::badge color="{{ $module['enabled'] ? 'success' : 'gray' }}">
                            {{ $module['enabled'] ? 'Enabled' : 'Disabled' }}
                        </x-filament::badge>
                    </div>

                    @if ($module['enabled'])
                        <div class="grid grid-cols-3 gap-2 text-xs text-gray-500">
                            <div>Eligible <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['eligible'] }}</span></div>
                            <div>Bundled <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['bundled'] }}</span></div>
                            <div>Blocked <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['blocked'] }}</span></div>
                            <div>Authorized <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['authorized'] }}</span></div>
                            <div>Recording <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['recording'] }}</span></div>
                            <div>Paid <span class="font-semibold text-gray-900 dark:text-white">{{ $module['summary']['paid'] }}</span></div>
                        </div>
                    @else
                        <p class="text-gray-500">{{ $module['disabled_reason'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
