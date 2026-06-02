<?php

namespace App\Console\Commands;

use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 0 spike — proves PDF generation, canonical hashing, and FinanceAttachment storage.
 * Not a production service; extracted / replaced in Phases 4, 5, 9.
 */
class PaymentBundlePocCommand extends Command
{
    protected $signature = 'payment-bundle:poc';

    protected $description = 'Phase 0 PoC: canonical hash, unsigned + signed PDF, FinanceAttachment storage';

    public function handle(): int
    {
        $payload = $this->samplePayload();

        // Prove determinism: same payload → identical hash, even with items in reversed order
        $hash1 = PaymentBundlePocCanonical::hash($payload);
        $reversed = $payload;
        $reversed['ordered_items'] = array_reverse($payload['ordered_items']);
        $hash2 = PaymentBundlePocCanonical::hash($reversed);

        if ($hash1 !== $hash2) {
            $this->error('FAIL: hashes differ with reversed item order!');

            return self::FAILURE;
        }

        $this->line("Canonical hash (both): <info>{$hash1}</info>");

        // Build view data (inject hash into the bundle so it appears on the PDF)
        $bundleData = array_merge($payload, ['canonical_hash' => $hash1]);

        // Unsigned PDF
        $unsignedBytes = Pdf::loadView('finance.payment-bundles.poc', [
            'bundle'     => $bundleData,
            'signatures' => [],
        ])->output();

        // Signed PDF — same template, fake signature data-URIs injected
        $fakeSignatureDataUri = $this->fakePngDataUri();
        $signedBytes = Pdf::loadView('finance.payment-bundles.poc', [
            'bundle'     => $bundleData,
            'signatures' => [
                'coo'             => $fakeSignatureDataUri,
                'finance_manager' => $fakeSignatureDataUri,
                'hr_manager'      => $fakeSignatureDataUri,
            ],
        ])->output();

        // Write to private disk
        Storage::disk('private')->makeDirectory('poc');
        $unsignedPath = 'poc/bundle-poc-unsigned.pdf';
        $signedPath   = 'poc/bundle-poc-signed.pdf';
        Storage::disk('private')->put($unsignedPath, $unsignedBytes);
        Storage::disk('private')->put($signedPath, $signedBytes);

        $this->line("Unsigned PDF: <info>{$unsignedPath}</info> (" . strlen($unsignedBytes) . " bytes)");
        $this->line("Signed PDF:   <info>{$signedPath}</info> (" . strlen($signedBytes) . " bytes)");

        // Store signed PDF as FinanceAttachment attached to first User (or create one)
        $user = User::first() ?? User::factory()->create(['name' => 'PoC User', 'email' => 'poc@partnershub.co']);

        FinanceAttachment::create([
            'attachable_type'  => get_class($user),
            'attachable_id'    => $user->id,
            'attachment_role'  => 'payment_bundle_signed_pdf',
            'file_path'        => $signedPath,
            'file_hash'        => hash('sha256', $signedBytes),
            'file_size'        => strlen($signedBytes),
            'mime_type'        => 'application/pdf',
            'original_filename' => 'bundle-poc-signed.pdf',
            'uploaded_by'      => $user->id,
            'uploaded_at'      => now(),
            'metadata'         => ['poc' => true, 'canonical_hash' => $hash1],
        ]);

        $this->info('FinanceAttachment row created for signed PDF.');

        return self::SUCCESS;
    }

    private function samplePayload(): array
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

    /** Minimal 1×1 red PNG as a data-URI for testing signature rendering. */
    private function fakePngDataUri(): string
    {
        // 1×1 red pixel PNG (base64)
        $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

        return 'data:image/png;base64,' . $b64;
    }
}
