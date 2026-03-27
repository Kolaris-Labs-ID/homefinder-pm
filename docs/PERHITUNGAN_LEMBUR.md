# Dokumentasi Perhitungan Lembur (Overtime)

> **Versi:** 1.0 — 27 Maret 2026  
> **Berlaku untuk:** PHI Connect HRIS — Modul ESS Lembur

---

## Ringkasan

Modul lembur di PHI Connect menggunakan mekanisme **time-tracker**: karyawan menekan "Mulai" saat lembur dimulai dan "Berhenti" saat selesai. Sistem secara otomatis menghitung durasi, tarif per jam, dan estimasi upah lembur berdasarkan **UU Ketenagakerjaan No. 13 Tahun 2003** beserta perubahannya di **UU Cipta Kerja (Kepmenaker No. 102/MEN/VI/2004 Pasal 11)**.

---

## 1. Tarif Per Jam (Hourly Rate)

### Dasar Hukum

Kepmenaker No. 102/MEN/VI/2004 Pasal 8 menetapkan bahwa upah per jam dihitung dengan cara:

$$\text{Upah per jam} = \frac{\text{Gaji Pokok}}{173}$$

Angka **173** berasal dari:

| Komponen | Nilai |
|---|---|
| Jam kerja per minggu | 40 jam |
| Minggu per tahun | 52 minggu |
| Total jam per tahun | 2.080 jam |
| Total jam per bulan | 2.080 ÷ 12 = **173,33 ≈ 173 jam** |

### Sumber Data di Sistem

Tarif per jam diambil secara otomatis dari **Salary Structure aktif** milik karyawan pada saat sesi lembur dimulai:

```
hourly_rate = ROUND(basic_salary ÷ 173, 2)
```

Nilai ini di-*snapshot* ke dalam tabel `overtime_logs` sehingga:
- Perubahan gaji di masa depan **tidak** mempengaruhi lembur yang sudah berjalan.
- HR dapat mengubah `hourly_rate` secara manual per entri di Filament Admin jika diperlukan.

### Contoh

| Gaji Pokok | Tarif Per Jam |
|---|---|
| Rp 4.000.000 | Rp 4.000.000 ÷ 173 = **Rp 23.121,39** |
| Rp 5.000.000 | Rp 5.000.000 ÷ 173 = **Rp 28.901,73** |
| Rp 7.000.000 | Rp 7.000.000 ÷ 173 = **Rp 40.462,43** |
| Rp 10.000.000 | Rp 10.000.000 ÷ 173 = **Rp 57.803,47** |

---

## 2. Perhitungan Upah Lembur

### Aturan Multiplier (Kepmenaker No. 102/MEN/VI/2004 Pasal 11)

| Jam Lembur ke- | Multiplier |
|---|---|
| **Jam pertama** (0–60 menit) | **1,5×** tarif per jam |
| **Jam kedua dan seterusnya** | **2,0×** tarif per jam |

### Formula

$$\text{Estimasi Upah} = \begin{cases} \text{tarif\_per\_jam} \times 1{,}5 \times t & \text{jika } t \leq 1 \text{ jam} \\ (\text{tarif\_per\_jam} \times 1{,}5) + (\text{tarif\_per\_jam} \times 2{,}0 \times (t - 1)) & \text{jika } t > 1 \text{ jam} \end{cases}$$

Di mana **t** = total durasi lembur dalam jam (menit ÷ 60).

### Contoh Perhitungan Lengkap

**Asumsi:** Gaji pokok Rp 4.000.000 → Tarif per jam = Rp 23.121,39

| Durasi | Kalkulasi | Estimasi Upah |
|---|---|---|
| 30 menit (0,5 jam) | Rp 23.121,39 × 1,5 × 0,5 | **Rp 17.341,04** |
| 1 jam | Rp 23.121,39 × 1,5 × 1 | **Rp 34.682,09** |
| 2 jam | (Rp 23.121,39 × 1,5) + (Rp 23.121,39 × 2,0 × 1) | **Rp 80.924,87** |
| 3 jam | (Rp 23.121,39 × 1,5) + (Rp 23.121,39 × 2,0 × 2) | **Rp 127.167,65** |
| 4 jam | (Rp 23.121,39 × 1,5) + (Rp 23.121,39 × 2,0 × 3) | **Rp 173.410,43** |

