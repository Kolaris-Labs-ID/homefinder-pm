# Dokumentasi Alur Kerja Penggajian

## Ringkasan

Sistem penggajian menangani dua siklus pencairan:

1. **Penggajian Bulanan** — perhitungan gaji lengkap per periode melalui `PayrunBatch`
2. **Uang Harian Mingguan** — pencairan tunjangan harian secara mingguan melalui `WeeklyAllowanceBatch`

Kedua alur dikelola melalui panel admin Filament dan didukung oleh arsitektur service berlapis.

---

## Arsitektur

```
Panel Admin (Filament)
    ├── PayrunBatchResource / WeeklyAllowanceBatchResource
    │       │
    │       ├── PayrollApprovalService     (alur persetujuan)
    │       │       ├── PayrollWorkflowConfig / PayrollWorkflowStep
    │       │       └── PayrollApprovalLog  (audit trail)
    │       │
    │       └── PayrollCalculationService / WeeklyAllowanceService
    │               ├── AttendancePayrollService   (kehadiran & prorata)
    │               ├── BpjsCalculationService     (iuran BPJS)
    │               ├── Pph21CalculationService    (pajak penghasilan / PPh 21)
    │               └── PayrollSettingService      (tarif yang dapat dikonfigurasi)
    │
    └── PayrollApprovalConfigPage          (konfigurasi workflow per modul)
            │
            ▼
    PayrollRecord / WeeklyAllowancePayment  (hasil tersimpan)
```

---

## 1. Alur Penggajian Bulanan

### 1.1 Siklus Status

```
draft → calculated → pending_finance → [pending_coo] → paid
                                  └──── rejected ──→ pending_finance (resubmit)
```

| Status            | Keterangan                                                         |
| ----------------- | ------------------------------------------------------------------ |
| `draft`           | Batch dibuat, belum dihitung                                       |
| `calculated`      | Semua record karyawan sudah dihitung                               |
| `pending_finance` | Sudah disubmit oleh HR, menunggu persetujuan Finance               |
| `pending_coo`     | Dieskalasi oleh Finance, menunggu persetujuan COO                  |
| `rejected`        | Ditolak oleh approver (Finance/COO) dengan alasan penolakan        |
| `paid`            | Sudah dicairkan; batch dan record terkunci, tidak bisa diubah lagi |

### 1.2 Proses Langkah demi Langkah

#### Langkah 1 — Buat PayrunBatch (Draft)

Admin membuat batch dengan:

- `period_start_date` / `period_end_date` — periode penggajian
- `pay_date` — tanggal pencairan yang dijadwalkan

#### Langkah 2 — Hitung Payrun

Memicu `PayrollCalculationService::calculatePayrun()` yang mengiterasi semua karyawan aktif dan memanggil `calculateEmployee()` untuk masing-masing.

**Urutan perhitungan per karyawan:**

```
1.  Ambil SalaryStructure aktif (effective_date terbaru ≤ period_end)
2.  Ambil ringkasan kehadiran (AttendancePayrollService)
3.  Hitung rasio prorata (jika prorate_enabled)
4.  Hitung gaji pokok prorata
5.  Hitung tunjangan tetap (diprorata jika diaktifkan)
6.  Hitung tunjangan variabel (berbasis kehadiran)
7.  Hitung total uang harian (daily_allowance_rate × hari kerja aktual)
8.  Rekonsiliasi uang harian mingguan (jika weekly_allowance_enabled)
9.  Jumlahkan penghasilan bruto
10. Hitung BPJS (berdasarkan gaji penuh, bukan prorata)
11. Hitung penghasilan kena pajak (bruto − BPJS karyawan)
12. Hitung PPh 21 (metode TER)
13. Hitung potongan lainnya (diprorata jika diaktifkan)
14. Jumlahkan total potongan
15. Gaji bersih = bruto − total potongan
16. Simpan ke PayrollRecord (upsert)
```

Status batch → `calculated`.

#### Langkah 3 — Submit untuk Persetujuan

HR mengirim batch yang sudah dihitung ke pipeline persetujuan melalui `PayrollApprovalService::submitForApproval()`. Memvalidasi bahwa konfigurasi workflow aktif ada untuk modul ini. Status → `pending_finance`, mencatat `submitted_by` dan `submitted_at`.

#### Langkah 4 — Persetujuan Finance

Finance mereview batch dan memilih salah satu:

- **Approve & Pay** — `PayrollApprovalService::approveAndPay()` → status `paid`, mencatat `paid_by` dan `paid_at`
- **Escalate to COO** — `PayrollApprovalService::escalateToCoo()` → status `pending_coo` (hanya jika step COO dikonfigurasi)
- **Reject** — `PayrollApprovalService::reject()` → status `rejected`, wajib menyertakan alasan penolakan

