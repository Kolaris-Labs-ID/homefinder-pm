# Panduan Late Pay Compensation (Kompensasi Keterlambatan Gaji)

## Apa itu Late Pay Compensation?

Late Pay Compensation adalah kompensasi yang diberikan kepada karyawan karena keterlambatan pembayaran gaji. Kompensasi ini dihitung sebagai **1% dari gaji pokok per hari kalender terlambat**, dan dibayarkan secara terpisah dari gaji utama.

### Rumus Dasar (Tanpa Pembayaran Parsial)

```
Kompensasi = Gaji Pokok × (Rate % / 100) × Jumlah Hari Terlambat
```

Contoh:
- Gaji Pokok: Rp 10.000.000
- Rate: 1% per hari
- Terlambat: 2 hari
- Kompensasi: 10.000.000 × 0.01 × 2 = **Rp 200.000**

---

## Rumus dengan Pembayaran Parsial (Multi-Phase)

Jika gaji dibayarkan sebagian (partial payment) sebelum tanggal pembayaran penuh, kompensasi dihitung secara bertahap (multi-phase). Kompensasi hanya diterapkan pada **sisa gaji yang belum dibayar** pada setiap fase.

### Contoh Perhitungan Multi-Phase

**Skenario:**
- Gaji Pokok: Rp 10.000.000
- Rate: 1% per hari
- Tanggal Scheduled: 25 April
- Tanggal Actual (pembayaran penuh): 2 Mei (7 hari terlambat)
- Pembayaran Parsial:
  - 28 April: 30% dibayar (sisa 70%)
  - 30 April: 20% dibayar (sisa 50%)

**Perhitungan:**

| Fase | Tanggal | Hari | Sisa Gaji | Kompensasi |
|------|---------|------|-----------|------------|
| 1 | 25–28 April | 3 hari | 100% (Rp 10M) | 1% × 10M × 3 = Rp 300.000 |
| 2 | 28–30 April | 2 hari | 70% (Rp 7M) | 1% × 7M × 2 = Rp 140.000 |
| 3 | 30 April–2 Mei | 2 hari | 50% (Rp 5M) | 1% × 5M × 2 = Rp 100.000 |
| **Total** | | | | **Rp 540.000** |

**Tanpa pembayaran parsial:** 10M × 0.01 × 7 = Rp 700.000  
**Dengan pembayaran parsial:** Rp 540.000  
**Hemat:** Rp 160.000

---

## Panduan untuk HR

### 1. Generate Batch (Membuat Batch Baru)

**Menu:** Payroll → Late Pay Compensation

**Langkah-langkah:**
1. Klik tombol **"Generate batch"** di pojok kanan atas
2. Isi form berikut:
   - **Scheduled pay date**: Tanggal seharusnya gaji dibayar
   - **Actual salary payment date**: Tanggal gaji benar-benar dibayar
   - **Compensation transfer date** (opsional): Tanggal kompensasi 1% dibayar terpisah
   - **Rate (% per day)**: Default 1%, bisa disesuaikan jika perlu
   - **Notes** (opsional): Catatan tambahan
3. Klik **"Generate"**

Sistem akan otomatis:
- Menghitung jumlah hari terlambat
- Mengambil semua karyawan aktif dengan gaji pokok
- Menghitung kompensasi per karyawan
- Membuat status batch: **"Calculated"**

---

### 2. Menambahkan Pembayaran Parsial

**Kapan digunakan:** Jika gaji dibayarkan sebagian sebelum tanggal pembayaran penuh, untuk mengurangi kompensasi keterlambatan.

**Langkah-langkah:**
1. Buka batch yang ingin diedit
2. Klik tab **"Employee compensation"**
3. Di tabel karyawan, klik tombol **"Add partial payment"** pada baris karyawan yang bersangkutan
4. Isi form:
   - **Payment date**: Tanggal pembayaran parsial (harus antara scheduled dan actual pay date)
   - **Paid %**: Persentase gaji yang dibayar pada tanggal tersebut (contoh: 30, 50, 70)
5. Klik **"Add"**

