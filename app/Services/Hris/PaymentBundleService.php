<?php

namespace App\Services\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Support\Finance\PaymentBundleSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PaymentBundle assembly (Phase 3): create a draft bundle and add/remove approved money-out
 * sources. Adding freezes a deterministic, encrypted source snapshot per item and flips the
 * source into in_payment_bundle under a row lock so a source can live in at most one active bundle.
 */
class PaymentBundleService
{
    public function __construct(
        protected FinanceAuditLogger $audit,
        protected CanonicalPayloadHashService $canonical,
        protected PaymentBundleDocumentService $documents,
        protected PaymentBundleSignatureService $signatures,
    ) {}

    public function create(User $creator, array $attrs = []): PaymentBundle
    {
        return DB::transaction(function () use ($creator, $attrs) {
            $bundle = PaymentBundle::create([
                'bundle_number' => $this->generateNumber(),
                'public_verification_code' => $this->generateVerificationCode(),
                'title' => $attrs['title'] ?? null,
                'status' => PaymentBundle::STATUS_DRAFT,
                'bundle_version' => 1,
                'period_start' => $attrs['period_start'] ?? null,
                'period_end' => $attrs['period_end'] ?? null,
                'total_amount' => '0.00',
                'currency' => $attrs['currency'] ?? 'IDR',
                'created_by' => $creator->id,
            ]);

            $this->audit->log($bundle, 'payment_bundle.created', $creator);

            return $bundle;
        });
    }

    public function addPaymentRequest(PaymentBundle $bundle, PaymentRequest $pr, User $actor): PaymentBundleItem
    {
        return DB::transaction(function () use ($bundle, $pr, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Only a draft bundle can receive items.');
            }

            $pr = PaymentRequest::query()->lockForUpdate()->findOrFail($pr->id);

            if (! in_array($pr->status, [PaymentRequest::STATUS_APPROVED_BY_COO, PaymentRequest::STATUS_APPROVED_FOR_BUNDLE], true)) {
                throw new \RuntimeException('PR is not approved/eligible for bundling.');
            }

            if (PaymentBundleItem::where('source_type', PaymentBundleItem::SOURCE_PAYMENT_REQUEST)
                ->where('source_id', $pr->id)
                ->where('status', PaymentBundleItem::STATUS_ACTIVE)
                ->exists()) {
                throw new \RuntimeException('This payment request is already in an active bundle.');
            }

            $snapshot = PaymentBundleSnapshot::forPaymentRequest($pr);

            $item = PaymentBundleItem::create([
                'payment_bundle_id' => $bundle->id,
                'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
                'source_id' => $pr->id,
                'payment_request_id' => $pr->id,
                'description' => $pr->description,
                'payee_name' => $pr->payee_name,
                'payee_bank_name' => $pr->bank_name,
                'payee_bank_account_last4' => $pr->bank_account_last4,
                'amount' => $pr->amount,
                'currency' => $pr->currency,
                'source_snapshot_json' => $snapshot,
                'source_hash' => PaymentBundleSnapshot::hash($snapshot),
                'status' => PaymentBundleItem::STATUS_ACTIVE,
            ]);

            $pr->update(['status' => PaymentRequest::STATUS_IN_PAYMENT_BUNDLE]);

            $this->recomputeTotal($bundle);

            $this->audit->log($bundle, 'payment_bundle.item_added', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'amount' => (string) $item->amount,
            ]]);