#### Langkah 5 — Persetujuan COO (opsional)

Jika dieskalasi, COO mereview dan memilih:

- **Approve & Pay** — `PayrollApprovalService::cooApproveAndPay()` → status `paid`
- **Reject** — `PayrollApprovalService::reject()` → status `rejected`

#### Langkah 6 — Penolakan & Resubmit

Jika ditolak, HR dapat melihat alasan penolakan dan approver yang menolak. HR dapat mengedit catatan batch, lalu resubmit melalui `PayrollApprovalService::resubmit()` → status kembali ke `pending_finance`.

#### Langkah 7 — Batch Terkunci (Paid)

Setelah status `paid`, batch dan semua record menjadi **terkunci** — tidak ada transisi status, edit, atau aksi persetujuan yang diperbolehkan.

#### Langkah 8 — Hitung Ulang (opsional)

Hanya diperbolehkan saat status `draft` atau `calculated`. Direset ke `draft` lalu perhitungan dijalankan ulang. Bersifat idempoten.

---

## 2. Detail Perhitungan Gaji

### 2.1 Prorata

Ketika `SalaryStructure.prorate_enabled = true`:

```
rasio_prorata = hari_kerja_aktual / total_hari_kerja   (maksimal 1.0)
gaji_pokok_prorata = gaji_pokok_penuh × rasio_prorata
```

`hari_kerja_aktual` = `hari_hadir` + `hari_terlambat` + (`hari_setengah` × 0.5)

> **Catatan:** Karyawan yang check-in tetapi lupa check-out tetap dihitung sebagai kehadiran penuh (present/late sesuai jam masuk). Record tersebut akan ditandai `missing_checkout = true` sebagai flag untuk HR dan manajemen. Flag ini **tidak mempengaruhi** perhitungan gaji.

`total_hari_kerja` = hari kerja (Senin–Jumat) dalam periode, tidak termasuk akhir pekan.

### 2.2 Tunjangan

| Jenis              | Perhitungan                                                 |
| ------------------ | ----------------------------------------------------------- |
| Tunjangan tetap    | Jumlah dari `allowances_fixed[]`, diprorata jika diaktifkan |
| Tunjangan variabel | Berbasis kehadiran, dikonfigurasi per item tunjangan        |
| Uang harian        | `daily_allowance_rate × hari_kerja_aktual`                  |

### 2.3 Iuran BPJS

BPJS dihitung berdasarkan **gaji penuh** (bukan prorata):

```
basis_bpjs = gaji_pokok_penuh + total_tunjangan_tetap
```

| Komponen  | Karyawan | Perusahaan              | Batas Atas    |
| --------- | -------- | ----------------------- | ------------- |
| Kesehatan | 1%       | 4%                      | Rp 12.000.000 |
| JHT       | 2%       | 3,7%                    | Tanpa batas   |
| JP        | 1%       | 2%                      | Rp 10.547.400 |
| JKK       | —        | 0,24–1,74% (per risiko) | —             |
| JKM       | —        | 0,3%                    | —             |

> Semua tarif dapat dikonfigurasi melalui `PayrollSetting` (tidak di-hardcode).

**BPJS ditanggung perusahaan** (`bpjs_paid_by = 'company'`): iuran karyawan menjadi Rp 0; perusahaan menanggung seluruh jumlah.

### 2.4 PPh 21 (Pajak Penghasilan)

Dihitung menggunakan metode **TER (Tarif Efektif Rata-rata)**:

```
penghasilan_kena_pajak = penghasilan_bruto − total_bpjs_karyawan
pph21 = penghasilan_kena_pajak × tarif_TER
```

Bracket TER dapat dikonfigurasi melalui `PayrollSetting`. Tiga metode yang didukung:

| Metode     | Keterangan                                                   |
| ---------- | ------------------------------------------------------------ |
| `gross`    | Pajak ditanggung karyawan, dihitung dari bruto               |
| `netto`    | Sama dengan gross (karyawan menanggung pajak)                |
| `gross_up` | Pajak ditanggung perusahaan: `(bruto × tarif) / (1 − tarif)` |

### 2.5 Gaji Bersih

```
penghasilan_bruto = gaji_pokok_prorata + tunjangan_tetap + tunjangan_variabel + uang_harian
total_potongan = bpjs_karyawan + pph21 + potongan_lainnya
gaji_bersih = penghasilan_bruto − total_potongan
```

---

## 3. Alur Uang Harian Mingguan

### 3.1 Siklus Status

```
draft → calculated → pending_finance → [pending_coo] → paid
                                  └──── rejected ──→ pending_finance (resubmit)
```

Siklus status sekarang **identik** dengan Penggajian Bulanan. Status lama (`pending_hr`, `pending_final`, `approved`, `cancelled`) sudah dihapus dan diganti dengan alur persetujuan terpadu.

