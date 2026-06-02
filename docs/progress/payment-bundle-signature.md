# Payment Bundle — PDF & Signing Design (Phase 0 PoC)

## 1. PDF Engine Decision: DomPDF

**Engine:** `barryvdh/laravel-dompdf` ^3.1 (already installed, already used by payslips).

**Rationale:**
- Pure PHP — no Docker daemon, no Chromium binary, no external process; fits the existing server environment.
- Already proven for payslip PDFs (`resources/views/payslips/template.blade.php` → `Pdf::loadView()->output()`); zero new infrastructure.
- Gotenberg requires a running container; Browsershot requires a Puppeteer/Chromium install and a Node process — both are overkill and unavailable in this environment.
- DomPDF limitation: no CSS flexbox/grid — use `display:table` / `display:table-cell` for multi-column layouts, matching the payslip pattern.

**Rendering strategy:**
```php
$bytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.payment-bundles.poc', $data)->output();
```
`$data` is the same array for both unsigned and signed renders; signatures are injected as optional keys. When signature data is absent, the template renders blank signature lines. When present, it renders `<img>` tags with data-URIs.

## 2. One-Blade Re-Render Strategy (No FPDI)

Both unsigned and signed PDFs are produced by rendering the same Blade template (`finance/payment-bundles/poc.blade.php`) with different `$data`. No PDF stamping, no FPDI, no byte-level manipulation.

```
Unsigned: Pdf::loadView('finance.payment-bundles.poc', ['signatures' => []])
Signed:   Pdf::loadView('finance.payment-bundles.poc', ['signatures' => ['coo' => $dataUri, ...]])
```

The template conditionally renders `<img src="{{ $signatures['slot'] }}">` or a `border-top` blank line based on whether the slot key exists and is non-null. This is idiomatic DomPDF and eliminates any dependency on PDF manipulation libraries.

## 3. Canonical Payload Hash (Section 4.2)

### 3.1 Purpose

The `canonical_payload_hash` is a tamper-evident fingerprint of the payment bundle's financial and authorization content. It is embedded in the PDF and in the database row. If any material field changes after signing begins, the hash changes — invalidating prior signatures.

### 3.2 Included Fields

```
bundle_number, bundle_version, source_type, source_id, source_hash,
payee_name, bank_last4, amount, currency, total_amount,
required_signature_slots (sorted list), assigned_signer_ids (sorted list),
ordered_items (each: source_type, source_id, source_hash, payee_name, bank_last4, amount, currency)
```

### 3.3 Rules (all MUST be upheld)

1. **Items sorted** by `source_type` ASC then `source_id` ASC before hashing — input order never changes the hash.
2. **Money as fixed-precision strings** — `number_format($v, 2, '.', '')` → `"1500000.00"`. Never raw floats.
3. **Top-level keys sorted recursively** — `ksort_recursive()` then `json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.
4. **No random values** — no timestamps, no UUIDs generated at hash time, no raw PDF bytes.
5. Any change to `amount`, `payee_name`, `bank_last4`, signer set, or items list changes the hash.

### 3.4 Implementation (spike helper, to be extracted in Phase 4)

See `App\Console\Commands\PaymentBundlePocCanonical::hash(array $payload): string`.

Phase 4 will extract this into `App\Services\Hris\CanonicalPayloadHashService` with full validation and unit test coverage.

## 4. FinanceAttachment Storage for Generated PDFs

### 4.1 Current Gap

`FinanceAttachmentService::store()` only accepts `UploadedFile`. DomPDF emits a raw byte string, not a file handle.

**Workaround for PoC:** write bytes directly to the `private` disk and create a `FinanceAttachment` row manually:
```php
$path = 'poc/' . $filename;
Storage::disk('private')->put($path, $bytes);
FinanceAttachment::create([
    'attachable_type' => get_class($user),
    'attachable_id'   => $user->id,
    'attachment_role' => 'payment_bundle_signed_pdf',
    'file_path'       => $path,
    'file_hash'       => hash('sha256', $bytes),
    'file_size'       => strlen($bytes),
    'mime_type'       => 'application/pdf',
    'original_filename' => $filename,
    'uploaded_by'     => $user->id,
    'uploaded_at'     => now(),
]);
```

### 4.2 Required Phase 5/9 Addition

`FinanceAttachmentService` MUST gain:
```php
public function storeGeneratedFile(
    Model $attachable,
    string $bytes,
    string $role,
    string $filename,
    string $mime,
    User $uploader
): FinanceAttachment
```
This writes bytes to disk, computes hash, detects duplicates, fires audit event — matching the existing `store()` contract.

### 4.3 Proposed New Role Constants

Add to `FinanceAttachment`:
```php
const ROLE_PAYMENT_BUNDLE_UNSIGNED_PDF   = 'payment_bundle_unsigned_pdf';
const ROLE_PAYMENT_BUNDLE_SIGNED_PDF     = 'payment_bundle_signed_pdf';
const ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE = 'payment_bundle_signature_image';
```

### 4.4 Audit Event Convention

Bundle events follow the dotted convention used by `FinanceAuditLogger`. Planned event types:
- `payment_bundle.created`, `payment_bundle.submitted`, `payment_bundle.cancelled`
- `payment_bundle_signature.requested`, `payment_bundle_signature.completed`, `payment_bundle_signature.rejected`
- `payment_bundle.pdf_generated` (unsigned), `payment_bundle.pdf_signed`
- `payment_bundle.paid`

## 5. PDF.js Preview Approach (Full Implementation: Phase 7)

The unsigned PDF is served from the private disk via a permission-gated controller endpoint (returns the raw bytes with `Content-Type: application/pdf`). The signer page embeds a PDF.js viewer:

1. **Bundle `pdfjs-dist`** (npm) or load from CDN with a stable pinned version (e.g. `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.x.x/`).
2. The viewer page fetches the PDF via an authenticated fetch (Bearer token or session cookie), passes the `ArrayBuffer` to `pdfjsLib.getDocument({ data })`.
3. Render each page into a `<canvas>` element; implement zoom + scroll navigation.
4. The signer must view 100% of the document (track render completion) before the "Sign" button activates.
5. On mobile (Expo WebView), the PDF stream can be rendered using `expo-pdf` / React Native PDF viewer with the same gated URL.

Full implementation in Phase 7 (Signature Capture page).

## 6. Phase 0 Spike Scope

This phase produces:
- This design document
- `PaymentBundlePocCommand` (spike artisan command) — not production code
- `resources/views/finance/payment-bundles/poc.blade.php` (PoC template)
- `PaymentBundlePocCanonical` helper (to be extracted in Phase 4)
- `tests/Feature/Hris/PaymentBundlePocTest.php` proving all acceptance criteria

Production services are built in Phases 4 (canonical hash), 5 (unsigned PDF), and 9 (signed PDF).
