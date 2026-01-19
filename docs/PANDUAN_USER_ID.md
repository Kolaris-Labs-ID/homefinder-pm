# Panduan Anggota Tim (User Biasa)

## Sistem Manajemen Proyek PartnersHub

Panduan lengkap untuk anggota tim dalam menggunakan sistem manajemen proyek.

---

## Daftar Isi

1. [Memulai](#memulai)
2. [Memahami Dashboard](#memahami-dashboard)
3. [Bekerja dengan Project](#bekerja-dengan-project)
4. [Menggunakan Kanban Board](#menggunakan-kanban-board)
5. [Membuat dan Mengelola Ticket](#membuat-dan-mengelola-ticket)
6. [Mengelola Daily Todo](#mengelola-daily-todo)
7. [Fitur Komentar](#fitur-komentar)
8. [Tips Produktivitas](#tips-produktivitas)

---

## Memulai

### Login ke Sistem

1. Buka browser dan akses `http://pm.partnershub.co/admin`
2. Masukkan email dan password
3. Klik **"Sign in"**

### Memahami Akses Anda

Sebagai anggota tim, Anda bisa:

| Fitur                 | Akses                      |
| --------------------- | -------------------------- |
| Project yang diikuti  | ✅ Bisa lihat              |
| Ticket di project     | ✅ Bisa buat dan update    |
| Ticket yang di-assign | ✅ Bisa pindahkan di board |
| Daily todo pribadi    | ✅ Bisa kelola             |
| Komentar              | ✅ Bisa tambah             |
| Statistik pribadi     | ✅ Bisa lihat              |

**Yang tidak bisa Anda lakukan:**

- ❌ Melihat project yang tidak diikuti
- ❌ Melihat daily todo orang lain
- ❌ Mengubah konfigurasi sistem

---

## Memahami Dashboard

### Halaman Utama

Setelah login, Anda akan melihat dashboard dengan:

| Widget              | Penjelasan                           |
| ------------------- | ------------------------------------ |
| My Assigned Tickets | Jumlah ticket yang di-assign ke Anda |
| Projects I'm In     | Jumlah project yang Anda ikuti       |
| My Created Tickets  | Ticket yang Anda buat                |
| Recent Activity     | Aktivitas terbaru                    |

### Navigasi

Gunakan sidebar kiri untuk navigasi:

```
Dashboard
├── Overview

Project Management
├── Projects
├── Project Board (Kanban)
├── Epics Overview
├── Leaderboard
├── Project Timeline
└── Ticket Timeline

Daily Todos
└── Todo pribadi Anda

Tickets
└── Daftar ticket

Analytics
└── User Contributions
```

---

## Bekerja dengan Project

### Melihat Project Anda

1. Klik menu **"Projects"** di sidebar
2. Anda akan melihat daftar project yang Anda ikuti
3. Klik project untuk melihat detail

**Catatan:** Anda hanya bisa melihat project di mana Anda sudah ditambahkan sebagai member.

### Informasi Project

Setiap project menampilkan:

- Nama dan deskripsi
- Tanggal mulai dan selesai
- Daftar anggota tim
- Ticket dan epic

### Tidak Bisa Melihat Project?

Jika Anda tidak bisa melihat project yang seharusnya:

1. Hubungi Project Manager atau Head Division
2. Minta untuk ditambahkan sebagai member

---

## Menggunakan Kanban Board

### Apa itu Kanban Board?

Kanban Board adalah tampilan visual berbentuk kolom untuk melihat semua ticket berdasarkan statusnya.

**PENTING:** Kanban bukan sekadar papan tulis. Kanban adalah **sistem manajemen aliran (Flow Management)**.

```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│   TO DO     │ PROCESSING  │   REVIEW    │    DONE     │
│    (∞)      │   (2/3)     │   (1/3)     │     ✓       │
├─────────────┼─────────────┼─────────────┼─────────────┤
│ [Ticket 1]  │ [Ticket 3]  │ [Ticket 5]  │ [Ticket 7]  │
│ [Ticket 2]  │ [Ticket 4]  │             │ [Ticket 8]  │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

### Aturan WIP Limit (WAJIB!)

**WIP Limit** adalah batasan jumlah tugas yang boleh dikerjakan bersamaan.

**Mantra:** "Stop Starting, Start Finishing." (Berhenti memulai hal baru, selesaikan dulu yang sedang berjalan)

| Aturan                     | Penjelasan                                                    |
| -------------------------- | ------------------------------------------------------------- |
| **Maksimal 3 tugas aktif** | Satu orang maksimal mengerjakan 3 klien/tugas di "Processing" |
| **Selesaikan dulu**        | Kalau slot penuh, selesaikan yang ada sebelum ambil baru      |
| **Jangan bangga sibuk**    | Yang penting tugas SELESAI, bukan SIBUK                       |

**Contoh:**

```
Anda sedang mengerjakan:
- PT X (Processing) ✓
- PT Y (Processing) ✓
- PT Z (Processing) ✓
Slot: 3/3 (PENUH)

❌ SALAH: Ambil PT baru (PT A) → PT X terbengkalai
✅ BENAR: Selesaikan PT X dulu → baru ambil PT A
```

### Mengakses Board

1. Klik menu **"Project Board"** di sidebar
2. Pilih project dari dropdown di atas
3. Board akan menampilkan semua ticket

### Memahami Kartu Ticket

Setiap kartu menampilkan:

- **UUID**: ID unik (contoh: TAX-ABC123)
- **Nama**: Judul ticket
- **Priority**: Badge warna prioritas
- **Due Date**: Tenggat waktu
- **Assignees**: Avatar penanggung jawab
- **Tombol View**: Ikon mata untuk detail

### Memindahkan Ticket

**Anda bisa memindahkan ticket yang:**

- ✅ Di-assign ke Anda
- ✅ Dibuat oleh Anda

**PENTING:** Jangan pindahkan ticket ke "Processing" kalau WIP limit sudah penuh!

**Cara memindahkan (Desktop):**

1. Cek dulu: Apakah slot Processing masih ada?
2. Klik dan tahan kartu ticket
3. Seret ke kolom tujuan
4. Lepaskan mouse
5. Status otomatis berubah

**Cara memindahkan (Mobile):**

1. Cek dulu: Apakah slot Processing masih ada?
2. Tekan dan tahan kartu (sekitar 0.5 detik)
3. Seret ke kolom tujuan
4. Lepaskan
5. Status otomatis berubah

### Mode View Only

Jika Anda melihat banner **"View Only Mode"**:

- Anda tidak bisa memindahkan ticket
- Anda hanya bisa melihat
- Hubungi Head Division jika butuh akses

### Sorting (Pengurutan)

Klik ikon ⋮ di header kolom untuk mengurutkan:

| Opsi                  | Penjelasan            |
| --------------------- | --------------------- |
| Date created (newest) | Terbaru di atas       |
| Date created (oldest) | Terlama di atas       |
| Card name             | Urut abjad            |
| Due date              | Berdasarkan deadline  |
| Priority              | Berdasarkan prioritas |

### Filter by User

1. Klik tombol **"Filter by User"**
2. Pilih user (termasuk diri sendiri)
3. Board hanya menampilkan ticket user tersebut
4. Kosongkan untuk melihat semua

**Tips:** Filter diri sendiri untuk fokus pada ticket Anda.

---

## Membuat dan Mengelola Ticket

### Membuat Ticket Baru

**Cara 1: Dari Menu Tickets**

1. Klik menu **"Tickets"**
2. Klik **"New Ticket"**
3. Isi form

**Cara 2: Dari Kanban Board**

1. Buka **"Project Board"**
2. Pilih project
3. Klik **"New Ticket"** di pojok kanan atas

### Form Ticket

| Field       | Wajib | Penjelasan              |
| ----------- | ----- | ----------------------- |
| Project     | Ya    | Pilih project           |
| Name        | Ya    | Judul ticket yang jelas |
| Description | Tidak | Deskripsi detail        |
| Status      | Ya    | Status awal             |
| Priority    | Tidak | Tingkat urgensi         |
| Epic        | Tidak | Kelompok epic           |
| Assignees   | Tidak | Penanggung jawab        |
| Start Date  | Tidak | Tanggal mulai           |
| Due Date    | Tidak | Tenggat waktu           |

### Tips Membuat Ticket

**✅ Judul yang Baik:**

- "Implementasi fitur login dengan Google"
- "Fix bug: Tombol submit error di mobile"
- "Buat halaman dashboard user"

**❌ Judul yang Buruk:**

- "Bug"
- "Task"
- "Perbaiki"

**Tips Description:**

- Jelaskan apa yang harus dilakukan
- Sertakan kriteria selesai
- Tambahkan link referensi jika ada

### Mengedit Ticket

**Anda bisa edit ticket yang:**

- ✅ Di-assign ke Anda
- ✅ Dibuat oleh Anda

**Langkah:**

1. Buka ticket (klik kartu atau tombol view)
2. Klik **"Edit"**
3. Ubah yang diperlukan
4. Klik **"Save"**

### Update Status Ticket

**Cara 1: Drag and Drop**

- Seret ticket ke kolom status baru di board

**Cara 2: Edit Ticket**

1. Edit ticket
2. Ubah dropdown Status
3. Simpan

### Memahami Status

| Status      | Penjelasan                  |
| ----------- | --------------------------- |
| To Do       | Belum dikerjakan            |
| In Progress | Sedang dikerjakan           |
| Review      | Selesai, menunggu review    |
| Done        | Selesai dan terverifikasi ✓ |

**Tips:** Pindahkan ticket sesuai progress Anda.

---

## Mengelola Daily Todo

### Apa itu Daily Todo?

Daily Todo adalah daftar tugas harian personal. Berbeda dengan ticket project, daily todo lebih bersifat personal.

**Catatan:** Anda hanya bisa melihat dan mengelola daily todo **pribadi Anda**.

### Membuat Daily Todo

1. Klik menu **"Daily Todos"**
2. Klik **"New To-Do"**
3. Isi form:

| Field       | Wajib | Penjelasan                 |
| ----------- | ----- | -------------------------- |
| Date        | Ya    | Tanggal todo               |
| Title       | Ya    | Judul tugas                |
| Description | Tidak | Detail tugas               |
| Status      | Ya    | Pending, In Progress, Done |

4. Klik **"Create"**

### Status Daily Todo

| Status      | Warna   | Penjelasan        |
| ----------- | ------- | ----------------- |
| Pending     | Abu-abu | Belum dimulai     |
| In Progress | Kuning  | Sedang dikerjakan |
| Done        | Hijau   | Selesai           |

### Mengelola Todo

**Edit Todo:**

1. Klik todo yang ingin diedit
2. Klik **"Edit"**
3. Ubah yang diperlukan
4. Simpan

**Hapus Todo:**

1. Buka todo
2. Klik **"Delete"**
3. Konfirmasi

### Filter Todo

| Filter           | Fungsi                      |
| ---------------- | --------------------------- |
| Filter by Date   | Lihat todo tanggal tertentu |
| Today's Todos    | Lihat todo hari ini         |
| Filter by Status | Pending, In Progress, Done  |

### Workflow Daily Todo

**Pagi:**

- 📋 Review todo hari ini
- ➕ Buat todo baru jika perlu
- 🎯 Prioritaskan tugas penting

**Selama Kerja:**

- 🔄 Update status ke "In Progress" saat mulai
- ✅ Update ke "Done" saat selesai
- ➕ Tambah todo baru jika ada tugas mendadak

**Sore:**

- ✅ Review semua todo
- 📝 Tandai yang selesai
- 📅 Buat todo untuk besok

---

## Fitur Komentar

### Menambah Komentar

Komentar berguna untuk:

- Bertanya tentang ticket
- Memberikan update progress
- Diskusi dengan tim
- Dokumentasi keputusan

**Langkah:**

1. Buka detail ticket
2. Scroll ke bagian **Comments**
3. Ketik komentar
4. Klik **"Post Comment"**

### Tips Komentar

✅ **Do:**

- Jelas dan ringkas
- Sertakan detail yang relevan
- Respond pertanyaan dengan cepat
- Gunakan bahasa profesional

❌ **Don't:**

- Komentar yang tidak relevan
- Spam atau duplikat
- Bahasa tidak sopan

---

## Melihat Epic

### Apa itu Epic?

Epic adalah kumpulan ticket yang saling berhubungan untuk satu fitur atau tujuan besar.

### Melihat Epic

1. Klik menu **"Epics Overview"**
2. Pilih project
3. Lihat semua epic dan ticket-nya
4. Klik epic untuk expand/collapse

### Manfaat Epic

- Memahami konteks ticket Anda
- Melihat progress fitur besar
- Koordinasi dengan tim

---

## Analytics

### User Contributions

1. Klik menu **"User Contributions"**
2. Lihat statistik Anda:
    - Aktivitas harian
    - Ticket yang di-assign
    - Ticket yang diselesaikan
3. Filter berdasarkan periode

**Catatan:** Anda hanya bisa melihat statistik **pribadi Anda**.

### Leaderboard

1. Klik menu **"Leaderboard"**
2. Pilih project
3. Lihat ranking tim

---

## Tips Produktivitas

### 3 Aturan Main Kanban (WAJIB!)

#### 1. VISUALISASIKAN

> "Masukkan semua ke pm.partnershub.co. Tidak ada tiket = Tidak dikerjakan."

#### 2. BATASI (LIMIT)

> "Jangan bangga kalau status 'Processing' kamu ada 10. Itu artinya kamu tidak fokus. Maksimal 3."

#### 3. TARIK (PULL), JANGAN DORONG (PUSH)

> "Ambil tugas baru HANYA jika tugas lama sudah selesai. Jangan terima dorongan tugas kalau tanganmu penuh."

### Workflow Harian

**Pagi:**

1. 📊 Cek dashboard
2. 📋 Cek ticket "Processing" - apa yang sedang dikerjakan?
3. 🎯 Cek WIP: Apakah slot masih ada?

**Mulai Kerja:**

1. ✅ Jika ada slot → Ambil ticket dari "To Do"
2. ❌ Jika slot penuh → Selesaikan yang ada dulu!
3. 🔄 Update status ticket saat mulai

**Selesai Task:**

1. ✅ Pindahkan ke "Review" atau "Done"
2. 💬 Tambah komentar untuk update
3. 🆕 Baru boleh ambil ticket baru

**Akhir Hari:**

1. ✅ Review semua ticket
2. 📝 Update status yang belum
3. 📅 Cek deadline untuk besok

### Tips Kanban

- **Batasi WIP:** Maksimal 3 tugas aktif per orang
- **Selesaikan Dulu:** Jangan ambil baru kalau slot penuh
- **Update Rutin:** Pindahkan ticket sesuai progress
- **Cek Due Date:** Perhatikan deadline

### Tips Ticket

- **Judul Jelas:** Gunakan judul yang deskriptif
- **Description Lengkap:** Sertakan detail yang diperlukan
- **Update Status:** Jangan biarkan ticket stuck
- **Komentar:** Dokumentasi progress via komentar

### Tips Daily Todo

- **Buat Pagi:** Buat todo di awal hari
- **Update Rutin:** Update status sepanjang hari
- **Review Sore:** Review dan plan untuk besok
- **Realistis:** Buat todo yang achievable

---

## Troubleshooting

### Tidak Bisa Melihat Project

**Penyebab:** Anda belum ditambahkan sebagai member.

**Solusi:** Hubungi Project Manager atau Head Division untuk ditambahkan.

### Tidak Bisa Memindahkan Ticket

**Penyebab:**

- Ticket tidak di-assign ke Anda
- Anda bukan pembuat ticket
- Mode View Only

**Solusi:**

- Minta di-assign ke ticket tersebut
- Hubungi Head Division untuk akses

### Ticket Tidak Muncul di Board

**Penyebab:**

- Filter aktif
- Project salah

**Solusi:**

1. Clear filter
2. Pastikan project benar
3. Refresh halaman

### Daily Todo Tidak Muncul

**Penyebab:**

- Filter tanggal aktif
- Filter status aktif

**Solusi:**

1. Clear semua filter
2. Cek range tanggal

---

## Referensi Cepat

### Aksi Umum

| Aksi            | Cara                                    |
| --------------- | --------------------------------------- |
| Lihat board     | Project Board → Pilih project           |
| Buat ticket     | Board: New Ticket / Tickets: New Ticket |
| Update status   | Drag ticket ke kolom baru               |
| Tambah komentar | Buka ticket → Comments                  |
| Buat todo       | Daily Todos → New To-Do                 |
| Filter board    | Filter by User → Pilih user             |

### Shortcut Status

| Dari       | Ke         | Artinya                  | Cek WIP? |
| ---------- | ---------- | ------------------------ | -------- |
| To Do      | Processing | Mulai mengerjakan        | ✅ Ya!   |
| Processing | Review     | Selesai, minta review    | Tidak    |
| Review     | Done       | Sudah di-review, selesai | Tidak    |
| Review     | Processing | Ada revisi               | ✅ Ya!   |

### Warna Priority

| Priority | Warna  | Artinya          |
| -------- | ------ | ---------------- |
| Critical | Merah  | Sangat urgent    |
| High     | Orange | Prioritas tinggi |
| Medium   | Kuning | Normal           |
| Low      | Hijau  | Bisa ditunda     |

---

## Butuh Bantuan?

- **Masalah Akses:** Hubungi HR atau Head Division
- **Pertanyaan Fitur:** Hubungi Head Division
- **Masalah Teknis:** Hubungi IT Support

---

**Dokumen ini terakhir diupdate:** Januari 2026
**Versi:** 2.0
