# Panduan Lengkap Kanban Board

## Sistem Manajemen Proyek PartnersHub

Panduan detail tentang cara menggunakan Kanban Board untuk mengelola pekerjaan tim.

---

## Daftar Isi

1. [Apa itu Kanban? (Bukan Sekadar Papan Tulis)](#apa-itu-kanban-bukan-sekadar-papan-tulis)
2. [Prinsip Dasar Kanban](#prinsip-dasar-kanban)
3. [Aturan WIP Limit (Work In Progress)](#aturan-wip-limit-work-in-progress)
4. [Penerapan per Divisi](#penerapan-per-divisi)
5. [Mengakses Kanban Board](#mengakses-kanban-board)
6. [Cara Memindahkan Ticket](#cara-memindahkan-ticket)
7. [Fitur Sorting dan Filter](#fitur-sorting-dan-filter)
8. [Mengatur Status Kolom](#mengatur-status-kolom)
9. [Aturan Main (3 Poin Utama)](#aturan-main-3-poin-utama)
10. [FAQ dan Troubleshooting](#faq-dan-troubleshooting)

---

## Apa itu Kanban? (Bukan Sekadar Papan Tulis)

### Kesalahpahaman Umum

Banyak orang mengira Kanban itu cuma soal memindahkan kartu dari kiri ke kanan. **Salah.**

**Kanban adalah sistem manajemen aliran (Flow Management).**

### Analogi Jalan Tol

Bayangkan jalan tol:

**Tanpa Kanban:**

```
🚗🚗🚗🚗🚗🚗🚗🚗🚗🚗 → MACET TOTAL!
Semua mobil (tugas) masuk sekaligus.
Hasilnya? Tidak ada yang sampai tujuan.
```

**Dengan Kanban:**

```
🚗 → 🚗 → 🚗 → LANCAR!
Kita batasi jumlah mobil yang boleh masuk.
Hasilnya? Mobil bisa ngebut lancar sampai tujuan.
```

### Mengapa Ini Penting?

| Tanpa Kanban                     | Dengan Kanban                           |
| -------------------------------- | --------------------------------------- |
| Semua tugas dikerjakan sekaligus | Fokus pada tugas yang sedang berjalan   |
| Banyak yang setengah jadi        | Tugas selesai satu per satu             |
| Tidak tahu mana yang prioritas   | Jelas mana yang harus diselesaikan dulu |
| Masalah tersembunyi              | Masalah langsung terlihat               |

---

## Prinsip Dasar Kanban

### 3 Prinsip Utama

#### 1. Visualisasikan Kerja

> "Kalau tidak terlihat, tidak bisa diatur."

Semua pekerjaan HARUS masuk ke sistem. Tidak ada tiket = tidak dikerjakan.

#### 2. Batasi WIP (Work In Progress)

> "Stop Starting, Start Finishing."
> (Berhenti memulai hal baru, selesaikan dulu yang sedang berjalan)

Jangan kerjakan semua sekaligus. Batasi jumlah tugas yang boleh dikerjakan bersamaan.

#### 3. Fokus pada Aliran (Flow)

> "Seberapa cepat tugas selesai, bukan seberapa sibuk orangnya."

Yang penting bukan berapa banyak yang dikerjakan, tapi berapa banyak yang **SELESAI**.

---

## Aturan WIP Limit (Work In Progress)

### Apa itu WIP Limit?

**WIP Limit** adalah batasan jumlah kartu (tugas) yang boleh ada di satu kolom status atau dikerjakan oleh satu orang pada waktu yang sama.

### Mengapa Harus Dibatasi?

#### 1. Mencegah Context Switching

Otak manusia butuh **20 menit** untuk fokus kembali setelah terganggu.

```
Mengerjakan 5 klien sekaligus = 40% waktu habis cuma buat "ingat-ingat lagi tadi sampai mana"
```

**Contoh:**

- Staf A mengerjakan SPT PT X, tiba-tiba pindah ke PT Y
- Saat kembali ke PT X, butuh waktu untuk ingat: "Tadi sampai mana ya?"
- Waktu 20 menit terbuang setiap kali pindah tugas

#### 2. Mendeteksi Masalah Lebih Cepat

Kalau kolom "Review" dibatasi 3 kartu, dan sekarang ada 3 kartu macet di sana:

- Staf **DILARANG** mengambil kerjaan baru
- Staf **DIPAKSA** untuk menyelesaikan (atau membantu menyelesaikan) 3 kartu itu dulu

**Hasil:** Masalah tidak ditumpuk di bawah karpet.

#### 3. Mencegah Kerja Sia-sia

Kalau klien belum kasih feedback untuk 4 desain, jangan buat desain ke-5!

- Desain ke-5 kemungkinan besar akan ditolak atau minta revisi juga
- Lebih baik follow-up klien dulu

### Rekomendasi WIP Limit

| Situasi                | WIP Limit      | Penjelasan                                    |
| ---------------------- | -------------- | --------------------------------------------- |
| Per Orang (Processing) | **Maksimal 3** | Satu orang maksimal mengerjakan 3 klien aktif |
| Kolom Review           | **3-5 kartu**  | Mencegah penumpukan di tahap review           |
| Kolom Client Review    | **4 kartu**    | Mencegah desain menumpuk tanpa feedback       |
| Kolom Ready to Pay     | **10 tiket**   | Mencegah pembayaran tertunda                  |

---

## Penerapan per Divisi

### A. Divisi Accounting & Tax (Model: The Factory Line)

**Karakteristik:** Repetitif, Deadline Ketat, Volume Tinggi

**Kolom Kanban:**

```
To Do → Gathering Data → Processing → Review → Submitted → Done
```

**WIP Rules:**

- **Per Orang:** Maksimal 3 Klien Aktif di tahap "Processing"

**Skenario:**

```
Staf A sedang memproses Laporan Pajak:
- PT X (Processing) ✓
- PT Y (Processing) ✓
- PT Z (Processing) ✓
Slot: 3/3 (PENUH)

Masalah: Data PT X belum lengkap dari klien.
```

**❌ Tindakan SALAH (Tanpa WIP):**

- Tinggalkan PT X, ambil PT baru (PT A)
- Akhirnya PT X terbengkalai berbulan-bulan

**✅ Tindakan BENAR (Dengan WIP):**

- Karena slot penuh (3/3), Staf A **tidak boleh** ambil PT A
- Staf A harus:
    1. Kejar klien PT X sampai data lengkap, ATAU
    2. Selesaikan PT Y dulu sampai "Submitted"
- Baru setelah ada slot kosong, boleh ambil PT baru

---

### B. Divisi Legal (Model: The Relay Race)

**Karakteristik:** Ketergantungan Pihak Ketiga (Notaris, Kemenkumham), Sequential

**Kolom Kanban:**

```
Draft Akta → Tanda Tangan (Notaris) → SK Kemenkumham → OSS/NIB → Done
```

**WIP Rules:**

- **Kolom "Tanda Tangan":** Limit 5 Project

**Skenario:**

```
Ada 5 pendirian PT yang statusnya "Menunggu Tanda Tangan Klien/Notaris"
Slot: 5/5 (PENUH)
```

**Tindakan:**

- Tim Legal **tidak boleh** buat Draft Akta baru
- Kenapa? Percuma nambah draft kalau di notaris macet
- **Solusi:** Tim Legal harus follow-up klien atau notaris agar ada yang geser ke "SK Kemenkumham"
- Baru setelah ada slot kosong, boleh mulai draft baru

**Manfaat:** Mencegah dokumen menumpuk di meja notaris dan hilang.

---

### C. Divisi Commerce (Model: The Creative Studio)

**Karakteristik:** Ide Abstrak, Revisi Klien, Kerja Lapangan

**Kolom Kanban:**

```
Idea/Backlog → Production/Visit → Client Review → Scheduled/Posted
```

**WIP Rules:**

- **Kolom "Client Review":** Limit 4 Konten

**Skenario:**

```
Tim desain sudah kirim 4 desain ke klien tapi belum ada feedback.
Slot Client Review: 4/4 (PENUH)
```

**Tindakan:**

- **Jangan desain konten ke-5!**
- Desainer nganggur? Jangan desain baru!
- Gunakan waktu untuk:
    1. Follow-up klien minta revisi/acc
    2. Lakukan riset untuk konten berikutnya
    3. Rapikan file dan dokumentasi

**Logika:** Desain ke-5 kemungkinan besar akan ditolak atau minta revisi juga kalau selera klien di 4 desain awal belum jelas. Mencegah kerja sia-sia.

---

### D. Divisi Finance (Model: The Gatekeeper)

**Karakteristik:** Validasi, Eksekusi Cepat, Anti-Error

**Kolom Kanban:**

```
Inbox (Request Masuk) → Validasi (Cek Bukti) → Ready to Pay → Paid
```

**WIP Rules:**

- **Kolom "Ready to Pay":** Limit 10 Tiket

**Skenario:**

```
Ada 10 tiket (Gaji, Reimbursement, Vendor) yang sudah divalidasi
tapi belum ditransfer (menunggu token/approval Manager).
Slot Ready to Pay: 10/10 (PENUH)
```

**Tindakan:**

- Staf Finance **stop validasi tiket baru**
- Mereka harus "teriak" ke Manager: "Pak, tolong approve dulu yang 10 ini, aliran macet."

**Manfaat:** Mencegah staf validasi 50 tiket tapi uangnya tidak keluar-keluar, yang ujungnya dikomplain vendor/karyawan.

---

## Mengakses Kanban Board

### Langkah-langkah

1. Login ke sistem di `http://pm.partnershub.co/admin`
2. Klik menu **"Project Board"** di sidebar kiri
3. Pilih project dari dropdown di bagian atas
4. Board akan menampilkan semua ticket

### Tampilan Board

```
┌──────────────────────────────────────────────────────────┐
│  Project Board                    [Filter] [New Ticket]  │
├──────────────────────────────────────────────────────────┤
│  Project: [Dropdown Pilih Project ▼]                     │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐     │
│  │ TO DO   │  │PROCESSING│ │ REVIEW  │  │  DONE   │     │
│  │  (∞)    │  │  (3/3)  │  │  (2/3)  │  │   ✓     │     │
│  ├─────────┤  ├─────────┤  ├─────────┤  ├─────────┤     │
│  │[Ticket] │  │[Ticket] │  │[Ticket] │  │[Ticket] │     │
│  │[Ticket] │  │[Ticket] │  │[Ticket] │  │[Ticket] │     │
│  │[Ticket] │  │[Ticket] │  │         │  │[Ticket] │     │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘     │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Informasi di Kartu Ticket

| Elemen             | Penjelasan                     |
| ------------------ | ------------------------------ |
| **UUID**           | ID unik ticket (PREFIX-RANDOM) |
| **Nama**           | Judul ticket                   |
| **Priority Badge** | Warna sesuai prioritas         |
| **Due Date**       | Tenggat waktu (jika ada)       |
| **Assignees**      | Avatar penanggung jawab        |
| **View Button**    | Tombol untuk melihat detail    |

### Warna Priority

| Priority | Warna     | Urgensi                 |
| -------- | --------- | ----------------------- |
| Critical | 🔴 Merah  | Deadline hari ini/besok |
| High     | 🟠 Orange | Deadline minggu ini     |
| Medium   | 🟡 Kuning | Normal                  |
| Low      | 🟢 Hijau  | Bisa ditunda            |

---

## Cara Memindahkan Ticket

### Siapa yang Bisa Memindahkan?

| Role          | Bisa Pindahkan                               |
| ------------- | -------------------------------------------- |
| Head Division | ✅ Ticket di project-nya                     |
| Member        | ⚠️ Ticket yang di-assign atau dibuat sendiri |

### Cara Memindahkan (Desktop)

1. **Arahkan mouse** ke kartu ticket
2. **Klik dan tahan** kartu tersebut
3. **Seret (drag)** kartu ke kolom tujuan
4. **Lepaskan (drop)** kartu di kolom baru
5. Status ticket **otomatis berubah**

### Cara Memindahkan (Mobile/Touch)

1. **Tekan dan tahan** kartu ticket (sekitar 0.5 detik)
2. Kartu akan "terangkat" dan bisa digerakkan
3. **Seret** kartu ke kolom tujuan
4. **Lepaskan** jari dari layar

### Kapan Memindahkan Ticket?

| Dari       | Ke         | Kapan                            |
| ---------- | ---------- | -------------------------------- |
| To Do      | Processing | Saat **mulai** mengerjakan       |
| Processing | Review     | Saat **selesai** dan butuh dicek |
| Review     | Done       | Setelah **disetujui** supervisor |
| Review     | Processing | Jika ada **revisi**              |

**PENTING:** Jangan pindahkan ticket ke "Processing" kalau WIP limit sudah penuh!

---

## Fitur Sorting dan Filter

### Sorting (Pengurutan)

Klik ikon **⋮** (titik tiga) di header kolom:

| Opsi                  | Penjelasan                  |
| --------------------- | --------------------------- |
| Date created (newest) | Ticket terbaru di atas      |
| Date created (oldest) | Ticket terlama di atas      |
| Card name             | Urut abjad A-Z              |
| Due date              | Deadline terdekat di atas   |
| Priority              | Prioritas tertinggi di atas |

**Tips:** Gunakan **Due date** untuk fokus pada deadline terdekat.

### Filter by User

1. Klik tombol **"Filter by User"**
2. Pilih nama staff
3. Board hanya menampilkan ticket staff tersebut

**Kegunaan:**

- Cek workload anggota tim
- Review progress individual
- Identifikasi siapa yang overload

---

## Mengatur Status Kolom

### Siapa yang Bisa Mengatur?

| Role          | Bisa Mengatur Status |
| ------------- | -------------------- |
| Head Division | ✅ Di project-nya    |
| Member        | ❌ Tidak             |

### Contoh Konfigurasi Status per Divisi

**Divisi Tax & Accounting:**

```
To Do (10) → Gathering Data (20) → Processing (30) → Review (40) → Submitted (50) → Done (60)
```

**Divisi Legal:**

```
Draft Akta (10) → Tanda Tangan (20) → SK Kemenkumham (30) → OSS/NIB (40) → Done (50)
```

**Divisi Commerce:**

```
Idea/Backlog (10) → Production (20) → Client Review (30) → Scheduled/Posted (40)
```

**Divisi Finance:**

```
Inbox (10) → Validasi (20) → Ready to Pay (30) → Paid (40)
```

### Cara Membuat Status Baru

1. Buka project
2. Klik tab **"Ticket Statuses"**
3. Klik **"Create"**
4. Isi form:
    - **Name**: Nama status
    - **Color**: Warna kolom
    - **Sort Order**: Urutan (angka kecil = kiri)
    - **Is Completed**: Centang untuk status akhir
5. Klik **"Create"**

---

## Aturan Main (3 Poin Utama)

### 1. VISUALISASIKAN

> "Masukkan semua ke pm.partnershub.co. Tidak ada tiket = Tidak dikerjakan."

- Semua pekerjaan HARUS ada tiketnya
- Kalau tidak ada di sistem, dianggap tidak ada
- Ini membantu manajemen melihat beban kerja sebenarnya

### 2. BATASI (LIMIT)

> "Jangan bangga kalau status 'In Progress' kamu ada 10. Itu artinya kamu tidak fokus. Maksimal 3."

- Batasi pekerjaan yang sedang dikerjakan
- Fokus selesaikan yang ada sebelum ambil baru
- Kalau WIP penuh, selesaikan dulu atau minta bantuan

### 3. TARIK (PULL), JANGAN DORONG (PUSH)

> "Ambil tugas baru (Pull) HANYA jika tugas lama sudah selesai atau geser ke kanan. Jangan terima dorongan tugas (Push) kalau tanganmu penuh."

- Ambil tugas baru hanya kalau ada slot kosong
- Jangan terima tugas baru kalau masih penuh
- Kalau dipaksa, bilang: "Slot saya penuh, mana yang harus saya tinggalkan?"

---

## FAQ dan Troubleshooting

### "Tapi Nanti Kerjaan Numpuk di To-Do?"

**Pertanyaan:** "Pak, kalau saya dibatasi cuma boleh kerjakan 3, nanti antrean di 'To Do' makin panjang dong?"

**Jawaban:**

Antrean panjang di "To Do" itu **JUJUR**. Itu menunjukkan beban kerja kita yang sebenarnya.

**Lebih baik:**

```
Antrean panjang di depan pintu (belum dimulai)
```

**Daripada:**

```
Macet di dalam ruangan (setengah jadi)
```

Kalau antrean di To Do panjang tapi WIP kita sudah maksimal, itu **sinyal buat Management** untuk:

- Menambah orang
- Menolak klien baru
- Memprioritaskan ulang

Tapi kalau kalian paksakan semua masuk "In Progress", seolah-olah semua sedang dikerjakan padahal macet, Management jadi **buta**. Dikira semua aman, padahal bom waktu.

### Board Tidak Menampilkan Ticket

**Penyebab:**

- Filter aktif
- Project salah

**Solusi:**

1. Clear filter
2. Pastikan project benar
3. Refresh board

### Tidak Bisa Drag and Drop

**Penyebab:**

- Mode View Only
- Tidak punya permission

**Solusi:**

1. Cek apakah ticket di-assign ke Anda
2. Hubungi Head Division jika butuh akses

### Kolom Tidak Muncul

**Penyebab:**

- Status belum dibuat
- Sort order salah

**Solusi:**

1. Minta Head Division buat status
2. Cek sort order

---

## Workflow Harian per Divisi

### Staff Tax & Accounting

```
Pagi:
1. Buka Project Board
2. Cek ticket "Processing" - pekerjaan yang sedang dikerjakan
3. Cek ticket "Gathering Data" - follow up ke klien jika perlu

Mulai Kerja:
1. Cek WIP: Apakah slot Processing masih ada?
2. Jika ada slot → Ambil ticket dari "To Do" atau "Gathering Data" yang sudah lengkap
3. Jika slot penuh → Selesaikan yang ada dulu!

Selesai Task:
1. Pindahkan ke "Review" untuk dicek supervisor
2. Jika sudah disubmit ke DJP → pindahkan ke "Submitted"
3. Setelah BPE dikirim ke klien → pindahkan ke "Done"

Akhir Hari:
1. Update status semua ticket
2. Catat progress di komentar jika perlu
```

### Staff Legal

```
Pagi:
1. Buka Project Board
2. Cek ticket "Tanda Tangan" - follow up ke notaris/klien
3. Cek ticket "SK Kemenkumham" atau "OSS/NIB" - cek status

Mulai Kerja:
1. Cek WIP: Apakah slot Tanda Tangan masih ada?
2. Jika ada slot → Boleh buat draft akta baru
3. Jika slot penuh → Follow up yang macet dulu!

Selesai Task:
1. Update status sesuai tahapan
2. Catat nomor akta/NIB di komentar
3. Pindahkan ke "Done" jika dokumen sudah dikirim ke klien
```

### Staff Commerce

```
Pagi:
1. Buka Project Board
2. Cek ticket "Client Review" - ada feedback?
3. Cek ticket "Production" - apa yang sedang dikerjakan

Mulai Kerja:
1. Cek WIP: Apakah slot Client Review masih ada?
2. Jika ada slot → Boleh kirim desain baru ke klien
3. Jika slot penuh → Follow up feedback dulu!

Selesai Task:
1. Pindahkan ke "Client Review" setelah kirim ke klien
2. Jika ada revisi → kerjakan revisi
3. Setelah publish → pindahkan ke "Posted"
```

---

## Ringkasan

| Prinsip              | Penjelasan                              |
| -------------------- | --------------------------------------- |
| **Visualisasikan**   | Semua pekerjaan harus ada tiketnya      |
| **Batasi WIP**       | Maksimal 3 tugas aktif per orang        |
| **Pull, bukan Push** | Ambil tugas baru hanya jika ada slot    |
| **Fokus pada Flow**  | Yang penting tugas SELESAI, bukan SIBUK |

**Ingat:** Kanban bukan tentang seberapa banyak yang dikerjakan, tapi seberapa banyak yang **SELESAI**.

---

**Dokumen ini terakhir diupdate:** Januari 2026
**Versi:** 2.0
