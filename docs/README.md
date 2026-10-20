# Dokumentasi Homefinder Project Management

## Panduan Lengkap dalam Bahasa Indonesia

Selamat datang di dokumentasi sistem manajemen proyek Homefinder. Dokumen ini berisi panduan lengkap untuk semua pengguna sistem dari divisi Tax & Accounting, Legal, dan Commerce.

---

## 📚 Daftar Dokumentasi

### Panduan Utama (WAJIB BACA)

| Dokumen                                               | Deskripsi                                                    |
| ----------------------------------------------------- | ------------------------------------------------------------ |
| [**Panduan Lengkap**](PANDUAN_LENGKAP_HOMEFINDER.md) | ⭐ Panduan komprehensif semua fitur dengan contoh per divisi |
| [Glosarium](GLOSARIUM_ID.md)                          | Kamus istilah dalam Bahasa Indonesia                         |
| [Panduan Kanban](PANDUAN_KANBAN_ID.md)                | Panduan detail penggunaan Kanban Board                       |

### Panduan per Role

| Dokumen                                              | Untuk Siapa                                       |
| ---------------------------------------------------- | ------------------------------------------------- |
| [Panduan Head Division](PANDUAN_HEAD_DIVISION_ID.md) | Kepala Divisi Tax, Legal, Commerce                |
| [Panduan User](PANDUAN_USER_ID.md)                   | Staff Tax, Staff Legal, Designer, Content Creator |

---

## 🏢 Struktur Divisi Homefinder

### 1. Tax & Accounting (The Factory Line)

- Pelaporan SPT (Tahunan & Masa)
- Pembukuan & Laporan Keuangan
- Konsultasi Pajak
- Pengajuan PKP

### 2. Legal (The Relay Race)

- Pendirian PT, CV, Yayasan
- NIB & Izin Usaha
- Perubahan Akta
- HAKI (Merek, Paten)

### 3. Commerce (The Creative Studio)

- Social Media Management
- Content Creation
- Graphic Design
- Digital Marketing

### 4. Finance (The Gatekeeper)

- Pembayaran Gaji
- Reimbursement
- Pembayaran Vendor
- Validasi Transaksi

---

## 🚀 Quick Start

### Untuk Staff Baru

1. **Login** ke sistem di `https://your-homefinder-domain.example/admin`
2. **Baca** [Panduan Lengkap](PANDUAN_LENGKAP_HOMEFINDER.md) - terutama bagian divisi Anda
3. **Pelajari** [Glosarium](GLOSARIUM_ID.md) untuk memahami istilah
4. **WAJIB Pahami** [Panduan Kanban](PANDUAN_KANBAN_ID.md) - terutama aturan WIP Limit

### Untuk Kepala Divisi

1. **Baca** [Panduan Head Division](PANDUAN_HEAD_DIVISION_ID.md)
2. **Pahami** workflow divisi Anda di [Panduan Lengkap](PANDUAN_LENGKAP_HOMEFINDER.md#panduan-per-divisi)
3. **Setup** status Kanban sesuai kebutuhan divisi
4. **Terapkan** WIP Limit untuk tim Anda

---

## ⚠️ 3 Aturan Main Kanban (WAJIB!)

### 1. VISUALISASIKAN

> "Masukkan semua ke your-homefinder-domain.example. Tidak ada tiket = Tidak dikerjakan."

### 2. BATASI (LIMIT)

> "Jangan bangga kalau status 'Processing' kamu ada 10. Itu artinya kamu tidak fokus. **Maksimal 3.**"

### 3. TARIK (PULL), JANGAN DORONG (PUSH)

> "Ambil tugas baru HANYA jika tugas lama sudah selesai. Jangan terima dorongan tugas kalau tanganmu penuh."

**Baca selengkapnya:** [Panduan Kanban - Aturan WIP Limit](PANDUAN_KANBAN_ID.md#aturan-wip-limit-work-in-progress)

---

## 📖 Topik Utama

### Project Management

- [Cara Membuat Project](PANDUAN_LENGKAP_HOMEFINDER.md#cara-membuat-project)
- [Cara Membuat Epic](PANDUAN_LENGKAP_HOMEFINDER.md#cara-membuat-epic)
- [Cara Membuat Ticket](PANDUAN_LENGKAP_HOMEFINDER.md#cara-membuat-ticket)

### Kanban Board

- [Apa itu Kanban?](PANDUAN_KANBAN_ID.md#apa-itu-kanban)
- [Cara Memindahkan Ticket](PANDUAN_KANBAN_ID.md#cara-memindahkan-ticket)
- [Mengatur Status Kolom](PANDUAN_KANBAN_ID.md#mengatur-status-kolom)

### Panduan per Divisi

- [Divisi Tax & Accounting](PANDUAN_LENGKAP_HOMEFINDER.md#divisi-tax--accounting)
- [Divisi Legal](PANDUAN_LENGKAP_HOMEFINDER.md#divisi-legal)
- [Divisi Commerce](PANDUAN_LENGKAP_HOMEFINDER.md#divisi-commerce)

---

## 🔑 Referensi Cepat

### Ticket Prefix Standar

| Divisi           | Prefix | Contoh UUID |
| ---------------- | ------ | ----------- |
| Tax & Accounting | TAX    | TAX-ABC123  |
| Legal            | LEG    | LEG-XYZ789  |
| Commerce         | COM    | COM-DEF456  |

### Workflow Status per Divisi

**Tax & Accounting (The Factory Line):**

```
To Do → Gathering Data → Processing (WIP:3) → Review → Submitted → Done
```

**Legal (The Relay Race):**

```
Draft Akta → Tanda Tangan (WIP:5) → SK Kemenkumham → OSS/NIB → Done
```

**Commerce (The Creative Studio):**

```
Idea/Backlog → Production (WIP:3) → Client Review (WIP:4) → Posted
```

**Finance (The Gatekeeper):**

```
Inbox → Validasi → Ready to Pay (WIP:10) → Paid
```

### Role dan Akses

| Role          | Project         | Ticket               | Daily Todo   |
| ------------- | --------------- | -------------------- | ------------ |
| Head Division | ✅ Divisinya    | ✅ Assign siapa saja | ✅ Divisinya |
| Staff         | ⚠️ Yang diikuti | ⚠️ Yang di-assign    | ✅ Pribadi   |

---

## ❓ FAQ (Pertanyaan Umum)

### Tidak bisa melihat project?

Anda belum ditambahkan sebagai member. Hubungi Head Division Anda.

### Tidak bisa memindahkan ticket?

Pastikan ticket di-assign ke Anda atau Anda yang membuat ticket tersebut.

### "Tapi nanti kerjaan numpuk di To-Do?"

Antrean panjang di "To Do" itu **JUJUR**. Itu menunjukkan beban kerja sebenarnya. Lebih baik antrean panjang di depan pintu (belum dimulai), daripada macet di dalam ruangan (setengah jadi).

### Bagaimana cara mengajukan cuti?

Buka menu Leave Requests → New Leave Request → Isi form → Submit.

### Apa bedanya Ticket dan Daily Todo?

- **Ticket**: Tugas terkait project/klien, bisa dilihat tim
- **Daily Todo**: Tugas personal harian, hanya Anda yang bisa lihat

---

## 📞 Butuh Bantuan?

| Masalah            | Hubungi               |
| ------------------ | --------------------- |
| Akses & Permission | HR atau Head Division |
| Fitur & Penggunaan | Head Division Anda    |
| Teknis & Bug       | IT Support            |

---

**Dokumentasi ini terakhir diupdate:** Januari 2026
**Versi:** 2.2
