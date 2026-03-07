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
    └── PayrunBatchResource / WeeklyAllowanceBatchResource
            │
            ▼
    PayrollCalculationService / WeeklyAllowanceService
            │
            ├── AttendancePayrollService   (kehadiran & prorata)
            ├── BpjsCalculationService     (iuran BPJS)
            ├── Pph21CalculationService    (pajak penghasilan / PPh 21)
            └── PayrollSettingService      (tarif yang dapat dikonfigurasi)
            │
            ▼
    PayrollRecord / WeeklyAllowancePayment  (hasil tersimpan)
```

---

## 1. Alur Penggajian Bulanan

### 1.1 Siklus Status

```
draft → calculated → approved → paid
```

| Status       | Keterangan                                        |
| ------------ | ------------------------------------------------- |
| `draft`      | Batch dibuat, belum dihitung                      |
| `calculated` | Semua record karyawan sudah dihitung              |
| `approved`   | Sudah direview dan disetujui oleh pihak berwenang |
| `paid`       | Sudah dicairkan; batch dan record terkunci        |

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

#### Langkah 3 — Persetujuan

`PayrollCalculationService::approve()` — mengubah status menjadi `approved`, mencatat `approved_by` dan `approved_at`.

#### Langkah 4 — Tandai Sudah Dibayar

`PayrollCalculationService::markAsPaid()` — mengubah status menjadi `paid`, mencatat `paid_by` dan `paid_at`. Batch dan semua record menjadi **terkunci** (tidak bisa diedit lagi).

#### Langkah 5 — Hitung Ulang (opsional)

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
draft → pending_hr → pending_finance → pending_final → approved → paid
                                                              └── cancelled (tahap sebelum paid)
```

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

#### Langkah 3 — Review HR (`pending_hr`)

HR mereview pembayaran, dapat mengedit jumlah individual. Tidak dapat mengirim ke Finance jika ada `has_bank_warning = true`.

#### Langkah 4 — Persetujuan Finance (`pending_finance → pending_final`)

Finance menyetujui; semua pembayaran berpindah ke status `approved`.

#### Langkah 5 — Persetujuan Akhir (`pending_final → approved`)

COO (atau approver akhir yang dikonfigurasi) memberikan persetujuan final.

#### Langkah 6 — Tandai Sudah Dibayar

Semua pembayaran berpindah ke `paid`. Batch terkunci.

#### Langkah 7 — Ekspor untuk Transfer Bank

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

| Model                    | Fungsi                                                     |
| ------------------------ | ---------------------------------------------------------- |
| `PayrunBatch`            | Kontainer batch penggajian bulanan                         |
| `PayrollRecord`          | Hasil penggajian per karyawan untuk sebuah batch           |
| `SalaryStructure`        | Konfigurasi gaji karyawan (tarif, tunjangan, flag)         |
| `PayrollSetting`         | Tarif BPJS/pajak yang dapat dikonfigurasi, tersimpan di DB |
| `WeeklyAllowanceBatch`   | Kontainer batch uang harian mingguan                       |
| `WeeklyAllowancePayment` | Pembayaran uang harian per karyawan                        |
| `AttendanceLog`          | Catatan kehadiran harian yang digunakan untuk perhitungan  |

---

## 6. Service Utama

| Service                     | Tanggung Jawab                                                   |
| --------------------------- | ---------------------------------------------------------------- |
| `PayrollCalculationService` | Mengorkestrasikan perhitungan penggajian bulanan lengkap         |
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