Sistem akan:
- Merekam pembayaran parsial
- Otomatis menghitung ulang kompensasi dengan multi-phase calculation
- Mengupdate total kompensasi item dan batch

**Catatan:**
- Total persentase pembayaran parsial per karyawan tidak boleh melebihi 100%
- Tanggal pembayaran harus berada dalam range scheduled_pay_date → actual_pay_date
- Bisa menambahkan lebih dari satu pembayaran parsial per karyawan

---

### 3. Melihat Pembayaran Parsial

**Langkah-langkah:**
1. Di tabel karyawan, klik tombol **"View partials"** pada baris karyawan
2. Modal akan menampilkan semua pembayaran parsial yang sudah diinput:
   - Tanggal pembayaran
   - Persentase yang dibayar

---

### 4. Menghapus Pembayaran Parsial

**Langkah-langkah:**
1. Di tabel karyawan, klik tombol **"Remove partial"** pada baris karyawan
2. Pilih pembayaran parsial yang ingin dihapus dari dropdown
3. Klik **"Remove"**

Sistem akan:
- Menghapus pembayaran parsial
- Menghitung ulang kompensasi (kembali ke perhitungan tanpa partial payment)
- Mengupdate total kompensasi item dan batch

---

### 5. Menambah/Menghapus Karyawan dari Batch

**Exclude Karyawan:**
1. Di tabel karyawan, klik tombol **"Exclude"** pada baris karyawan
2. Status berubah menjadi **"Excluded"**
3. Karyawan tidak akan mendapatkan kompensasi

**Include Kembali:**
1. Klik tombol **"Include"** pada karyawan yang di-exclude
2. Status kembali menjadi **"Pending"**

---

### 6. Submit untuk Approval

Setelah semua data benar:
1. Klik tombol **"Submit for approval"** di halaman view batch
2. Batch berubah status menjadi **"Pending Finance"**
3. Batch masuk ke workflow approval Finance

---

### 7. Refresh Bank Info

Jika ada karyawan dengan warning bank:
1. Di tab **"Employee compensation"**, klik tombol **"Refresh bank info"** di header
2. Sistem akan mengambil data bank terbaru dari profil karyawan
3. Warning akan hilang jika data bank sudah lengkap

---

## Panduan untuk Finance Approver

### 1. Review Batch

**Menu:** Payroll → Late Pay Compensation

**Langkah-langkah:**
1. Buka batch dengan status **"Pending Finance"**
2. Review informasi batch:
   - **Scheduled → Actual**: Rentang tanggal keterlambatan
   - **Days late**: Jumlah hari terlambat
   - **Rate %**: Persentase kompensasi per hari
   - **Total employees**: Jumlah karyawan yang mendapatkan kompensasi
   - **Employees with warnings**: Karyawan dengan data bank tidak lengkap
   - **Total amount**: Total kompensasi yang harus dibayarkan
3. Klik tab **"Employee compensation"** untuk detail per karyawan

---

### 2. Approval Workflow

Batch akan melewati beberapa tahap approval sesuai konfigurasi payroll workflow.

#### Approve (Langkah Approval)

**Kondisi:**
- Batch status: **"Pending Finance"**
- Anda adalah approver pada tahap saat ini

**Langkah-langkah:**
1. Klik tombol **"Approve"**
2. Batch bergerak ke tahap approval berikutnya

#### Approve & Pay (Final Step)

**Kondisi:**
- Batch status: **"Pending Finance"**
- Anda adalah approver pada tahap **terakhir**

**Langkah-langkah:**
1. Klik tombol **"Approve & pay"**
2. Batch status berubah menjadi **"Paid"**
3. Tanggal pembayaran dicatat

#### Reject

**Kondisi:**
- Batch status: **"Pending Finance"**
- Anda adalah approver pada tahap saat ini

**Langkah-langkah:**
1. Klik tombol **"Reject"**
2. Masukkan alasan penolakan
3. Batch status berubah menjadi **"Rejected"**
4. HR bisa melakukan resubmit setelah perbaikan

---

### 3. Resubmit (Untuk HR)

