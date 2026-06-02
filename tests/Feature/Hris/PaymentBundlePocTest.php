<?php

use App\Console\Commands\PaymentBundlePocCanonical;
use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

// ── helpers ──────────────────────────────────────────────────────────────────

function pocPayload(): array
{
    return [
        'bundle_number'            => 'BND-2026-0001',
        'bundle_version'           => 1,
        'source_type'              => 'expense_report',
        'source_id'                => '42',
        'source_hash'              => hash('sha256', 'expense_report:42:v1'),
        'payee_name'               => 'Budi Santoso',
        'bank_last4'               => '7890',
        'amount'                   => 1500000,
        'currency'                 => 'IDR',
        'total_amount'             => 3250000,
        'required_signature_slots' => ['coo', 'finance_manager', 'hr_manager'],
        'assigned_signer_ids'      => [3, 1, 2],
        'ordered_items'            => [
            [
                'source_type' => 'expense_report',
                'source_id'   => '42',
                'source_hash' => hash('sha256', 'expense_report:42:v1'),
                'payee_name'  => 'Budi Santoso',
                'bank_last4'  => '7890',
                'amount'      => 1500000,
                'currency'    => 'IDR',
            ],
            [
                'source_type' => 'payment_request',
                'source_id'   => '17',
                'source_hash' => hash('sha256', 'payment_request:17:v1'),
                'payee_name'  => 'Sari Dewi',
                'bank_last4'  => '1234',
                'amount'      => 1750000,
                'currency'    => 'IDR',
            ],
        ],
    ];
}

function pocBundleViewData(array $payload, array $signatures = []): array
{
    return [
        'bundle'     => array_merge($payload, ['canonical_hash' => PaymentBundlePocCanonical::hash($payload)]),
        'signatures' => $signatures,
    ];
}

// ── canonical hash: determinism ───────────────────────────────────────────────

it('produces the same hash for identical payload', function () {
    $p = pocPayload();
    expect(PaymentBundlePocCanonical::hash($p))->toBe(PaymentBundlePocCanonical::hash($p));
});

it('produces the same hash regardless of item input order', function () {
    $p1 = pocPayload();
    $p2 = pocPayload();
    $p2['ordered_items'] = array_reverse($p2['ordered_items']);

    expect(PaymentBundlePocCanonical::hash($p1))->toBe(PaymentBundlePocCanonical::hash($p2));
});

it('produces the same hash regardless of assigned_signer_ids order', function () {
    $p1 = pocPayload();
    $p2 = pocPayload();
    $p2['assigned_signer_ids'] = array_reverse($p2['assigned_signer_ids']);

    expect(PaymentBundlePocCanonical::hash($p1))->toBe(PaymentBundlePocCanonical::hash($p2));
});

// ── canonical hash: sensitivity ───────────────────────────────────────────────

it('changes hash when amount changes', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['amount'] = 9999999;

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

it('changes hash when payee_name changes', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['payee_name'] = 'Different Person';

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

it('changes hash when bank_last4 changes', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['bank_last4'] = '0000';

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

it('changes hash when assigned_signer_ids set changes', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['assigned_signer_ids'] = [99, 100];

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

it('changes hash when an item amount changes', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['ordered_items'][0]['amount'] = 1;

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

it('changes hash when an item is added', function () {
    $base  = pocPayload();
    $other = pocPayload();
    $other['ordered_items'][] = [
        'source_type' => 'payment_request',
        'source_id'   => '99',
        'source_hash' => 'x',
        'payee_name'  => 'Extra',
        'bank_last4'  => '0001',
        'amount'      => 500000,
        'currency'    => 'IDR',
    ];

    expect(PaymentBundlePocCanonical::hash($base))->not->toBe(PaymentBundlePocCanonical::hash($other));
});

// ── money formatting ─────────────────────────────────────────────────────────

it('formats money as fixed-precision string in canonical form', function () {
    $p    = pocPayload();
    $data = PaymentBundlePocCanonical::buildCanonical($p);

    expect($data['amount'])->toBe('1500000.00')
        ->and($data['total_amount'])->toBe('3250000.00');
});

// ── DomPDF rendering ─────────────────────────────────────────────────────────

it('renders non-empty unsigned PDF bytes from the Blade template', function () {
    $p    = pocPayload();
    $data = pocBundleViewData($p, []);

    $bytes = Pdf::loadView('finance.payment-bundles.poc', $data)->output();

    expect(strlen($bytes))->toBeGreaterThan(0)
        ->and(substr($bytes, 0, 4))->toBe('%PDF');
});

it('renders non-empty signed PDF bytes from the SAME Blade template', function () {
    // 1×1 red pixel PNG data-URI
    $fakeSig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
    $p       = pocPayload();
    $data    = pocBundleViewData($p, [
        'coo'             => $fakeSig,
        'finance_manager' => $fakeSig,
        'hr_manager'      => $fakeSig,
    ]);

    $bytes = Pdf::loadView('finance.payment-bundles.poc', $data)->output();

    expect(strlen($bytes))->toBeGreaterThan(0)
        ->and(substr($bytes, 0, 4))->toBe('%PDF');
});

it('signed and unsigned PDFs are different byte sequences', function () {
    $fakeSig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
    $p       = pocPayload();

    $unsigned = Pdf::loadView('finance.payment-bundles.poc', pocBundleViewData($p, []))->output();
    $signed   = Pdf::loadView('finance.payment-bundles.poc', pocBundleViewData($p, [
        'coo'             => $fakeSig,
        'finance_manager' => $fakeSig,
        'hr_manager'      => $fakeSig,
    ]))->output();

    expect($unsigned)->not->toBe($signed);
});

// ── FinanceAttachment storage ─────────────────────────────────────────────────

it('stores signed PDF as a FinanceAttachment on the private disk', function () {
    Storage::fake('private');

    $user  = User::factory()->create();
    $p     = pocPayload();
    $bytes = Pdf::loadView('finance.payment-bundles.poc', pocBundleViewData($p, []))->output();

    $path = 'poc/test-signed.pdf';
    Storage::disk('private')->put($path, $bytes);

    $att = FinanceAttachment::create([
        'attachable_type'   => get_class($user),
        'attachable_id'     => $user->id,
        'attachment_role'   => 'payment_bundle_signed_pdf',
        'file_path'         => $path,
        'file_hash'         => hash('sha256', $bytes),
        'file_size'         => strlen($bytes),
        'mime_type'         => 'application/pdf',
        'original_filename' => 'test-signed.pdf',
        'uploaded_by'       => $user->id,
        'uploaded_at'       => now(),
    ]);

    Storage::disk('private')->assertExists($path);
    expect($att->attachment_role)->toBe('payment_bundle_signed_pdf')
        ->and($att->file_size)->toBeGreaterThan(0)
        ->and(strlen($att->file_hash))->toBe(64); // sha256 hex
});
