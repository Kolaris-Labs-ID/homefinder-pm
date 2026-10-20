<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Payment Document</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f3f4f6; margin: 0; padding: 1rem; color: #111827; }
        .wrap { max-width: 560px; margin: 8vh auto; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.5rem; }
        .icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .icon-ok { background: #dcfce7; color: #16a34a; }
        .icon-warn { background: #fef9c3; color: #854d0e; }
        .icon-err { background: #fee2e2; color: #991b1b; }
        .icon-unknown { background: #f3f4f6; color: #6b7280; }
        h1 { font-size: 1.1rem; margin: 0; font-weight: 700; }
        .sub { font-size: .85rem; color: #6b7280; margin: .15rem 0 0; }
        dl { margin: 0; }
        dt { font-size: .78rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; margin-top: 1.1rem; }
        dd { margin: .25rem 0 0; }
        .hash-box { font-family: ui-monospace, 'Cascadia Code', monospace; font-size: .78rem; background: #f9fafb;
                    border: 1px dashed #d1d5db; border-radius: 8px; padding: .75rem; word-break: break-all;
                    line-height: 1.5; color: #374151; }
        .badge { display: inline-block; border-radius: 999px; padding: .2rem .7rem; font-size: .78rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .hash-result { border-radius: 8px; padding: .85rem 1rem; font-size: .88rem; font-weight: 600; margin-top: 1.25rem; display: flex; align-items: center; gap: .5rem; }
        .hash-match { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .hash-mismatch { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
        .not-found { text-align: center; padding: 1rem 0; }
        .not-found .big-icon { font-size: 2.5rem; margin-bottom: .75rem; }
        .not-found h2 { font-size: 1.1rem; margin: 0 0 .5rem; }
        .not-found p { color: #6b7280; font-size: .9rem; margin: 0; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: .78rem; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        @if (!$found)
            {{-- ── Unrecognized code ── --}}
            <div class="not-found">
                <div class="big-icon">&#x1F50D;</div>
                <h2>Verification code not recognized</h2>
                <p>This verification code is not recognized. Please check the QR code or URL and try again.</p>
            </div>
        @else
            {{-- ── Recognized document ── --}}
            <div class="header">
                @if ($is_finalized)
                    <div class="icon icon-ok">&#10003;</div>
                    <div>
                        <h1>Payment Authorization Document</h1>
                        <div class="sub">This document has been fully signed and authorized.</div>
                    </div>
                @else
                    <div class="icon icon-warn">&#9888;</div>
                    <div>
                        <h1>Payment Authorization Document</h1>
                        <div class="sub">This document has not been finalized / is not a completed authorization.</div>
                    </div>
                @endif
            </div>

            <dl>
                <dt>Bundle Number</dt>
                <dd>{{ $bundle_number }}</dd>

                <dt>Status</dt>
                <dd>
                    @if ($is_finalized)
                        <span class="badge badge-green">{{ $status_label }}</span>
                    @elseif (in_array($status_raw, ['rejected', 'voided', 'cancelled']))
                        <span class="badge badge-red">{{ $status_label }}</span>
                    @else
                        <span class="badge badge-gray">{{ $status_label }}</span>
                    @endif
                </dd>

                @if ($display_date)
                    <dt>Authorized / Signed Date</dt>
                    <dd>{{ $display_date }}</dd>
                @endif

                <dt>Canonical Payload Hash (SHA-256)</dt>
                <dd><div class="hash-box">{{ $canonical_hash }}</div></dd>
            </dl>

            {{-- ── Hash comparison result ── --}}
            @if ($hash_check === 'match')
                <div class="hash-result hash-match">
                    &#10003;&nbsp; Hash matches — document is authentic
                </div>
            @elseif ($hash_check === 'mismatch')
                <div class="hash-result hash-mismatch">
                    &#10007;&nbsp; Hash does NOT match — this document may be altered or is a different version
                </div>
            @endif
        @endif
    </div>

    <div class="footer">
        Verification provided by Homefinder &mdash; for authorized personnel only
    </div>
</div>
</body>
</html>