Jika batch di-reject:
1. HR melakukan perbaikan
2. Klik tombol **"Resubmit"**
3. Batch kembali ke workflow approval

---

## Status Batch

| Status | Deskripsi | Bisa Diedit? |
|--------|-----------|--------------|
| **Draft** | Batch baru, belum selesai di-generate | Ya |
| **Calculated** | Batch sudah di-generate, menunggu review HR | Ya |
| **Pending Finance** | Batch disubmit, menunggu approval Finance | Tidak |
| **Rejected** | Batch ditolak oleh Finance | Ya (setelah resubmit) |
| **Paid** | Batch sudah disetujui dan dibayar | Tidak |

---

## Status Item (Per Karyawan)

| Status | Deskripsi |
|--------|-----------|
| **Pending** | Menunggu approval |
| **Approved** | Disetujui untuk pembayaran |
| **Excluded** | Dikecualikan dari kompensasi |
| **Paid** | Sudah dibayar |

---

## Tips & Best Practices

### Untuk HR
1. **Cek tanggal dengan teliti** — Pastikan scheduled dan actual pay date benar
2. **Gunakan pembayaran parsial** — Jika gaji dibayar sebagian, input partial payment untuk mengurangi kompensasi
3. **Review warning bank** — Pastikan data bank karyawan lengkap sebelum submit
4. **Catat notes** — Gunakan kolom notes untuk dokumentasi

### Untuk Finance
1. **Review total amount** — Pastikan total kompensasi masuk akal
2. **Cek partial payments** — Pastikan pembayaran parsial diinput dengan benar
3. **Approve dengan cepat** — Jangan tahan approval terlalu lama agar kompensasi cepat dibayar ke karyawan
4. **Catat rejection reason** — Jika reject, berikan alasan yang jelas untuk HR

---

## Contoh Kasus

### Kasus 1: Keterlambatan 2 Hari, Tanpa Partial Payment

- Scheduled: 25 April
- Actual: 27 April
- Gaji Pokok: Rp 10.000.000
- Rate: 1%
- Kompensasi: 10.000.000 × 0.01 × 2 = **Rp 200.000**

### Kasus 2: Keterlambatan 7 Hari, Dengan 2 Partial Payment

- Scheduled: 25 April
- Actual: 2 Mei
- Gaji Pokok: Rp 10.000.000
- Rate: 1%
- Partial 1: 28 April, 30%
- Partial 2: 30 April, 20%
- Kompensasi: **Rp 540.000** (lihat contoh perhitungan di atas)

### Kasus 3: Keterlambatan 5 Hari, 50% Partial Payment di Tengah

- Scheduled: 25 April
- Actual: 30 April
- Gaji Pokok: Rp 10.000.000
- Rate: 1%
- Partial: 27 April, 50%
- Fase 1 (25–27, 2 hari): 1% × 10M × 2 = Rp 200.000
- Fase 2 (27–30, 3 hari): 1% × 5M × 3 = Rp 150.000
- Total: **Rp 350.000**

---

## FAQ

**Q: Apakah rate 1% bisa diubah?**  
A: Ya, HR bisa mengubah rate saat generate batch, tapi default adalah 1%.

**Q: Bisa menambah partial payment setelah batch di-submit?**  
A: Tidak. Batch yang sudah "Pending Finance" atau "Paid" tidak bisa diubah. Pastikan semua partial payment diinput sebelum submit.

**Q: Apa yang terjadi jika total partial payment > 100%?**  
A: Sistem akan menolak dan menampilkan error. Total tidak boleh melebihi 100%.

**Q: Bisa menghapus batch yang sudah paid?**  
A: Tidak. Batch yang sudah paid tidak bisa dihapus untuk audit trail.

**Q: Karyawan yang tidak punya salary structure akan mendapat kompensasi?**  
A: Tidak. Sistem akan melewati karyawan tanpa salary structure aktif.

---

## Bantuan

Jika mengalami masalah atau ada pertanyaan:
- Hubungi tim IT/Developer
- Cek log approval di tab "Approval timeline"
- Review error message yang ditampilkan sistem
