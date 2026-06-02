<?php

namespace App\Http\Controllers;

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Signing-link preview (GET, Phase 6) + signature capture (POST, Phase 7).
 * Route is gated by `signed` (HMAC) + `auth`; Laravel's middleware priority runs `auth` first,
 * so a guest is bounced to login and returned to the intact signed URL.
 * Preview (GET) must NEVER consume the one-time token; consumption happens on the POST submit.
 */
class SignPaymentBundleController extends Controller
{
    public function show(Request $request, PaymentBundle $bundle, PaymentBundleSignature $paymentSignature, PaymentBundleSignatureService $signatures)
    {
        $this->assertGates($request, $bundle, $paymentSignature, $signatures);

        app(FinanceAuditLogger::class)->log(
            $paymentSignature,
            'payment_bundle.signature_preview_viewed',
            auth()->user(),
            ['after' => [
                'signature_id' => $paymentSignature->id,
                'slot' => $paymentSignature->signature_slot->value,
            ]],
        );

        return view('finance.payment-bundles.sign', [
            'bundle' => $bundle,
            'signature' => $paymentSignature,
            'token' => (string) $request->query('token'),
            'canViewPdf' => auth()->user()->can('payment_bundles.download_pdf'),
        ]);
    }

    public function store(Request $request, PaymentBundle $bundle, PaymentBundleSignature $paymentSignature, PaymentBundleSignatureService $signatures)
    {
        $this->assertGates($request, $bundle, $paymentSignature, $signatures);

        $request->validate([
            // max length caps the base64 payload (~375KB decoded) so a giant string can't drive expensive GD work.
            'signature_image' => ['required', 'string', 'max:500000'],
            'confirmed' => ['accepted'],
        ]);

        try {
            $signatures->submitSignature(
                $paymentSignature,
                $request->user(),
                $request->input('signature_image'),
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['signature_image' => $e->getMessage()]);
        }

        // A successful submit consumes the one-time token, so the signed signing URL is now dead —
        // redirect to the token-free landing instead of back() (which would 403 on the spent token).
        return redirect()->route('sign.payment-bundle.thanks');
    }

    public function reject(Request $request, PaymentBundle $bundle, PaymentBundleSignature $paymentSignature, PaymentBundleSignatureService $signatures)
    {
        $this->assertGates($request, $bundle, $paymentSignature, $signatures);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            app(PaymentBundleService::class)->rejectSignature(
                $paymentSignature,
                $request->user(),
                $request->input('reason'),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('sign.payment-bundle.thanks');
    }

    public function thanks()
    {
        return view('finance.payment-bundles.signed-thanks');
    }

    /** Shared gate checks: bundle-owns-signature, token validity, user identity, permission. */
    private function assertGates(Request $request, PaymentBundle $bundle, PaymentBundleSignature $paymentSignature, PaymentBundleSignatureService $signatures): void
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
    }
}