> **Catatan:** Nilai di atas adalah **estimasi** yang ditampilkan di aplikasi dan dashboard admin. Persetujuan final tetap dilakukan oleh HR/Manager melalui alur approval.

---

## 3. Alur Status Lembur

```
[Karyawan mulai]
       │
       ▼
   RUNNING ──── (Auto-close jika > 6 jam) ───► FLAGGED
       │                                          │
       │ (Karyawan berhenti & submit)             │ (HR review manual)
       ▼                                          │
   PENDING ◄─────────────────────────────────────┘
       │
       ├──► APPROVED (HR/Manager setujui)
       │
       └──► REJECTED (HR/Manager tolak + catatan alasan)
```

| Status | Keterangan |
|---|---|
| `running` | Sesi sedang berjalan (timer aktif) |
| `pending` | Karyawan telah berhenti, menunggu persetujuan |
| `approved` | Disetujui HR/Manager, siap diproses ke payroll |
| `rejected` | Ditolak beserta catatan alasan |
| `flagged` | Di-*close* otomatis oleh sistem karena timer > 6 jam |

---

## 4. Aturan Keamanan Sistem

### Auto-Close Timer

Untuk mencegah "lupa berhenti" yang merusak data payroll, sistem menjalankan command otomatis setiap **30 menit**:

- Jika ada sesi dengan status `running` dan sudah berjalan **lebih dari 6 jam**, sistem akan:
  1. Menetapkan `end_time = start_time + 4 jam`
  2. Menghitung durasi = **240 menit**
  3. Mengubah status menjadi `flagged`
  4. Menandai `auto_closed = true` untuk diketahui HR

> **Mengapa di-cap 4 jam, bukan 6 jam?**  
> Untuk memberi buffer keamanan — jika seseorang lupa berhenti di tengah malam, durasi yang di-*record* tetap masuk akal dan tidak menggelembungkan biaya lembur.

### Bukti Foto (Evidence)

| Jenis Lembur | Bukti Foto |
|---|---|
| Lembur biasa (tanpa klien) | Opsional |
| Lembur dengan klien | **Wajib** — harus dilampirkan saat berhenti |

Foto dikompresi secara otomatis di server hingga lebar maksimal **1.200 piksel** sebelum disimpan, untuk menghemat storage.

### Satu Sesi Aktif

Sistem hanya mengizinkan **satu sesi `running` per karyawan** pada satu waktu. Jika karyawan mencoba memulai sesi kedua, permintaan akan ditolak hingga sesi yang berjalan diselesaikan.

---

## 5. Tampilan di Aplikasi Karyawan (ESS)

| Tahap | Yang Ditampilkan |
|---|---|
| **Sebelum mulai** | Form: pilih manager, nama tugas, klien (opsional) |
| **Saat berjalan** | Timer HH:MM:SS, estimasi upah real-time, tombol Stop |
| **Setelah berhenti** | Ringkasan: durasi, estimasi upah, status "Menunggu Persetujuan" |

---

## 6. Tampilan di Dashboard Admin (HR/Manager)

- **Tabel Overtime Logs**: kolom karyawan, manajer, nama tugas, klien, jam mulai, durasi, tarif/jam, estimasi upah, foto bukti, status.
- **Aksi**: Approve (individual atau bulk), Reject (dengan modal catatan alasan).
- **Widget "Live Overtime"**: menampilkan semua sesi yang sedang `running` secara *real-time* (refresh otomatis setiap 30 detik).

---

## 7. Referensi Hukum

| Regulasi | Pasal | Substansi |
|---|---|---|
| Kepmenaker No. 102/MEN/VI/2004 | Pasal 8 | Formula upah per jam = gaji pokok ÷ 173 |
| Kepmenaker No. 102/MEN/VI/2004 | Pasal 11 | Multiplier: jam pertama 1,5×, jam berikutnya 2,0× |
| UU No. 13 Tahun 2003 | Pasal 78 | Maksimum lembur 3 jam/hari dan 14 jam/minggu |

---

*Dokumen ini dibuat otomatis berdasarkan implementasi sistem PHI Connect HRIS.*
