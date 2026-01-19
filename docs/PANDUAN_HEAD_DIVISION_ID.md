# Panduan Kepala Divisi (Head Division)

## Sistem Manajemen Proyek PartnersHub

Panduan lengkap untuk Kepala Divisi dalam mengelola tim dan pekerjaan divisi.

---

## Daftar Isi

1. [Memahami Peran Anda](#memahami-peran-anda)
2. [Akses dan Hak Istimewa](#akses-dan-hak-istimewa)
3. [Manajemen Project](#manajemen-project)
4. [Assign Ticket ke Tim](#assign-ticket-ke-tim)
5. [Monitoring Daily Todo Divisi](#monitoring-daily-todo-divisi)
6. [Menggunakan Kanban Board](#menggunakan-kanban-board)
7. [Koordinasi dengan C-Level](#koordinasi-dengan-c-level)
8. [Analytics dan Reporting](#analytics-dan-reporting)

---

## Memahami Peran Anda

### Apa itu Head Division?

Head Division (Kepala Divisi) adalah peran kepemimpinan yang menjembatani antara manajemen (C-Level) dan anggota tim. Anda bertanggung jawab untuk:

- 📋 Mengelola project dan ticket divisi
- 👥 Mendistribusikan tugas ke anggota tim
- 📊 Memantau progress dan produktivitas tim
- 🔄 Koordinasi dengan C-Level

### Alur Kerja Anda

```
C-Level (CEO, COO, CTO, HR)
         ↓
    Assign tugas ke Anda
         ↓
Head Division (Anda)
         ↓
    Distribusikan ke tim
         ↓
Anggota Tim (Member)
```

### Perbedaan dengan Role Lain

| Aspek                 | Head Division     | C-Level                   | Member           |
| --------------------- | ----------------- | ------------------------- | ---------------- |
| Assign ticket         | ✅ Ke siapa saja  | ⚠️ Hanya ke Head Division | ⚠️ Terbatas      |
| Lihat daily todo      | ✅ Anggota divisi | ✅ Semua                  | ❌ Hanya pribadi |
| Buat project          | ✅ Ya             | ✅ Ya                     | ❌ Tidak         |
| Tambah member project | ✅ Siapa saja     | ⚠️ Hanya Head Division    | ❌ Tidak         |

---

## Akses dan Hak Istimewa

### Yang Bisa Anda Lakukan

✅ **Project Management:**

- Membuat project baru
- Menambahkan siapa saja ke project (tanpa batasan)
- Mengelola ticket dan epic
- Mengatur status kanban

✅ **Ticket Assignment:**

- Assign ticket ke siapa saja (tidak ada batasan role)
- Reassign ticket jika perlu
- Multi-assign ke beberapa orang

✅ **Daily Todo:**

- Melihat semua daily todo anggota divisi Anda
- Membuat todo untuk anggota divisi
- Mengelola todo anggota divisi

✅ **Analytics:**

- Melihat statistik divisi
- Melihat kontribusi anggota tim
- Melihat leaderboard project

### Yang Tidak Bisa Anda Lakukan

❌ Melihat daily todo divisi lain
❌ Akses konfigurasi sistem

---

## Manajemen Project

### Membuat Project Baru

**Langkah-langkah:**

1. Buka menu **"Projects"** di sidebar
2. Klik **"New Project"**
3. Isi form:

| Field         | Wajib | Penjelasan     | Contoh                         |
| ------------- | ----- | -------------- | ------------------------------ |
| Name          | Ya    | Nama project   | "Mobile App Development"       |
| Description   | Tidak | Deskripsi      | "Pengembangan aplikasi mobile" |
| Ticket Prefix | Ya    | Kode ID ticket | "MOB"                          |
| Start Date    | Tidak | Tanggal mulai  | 01/02/2024                     |
| End Date      | Tidak | Target selesai | 30/04/2024                     |

4. Klik **"Create"**

### Menambah Anggota ke Project

**Keuntungan Anda:** Sebagai Head Division, Anda bisa menambahkan **siapa saja** ke project tanpa batasan role.

**Langkah-langkah:**

1. Buka project
2. Klik tab **"Members"**
3. Klik **"Add Member"** atau **"Attach"**
4. Pilih user dari dropdown
5. Klik **"Attach"**

**Anda bisa menambahkan:**

- ✅ Anggota tim biasa
- ✅ Head Division lain
- ✅ C-Level (CEO, COO, CTO, HR)
- ✅ Siapa saja di organisasi

### Mengatur Status Kanban

1. Buka project
2. Klik tab **"Ticket Statuses"**
3. Klik **"Create"** untuk status baru
4. Isi:
    - **Name**: Nama status
    - **Color**: Warna
    - **Sort Order**: Urutan kolom
    - **Is Completed**: Centang untuk status akhir
5. Simpan

**Contoh Status untuk Tim Development:**

| Status      | Sort Order | Warna   |
| ----------- | ---------- | ------- |
| Backlog     | 1          | Abu-abu |
| To Do       | 2          | Kuning  |
| In Progress | 3          | Biru    |
| Code Review | 4          | Ungu    |
| Testing     | 5          | Orange  |
| Done        | 6          | Hijau ✓ |

---

## Assign Ticket ke Tim

### Membuat dan Assign Ticket

**Langkah-langkah:**

1. Buka menu **"Tickets"** atau **"Project Board"**
2. Klik **"New Ticket"**
3. Isi form:

| Field         | Penjelasan                     |
| ------------- | ------------------------------ |
| Project       | Pilih project                  |
| Name          | Judul ticket yang jelas        |
| Description   | Deskripsi detail               |
| Status        | Status awal (biasanya "To Do") |
| Priority      | Tingkat urgensi                |
| Epic          | Kelompok epic (opsional)       |
| **Assignees** | Pilih penanggung jawab         |
| Start Date    | Tanggal mulai                  |
| Due Date      | Tenggat waktu                  |

4. Di field **Assignees**, pilih satu atau lebih anggota tim
5. Klik **"Create"**

### Keuntungan Assignment Anda

Sebagai Head Division, Anda memiliki **fleksibilitas penuh** dalam assign ticket:

- ✅ Bisa assign ke anggota tim biasa
- ✅ Bisa assign ke Head Division lain
- ✅ Bisa assign ke C-Level
- ✅ Bisa assign ke siapa saja di project

**Catatan:** Anda akan melihat pesan: _"Head Division can assign to any project member"_

### Multi-User Assignment

Untuk tugas kolaboratif, assign ke beberapa orang:

1. Edit ticket
2. Di field **Assignees**, pilih beberapa user
3. Simpan

Semua assignee bisa:

- Melihat ticket
- Update ticket
- Memindahkan ticket di board

### Reassign Ticket

Jika perlu mengubah penanggung jawab:

1. Edit ticket
2. Ubah field **Assignees**
3. Simpan

**Kapan reassign:**

- Anggota tim overload
- Anggota tim tidak available
- Butuh skill berbeda

---

## Monitoring Daily Todo Divisi

### Melihat Todo Anggota Divisi

Sebagai Head Division, Anda bisa melihat **semua daily todo anggota divisi Anda**.

**Langkah-langkah:**

1. Buka menu **"Daily Todos"**
2. Anda akan melihat todo dari:
    - ✅ Todo pribadi Anda
    - ✅ Todo semua anggota divisi Anda
    - ❌ Todo divisi lain (tidak terlihat)

### Filter Todo

Gunakan filter untuk fokus:

| Filter           | Fungsi                      |
| ---------------- | --------------------------- |
| Filter by User   | Lihat todo user tertentu    |
| Filter by Date   | Lihat todo tanggal tertentu |
| Today's Todos    | Lihat todo hari ini         |
| Filter by Status | Pending, In Progress, Done  |

### Membuat Todo untuk Anggota Tim

**Langkah-langkah:**

1. Buka **"Daily Todos"**
2. Klik **"New To-Do"**
3. Isi form:

| Field       | Penjelasan                      |
| ----------- | ------------------------------- |
| User        | Pilih anggota divisi            |
| Date        | Tanggal todo                    |
| Title       | Judul tugas                     |
| Description | Detail tugas                    |
| Status      | Pending, In Progress, atau Done |

4. Klik **"Create"**

**Kapan membuat todo untuk tim:**

- Menugaskan tugas harian
- Follow-up meeting
- Reminder deadline
- Koordinasi tim

### Mengelola Todo Anggota

Anda bisa:

- ✅ Edit todo anggota divisi
- ✅ Hapus todo anggota divisi
- ✅ Update status todo

**Langkah edit:**

1. Klik todo yang ingin diedit
2. Klik **"Edit"**
3. Ubah yang diperlukan
4. Simpan

### Dashboard Widget

Dashboard menampilkan widget Daily Todo:

- Tabel todo anggota divisi
- Status dengan warna
- Filter cepat

**Gunakan untuk:**

- Monitor produktivitas tim
- Identifikasi yang butuh bantuan
- Review beban kerja

---

## Menggunakan Kanban Board

### Mengakses Board

1. Klik menu **"Project Board"**
2. Pilih project dari dropdown
3. Board menampilkan semua ticket dalam kolom status

### Memindahkan Ticket

**Desktop (Mouse):**

1. Klik dan tahan kartu ticket
2. Seret ke kolom tujuan
3. Lepaskan

**Mobile (Touch):**

1. Tekan dan tahan kartu (0.5 detik)
2. Seret ke kolom tujuan
3. Lepaskan

### Sorting Kolom

Klik ikon ⋮ di header kolom:

- Date created (newest/oldest)
- Card name (alphabetically)
- Due date
- Priority

### Filter by User

1. Klik **"Filter by User"**
2. Pilih anggota tim
3. Board hanya menampilkan ticket mereka

**Gunakan untuk:**

- Cek workload anggota tim
- Review progress individual
- Identifikasi bottleneck

---

## Koordinasi dengan C-Level

### Menerima Assignment dari C-Level

Ketika C-Level (CEO, COO, CTO, HR) assign ticket ke Anda:

1. Anda menerima notifikasi
2. Ticket muncul di dashboard Anda
3. Review ticket dan pahami requirement
4. Distribusikan ke anggota tim yang sesuai

### Alur Kerja Standar

```
1. C-Level assign ticket ke Anda
         ↓
2. Anda review dan pahami
         ↓
3. Anda assign ke anggota tim
         ↓
4. Anggota tim mengerjakan
         ↓
5. Anda monitor progress
         ↓
6. Update ke C-Level jika perlu
```

### Tips Koordinasi

- 📝 Pahami requirement sebelum distribute
- 👥 Assign ke orang yang tepat
- 📊 Monitor progress secara rutin
- 📢 Komunikasikan blocker ke C-Level

---

## Analytics dan Reporting

### User Contributions

1. Buka menu **"User Contributions"**
2. Lihat statistik anggota divisi:
    - Aktivitas harian
    - Ticket yang di-assign
    - Ticket yang diselesaikan
3. Filter berdasarkan periode

### Leaderboard

1. Buka menu **"Leaderboard"**
2. Pilih project
3. Lihat ranking berdasarkan:
    - Ticket completed
    - Ticket created
    - Activity level

### Project Timeline

1. Buka menu **"Project Timeline"**
2. Lihat Gantt chart semua project
3. Identifikasi:
    - Project yang overlap
    - Deadline yang mendekat
    - Progress keseluruhan

### Ticket Timeline

1. Buka menu **"Ticket Timeline"**
2. Pilih project
3. Lihat jadwal ticket berdasarkan tanggal

---

## Best Practices

### Workflow Harian

**Pagi:**

- 📋 Review daily todo anggota divisi
- 📊 Cek dashboard untuk update
- 📝 Plan distribusi tugas

**Selama Kerja:**

- 🔄 Monitor progress ticket
- 💬 Respond komentar di ticket
- 📢 Koordinasi dengan tim

**Sore:**

- ✅ Review todo yang selesai
- 📊 Update status project
- 📝 Plan untuk besok

### Tips Assignment

✅ **Do:**

- Assign berdasarkan skill dan availability
- Set due date yang realistis
- Gunakan multi-assign untuk kolaborasi
- Reassign jika perlu

❌ **Don't:**

- Overload satu orang
- Assign tanpa due date
- Biarkan ticket tanpa assignee
- Ignore blocker

### Tips Monitoring

- 📊 Review board secara rutin
- 👀 Perhatikan ticket yang stuck
- 💬 Gunakan komentar untuk follow-up
- 📈 Track progress dengan timeline

---

## Troubleshooting

### Tidak Bisa Assign ke User Tertentu

**Kemungkinan penyebab:**

- User belum jadi member project

**Solusi:**

1. Tambahkan user ke project dulu
2. Baru assign ticket

### Tidak Bisa Lihat Todo Divisi Lain

**Ini normal!** Head Division hanya bisa lihat todo divisi sendiri.

Jika butuh akses lintas divisi, hubungi tim IT.

### Anggota Tim Tidak Bisa Lihat Project

**Solusi:**

1. Buka project
2. Tab **"Members"**
3. Tambahkan anggota tim

---

## Referensi Cepat

### Menu Utama

| Menu               | Fungsi                 |
| ------------------ | ---------------------- |
| Dashboard          | Statistik dan overview |
| Projects           | Daftar project         |
| Project Board      | Kanban board           |
| Tickets            | Daftar ticket          |
| Daily Todos        | Todo divisi            |
| Epics Overview     | Lihat epic             |
| Leaderboard        | Ranking tim            |
| User Contributions | Statistik kontribusi   |

### Shortcut Assignment

| Aksi          | Cara                             |
| ------------- | -------------------------------- |
| Buat ticket   | Project Board → New Ticket       |
| Assign ticket | Edit ticket → Assignees          |
| Reassign      | Edit ticket → Ubah Assignees     |
| Multi-assign  | Pilih beberapa user di Assignees |

---

**Dokumen ini terakhir diupdate:** Januari 2026
**Versi:** 2.0
