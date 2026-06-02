<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Final Payment Authorization &mdash; {{ $bundle->bundle_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; color: #222; }
        h1 { font-size: 15px; text-transform: uppercase; margin: 0 0 2px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px; }
        .company { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        .meta { color: #555; font-size: 10px; margin: 2px 0; }
        .hash-box { background: #f5f5f5; border: 1px solid #ccc; padding: 6px 10px; margin-bottom: 14px; word-break: break-all; font-family: monospace; font-size: 9.5px; }
        .hash-label { font-weight: bold; font-size: 10px; margin-bottom: 3px; }
        .section-title { font-weight: bold; font-size: 11px; margin: 12px 0 5px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th { background: #4a90d9; color: #fff; padding: 5px 7px; text-align: left; font-size: 10px; }
        table.items td { padding: 4px 7px; border-bottom: 1px solid #e0e0e0; font-size: 10px; }
        table.items tr:last-child td { border-bottom: none; }
        .amount { text-align: right; font-family: monospace; }
        .total-row td { font-weight: bold; background: #f0f0f0; }
        .risk-list { margin: 0; padding-left: 16px; }
        .risk-list li { margin-bottom: 2px; color: #a00; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .sig-table td { width: 33%; text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-img { max-width: 140px; max-height: 60px; display: block; margin: 0 auto 4px; }
        .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 4px; font-size: 10px; }
        .sig-name { font-weight: bold; font-size: 10px; }
        .sig-role { color: #666; font-size: 9px; }
        .sig-date { color: #555; font-size: 9px; }
        .footer { margin-top: 20px; text-align: center; color: #aaa; font-size: 9px; border-top: 1px solid #ddd; padding-top: 8px; }
        .qr-section { text-align: center; margin-top: 14px; }
        .qr-url { font-size: 8px; color: #555; word-break: break-all; }
        .proof-note { font-style: italic; color: #666; font-size: 10px; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company">{{ config('app.name') }}</div>
        <h1>Final Payment Authorization</h1>
        <div class="meta">Bundle No: <strong>{{ $bundle->bundle_number }}</strong></div>
        <div class="meta">
            Prepared by: {{ $bundle->creator?->name ?? '—' }}
            &nbsp;|&nbsp;
            Created: {{ $bundle->created_at->format('d M Y') }}
        </div>
        <div class="meta">
            Period:
            @if($bundle->period_start && $bundle->period_end)
                {{ $bundle->period_start->format('d M Y') }} &ndash; {{ $bundle->period_end->format('d M Y') }}
            @else
                &mdash;
            @endif
        </div>
    </div>

    <div class="hash-box">
        <div class="hash-label">Canonical Payload Hash (SHA-256)</div>
        {{ $bundle->canonical_payload_hash }}
    </div>

    <div class="section-title">Payment Items</div>
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Description / Payee</th>
                <th>Source</th>
                <th>Bank</th>
                <th class="amount">Amount ({{ $bundle->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @foreach(($payload['items'] ?? []) as $item)
            @php $i++; @endphp
            <tr>
                <td>{{ $i }}</td>
                <td>{{ $item['payee_name'] }}</td>
                <td>
                    @switch($item['source_type'])
                        @case('payment_request')
                            Payment Request
                            @break
                        @case('weekly_allowance_batch')
                            Weekly Allowance
                            @break
                        @case('overtime_pay_batch')
                            Overtime Pay
                            @break
                        @case('late_pay_compensation_batch')
                            Late Pay Compensation
                            @break
                        @case('payrun_batch')
                            Payroll
                            @break
                        @default
                            Batch Payment
                    @endswitch
                </td>
                <td>&bull;&bull;&bull;&bull; {{ $item['bank_last4'] }}</td>
                <td class="amount">{{ number_format((float) $item['amount'], 2, '.', ',') }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Total</td>
                <td class="amount">{{ number_format((float) $bundle->total_amount, 2, '.', ',') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Risk Flags</div>
    @php $riskFlags = $bundle->metadata['risk_flags'] ?? []; @endphp
    @if(count($riskFlags) > 0)
        <ul class="risk-list">
            @foreach($riskFlags as $flag)
            <li>{{ $flag }}</li>
            @endforeach
        </ul>
    @else
        <p style="font-size:10px; color:#555;">None</p>
    @endif

    <div class="section-title">Authorization Signatures</div>
    @php
        $slots = \App\Enums\PaymentBundleSignatureSlot::ordered();
        $assignedSignerIds = $payload['assigned_signer_ids'] ?? [];
    @endphp
    <table class="sig-table">
        <tr>
            @foreach($slots as $slot)
            <td>
                @if(!empty($signatures[$slot->value]))
                    @php $sig = $signatures[$slot->value]; @endphp
                    <img class="sig-img" src="{{ $sig['image_data_uri'] }}" alt="Signature">
                    <div class="sig-line">
                        <div class="sig-name">{{ $sig['name'] }}</div>
                        <div class="sig-role">{{ $sig['role'] }}</div>
                        <div class="sig-date">{{ $sig['signed_at'] }}</div>
                    </div>
                @else
                    <div class="sig-line">
                        <div class="sig-name">{{ $slot->label() }}</div>
                        @if(!empty($assignedSignerIds[$slot->value]))
                            <div class="sig-role" style="color:#555;">Assigned: ID {{ $assignedSignerIds[$slot->value] }}</div>
                        @else
                            <div class="sig-role">Awaiting signature</div>
                        @endif
                    </div>
                @endif
            </td>
            @endforeach
        </tr>
    </table>

    <p class="proof-note">Payment proof to be attached after payment.</p>

    <div class="qr-section">
        <img src="{{ $qrDataUri }}" alt="QR Verification Code" style="width:100px; height:100px;">
        <div class="qr-url">{{ $verificationUrl }}</div>
    </div>

    <div class="footer">
        {{ $bundle->bundle_number }} &nbsp;|&nbsp;
        {{ substr($bundle->canonical_payload_hash ?? '', 0, 16) }}&hellip;
        &nbsp;|&nbsp; Generated by {{ config('app.name') }} HRIS
    </div>

</body>
</html>
