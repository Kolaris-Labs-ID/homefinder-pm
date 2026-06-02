<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Payment Bundle {{ $bundle->bundle_number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f5; color: #18181b; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 2rem 1.25rem; }
        .card { background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
        h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
        .muted { color: #71717a; font-size: .875rem; }
        dt { font-weight: 600; font-size: .8rem; color: #52525b; margin-top: .75rem; }
        dd { margin: .15rem 0 0; }
        .hash { font-family: ui-monospace, monospace; font-size: .85rem; word-break: break-all;
                background: #fafafa; border: 1px dashed #d4d4d8; border-radius: 8px; padding: .75rem; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; border-radius: 999px;
                 padding: .15rem .65rem; font-size: .8rem; font-weight: 600; }
        .note { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px;
                padding: .75rem; font-size: .875rem; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px;
                       padding: .75rem; font-size: .875rem; margin-bottom: 1rem; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px;
                         padding: .75rem; font-size: .875rem; margin-bottom: 1rem; }
        #pdf-canvas-wrap { background: #e4e4e7; border-radius: 8px; padding: .5rem; text-align: center; min-height: 200px; }
        #pdf-canvas { max-width: 100%; border-radius: 4px; }
        #pdf-pages { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .5rem; }
        .page-btn { font-size: .8rem; padding: .2rem .6rem; border: 1px solid #d4d4d8; border-radius: 6px;
                    background: #fff; cursor: pointer; }
        .page-btn.active { background: #4338ca; color: #fff; border-color: #4338ca; }
        #sig-canvas { border: 2px dashed #a1a1aa; border-radius: 8px; width: 100%; cursor: crosshair; touch-action: none; }
        .btn { display: inline-block; padding: .5rem 1.25rem; border-radius: 8px; font-size: .95rem;
               font-weight: 600; cursor: pointer; border: none; }
        .btn-outline { background: #fff; border: 1px solid #d4d4d8; color: #18181b; }
        .btn-outline:hover { background: #f4f4f5; }
        .btn-primary { background: #4338ca; color: #fff; }
        .btn-primary:hover { background: #3730a3; }
        .btn-primary:disabled { background: #a1a1aa; cursor: not-allowed; }
        label.check-label { display: flex; align-items: flex-start; gap: .5rem; font-size: .9rem; cursor: pointer; }
        label.check-label input { margin-top: .15rem; width: 1rem; height: 1rem; flex-shrink: 0; }
        .actions { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="wrap">

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->has('signature_image'))
        <div class="alert-error">{{ $errors->first('signature_image') }}</div>
    @endif
    @if ($errors->has('confirmed'))
        <div class="alert-error">{{ $errors->first('confirmed') }}</div>
    @endif

    {{-- Bundle info --}}
    <div class="card">
        <h1>Sign Payment Bundle</h1>
        <p class="muted">{{ $bundle->bundle_number }} &middot; <span class="badge">{{ $signature->signature_slot->label() }}</span></p>

        <dl>
            <dt>Signer</dt>
            <dd>{{ $signature->assignedUser?->name ?? auth()->user()->name }}</dd>

            <dt>Bundle total</dt>
            <dd>{{ $bundle->currency }} {{ number_format((float) $bundle->total_amount, 2) }}</dd>

            <dt>Canonical payload hash (you are attesting to THIS)</dt>
            <dd><div class="hash">{{ $bundle->canonical_payload_hash }}</div></dd>
        </dl>
    </div>

    {{-- PDF Preview via PDF.js --}}
    <div class="card">
        @if ($canViewPdf)
            <p class="muted" style="margin:0 0 .5rem">Document preview</p>
            <div id="pdf-canvas-wrap">
                <canvas id="pdf-canvas"></canvas>
                <p id="pdf-loading" style="color:#71717a;font-size:.875rem;margin:.5rem 0">Loading PDF…</p>
                <p id="pdf-error" style="display:none;color:#991b1b;font-size:.875rem;margin:.5rem 0">
                    PDF preview unavailable. Verify the canonical hash above.</p>
            </div>
            <div id="pdf-pages"></div>
        @else
            <p class="note">PDF preview is shown to download-authorized roles only. Verify the canonical hash above.</p>
        @endif
    </div>

    {{-- Signature capture form --}}
    <div class="card">
        <h2 style="font-size:1.1rem;margin:0 0 .75rem">Draw your signature</h2>
        <p class="muted" style="margin:0 0 .75rem">Draw in the box below, then confirm and submit.</p>
        <canvas id="sig-canvas" width="680" height="200"></canvas>

        <form id="sig-form" method="POST" action="{{ request()->fullUrl() }}">
            @csrf
            <input type="hidden" name="signature_image" id="sig-data">

            <div class="actions" style="margin-top:.75rem">
                <button type="button" id="btn-clear" class="btn btn-outline">Clear</button>
            </div>

            <div style="margin-top:1rem">
                <label class="check-label">
                    <input type="checkbox" name="confirmed" id="confirmed" value="1">
                    I confirm I am signing this Final Payment Authorization as
                    <strong>{{ $signature->signature_slot->label() }}</strong>
                    for bundle <strong>{{ $bundle->bundle_number }}</strong>.
                </label>
            </div>

            <div class="actions">
                <button type="submit" id="btn-submit" class="btn btn-primary" disabled>Submit Signature</button>
            </div>
        </form>
    </div>

    {{-- Reject form --}}
    <div class="card" style="border-color:#fecaca;">
        <h2 style="font-size:1rem;margin:0 0 .5rem;color:#991b1b">Reject this bundle</h2>
        <p class="muted" style="margin:0 0 .75rem">If you believe this bundle should not be signed, provide a reason and reject it.</p>
        <form method="POST" action="{{ $rejectUrl }}">
            @csrf
            @if ($errors->has('reason'))
                <div class="alert-error" style="margin-bottom:.75rem">{{ $errors->first('reason') }}</div>
            @endif
            <textarea name="reason" rows="3" required placeholder="Rejection reason…"
                style="width:100%;box-sizing:border-box;border:1px solid #d4d4d8;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;resize:vertical;"></textarea>
            <div class="actions" style="margin-top:.75rem">
                <button type="submit" class="btn btn-outline" style="border-color:#fca5a5;color:#991b1b;">Reject Bundle</button>
            </div>
        </form>
    </div>

</div>

{{-- PDF.js (CDN) --}}
@if ($canViewPdf)
{{--
    modulepreload with integrity pre-fetches and verifies each module file before import() runs.
    This is the correct SRI mechanism for ES modules (integrity="" on import() calls is not supported).
--}}
<link rel="modulepreload"
      href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs"
      integrity="sha384-fzqD3KLclV6zyTSuToNuSG370CipiqN572vfT2zti7ORKnQPaJrvsz8deY+/Tgwe"
      crossorigin="anonymous">
<link rel="modulepreload"
      href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs"
      integrity="sha384-RTiLhGDoA0SEd0K0E9qrg2HwSEEnAo91MwUh79PteR4v01eEByhHOdEkFKxeaeqw"
      crossorigin="anonymous">
<script type="module">
    import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

    const pdfUrl = @json(route('finance.payment-bundles.unsigned-pdf', $bundle));
    const canvas = document.getElementById('pdf-canvas');
    const ctx = canvas.getContext('2d');
    const loading = document.getElementById('pdf-loading');
    const errEl = document.getElementById('pdf-error');
    const pagesBar = document.getElementById('pdf-pages');

    let pdfDoc = null;

    async function renderPage(num) {
        const page = await pdfDoc.getPage(num);
        const vp = page.getViewport({ scale: 1.5 });
        canvas.width = vp.width;
        canvas.height = vp.height;
        await page.render({ canvasContext: ctx, viewport: vp }).promise;

        document.querySelectorAll('.page-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`.page-btn[data-page="${num}"]`)?.classList.add('active');
    }

    try {
        pdfDoc = await pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise;
        loading.style.display = 'none';
        await renderPage(1);

        if (pdfDoc.numPages > 1) {
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const btn = document.createElement('button');
                btn.className = 'page-btn' + (i === 1 ? ' active' : '');
                btn.dataset.page = i;
                btn.textContent = `Page ${i}`;
                btn.addEventListener('click', () => renderPage(i));
                pagesBar.appendChild(btn);
            }
        }
    } catch (e) {
        loading.style.display = 'none';
        errEl.style.display = 'block';
        console.error('PDF.js error:', e);
    }
</script>
@endif

{{-- signature_pad (CDN) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/4.1.7/signature_pad.umd.min.js"
        integrity="sha384-dPowQo9uxJU703klzvnG+vzLHQDNmO/zREXw6BhCMupB54CE70wj6SWOGVPySK3s"
        crossorigin="anonymous"></script>
<script>
    (function () {
        const canvas = document.getElementById('sig-canvas');
        const sigData = document.getElementById('sig-data');
        const btnClear = document.getElementById('btn-clear');
        const btnSubmit = document.getElementById('btn-submit');
        const confirmed = document.getElementById('confirmed');
        const form = document.getElementById('sig-form');

        // Resize canvas to its CSS width
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            pad.clear();
        }

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)' });
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        function updateSubmit() {
            btnSubmit.disabled = pad.isEmpty() || !confirmed.checked;
        }

        pad.addEventListener('endStroke', updateSubmit);
        confirmed.addEventListener('change', updateSubmit);
        btnClear.addEventListener('click', function () { pad.clear(); updateSubmit(); });

        form.addEventListener('submit', function (e) {
            if (pad.isEmpty()) { e.preventDefault(); return; }
            sigData.value = pad.toDataURL('image/png');
        });
    })();
</script>
</body>
</html>
