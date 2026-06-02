<?php

namespace App\Http\Controllers;

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentBundleSignatureService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Signing-link preview (Phase 6, GET only). Route is gated by `signed` (HMAC) + `auth`; Laravel's
 * middleware priority runs `auth` first, so a guest is bounced to login and returned to the intact
 * signed URL, where `signed` + the DB token gate then enforce. No bypass: HMAC/token still 403 post-auth.
 * Preview must NEVER consume the one-time token; consumption happens on the Phase 7 POST submit.
 */
class SignPaymentBundleController extends Controller
{
    public function show(Request $request, PaymentBundle $bundle, PaymentBundleSignature $paymentSignature, PaymentBundleSignatureService $signatures)
    {
        abort_unless($paymentSignature->payment_bundle_id === $bundle->id, 404);

        $token = (string) $request->query('token');

        try {
            $signatures->assertTokenValid($paymentSignature, $token);
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        abort_unless(auth()->id() === $paymentSignature->assigned_user_id, 403);
        abort_unless(auth()->user()->can($paymentSignature->required_permission), 403);

        $this->audit($paymentSignature);

        return view('finance.payment-bundles.sign', [
            'bundle' => $bundle,
            'signature' => $paymentSignature,
            'token' => $token,
            'canViewPdf' => auth()->user()->can('payment_bundles.download_pdf'),
        ]);
    }

    private function audit(PaymentBundleSignature $signature): void
    {
        app(FinanceAuditLogger::class)->log(
            $signature,
            'payment_bundle.signature_preview_viewed',
            auth()->user(),
            ['after' => [
                'signature_id' => $signature->id,
                'slot' => $signature->signature_slot->value,
            ]],
        );
    }
}