            return $item;
        });
    }

    public function addWeeklyAllowanceBatch(PaymentBundle $bundle, WeeklyAllowanceBatch $wab, User $actor): PaymentBundleItem
    {
        return DB::transaction(function () use ($bundle, $wab, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Only a draft bundle can receive items.');
            }

            $wab = WeeklyAllowanceBatch::query()->lockForUpdate()->findOrFail($wab->id);

            if ($wab->status !== WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE) {
                throw new \RuntimeException('Weekly allowance batch is not eligible for bundling.');
            }

            if (PaymentBundleItem::where('source_type', PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH)
                ->where('source_id', $wab->id)
                ->where('status', PaymentBundleItem::STATUS_ACTIVE)
                ->exists()) {
                throw new \RuntimeException('This weekly allowance batch is already in an active bundle.');
            }

            $snapshot = PaymentBundleSnapshot::forWeeklyAllowanceBatch($wab);

            $item = PaymentBundleItem::create([
                'payment_bundle_id' => $bundle->id,
                'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
                'source_id' => $wab->id,
                'description' => $snapshot['description'],
                'payee_name' => $snapshot['payee_name'],
                'payee_bank_name' => null,
                'payee_bank_account_last4' => null,
                'amount' => $wab->total_amount,
                'currency' => 'IDR',
                'source_snapshot_json' => $snapshot,
                'source_hash' => PaymentBundleSnapshot::hash($snapshot),
                'status' => PaymentBundleItem::STATUS_ACTIVE,
            ]);

            $wab->update(['status' => WeeklyAllowanceBatch::STATUS_IN_PAYMENT_BUNDLE]);

            $this->recomputeTotal($bundle);

            $this->audit->log($bundle, 'payment_bundle.item_added', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'amount' => (string) $item->amount,
            ]]);

            return $item;
        });
    }

    public function removeItem(PaymentBundleItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $bundle = $item->bundle()->lockForUpdate()->firstOrFail();

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Items can only be removed from a draft bundle.');
            }

            $releasedSourceStatus = null;
            if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
                $releasedSourceStatus = PaymentRequest::STATUS_APPROVED_FOR_BUNDLE;
                PaymentRequest::find($item->source_id)?->update(['status' => $releasedSourceStatus]);
            } elseif ($item->source_type === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH) {
                $releasedSourceStatus = WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE;
                WeeklyAllowanceBatch::find($item->source_id)?->update(['status' => $releasedSourceStatus]);
            }

            $item->update(['status' => PaymentBundleItem::STATUS_RELEASED]);

            $this->recomputeTotal($bundle);

            // Two distinct lifecycle events (plan §14): the item leaving the bundle, and the
            // underlying source becoming eligible again.
            $this->audit->log($bundle, 'payment_bundle.item_removed', $actor, ['after' => [
                'item_id' => $item->id,
                'bundle_total' => (string) $bundle->fresh()->total_amount,
            ]]);
            $this->audit->log($bundle, 'payment_bundle.item_released', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'released_source_status' => $releasedSourceStatus,
            ]]);
        });
    }

    public function cancel(PaymentBundle $bundle, User $actor): PaymentBundle
    {
        return DB::transaction(function () use ($bundle, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new RuntimeException('Only a draft bundle can be cancelled.');
            }

            $this->releaseActiveItems($bundle, $actor);

            $oldStatus = $bundle->status;
            $bundle->update(['status' => PaymentBundle::STATUS_CANCELLED]);

            $this->audit->log($bundle, 'payment_bundle.cancelled', $actor, [
                'before' => ['status' => $oldStatus],
                'after' => ['status' => PaymentBundle::STATUS_CANCELLED],
            ]);

            return $bundle->fresh();
        });
    }

    public function void(PaymentBundle $bundle, User $actor, string $reason): PaymentBundle
    {
        return DB::transaction(function () use ($bundle, $actor, $reason) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            $voidable = [
                PaymentBundle::STATUS_DRAFT,
                PaymentBundle::STATUS_SUBMITTED_FOR_SIGNATURE,
                PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2,
                PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE,
                PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
                PaymentBundle::STATUS_PAYMENT_RECORDING,
                PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
            ];

            if (! in_array($bundle->status, $voidable, true)) {
                throw new RuntimeException('This bundle cannot be voided.');
            }

            $this->releaseActiveItems($bundle, $actor);

            $terminalSignatureStatuses = [
                PaymentBundleSignature::STATUS_VOIDED,
                PaymentBundleSignature::STATUS_REJECTED,
            ];

            foreach ($bundle->signatures()->get() as $sig) {
                if (in_array($sig->status, $terminalSignatureStatuses, true)) {
                    continue;
                }
                $sig->forceFill(['status' => PaymentBundleSignature::STATUS_VOIDED])->save();
                $this->audit->log($bundle, 'payment_bundle.signature_voided', $actor, ['after' => [
                    'signature_id' => $sig->id,
                    'slot' => $sig->signature_slot->value,
                ]]);
            }

            $oldStatus = $bundle->status;
            $bundle->update([
                'status' => PaymentBundle::STATUS_VOIDED,
                'metadata' => array_merge($bundle->metadata ?? [], [
                    'void_reason' => $reason,
                    'voided_by' => $actor->id,
                    'voided_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->audit->log($bundle, 'payment_bundle.voided', $actor, [
                'before' => ['status' => $oldStatus],
                'after' => ['status' => PaymentBundle::STATUS_VOIDED, 'reason' => $reason],
            ]);

            return $bundle->fresh();
        });
    }

    public function rejectSignature(PaymentBundleSignature $signature, User $signer, string $reason): PaymentBundle
    {
        return DB::transaction(function () use ($signature, $signer, $reason) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($signature->payment_bundle_id);
            $signature = PaymentBundleSignature::query()->lockForUpdate()->findOrFail($signature->id);

            $signingStatuses = [
                PaymentBundle::STATUS_SUBMITTED_FOR_SIGNATURE,
                PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2,
                PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE,
            ];

            if (! in_array($bundle->status, $signingStatuses, true)) {
                throw new RuntimeException('This bundle is not in a signing state.');
            }

            if ($signature->status !== PaymentBundleSignature::STATUS_PENDING) {
                throw new RuntimeException('This signature slot is not pending.');
            }

            if ($signature->assigned_user_id !== $signer->id) {
                throw new RuntimeException('Only the assigned signer can reject this signature slot.');
            }

            // Set rejected state (AFTER the check — override revokeToken's STATUS_REVOKED).
            $signature->forceFill([
                'status' => PaymentBundleSignature::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'signed_by' => $signer->id,
                'token_revoked_at' => now(),
            ])->save();

            $this->audit->log($bundle, 'payment_bundle.signature_rejected', $signer, ['after' => [
                'signature_id' => $signature->id,
                'slot' => $signature->signature_slot->value,
                'reason' => $reason,
            ]]);

            // Revoke every other non-terminal signature.
            $terminalStatuses = [
                PaymentBundleSignature::STATUS_REJECTED,
                PaymentBundleSignature::STATUS_REVOKED,
                PaymentBundleSignature::STATUS_VOIDED,
            ];

            foreach ($bundle->signatures()->where('id', '!=', $signature->id)->get() as $other) {
                if (in_array($other->status, $terminalStatuses, true)) {
                    continue;
                }
                $this->signatures->revokeToken($other);
                $this->audit->log($bundle, 'payment_bundle.signature_revoked', $signer, ['after' => [
                    'signature_id' => $other->id,
                    'slot' => $other->signature_slot->value,
                ]]);
            }

            $this->releaseActiveItems($bundle, $signer);

            $oldStatus = $bundle->status;
            $bundle->update([
                'status' => PaymentBundle::STATUS_REJECTED,
                'metadata' => array_merge($bundle->metadata ?? [], [
                    'rejection_reason' => $reason,
                    'rejected_by' => $signer->id,
                    'rejected_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->audit->log($bundle, 'payment_bundle.rejected', $signer, [
                'before' => ['status' => $oldStatus],
                'after' => ['status' => PaymentBundle::STATUS_REJECTED, 'reason' => $reason],
            ]);

            return $bundle->fresh();
        });
    }

    /**
     * Lock the draft, validate the signer assignment (segregation of duties + per-slot permission),
     * create the three pending signature rows with signing links, freeze the canonical payload +
     * unsigned PDF, and move the bundle to the first actionable signing state.
     *
     * @param  array{finance_signer_1:int,finance_signer_2:int,executive_signer:int}  $assignments
     */
    public function submitForSignature(PaymentBundle $bundle, array $assignments, User $actor): PaymentBundle
    {
        return DB::transaction(function () use ($bundle, $assignments, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new RuntimeException('Only a draft bundle can be submitted for signature.');
            }

            if ($bundle->activeItems()->count() === 0) {
                throw new RuntimeException('Cannot submit an empty bundle.');
            }

            $users = $this->resolveAndValidateAssignment($bundle, $assignments);

            $signingLinks = [];
            foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
                $sig = PaymentBundleSignature::create([
                    'payment_bundle_id' => $bundle->id,
                    'signature_slot' => $slot,
                    'required_permission' => $slot->requiredPermission(),
                    'status' => PaymentBundleSignature::STATUS_PENDING,
                ]);

                $signingLinks[$slot->value] = $this->signatures->createSigningLink($sig, $users[$slot->value]);
            }

            $this->documents->generateUnsignedPdf($bundle, $actor);
            $bundle->refresh();

            $oldStatus = $bundle->status;
            $bundle->update([
                'status' => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                'submitted_at' => now(),
                'metadata' => array_merge($bundle->metadata ?? [], ['signing_links' => $signingLinks]),
            ]);

            $this->audit->log($bundle, 'payment_bundle.submitted_for_signature', $actor, [
                'before' => ['status' => $oldStatus],
                'after' => [
                    'status' => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                    'total_amount' => (string) $bundle->total_amount,
                    'finance_signer_1' => $users[PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value]->id,
                    'finance_signer_2' => $users[PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value]->id,
                    'executive_signer' => $users[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value]->id,
                ],
            ]);

            return $bundle->refresh();
        });
    }

    /**
     * @param  array{finance_signer_1:int,finance_signer_2:int,executive_signer:int}  $assignments
     * @return array<string, User> slot value => user
     */
    private function resolveAndValidateAssignment(PaymentBundle $bundle, array $assignments): array
    {
        $f1 = $assignments[PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value] ?? null;
        $f2 = $assignments[PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value] ?? null;
        $exec = $assignments[PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value] ?? null;

        if ($f1 === null || $f2 === null || $exec === null) {
            throw new RuntimeException('All three signer slots must be assigned.');
        }

        if ((int) $f1 === (int) $f2) {
            throw new RuntimeException('The two finance signers must be different people.');
        }

        if ((int) $exec === (int) $f1 || (int) $exec === (int) $f2) {
            throw new RuntimeException('The executive signer must differ from both finance signers.');
        }

        $threshold = (string) config('finance.payment_bundle_high_value_threshold');
        if (bccomp((string) $bundle->total_amount, $threshold, 2) >= 0 && (int) $exec === (int) $bundle->created_by) {
            throw new RuntimeException('On a high-value bundle the creator may not be the executive signer.');
        }

        $users = [
            PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => User::findOrFail($f1),
            PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => User::findOrFail($f2),
            PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => User::findOrFail($exec),
        ];

        foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
            if (! $users[$slot->value]->can($slot->requiredPermission())) {
                throw new RuntimeException("Assigned {$slot->label()} lacks the required permission ({$slot->requiredPermission()}).");
            }
        }

        return $users;
    }

    private function releaseActiveItems(PaymentBundle $bundle, User $actor): void
    {
        foreach ($bundle->items()->where('status', PaymentBundleItem::STATUS_ACTIVE)->get() as $item) {
            if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
                PaymentRequest::find($item->source_id)?->update(['status' => PaymentRequest::STATUS_APPROVED_FOR_BUNDLE]);
            } elseif ($item->source_type === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH) {
                WeeklyAllowanceBatch::find($item->source_id)?->update(['status' => WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE]);
            }

            $item->update(['status' => PaymentBundleItem::STATUS_RELEASED]);

            $this->audit->log($bundle, 'payment_bundle.item_released', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
            ]]);
        }
    }

    private function recomputeTotal(PaymentBundle $bundle): void
    {
        $total = '0';
        foreach ($bundle->items()->where('status', PaymentBundleItem::STATUS_ACTIVE)->get() as $item) {
            $total = bcadd($total, (string) $item->amount, 2);
        }

        $bundle->update(['total_amount' => $total]);
    }

    private function generateNumber(): string
    {
        $prefix = 'PB-'.now()->format('Ym').'-';

        $last = PaymentBundle::where('bundle_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('bundle_number')
            ->value('bundle_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = Str::random(48);
        } while (PaymentBundle::where('public_verification_code', $code)->exists());

        return $code;
    }
}