### 3.2 Proses Langkah demi Langkah

#### Langkah 1 — Generate Batch

`WeeklyAllowanceService::generateBatch(weekStart, weekEnd, paymentDate)`

- Mencakup Senin–Jumat minggu sebelumnya
- Tanggal pembayaran biasanya Selasa berikutnya
- Mengambil semua karyawan dengan `weekly_allowance_enabled = true` pada struktur gaji mereka

#### Langkah 2 — Hitung Pembayaran Per Karyawan

Untuk setiap karyawan yang memenuhi syarat:

```
1. Ambil SalaryStructure aktif
2. Ambil daily_allowance_rate
3. Ambil kehadiran Senin–Jumat (hadir / terlambat / WFH = dihitung)
4. hari_hadir = jumlah hari yang memenuhi syarat
5. total_jumlah = daily_allowance_rate × hari_hadir
6. Snapshot info bank (bank_name, account_number, account_holder)
7. Tandai has_bank_warning jika ada field bank yang kosong
```

#### Langkah 3 — Submit untuk Persetujuan

HR mengirim batch ke pipeline persetujuan melalui `PayrollApprovalService::submitForApproval()`. Status → `pending_finance`.

#### Langkah 4 — Persetujuan Finance

Sama seperti Penggajian Bulanan: Finance dapat Approve & Pay, Escalate to COO, atau Reject.

#### Langkah 5 — Persetujuan COO (opsional)

Jika dieskalasi, COO dapat Approve & Pay atau Reject.

#### Langkah 6 — Penolakan & Resubmit

Jika ditolak, HR melihat alasan penolakan dan dapat resubmit.

#### Langkah 7 — Tandai Sudah Dibayar

Semua pembayaran berpindah ke `paid`. Batch terkunci.

#### Langkah 8 — Ekspor untuk Transfer Bank

File CSV digenerate dengan: Bank, Nomor Rekening, Nama Pemilik Rekening, Jumlah, Keterangan.

---

## 4. Rekonsiliasi Penggajian Bulanan

Ketika `weekly_allowance_enabled = true` pada struktur gaji, penggajian bulanan merekonsiliasi pembayaran mingguan:

```
uang_harian_diperoleh    = daily_allowance_rate × hari_kerja_aktual (bulanan)
uang_harian_dibayar      = jumlah WeeklyAllowancePayments yang sudah dibayar dalam periode
selisih_uang_harian      = diperoleh − dibayar
```

Selisih ini ditampilkan pada slip gaji untuk menunjukkan perbedaan bersih antara yang diperoleh dan yang sudah dicairkan secara mingguan.

---

## 5. Model Utama

| Model                    | Fungsi                                                        |
| ------------------------ | ------------------------------------------------------------- |
| `PayrunBatch`            | Kontainer batch penggajian bulanan                            |
| `PayrollRecord`          | Hasil penggajian per karyawan untuk sebuah batch              |
| `SalaryStructure`        | Konfigurasi gaji karyawan (tarif, tunjangan, flag)            |
| `PayrollSetting`         | Tarif BPJS/pajak yang dapat dikonfigurasi, tersimpan di DB    |
| `WeeklyAllowanceBatch`   | Kontainer batch uang harian mingguan                          |
| `WeeklyAllowancePayment` | Pembayaran uang harian per karyawan                           |
| `AttendanceLog`          | Catatan kehadiran harian yang digunakan untuk perhitungan     |
| `PayrollWorkflowConfig`  | Konfigurasi workflow persetujuan per modul (bulanan/mingguan) |
| `PayrollWorkflowStep`    | Step persetujuan dalam workflow (Finance/COO) dengan assignee |
| `PayrollApprovalLog`     | Audit trail immutable untuk setiap transisi status batch      |

---

## 6. Service Utama

| Service                     | Tanggung Jawab                                                   |
| --------------------------- | ---------------------------------------------------------------- |
| `PayrollCalculationService` | Mengorkestrasikan perhitungan penggajian bulanan lengkap         |
| `PayrollApprovalService`    | Mengorkestrasikan seluruh transisi persetujuan untuk kedua modul |
| `AttendancePayrollService`  | Ringkasan kehadiran, rasio prorata, uang harian                  |
| `BpjsCalculationService`    | Perhitungan komponen BPJS                                        |
| `Pph21CalculationService`   | Perhitungan pajak PPh 21 / TER                                   |
| `PayrollSettingService`     | Mengambil tarif yang dapat dikonfigurasi dari `payroll_settings` |
| `WeeklyAllowanceService`    | Pembuatan batch dan alur kerja uang harian mingguan              |

---

## 7. Flag Konfigurasi SalaryStructure

