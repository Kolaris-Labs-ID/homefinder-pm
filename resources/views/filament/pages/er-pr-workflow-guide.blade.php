<x-filament-panels::page>
    {{-- Intro --}}
    <x-filament::section>
        <x-slot name="heading">📘 Panduan Alur Kerja ER / PR</x-slot>
        <x-slot name="description">Referensi lengkap modul Expense Request, Payment Request & Payment Bundle di PartnersHub HRIS.</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Dokumen ini menjelaskan alur kerja lengkap modul Expense Request (ER), Payment Request (PR), dan Payment Bundle di sistem PartnersHub HRIS.
            Gunakan panduan ini sebagai referensi saat memproses pengajuan biaya, membuat payment request, atau mengelola payment bundle.
        </p>
    </x-filament::section>

    {{-- Table of Contents --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">📑 Daftar Isi</x-slot>
        <nav class="columns-1 gap-4 text-sm md:columns-2">
            <ol class="list-decimal space-y-2 pl-6">
                <li><a href="#ringkasan" class="text-primary-600 hover:underline dark:text-primary-400">Ringkasan Arsitektur</a></li>
                <li><a href="#alur-utama" class="text-primary-600 hover:underline dark:text-primary-400">Alur Kerja Utama (End-to-End)</a></li>
                <li><a href="#er" class="text-primary-600 hover:underline dark:text-primary-400">Expense Request (ER) — Pengajuan Biaya</a></li>
                <li><a href="#pr" class="text-primary-600 hover:underline dark:text-primary-400">Payment Request (PR) — Permintaan Pembayaran</a></li>
                <li><a href="#konversi" class="text-primary-600 hover:underline dark:text-primary-400">Konversi ER → PR</a></li>
                <li><a href="#bundle" class="text-primary-600 hover:underline dark:text-primary-400">Payment Bundle — Otorisasi Pembayaran Final</a></li>
                <li><a href="#signing" class="text-primary-600 hover:underline dark:text-primary-400">Alur Tanda Tangan (Signing Workflow)</a></li>
                <li><a href="#bukti" class="text-primary-600 hover:underline dark:text-primary-400">Bukti Pembayaran & Penutupan</a></li>
                <li><a href="#roles" class="text-primary-600 hover:underline dark:text-primary-400">Peran & Hak Akses</a></li>
                <li><a href="#aturan" class="text-primary-600 hover:underline dark:text-primary-400">Aturan Bisnis Penting</a></li>
                <li><a href="#faq" class="text-primary-600 hover:underline dark:text-primary-400">FAQ — Pertanyaan Umum</a></li>
            </ol>
        </nav>
    </x-filament::section>

    {{-- 1. Ringkasan Arsitektur --}}
    <x-filament::section id="ringkasan" collapsible>
        <x-slot name="heading">1. Ringkasan Arsitektur</x-slot>
        <div class="space-y-4 text-sm">
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Komponen</th>
                            <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        <tr><td class="px-4 py-2 font-medium">ExpenseRequest (ER)</td><td class="px-4 py-2">Pengajuan biaya oleh karyawan</td></tr>
                        <tr><td class="px-4 py-2 font-medium">PaymentRequest (PR)</td><td class="px-4 py-2">Persetujuan pengeluaran oleh Finance</td></tr>
                        <tr><td class="px-4 py-2 font-medium">PaymentBundle</td><td class="px-4 py-2">Dokumen otorisasi pembayaran final</td></tr>
                        <tr><td class="px-4 py-2 font-medium">PaymentBundleItem</td><td class="px-4 py-2">Item snapshot yang sudah dibekukan</td></tr>
                        <tr><td class="px-4 py-2 font-medium">PaymentBundleSignature</td><td class="px-4 py-2">Slot tanda tangan yang diperlukan</td></tr>
                        <tr><td class="px-4 py-2 font-medium">FinanceAttachment</td><td class="px-4 py-2">Penyimpanan file aman (bukti, PDF)</td></tr>
                        <tr><td class="px-4 py-2 font-medium">FinanceAuditLog</td><td class="px-4 py-2">Jejak audit append-only</td></tr>
                    </tbody>
                </table>
            </div>

            <h4 class="font-semibold text-gray-900 dark:text-white">Prinsip Dasar</h4>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Prinsip</th>
                            <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        <tr><td class="px-4 py-2 font-medium">Pemisahan ER & PR</td><td class="px-4 py-2">ER adalah pengajuan karyawan, PR adalah kontrol keuangan. Dua model terpisah.</td></tr>
                        <tr><td class="px-4 py-2 font-medium">Bukti wajib</td><td class="px-4 py-2">ER tidak bisa disubmit tanpa lampiran bukti (evidence).</td></tr>
                        <tr><td class="px-4 py-2 font-medium">Snapshot dibekukan</td><td class="px-4 py-2">Setelah masuk Payment Bundle, data PR tidak dibaca ulang dari sumber asli.</td></tr>
                        <tr><td class="px-4 py-2 font-medium">Enkripsi rekening</td><td class="px-4 py-2">Nomor rekening bank dienkripsi (Laravel encrypted cast). Hanya 4 digit terakhir yang tampil.</td></tr>
                        <tr><td class="px-4 py-2 font-medium">Audit lengkap</td><td class="px-4 py-2">Setiap perubahan status, data sensitif, dan file dicatat di <code class="rounded bg-gray-100 px-1 dark:bg-white/10">finance_audit_logs</code>.</td></tr>
                        <tr><td class="px-4 py-2 font-medium">Pembayaran manual</td><td class="px-4 py-2">Sistem tidak memiliki gateway pembayaran. Finance membayar di luar sistem, lalu upload bukti.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>

    {{-- 2. Alur Kerja Utama --}}
    <x-filament::section id="alur-utama" collapsible>
        <x-slot name="heading">2. Alur Kerja Utama (End-to-End)</x-slot>
        <div class="space-y-4 text-sm">
            {{-- Visual step-by-step flow --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {{-- Step 1 --}}
                <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                        <h4 class="font-bold text-blue-800 dark:text-blue-300">Pengajuan (Karyawan)</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Karyawan membuat Expense Request (ER)</li>
                        <li>Upload bukti (struk, invoice, dll.)</li>
                        <li>Submit ER → status **Submitted (Diajukan)**</li>
                    </ul>
                </div>

                {{-- Step 2 --}}
                <div class="rounded-xl border-2 border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">2</span>
                        <h4 class="font-bold text-amber-800 dark:text-amber-300">Review Finance</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Finance mereview ER yang masuk</li>
                        <li>Opsi: <strong>Verifikasi</strong>, <strong>Minta Revisi</strong>, atau <strong>Tolak</strong></li>
                        <li>ER terverifikasi → siap dikonversi ke PR</li>
                    </ul>
                </div>

                {{-- Step 3 --}}
                <div class="rounded-xl border-2 border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">3</span>
                        <h4 class="font-bold text-indigo-800 dark:text-indigo-300">Konversi ER → PR</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Finance membuat Payment Request dari ER</li>
                        <li>Atau PR manual tanpa ER (operasional)</li>
                        <li>Data default disalin, bisa di-adjust</li>
                    </ul>
                </div>

                {{-- Step 4 --}}
                <div class="rounded-xl border-2 border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">4</span>
                        <h4 class="font-bold text-purple-800 dark:text-purple-300">Approval PR</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Submit PR ke Executive yang ditugaskan</li>
                        <li>Executive approve atau reject</li>
                        <li>Snapshot persetujuan disimpan & dibekukan</li>
                    </ul>
                </div>

                {{-- Step 5 --}}
                <div class="rounded-xl border-2 border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-teal-600 text-xs font-bold text-white">5</span>
                        <h4 class="font-bold text-teal-800 dark:text-teal-300">Payment Bundle & Tanda Tangan</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>PR dikelompokkan ke Payment Bundle</li>
                        <li>Tanda tangan berurutan: Finance 1 → Finance 2 → Executive</li>
                        <li>Bundle diotorisasi untuk pembayaran</li>
                    </ul>
                </div>

                {{-- Step 6 --}}
                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/50">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">6</span>
                        <h4 class="font-bold text-emerald-800 dark:text-emerald-300">Pembayaran & Penutupan</h4>
                    </div>
                    <ul class="list-disc space-y-2 pl-6 text-gray-700 dark:text-gray-300">
                        <li>Finance bayar manual di luar sistem</li>
                        <li>Upload bukti pembayaran ke sistem</li>
                        <li>Bundle ditandai **Paid (Dibayar)**</li>
                    </ul>
                </div>
            </div>

            {{-- Arrow flow diagram --}}
            <div class="flex items-center justify-center gap-1 overflow-x-auto py-2 text-xs font-semibold">
                <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-800 dark:bg-blue-900 dark:text-blue-200">ER Draft</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Submit</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Review</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Verified</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">PR</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-purple-100 px-3 py-1 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Executive OK</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-teal-100 px-3 py-1 text-teal-800 dark:bg-teal-900 dark:text-teal-200">Bundle</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-teal-100 px-3 py-1 text-teal-800 dark:bg-teal-900 dark:text-teal-200">Sign</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Bayar</span>
                <span class="text-gray-400">→</span>
                <span class="rounded-full bg-emerald-600 px-3 py-1 text-white">PAID ✅</span>
            </div>
        </div>
    </x-filament::section>

    {{-- 3. Expense Request (ER) --}}
    <x-filament::section id="er" collapsible>
        <x-slot name="heading">3. Expense Request (ER) — Pengajuan Biaya</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 3.1 Apa itu ER? --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">3.1 Apa itu ER?</h4>
                <p class="text-gray-600 dark:text-gray-400">
                    Expense Request (ER) adalah formulir pengajuan biaya yang dibuat oleh <strong>karyawan</strong>. ER merupakan tahap pertama dalam proses pengeluaran uang perusahaan.
                </p>
            </div>

            {{-- 3.2 Mode Pembayaran --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">3.2 Mode Pembayaran</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Mode</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                                <th class="px-4 py-2 text-left font-semibold">Lanjut ke PR?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr>
                                <td class="px-4 py-2 font-medium">Reimbursement Karyawan</td>
                                <td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">employee_reimbursement</code></td>
                                <td class="px-4 py-2">Karyawan sudah bayar duluan, minta ganti</td>
                                <td class="px-4 py-2 text-center"><x-filament::badge color="success">✅ Ya</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">Pembayaran Vendor</td>
                                <td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">vendor_payment</code></td>
                                <td class="px-4 py-2">Perusahaan bayar ke pihak ketiga</td>
                                <td class="px-4 py-2 text-center"><x-filament::badge color="success">✅ Ya</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">Catatan Saja</td>
                                <td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">company_paid_record_only</code></td>
                                <td class="px-4 py-2">Sudah dibayar perusahaan, hanya pencatatan</td>
                                <td class="px-4 py-2 text-center"><x-filament::badge color="danger">❌ Tidak</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">Lainnya</td>
                                <td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">other</code></td>
                                <td class="px-4 py-2">Kategori khusus</td>
                                <td class="px-4 py-2 text-center"><x-filament::badge color="success">✅ Ya</x-filament::badge></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                    <strong>⚠️ Penting:</strong> Mode <code class="rounded bg-amber-100 px-1 dark:bg-amber-900">company_paid_record_only</code> tidak pernah membuat Payment Request. Hanya untuk pencatatan internal.
                </div>
            </div>

            {{-- 3.3 Status ER --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">3.3 Status ER</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Status</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode Sistem (Tech)</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                                <th class="px-4 py-2 text-left font-semibold">Aksi Selanjutnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="gray">Draft</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">draft</code></td>
                                <td class="px-4 py-2">Karyawan belum mengirimkan pengajuan</td>
                                <td class="px-4 py-2">Submit (wajib ada bukti)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="info">Submitted (Diajukan)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">submitted</code></td>
                                <td class="px-4 py-2">Menunggu review dari Finance</td>
                                <td class="px-4 py-2">Finance mulai review</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="warning">Under Finance Review (Sedang Direview)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">under_finance_review</code></td>
                                <td class="px-4 py-2">Sedang direview oleh Finance</td>
                                <td class="px-4 py-2">Verifikasi / Revisi / Tolak</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="success">Verified (Terverifikasi)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">verified</code></td>
                                <td class="px-4 py-2">Terverifikasi oleh Finance</td>
                                <td class="px-4 py-2">Buat PR dari ER ini</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="warning">Needs Revision (Butuh Revisi)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">needs_revision</code></td>
                                <td class="px-4 py-2">Karyawan harus merevisi pengajuan</td>
                                <td class="px-4 py-2">Karyawan revisi → submit ulang</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="danger">Rejected (Ditolak)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">rejected</code></td>
                                <td class="px-4 py-2">Ditolak oleh Finance</td>
                                <td class="px-4 py-2">—</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="primary">PR Created (PR Dibuat)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">pr_created</code></td>
                                <td class="px-4 py-2">PR sudah dibuat dari ER ini</td>
                                <td class="px-4 py-2">Proses PR di tahap berikutnya</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="gray">Record Only Closed (Selesai Pencatatan)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">record_only_closed</code></td>
                                <td class="px-4 py-2">Khusus mode company_paid (Hanya Pencatatan)</td>
                                <td class="px-4 py-2">—</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="gray">Cancelled (Dibatalkan)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">cancelled</code></td>
                                <td class="px-4 py-2">ER dibatalkan</td>
                                <td class="px-4 py-2">—</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2"><x-filament::badge color="success">Closed (Selesai)</x-filament::badge></td>
                                <td class="px-4 py-2"><code class="text-xs">closed</code></td>
                                <td class="px-4 py-2">ER selesai (PR sudah dibayar)</td>
                                <td class="px-4 py-2">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 3.4 Aturan ER --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">3.4 Aturan ER</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Aturan</th>
                                <th class="px-4 py-2 text-left font-semibold">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2 font-medium">Bukti wajib</td><td class="px-4 py-2">ER tidak bisa di-submit tanpa upload bukti (<code class="rounded bg-gray-100 px-1 dark:bg-white/10">expense_evidence</code>).</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Revisi reset review</td><td class="px-4 py-2">Jika karyawan merevisi ER, review Finance di-reset dan harus direview ulang.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Perubahan sensitif</td><td class="px-4 py-2">Mengubah <code class="rounded bg-gray-100 px-1 dark:bg-white/10">amount</code>, <code class="rounded bg-gray-100 px-1 dark:bg-white/10">payee_name</code>, <code class="rounded bg-gray-100 px-1 dark:bg-white/10">payee_bank_name</code>, atau <code class="rounded bg-gray-100 px-1 dark:bg-white/10">payee_bank_account_number</code> setelah review akan me-reset verifikasi Finance.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Idempoten</td><td class="px-4 py-2">Submit ER yang sama dua kali (berdasarkan <code class="rounded bg-gray-100 px-1 dark:bg-white/10">client_request_id</code>) tidak membuat duplikat.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Nomor otomatis</td><td class="px-4 py-2">Format: <code class="rounded bg-gray-100 px-1 dark:bg-white/10">ER-YYYYMM-XXXX</code> (contoh: ER-202606-0001).</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 3.5 Alur ER di Mobile --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">3.5 Alur ER di Mobile (ESS)</h4>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <ol class="list-decimal space-y-3 pl-6 text-gray-700 dark:text-gray-300">
                        <li class="pl-1">Karyawan buka app → Menu "Pengajuan Biaya" / "Expense Request"</li>
                        <li class="pl-1">Isi formulir:
                            <ul class="list-disc space-y-1.5 pl-6 mt-2 text-gray-600 dark:text-gray-400">
                                <li>Kategori, Judul & Deskripsi</li>
                                <li>Jumlah (Rp), Tanggal pengeluaran</li>
                                <li>Mode pembayaran</li>
                                <li>Nama penerima (payee)</li>
                                <li>Bank & no. rekening (jika reimbursement/vendor)</li>
                                <li>Departemen & Proyek (opsional)</li>
                            </ul>
                        </li>
                        <li class="pl-1">Upload bukti (foto/scan struk, invoice, dll.)</li>
                        <li class="pl-1">Simpan sebagai Draft atau langsung Submit</li>
                    </ol>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 4. Payment Request (PR) --}}
    <x-filament::section id="pr" collapsible>
        <x-slot name="heading">4. Payment Request (PR) — Permintaan Pembayaran</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 4.1 Apa itu PR? --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">4.1 Apa itu PR?</h4>
                <p class="mb-3 text-gray-600 dark:text-gray-400">
                    Payment Request (PR) adalah dokumen pengeluaran yang <strong>dikendalikan oleh Finance</strong>. PR bisa berasal dari berbagai sumber:
                </p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Sumber</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode <code>source_type</code></th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2 font-medium">Expense Request karyawan</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">expense_request</code></td><td class="px-4 py-2">ER yang sudah diverifikasi Finance</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Manual oleh Finance</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">manual</code></td><td class="px-4 py-2">Pembayaran operasional (domain, hosting, transport, dll.)</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Tunjangan Mingguan</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">weekly_allowance_batch</code></td><td class="px-4 py-2">Tunjangan mingguan karyawan</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Batch Payroll</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">payroll_batch</code></td><td class="px-4 py-2">Gaji karyawan (future)</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Lembur</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">overtime_pay</code></td><td class="px-4 py-2">Pembayaran lembur (future)</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Kompensasi Keterlambatan</td><td class="px-4 py-2"><code class="rounded bg-gray-100 px-1 dark:bg-white/10">late_pay_compensation</code></td><td class="px-4 py-2">Kompensasi telat bayar (future)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 4.2 Status PR --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">4.2 Status PR</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Status</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode Sistem (Tech)</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2"><x-filament::badge color="gray">Draft</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">draft</code></td><td class="px-4 py-2">Finance sedang menyusun draft PR</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="info">Submitted for Approval (Diajukan untuk Approval)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">submitted_to_coo</code></td><td class="px-4 py-2">Menunggu persetujuan dari Executive yang ditugaskan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="success">Approved (Disetujui Executive)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">approved_by_coo</code></td><td class="px-4 py-2">Disetujui oleh Executive yang ditugaskan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="danger">Rejected (Ditolak Executive)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">rejected_by_coo</code></td><td class="px-4 py-2">Ditolak oleh Executive yang ditugaskan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Needs Clarification (Butuh Klarifikasi)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">needs_clarification</code></td><td class="px-4 py-2">Executive meminta klarifikasi atau informasi tambahan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="primary">Approved for Bundle (Siap Masuk Bundle)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">approved_for_bundle</code></td><td class="px-4 py-2">Siap untuk dimasukkan ke dalam Payment Bundle</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="info">In Payment Bundle (Dalam Bundle)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">in_payment_bundle</code></td><td class="px-4 py-2">Sudah masuk di dalam salah satu bundle pembayaran</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Scheduled for Payment (Dijadwalkan Dibayar)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">scheduled_for_payment</code></td><td class="px-4 py-2">Dijadwalkan untuk dibayarkan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="success">Paid (Dibayar)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">paid</code></td><td class="px-4 py-2">Pembayaran selesai dilakukan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Paid Pending Reconciliation (Menunggu Rekonsiliasi)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">paid_pending_reconciliation</code></td><td class="px-4 py-2">Sudah dibayar, menunggu pencocokan bank (rekonsiliasi)</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="success">Reconciled (Terekonsiliasi)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">reconciled</code></td><td class="px-4 py-2">Proses rekonsiliasi transaksi selesai</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="gray">Voided / Reversed / Cancelled (Batal/Void)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">voided</code> / <code class="text-xs">reversed</code> / <code class="text-xs">cancelled</code></td><td class="px-4 py-2">PR dibatalkan, di-void, atau di-reverse</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 4.3 Approval Snapshot --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">4.3 Approval Snapshot (Snapshot Persetujuan)</h4>
                <p class="mb-2 text-gray-600 dark:text-gray-400">
                    Saat Executive menyetujui PR, sistem membuat <strong>snapshot data bisnis yang dibekukan</strong>:
                </p>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="grid grid-cols-1 gap-1 text-xs md:grid-cols-2">
                        <div class="flex items-center gap-2"><span class="text-gray-500">📎</span> payment_request_number</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">📎</span> source_type & source_id</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">💰</span> amount & currency</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">👤</span> payee_name</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">🏦</span> bank_name & bank_account_last4</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">📄</span> attachment_hashes</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">📝</span> finance_notes & withholding info</div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">🔒</span> approval_snapshot_hash (SHA-256)</div>
                    </div>
                </div>
                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                    <strong>⚠️ Setelah disetujui:</strong> Mengubah jumlah, penerima, bank, atau bukti akan <strong>membatalkan persetujuan</strong> dan membutuhkan persetujuan ulang dari Executive.
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 5. Konversi ER → PR --}}
    <x-filament::section id="konversi" collapsible>
        <x-slot name="heading">5. Konversi ER → PR</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 5.1 Syarat --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">5.1 Syarat Konversi</h4>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950/50">
                        <span class="text-green-600">✅</span>
                        <span class="text-green-800 dark:text-green-300">ER harus berstatus **Verified (Terverifikasi)** (kode: <code class="text-xs">verified</code>)</span>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/50">
                        <span class="text-red-600">❌</span>
                        <span class="text-red-800 dark:text-red-300">ER bukan mode **Catatan Saja (Record Only)** (kode: <code class="text-xs">company_paid_record_only</code>)</span>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/50">
                        <span class="text-blue-600">👤</span>
                        <span class="text-blue-800 dark:text-blue-300">Pembuat PR harus memiliki role **Finance Creator** (kode: <code class="text-xs">finance_creator</code>)</span>
                    </div>
                </div>
            </div>

            {{-- 5.2 Proses --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">5.2 Proses Konversi</h4>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <ol class="list-decimal space-y-2.5 pl-6 text-gray-700 dark:text-gray-300">
                        <li class="pl-1">Finance klik <strong>"Buat Payment Request"</strong> pada ER yang terverifikasi</li>
                        <li class="pl-1">Sistem kunci baris ER (lock row)</li>
                        <li class="pl-1">Cek belum ada PR aktif dari ER ini</li>
                        <li class="pl-1">Salin data default: jumlah, payee, bank</li>
                        <li class="pl-1">Finance bisa adjust jumlah (perlu justifikasi jika berbeda)</li>
                        <li class="pl-1">Buat PR dalam transaksi DB</li>
                        <li class="pl-1">Ubah status ER → **PR Created (PR Dibuat)** (kode: <code class="text-xs">pr_created</code>)</li>
                        <li class="pl-1">Catat di audit log</li>
                    </ol>
                </div>
            </div>

            {{-- 5.3 Aturan Integritas --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">5.3 Aturan Integritas</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Aturan</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2 font-medium">Satu ER = Satu PR aktif</td><td class="px-4 py-2">Tidak bisa membuat dua PR dari satu ER.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Klik ganda aman</td><td class="px-4 py-2">Double-click pada tombol buat PR hanya membuat satu PR (idempoten).</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Variance tercatat</td><td class="px-4 py-2">Jika jumlah PR berbeda dari ER, perbedaan dicatat dan di-audit.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Threshold risiko</td><td class="px-4 py-2">Perbedaan besar memunculkan flag risiko.</td></tr>
                            <tr><td class="px-4 py-2 font-medium">PR ditolak → ER kembali</td><td class="px-4 py-2">Jika PR ditolak atau dibatalkan, ER kembali ke <code class="rounded bg-gray-100 px-1 dark:bg-white/10">finance_verified</code> untuk diproses ulang.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 6. Payment Bundle --}}
    <x-filament::section id="bundle" collapsible>
        <x-slot name="heading">6. Payment Bundle — Otorisasi Pembayaran Final</x-slot>
        <div class="space-y-6 text-sm">
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">6.1 Apa itu Payment Bundle?</h4>
                <p class="text-gray-600 dark:text-gray-400">
                    Payment Bundle adalah <strong>dokumen otorisasi pembayaran final</strong> yang mengelompokkan beberapa PR yang sudah disetujui menjadi satu paket pembayaran. Bundle ini harus ditandatangani secara berurutan sebelum Finance boleh melakukan pembayaran.
                </p>
            </div>

            {{-- 6.2 Sumber Bundle --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">6.2 Sumber Bundle (MVP)</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Sumber</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                                <th class="px-4 py-2 text-left font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr>
                                <td class="px-4 py-2 font-medium">payment_request</td>
                                <td class="px-4 py-2">PR yang sudah disetujui Executive</td>
                                <td class="px-4 py-2"><x-filament::badge color="success">Aktif</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">weekly_allowance_batch</td>
                                <td class="px-4 py-2">Batch tunjangan mingguan</td>
                                <td class="px-4 py-2"><x-filament::badge color="success">Aktif</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">payroll_batch</td>
                                <td class="px-4 py-2">Gaji karyawan</td>
                                <td class="px-4 py-2"><x-filament::badge color="gray">Belum aktif</x-filament::badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">overtime_pay_batch</td>
                                <td class="px-4 py-2">Lembur</td>
                                <td class="px-4 py-2"><x-filament::badge color="gray">Belum aktif</x-filament::badge></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 6.3 Status Bundle --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">6.3 Status Bundle</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Status</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode Sistem (Tech)</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2"><x-filament::badge color="gray">Draft</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">draft</code></td><td class="px-4 py-2">Finance sedang menyusun item dalam bundle</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="info">Submitted for Signature (Diajukan Tanda Tangan)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">submitted_for_signature</code></td><td class="px-4 py-2">Disubmit untuk memulai alur tanda tangan</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Pending Finance Signature 1 (Menunggu Finance 1)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">pending_finance_signature_1</code></td><td class="px-4 py-2">Menunggu tanda tangan digital dari Finance Signer 1</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Pending Finance Signature 2 (Menunggu Finance 2)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">pending_finance_signature_2</code></td><td class="px-4 py-2">Menunggu tanda tangan digital dari Finance Signer 2</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="warning">Pending Executive Signature (Menunggu Executive)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">pending_executive_signature</code></td><td class="px-4 py-2">Menunggu tanda tangan digital dari Executive Signer (CEO/CFO)</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="success">Authorized for Payment (Siap Dibayarkan)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">authorized_for_payment</code></td><td class="px-4 py-2">Tanda tangan lengkap, siap dibayarkan secara manual</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="info">Payment Recording (Proses Pencatatan)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">payment_recording</code></td><td class="px-4 py-2">Sedang dalam proses pencatatan bukti pembayaran</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="success">Paid (Selesai Dibayar)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">paid</code></td><td class="px-4 py-2">Selesai dibayar dan bukti pembayaran sudah diverifikasi</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="danger">Payment Proof Rejected (Bukti Pembayaran Ditolak)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">payment_proof_rejected</code></td><td class="px-4 py-2">Bukti pembayaran yang di-upload ditolak oleh reviewer</td></tr>
                            <tr><td class="px-4 py-2"><x-filament::badge color="gray">Rejected / Voided / Cancelled (Batal/Void)</x-filament::badge></td><td class="px-4 py-2"><code class="text-xs">rejected</code> / <code class="text-xs">voided</code> / <code class="text-xs">cancelled</code></td><td class="px-4 py-2">Bundle dibatalkan, ditolak, atau di-void</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 6.4 Canonical Payload Hash --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">6.4 Canonical Payload Hash</h4>
                <p class="mb-2 text-gray-600 dark:text-gray-400">Setiap bundle memiliki <strong>hash payload kanonik</strong> yang menjadi identitas dokumen:</p>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="grid grid-cols-1 gap-1 text-xs md:grid-cols-2">
                        <div>📦 bundle_number & bundle_version</div>
                        <div>📋 ordered_items (source_type, source_id, source_hash, payee, bank_last4, amount, currency)</div>
                        <div>💰 total_amount</div>
                        <div>✍️ required_signature_slots & assigned_signer_ids</div>
                    </div>
                </div>
                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                    <strong>⚠️ Aturan hash:</strong> Mengubah jumlah, penerima, bank, daftar item, atau penandatangan akan mengubah hash dan membutuhkan tanda tangan ulang.
                </div>
            </div>

            {{-- 6.5 Snapshot Dibekukan --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">6.5 Snapshot Dibekukan</h4>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/50">
                    <p class="mb-2 font-medium text-blue-800 dark:text-blue-300">PaymentBundleItem.source_snapshot_json</p>
                    <ul class="list-disc space-y-2 pl-6 text-blue-700 dark:text-blue-400">
                        <li>Data PR yang dibekukan saat masuk bundle</li>
                        <li>Digunakan untuk render PDF</li>
                        <li>Digunakan untuk eksekusi pembayaran</li>
                        <li>Perubahan pada PR asli <strong>TIDAK</strong> mempengaruhi bundle</li>
                    </ul>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 7. Alur Tanda Tangan --}}
    <x-filament::section id="signing" collapsible>
        <x-slot name="heading">7. Alur Tanda Tangan (Signing Workflow)</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 7.1 Urutan --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">7.1 Urutan Tanda Tangan</h4>
                <p class="mb-3 text-gray-600 dark:text-gray-400">Tanda tangan dilakukan <strong>secara berurutan</strong> (sequential):</p>
                <div class="flex flex-col items-center gap-2 sm:flex-row sm:gap-4">
                    <div class="flex items-center gap-2 rounded-lg border-2 border-blue-300 bg-blue-50 px-4 py-3 dark:border-blue-700 dark:bg-blue-950/50">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">1</span>
                        <div>
                            <p class="font-semibold text-blue-800 dark:text-blue-300">Finance Signer 1</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400">Petugas Finance pertama</p>
                        </div>
                    </div>
                    <span class="hidden text-2xl text-gray-400 sm:block">→</span>
                    <span class="text-2xl text-gray-400 sm:hidden">↓</span>
                    <div class="flex items-center gap-2 rounded-lg border-2 border-purple-300 bg-purple-50 px-4 py-3 dark:border-purple-700 dark:bg-purple-950/50">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-600 text-sm font-bold text-white">2</span>
                        <div>
                            <p class="font-semibold text-purple-800 dark:text-purple-300">Finance Signer 2</p>
                            <p class="text-xs text-purple-600 dark:text-purple-400">Petugas Finance kedua (orang berbeda!)</p>
                        </div>
                    </div>
                    <span class="hidden text-2xl text-gray-400 sm:block">→</span>
                    <span class="text-2xl text-gray-400 sm:hidden">↓</span>
                    <div class="flex items-center gap-2 rounded-lg border-2 border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-700 dark:bg-amber-950/50">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-600 text-sm font-bold text-white">3</span>
                        <div>
                            <p class="font-semibold text-amber-800 dark:text-amber-300">Executive Signer</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400">CEO / CFO / Executive</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 7.2 Segregation of Duties --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">7.2 Aturan Pemisahan Tugas (Segregation of Duties)</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Aturan</th>
                                <th class="px-4 py-2 text-left font-semibold">Penjelasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2 font-medium">Finance Signer 1 ≠ Finance Signer 2</td><td class="px-4 py-2">Harus orang yang berbeda</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Executive Signer ≠ Finance Signer 1/2</td><td class="px-4 py-2">Tidak boleh orang yang sama</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Bundle Creator ≠ Executive Signer</td><td class="px-4 py-2">Untuk bundle bernilai tinggi (>Rp10.000.000)</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Finance Executor ≠ Finance Signer</td><td class="px-4 py-2">Untuk bundle bernilai tinggi</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300">
                    <strong>ℹ️ Ambang batas nilai tinggi:</strong> Rp10.000.000 (dapat dikonfigurasi di Payment Authorization Settings).
                </div>
            </div>

            {{-- 7.3 Proses Tanda Tangan --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">7.3 Proses Tanda Tangan</h4>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <ol class="list-decimal space-y-2.5 pl-6 text-gray-700 dark:text-gray-300">
                        <li class="pl-1">Finance submit bundle untuk tanda tangan</li>
                        <li class="pl-1">Sistem generate link tanda tangan (signed URL) untuk Finance Signer 1</li>
                        <li class="pl-1">Finance Signer 1 menerima notifikasi</li>
                        <li class="pl-1">Buka link → Preview PDF + Canonical Hash → Tanda tangan di canvas</li>
                        <li class="pl-1">Setelah Finance Signer 1 selesai → Link untuk Finance Signer 2</li>
                        <li class="pl-1">Setelah Finance Signer 2 selesai → Link untuk Executive Signer</li>
                        <li class="pl-1">Setelah Executive menandatangani → Bundle berstatus **Authorized for Payment (Siap Dibayarkan)** (kode: <code class="text-xs">authorized_for_payment</code>)</li>
                        <li class="pl-1">Sistem generate PDF bertanda tangan (signed PDF)</li>
                    </ol>
                </div>
            </div>

            {{-- 7.4 Keamanan Link --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">7.4 Keamanan Link Tanda Tangan</h4>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                        <h5 class="mb-2 font-medium">Setiap link memiliki:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-xs text-gray-600 dark:text-gray-400">
                            <li><code>signature_token_hash</code> — Token unik</li>
                            <li><code>token_expires_at</code> — Waktu kedaluwarsa</li>
                            <li><code>token_used_at</code> — Kapan digunakan</li>
                            <li><code>token_revoked_at</code> — Kapan dicabut</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                        <h5 class="mb-2 font-medium">Aturan:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-xs text-gray-600 dark:text-gray-400">
                            <li>Preview PDF <strong>TIDAK</strong> menghabiskan token</li>
                            <li>Submit tanda tangan <strong>MENGHABISKAN</strong> token (sekali pakai)</li>
                            <li>Token kedaluwarsa → ditolak</li>
                            <li>Token dicabut → ditolak</li>
                            <li>User salah → ditolak</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- 7.5 Perubahan Setelah Tanda Tangan --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">7.5 Perubahan Setelah Tanda Tangan</h4>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/50">
                    <p class="mb-2 font-medium text-red-800 dark:text-red-300">Jika ada perubahan data bundle setelah tanda tangan pertama:</p>
                    <ol class="list-decimal space-y-2 pl-6 text-red-700 dark:text-red-400">
                        <li class="pl-1">Semua tanda tangan yang sudah ada → di-void</li>
                        <li class="pl-1">PDF lama tetap disimpan untuk audit</li>
                        <li class="pl-1">Bundle kembali ke draft/submitted_for_signature</li>
                        <li class="pl-1">Hash payload baru digenerate</li>
                        <li class="pl-1">PDF unsigned baru digenerate</li>
                        <li class="pl-1">Semua tanda tangan harus dikumpulkan ulang</li>
                    </ol>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 8. Bukti Pembayaran & Penutupan --}}
    <x-filament::section id="bukti" collapsible>
        <x-slot name="heading">8. Bukti Pembayaran & Penutupan</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 8.1 Alur Pembayaran --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">8.1 Alur Pembayaran</h4>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <ol class="list-decimal space-y-2.5 pl-6 text-gray-700 dark:text-gray-300">
                        <li class="pl-1">Bundle berstatus **Authorized for Payment (Siap Dibayarkan)** (kode: <code class="text-xs">authorized_for_payment</code>)</li>
                        <li class="pl-1">Finance membayar secara <strong>MANUAL</strong> di luar sistem (transfer bank, cash, dll.)</li>
                        <li class="pl-1">Finance masuk ke sistem → Upload bukti pembayaran (struk transfer, screenshot, dll.)</li>
                        <li class="pl-1">Isi referensi pembayaran & catatan pembayaran</li>
                        <li class="pl-1">Bundle berubah ke **Payment Recording (Pencatatan Pembayaran)** → **Paid (Selesai Dibayar)**</li>
                        <li class="pl-1">PR yang terkait berubah ke **Paid (Dibayar)**</li>
                    </ol>
                </div>
            </div>

            {{-- 8.2 Penolakan Bukti --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">8.2 Penolakan Bukti Pembayaran</h4>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/50">
                    <p class="mb-2 text-amber-800 dark:text-amber-300">Jika bukti pembayaran tidak valid/tidak lengkap:</p>
                    <ul class="list-disc space-y-2 pl-6 text-amber-700 dark:text-amber-400">
                        <li>Reviewer menolak bukti → status **Payment Proof Rejected (Bukti Ditolak)** (kode: <code class="text-xs">payment_proof_rejected</code>)</li>
                        <li>Bukti lama tetap disimpan untuk audit</li>
                        <li>Finance harus upload bukti yang benar atau void bundle</li>
                    </ul>
                </div>
            </div>

            {{-- 8.3 PDF --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">8.3 PDF Dokumen</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Jenis PDF</th>
                                <th class="px-4 py-2 text-left font-semibold">Kapan</th>
                                <th class="px-4 py-2 text-left font-semibold">Isi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr>
                                <td class="px-4 py-2 font-medium">Unsigned PDF</td>
                                <td class="px-4 py-2">Saat bundle di-submit untuk tanda tangan</td>
                                <td class="px-4 py-2">Data bundle + placeholder tanda tangan</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium">Signed PDF</td>
                                <td class="px-4 py-2">Setelah semua tanda tangan selesai</td>
                                <td class="px-4 py-2">Data bundle + gambar tanda tangan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Keduanya dirender dari <strong>template Blade yang sama</strong> (bukan stamping FPDI). Ini menghindari masalah overlay dan koordinat PDF.
                </p>
            </div>
        </div>
    </x-filament::section>

    {{-- 9. Peran & Hak Akses --}}
    <x-filament::section id="roles" collapsible>
        <x-slot name="heading">9. Peran & Hak Akses (Roles & Permissions)</x-slot>
        <div class="space-y-6 text-sm">
            {{-- 9.1 Peran Finance --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">9.1 Peran Finance</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Peran (Role)</th>
                                <th class="px-4 py-2 text-left font-semibold">Kode Sistem (Tech)</th>
                                <th class="px-4 py-2 text-left font-semibold">Tanggung Jawab</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-4 py-2 font-medium">Finance Reviewer (Pemeriksa)</td><td class="px-4 py-2"><code class="text-xs">finance_reviewer</code></td><td class="px-4 py-2">Review dan verifikasi Expense Request (ER)</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Finance Creator (Pembuat PR/Bundle)</td><td class="px-4 py-2"><code class="text-xs">finance_creator</code></td><td class="px-4 py-2">Buat Payment Request dari ER yang terverifikasi, serta menyusun bundle</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Finance Signer (Penandatangan Finance)</td><td class="px-4 py-2"><code class="text-xs">finance_signer</code></td><td class="px-4 py-2">Tanda tangan digital pada slot Finance di Payment Bundle</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Finance Executor (Pelaksana Bayar)</td><td class="px-4 py-2"><code class="text-xs">finance_executor</code></td><td class="px-4 py-2">Mencatat bukti pembayaran manual, menandai bundle selesai dibayar</td></tr>
                            <tr><td class="px-4 py-2 font-medium">Executive Signer (Penandatangan Direksi)</td><td class="px-4 py-2"><code class="text-xs">executive_signer</code></td><td class="px-4 py-2">Tanda tangan digital pada slot Executive di Payment Bundle (CEO/CFO)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 9.2 Matriks Hak Akses ER --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">9.2 Matriks Hak Akses — Expense Request</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Hak Akses</th>
                                <th class="px-3 py-2 text-center font-semibold">Karyawan</th>
                                <th class="px-3 py-2 text-center font-semibold">Finance Reviewer</th>
                                <th class="px-3 py-2 text-center font-semibold">Finance Creator</th>
                                <th class="px-3 py-2 text-center font-semibold">Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-3 py-2">Buat ER</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Lihat ER sendiri</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Lihat semua ER</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Submit ER</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Review ER</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Verifikasi ER</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Tolak ER</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Minta revisi</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Batalkan ER</td><td class="px-3 py-2 text-center">✅ (own)</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 9.2 Matriks Hak Akses PR --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">9.3 Matriks Hak Akses — Payment Request</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Hak Akses</th>
                                <th class="px-3 py-2 text-center font-semibold">Finance Reviewer</th>
                                <th class="px-3 py-2 text-center font-semibold">Finance Creator</th>
                                <th class="px-3 py-2 text-center font-semibold">Executive</th>
                                <th class="px-3 py-2 text-center font-semibold">Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-3 py-2">Lihat PR</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Buat PR</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Submit untuk Approval</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Approve/Reject</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Minta klarifikasi</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Tandai paid</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Void/Reverse</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 9.4 Matriks Hak Akses Bundle --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">9.4 Matriks Hak Akses — Payment Bundle</h4>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Hak Akses</th>
                                <th class="px-3 py-2 text-center font-semibold">Creator</th>
                                <th class="px-3 py-2 text-center font-semibold">Signer</th>
                                <th class="px-3 py-2 text-center font-semibold">Executive</th>
                                <th class="px-3 py-2 text-center font-semibold">Executor</th>
                                <th class="px-3 py-2 text-center font-semibold">Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            <tr><td class="px-3 py-2">Buat bundle</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Tambah item</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Submit tanda tangan</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Tanda tangan Finance</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td></tr>
                            <tr><td class="px-3 py-2">Tanda tangan Executive</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td></tr>
                            <tr><td class="px-3 py-2">Catat bukti bayar</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Download PDF</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td><td class="px-3 py-2 text-center">✅</td></tr>
                            <tr><td class="px-3 py-2">Void bundle</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">❌</td><td class="px-3 py-2 text-center">✅</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 10. Aturan Bisnis Penting --}}
    <x-filament::section id="aturan" collapsible>
        <x-slot name="heading">10. Aturan Bisnis Penting</x-slot>
        <div class="space-y-6 text-sm">
            {{-- Keamanan Data Bank --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">10.1 Keamanan Data Bank</h4>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950/50">
                        <h5 class="mb-2 font-medium text-green-800 dark:text-green-300">✅ Yang boleh:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-green-700 dark:text-green-400">
                            <li>Simpan 4 digit terakhir rekening dalam clear text</li>
                            <li>Enkripsi nomor rekening lengkap (Laravel encrypted cast)</li>
                            <li>Tampilkan hanya last4 di UI dan PDF</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/50">
                        <h5 class="mb-2 font-medium text-red-800 dark:text-red-300">❌ Yang TIDAK boleh:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-red-700 dark:text-red-400">
                            <li>Menampilkan nomor rekening lengkap di PDF</li>
                            <li>Log nomor rekening lengkap di audit</li>
                            <li>Expose nomor rekening via API tanpa otorisasi</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Aturan Uang --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">10.2 Aturan Uang</h4>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950/50">
                        <h5 class="mb-2 font-medium text-green-800 dark:text-green-300">✅ Yang boleh:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-green-700 dark:text-green-400">
                            <li>Gunakan <code class="rounded bg-green-100 px-1 dark:bg-white/10">decimal(15,2)</code> untuk semua field uang</li>
                            <li>Gunakan <code class="rounded bg-green-100 px-1 dark:bg-white/10">bccomp</code> untuk perbandingan</li>
                            <li>Return uang sebagai string di API</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/50">
                        <h5 class="mb-2 font-medium text-red-800 dark:text-red-300">❌ Yang TIDAK boleh:</h5>
                        <ul class="list-disc space-y-2 pl-6 text-red-700 dark:text-red-400">
                            <li>Gunakan float math untuk perhitungan uang</li>
                            <li>Cast uang ke float di API response</li>
                            <li>Bulatkan jumlah tanpa aturan yang jelas</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Aturan Reverse/Void --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">10.3 Aturan Reverse/Void</h4>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <h5 class="mb-2 font-semibold">Bundle Ditolak</h5>
                        <p class="mb-1 text-xs text-gray-500">(sebelum otorisasi)</p>
                        <ul class="list-disc space-y-2 pl-6 text-xs text-gray-600 dark:text-gray-400">
                            <li>Bundle → Rejected (Ditolak)</li>
                            <li>Items → Released (Dilepas)</li>
                            <li>PR → Approved for Bundle (Siap Masuk Bundle)</li>
                            <li>Tanda tangan → Revoked (Dibatalkan)</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <h5 class="mb-2 font-semibold">Bundle Di-void</h5>
                        <p class="mb-1 text-xs text-gray-500">(sebelum pembayaran)</p>
                        <ul class="list-disc space-y-2 pl-6 text-xs text-gray-600 dark:text-gray-400">
                            <li>Bundle → Voided (Dibatalkan Permanen)</li>
                            <li>Items → Released (Dilepas)</li>
                            <li>Sumber bisa diproses ulang</li>
                            <li>PDF tetap disimpan</li>
                        </ul>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <h5 class="mb-2 font-semibold">Bundle Dibatalkan</h5>
                        <p class="mb-1 text-xs text-gray-500">(masih draft)</p>
                        <ul class="list-disc space-y-2 pl-6 text-xs text-gray-600 dark:text-gray-400">
                            <li>Bundle → Cancelled (Dibatalkan)</li>
                            <li>Items → Released (Dilepas)</li>
                            <li>Sumber bisa diproses ulang</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Aturan Kunci --}}
            <div>
                <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">10.4 Aturan Kunci</h4>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/50">
                    <ol class="list-decimal space-y-2.5 pl-6 text-blue-800 dark:text-blue-300">
                        <li class="pl-1">Dokumen yang ditandatangani mengotorisasi <strong>catatan pembayaran internal</strong>, bukan transaksi bank.</li>
                        <li class="pl-1">Penandatangan menandatangani <code class="rounded bg-blue-100 px-1 dark:bg-blue-900">canonical_payload_hash</code>, bukan file PDF mentah.</li>
                        <li class="pl-1">PDF menampilkan <code class="rounded bg-blue-100 px-1 dark:bg-blue-900">canonical_payload_hash</code> untuk verifikasi.</li>
                        <li class="pl-1">Finance membayar <strong>secara manual</strong> di luar sistem.</li>
                        <li class="pl-1">Finance upload bukti pembayaran ke sistem.</li>
                        <li class="pl-1">Perubahan data bundle <strong>setelah tanda tangan</strong> menghasilkan hash baru dan membutuhkan tanda tangan ulang.</li>
                        <li class="pl-1">Pembayaran menggunakan <strong>snapshot yang dibekukan</strong>, bukan data PR yang hidup.</li>
                    </ol>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- 11. FAQ --}}
    <x-filament::section id="faq" collapsible>
        <x-slot name="heading">11. FAQ — Pertanyaan Umum</x-slot>
        <div class="space-y-6 text-sm">
            {{-- Umum --}}
            <div>
                <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Umum</h4>
                <div class="space-y-3">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apa bedanya ER dan PR?</p>
                        <p class="text-gray-600 dark:text-gray-400">ER (Expense Request) dibuat oleh <strong>karyawan</strong> untuk mengajukan biaya. PR (Payment Request) dibuat oleh <strong>Finance</strong> untuk memproses pembayaran. ER adalah input, PR adalah proses.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apakah setiap ER otomatis menjadi PR?</p>
                        <p class="text-gray-600 dark:text-gray-400">Tidak. ER harus diverifikasi Finance dulu, lalu Finance secara manual membuat PR dari ER tersebut. ER dengan mode **Catatan Saja (Record Only)** (kode: <code class="text-xs">company_paid_record_only</code>) tidak pernah menjadi PR.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Bisa buat PR tanpa ER?</p>
                        <p class="text-gray-600 dark:text-gray-400">Ya. Finance bisa membuat PR manual untuk pembayaran operasional seperti domain, hosting, transport, dll.</p>
                    </div>
                </div>
            </div>

            {{-- Tanda Tangan --}}
            <div>
                <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Tanda Tangan</h4>
                <div class="space-y-3">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Bisa tanda tangan lewat HP?</p>
                        <p class="text-gray-600 dark:text-gray-400">Ya, link tanda tangan bisa dibuka di browser mobile. Canvas tanda tangan support touch.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apa yang terjadi jika link tanda tangan expired?</p>
                        <p class="text-gray-600 dark:text-gray-400">Link ditolak. Finance harus generate link baru dari dashboard.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Bisa satu orang tanda tangan di dua slot Finance?</p>
                        <p class="text-gray-600 dark:text-gray-400">Tidak. Finance Signer 1 dan Finance Signer 2 harus orang yang berbeda (segregation of duties).</p>
                    </div>
                </div>
            </div>

            {{-- Pembayaran --}}
            <div>
                <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Pembayaran</h4>
                <div class="space-y-3">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apakah sistem otomatis transfer uang?</p>
                        <p class="text-gray-600 dark:text-gray-400">Tidak. Sistem hanya mengotorisasi dan mendokumentasikan. Finance membayar secara manual di luar sistem, lalu upload bukti.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apa yang terjadi jika data PR berubah setelah masuk bundle?</p>
                        <p class="text-gray-600 dark:text-gray-400">Bundle menggunakan snapshot yang dibekukan (<code class="rounded bg-gray-100 px-1 dark:bg-white/10">source_snapshot_json</code>). Perubahan di PR asli tidak berpengaruh pada bundle yang sudah ada.</p>
                    </div>
                </div>
            </div>

            {{-- Keamanan --}}
            <div>
                <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Keamanan</h4>
                <div class="space-y-3">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Siapa yang bisa melihat nomor rekening lengkap?</p>
                        <p class="text-gray-600 dark:text-gray-400">Nomor rekening dienkripsi di database. Hanya 4 digit terakhir yang tampil di UI. Akses ke data terenkripsi memerlukan permission <code class="rounded bg-gray-100 px-1 dark:bg-white/10">finance_center.view_sensitive_payment_data</code>.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <p class="mb-1 font-medium text-gray-900 dark:text-white">Q: Apakah audit log bisa dihapus?</p>
                        <p class="text-gray-600 dark:text-gray-400">Tidak. Audit log bersifat append-only. Tidak ada operasi update atau delete dari level aplikasi.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Footer --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
        <p>Dokumen ini terakhir diperbarui: Juni 2026</p>
        <p>Berdasarkan implementasi aktual di codebase PartnersHub HRIS</p>
    </div>
</x-filament-panels::page>
