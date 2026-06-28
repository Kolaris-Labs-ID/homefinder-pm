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

    <div
        x-data="{
            evidenceOpen: false,
            evidenceUrl: null,
            evidenceName: null,
            evidenceMime: null,
            openEvidence(url, name, mime) {
                this.evidenceUrl = url
                this.evidenceName = name || 'Expense evidence'
                this.evidenceMime = mime || ''
                this.evidenceOpen = true
            },
            closeEvidence() {
                this.evidenceOpen = false
                this.evidenceUrl = null
                this.evidenceName = null
                this.evidenceMime = null
            },
        }"
        @keydown.escape.window="closeEvidence()"
    >
        <x-filament::section>
            <x-slot name="heading">Expense review inbox</x-slot>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->expenseReviewQueue()->limit(15)->get() as $er)
                    @php
                        $evidence = $this->expenseEvidenceAttachment($er);
                        $evidenceUrl = $evidence ? $this->financeAttachmentViewUrl($evidence) : null;
                        $evidenceName = $evidence?->original_filename ?: 'Expense evidence';
                        $evidenceMime = $evidence?->mime_type;
                    @endphp
                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                        <div class="flex min-w-0 items-center gap-3">
                            @if ($evidenceUrl && $this->isImageFinanceAttachment($evidence))
                                <button
                                    type="button"
                                    @click="openEvidence(@js($evidenceUrl), @js($evidenceName), @js($evidenceMime))"
                                    class="shrink-0 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >
                                    <img
                                        src="{{ $evidenceUrl }}"
                                        alt="Expense evidence for {{ $er->request_number }}"
                                        class="rounded-lg border shadow-sm"
                                        style="width:3rem;height:3rem;object-fit:cover"
                                        loading="lazy"
                                    />
                                </button>
                            @elseif ($evidenceUrl)
                                <button
                                    type="button"
                                    @click="openEvidence(@js($evidenceUrl), @js($evidenceName), @js($evidenceMime))"
                                    class="shrink-0 text-xs font-medium text-primary-600 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-primary-400"
                                >
                                    View evidence
                                </button>
                            @endif

                            <span class="truncate font-medium">{{ $er->request_number }} — {{ $er->title }}</span>
                        </div>
                        <span class="shrink-0 tabular-nums">{{ $er->currency }} {{ number_format((float) $er->amount, 2) }}</span>
                        <span class="shrink-0 text-gray-500">{{ str($er->status)->headline() }}</span>
                    </div>
                @empty
                    <p class="py-2 text-sm text-gray-500">No expense requests awaiting review.</p>
                @endforelse
            </div>
        </x-filament::section>

        <div
            x-cloak
            x-show="evidenceOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-gray-950/70" @click="closeEvidence()"></div>

            <div
                class="relative z-10 flex w-full max-w-5xl flex-col rounded-lg bg-white shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
                style="max-height:90vh"
                @click.stop
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-white/10">
                    <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white" x-text="evidenceName || 'Expense evidence'"></h2>
                    <button
                        type="button"
                        @click="closeEvidence()"
                        class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        aria-label="Close evidence preview"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="overflow-auto p-4">
                    <template x-if="evidenceUrl && evidenceMime && evidenceMime.startsWith('image/')">
                        <img
                            :src="evidenceUrl"
                            :alt="evidenceName || 'Expense evidence'"
                            class="rounded-lg border border-gray-200 shadow-sm dark:border-gray-700"
                            style="max-height:75vh;width:100%;object-fit:contain"
                        />
                    </template>

                    <template x-if="evidenceUrl && evidenceMime === 'application/pdf'">
                        <iframe
                            :src="evidenceUrl"
                            :title="evidenceName || 'Expense evidence'"
                            class="w-full rounded-lg border border-gray-200 shadow-sm dark:border-gray-700"
                            style="height:75vh"
                        ></iframe>
                    </template>

                    <template x-if="evidenceUrl && !(evidenceMime && evidenceMime.startsWith('image/')) && evidenceMime !== 'application/pdf'">
                        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
                            <p class="font-medium text-gray-950 dark:text-white" x-text="evidenceName || 'Expense evidence'"></p>
                            <p class="mt-1 text-gray-500 dark:text-gray-400">
                                Preview is not available for this file type.
                            </p>
                            <a
                                :href="evidenceUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                            >
                                Open evidence
                            </a>
                        </div>
                    </template>

                    <template x-if="! evidenceUrl">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Evidence is not available.
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </div>

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
