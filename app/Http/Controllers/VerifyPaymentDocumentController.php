<?php

namespace App\Http\Controllers;

use App\Models\Hris\PaymentBundle;
use Illuminate\Http\Request;

class VerifyPaymentDocumentController extends Controller
{
    public function show(Request $request, string $code)
    {
        $bundle = PaymentBundle::where('public_verification_code', $code)->first();

        if ($bundle === null) {
            return response()->view('finance.payment-bundles.verify', [
                'found' => false,
            ]);
        }

        $finalizedStatuses = [
            PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
            PaymentBundle::STATUS_PAYMENT_RECORDING,
            PaymentBundle::STATUS_PAID,
            PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
        ];

        $isFinalized = in_array($bundle->status, $finalizedStatuses, true);

        $displayDate = $bundle->authorized_at ?? $bundle->paid_at ?? $bundle->submitted_at;

        $hashCheck = null;
        if ($request->filled('hash')) {
            $hashCheck = hash_equals((string) $bundle->canonical_payload_hash, (string) $request->query('hash'))
                ? 'match'
                : 'mismatch';
        }

        return response()->view('finance.payment-bundles.verify', [
            'found' => true,
            'bundle_number' => $bundle->bundle_number,
            'status_raw' => $bundle->status,
            'status_label' => ucwords(str_replace('_', ' ', $bundle->status)),
            'is_finalized' => $isFinalized,
            'display_date' => $displayDate ? $displayDate->format('d M Y H:i T') : null,
            'canonical_hash' => $bundle->canonical_payload_hash,
            'hash_check' => $hashCheck,
        ]);
    }
}
