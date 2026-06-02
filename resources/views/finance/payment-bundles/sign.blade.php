<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Payment Bundle {{ $bundle->bundle_number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f5; color: #18181b; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.25rem; }
        .card { background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
        h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
        .muted { color: #71717a; font-size: .875rem; }
        dt { font-weight: 600; font-size: .8rem; color: #52525b; margin-top: .75rem; }
        dd { margin: .15rem 0 0; }
        .hash { font-family: ui-monospace, monospace; font-size: .85rem; word-break: break-all;
                background: #fafafa; border: 1px dashed #d4d4d8; border-radius: 8px; padding: .75rem; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; border-radius: 999px;
                 padding: .15rem .65rem; font-size: .8rem; font-weight: 600; }
        iframe { width: 100%; height: 480px; border: 1px solid #e4e4e7; border-radius: 8px; }
        .note { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px;
                padding: .75rem; font-size: .875rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Sign Payment Bundle</h1>
        <p class="muted">{{ $bundle->bundle_number }} &middot; <span class="badge">{{ $signature->signature_slot->label() }}</span></p>

        <dl>
            <dt>Signer</dt>
            <dd>{{ $signature->assignedUser?->name ?? auth()->user()->name }}</dd>

            <dt>Bundle total</dt>
            <dd>{{ $bundle->currency }} {{ number_format((float) $bundle->total_amount, 2) }}</dd>

            <dt>Canonical payload hash (you are signing THIS)</dt>
            <dd><div class="hash">{{ $bundle->canonical_payload_hash }}</div></dd>
        </dl>
    </div>

    <div class="card">
        @if ($canViewPdf)
            <iframe src="{{ route('finance.payment-bundles.unsigned-pdf', $bundle) }}" title="Unsigned bundle PDF"></iframe>
        @else
            <p class="note">PDF preview is shown to download-authorized roles only. Verify the canonical hash above.
                The full PDF.js viewer + signature canvas arrive in Phase 7.</p>
        @endif
    </div>

    {{-- Token carried for the Phase 7 POST submit; preview does not consume it. --}}
    <input type="hidden" name="token" value="{{ $token }}">
</div>
</body>
</html>