| Field                      | Tipe    | Efek                                                   |
| -------------------------- | ------- | ------------------------------------------------------ |
| `prorate_enabled`          | boolean | Mengaktifkan perhitungan prorata berdasarkan kehadiran |
| `daily_allowance_rate`     | decimal | Jumlah tunjangan per hari (Uang Harian)                |
| `bpjs_paid_by`             | enum    | `employee` / `company` / `shared`                      |
| `pph21_method`             | enum    | `gross` / `netto` / `gross_up`                         |
| `jkk_risk_level`           | enum    | `very_low` sampai `very_high` — mempengaruhi tarif JKK |
| `weekly_allowance_enabled` | boolean | Mengaktifkan pencairan mingguan + rekonsiliasi bulanan |

---

## 10. Alur Persetujuan Penggajian (Payroll Approval Workflow)

### 10.1 Ringkasan

Sistem persetujuan baru menggantikan tombol approve/markAsPaid ad-hoc dengan pipeline terstruktur yang dikonfigurasi per modul. Sistem ini **terpisah sepenuhnya** dari `ApprovalWorkflow`/`ApprovalStep` yang digunakan untuk Cuti, Reimbursement, dan Training.

### 10.2 Konfigurasi Workflow

Dikelola melalui halaman Filament `PayrollApprovalConfig` (hanya `super_admin`):

- Dua konfigurasi: satu untuk Penggajian Bulanan, satu untuk Uang Harian Mingguan
- Setiap konfigurasi memiliki **1 step Finance (wajib)** dan **1 step COO (opsional)**
- Setiap step dapat di-assign ke user spesifik atau Spatie role

### 10.3 Aksi Persetujuan

| Aksi                | Dari Status                       | Ke Status         | Aktor         |
| ------------------- | --------------------------------- | ----------------- | ------------- |
| Submit for Approval | `calculated`                      | `pending_finance` | HR            |
| Approve & Pay       | `pending_finance`                 | `paid`            | Finance       |
| Approve & Pay       | `pending_coo`                     | `paid`            | COO           |
| Escalate to COO     | `pending_finance`                 | `pending_coo`     | Finance       |
| Reject              | `pending_finance` / `pending_coo` | `rejected`        | Finance / COO |
| Resubmit            | `rejected`                        | `pending_finance` | HR            |

### 10.4 Visibilitas Berbasis Peran

| Peran                    | Batch yang Terlihat        |
| ------------------------ | -------------------------- |
| HR / Admin / Super Admin | Semua batch                |
| Finance Approver         | `pending_finance` + `paid` |
| COO Approver             | `pending_coo` + `paid`     |
| Lainnya                  | Tidak ada akses            |

### 10.5 Audit Trail

Setiap transisi status menciptakan entry `PayrollApprovalLog` yang immutable, mencatat: aktor, aksi, status sebelum/sesudah, catatan opsional, dan timestamp. Timeline ditampilkan di halaman detail batch.

### 10.6 Tabel Database Baru

| Tabel                      | Fungsi                                             |
| -------------------------- | -------------------------------------------------- |
| `payroll_workflow_configs` | Konfigurasi workflow per modul                     |
| `payroll_workflow_steps`   | Step persetujuan (Finance/COO) dengan assignee     |
| `payroll_approval_logs`    | Audit trail immutable untuk setiap transisi status |

---

## 8. Output Slip Gaji (PENGHASILAN / PENGURANGAN)

---

## 8a. Flag Missing Checkout

Karyawan yang check-in tetapi tidak check-out akan ditandai secara otomatis:

- **Perlakuan payroll:** Dihitung sebagai kehadiran penuh (present atau late). Tidak ada penalti gaji.
- **Flag:** `missing_checkout = true` pada `attendance_logs`.
- **Otomasi:** Command `attendance:flag-missing-checkouts` berjalan setiap hari pukul 23:55 WIB via scheduler.
- **Self-healing:** Jika karyawan check-out setelah flag sudah terpasang, flag otomatis direset ke `false`.
- **Visibilitas:** HR dan C-level dapat melihat badge "⚠ Missing" di kolom Check-Out pada tabel Attendance Logs, serta memfilter berdasarkan flag ini.
- **Attendance summary:** Field `missing_checkout_days` tersedia di ringkasan kehadiran payroll untuk transparansi.

---

## 9. Output Slip Gaji (PENGHASILAN / PENGURANGAN)

**PENGHASILAN**

- Gaji Pokok (penuh)
- Prorata Hari Kerja (jika diprorata)
- Tunjangan Tetap
- Tunjangan Variabel
- Uang Harian
- BPJS ditanggung kantor (jika ditanggung perusahaan)

**PENGURANGAN**

- BPJS Kesehatan (bagian karyawan)
- BPJS JHT (bagian karyawan)
- BPJS JP (bagian karyawan)
- PPh 21
- Potongan lainnya

**TAKE HOME PAY = Penghasilan Bruto − Total Potongan**
