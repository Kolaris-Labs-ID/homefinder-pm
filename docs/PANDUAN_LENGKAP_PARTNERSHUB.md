# Panduan Lengkap PartnersHub Project Management

## Dokumentasi Fitur untuk Semua Divisi

Dokumen ini berisi panduan lengkap penggunaan sistem manajemen proyek PartnersHub dalam Bahasa Indonesia. Panduan ini mencakup semua fitur utama termasuk Project, Epic, Ticket, Kanban Board, dan fitur HRIS.

---

## Daftar Isi

1. [Glosarium (Penjelasan Istilah)](#glosarium-penjelasan-istilah)
2. [Struktur Divisi PartnersHub](#struktur-divisi-partnershub)
3. [Panduan untuk Head Division (Kepala Divisi)](#panduan-untuk-head-division-kepala-divisi)
4. [Panduan untuk Staff (Anggota Tim)](#panduan-untuk-staff-anggota-tim)
5. [Cara Membuat Project](#cara-membuat-project)
6. [Cara Membuat Epic](#cara-membuat-epic)
7. [Cara Membuat Ticket](#cara-membuat-ticket)
8. [Cara Menggunakan Kanban Board](#cara-menggunakan-kanban-board)
9. [Cara Mengatur Status Kanban](#cara-mengatur-status-kanban)
10. [Panduan per Divisi](#panduan-per-divisi)
11. [Fitur HRIS](#fitur-hris)
12. [Fitur Daily Todo](#fitur-daily-todo)
13. [Fitur Tambahan](#fitur-tambahan)
14. [Tips dan Trik](#tips-dan-trik)

---

## Glosarium (Penjelasan Istilah)

Berikut adalah penjelasan istilah-istilah yang digunakan dalam sistem:

### Istilah Umum

| Istilah          | Bahasa Indonesia     | Penjelasan                                                                                         |
| ---------------- | -------------------- | -------------------------------------------------------------------------------------------------- |
| **Project**      | Proyek               | Wadah utama untuk mengorganisir pekerjaan. Berisi ticket, epic, dan anggota tim.                   |
| **Ticket**       | Tiket/Tugas          | Unit pekerjaan terkecil yang harus diselesaikan. Bisa berupa tugas, dokumen, atau pekerjaan klien. |
| **Epic**         | Epik/Inisiatif Besar | Kumpulan ticket yang saling berhubungan untuk satu klien atau tujuan besar.                        |
| **Kanban Board** | Papan Kanban         | Tampilan visual berbentuk kolom untuk melihat dan mengatur status ticket.                          |
| **Status**       | Status               | Tahapan pekerjaan ticket (misal: To Do, In Progress, Done).                                        |
| **Priority**     | Prioritas            | Tingkat urgensi ticket (Critical, High, Medium, Low).                                              |
| **Assignee**     | Penanggung Jawab     | Orang yang ditugaskan untuk mengerjakan ticket.                                                    |
| **Due Date**     | Tenggat Waktu        | Batas waktu penyelesaian ticket.                                                                   |
| **Dashboard**    | Dasbor               | Halaman utama yang menampilkan ringkasan dan statistik.                                            |

### Istilah Kanban

| Istilah                    | Bahasa Indonesia          | Penjelasan                                                             |
| -------------------------- | ------------------------- | ---------------------------------------------------------------------- |
| **Column**                 | Kolom                     | Bagian vertikal di Kanban Board yang mewakili satu status.             |
| **Card**                   | Kartu                     | Representasi visual ticket di Kanban Board.                            |
| **Drag and Drop**          | Seret dan Lepas           | Cara memindahkan ticket antar kolom dengan menyeret menggunakan mouse. |
| **WIP (Work In Progress)** | Pekerjaan Sedang Berjalan | Ticket yang sedang dikerjakan. **HARUS DIBATASI!**                     |
| **WIP Limit**              | Batas WIP                 | Batasan jumlah tugas yang boleh dikerjakan bersamaan (maks 3/orang).   |
| **Backlog**                | Daftar Tunggu             | Kumpulan ticket yang belum dimulai.                                    |
| **Flow**                   | Aliran                    | Seberapa cepat tugas selesai, bukan seberapa sibuk orangnya.           |
| **Pull System**            | Sistem Tarik              | Ambil tugas baru HANYA jika tugas lama sudah selesai.                  |

### Istilah Role (Peran)

| Istilah           | Bahasa Indonesia | Penjelasan                                                  |
| ----------------- | ---------------- | ----------------------------------------------------------- |
| **Head Division** | Kepala Divisi    | Manajer divisi yang mengawasi tim dan pekerjaan divisinya.  |
| **Staff/Member**  | Anggota/Staff    | User biasa dengan akses terbatas pada project yang diikuti. |

### Istilah Khusus Divisi

| Istilah              | Divisi           | Penjelasan                          |
| -------------------- | ---------------- | ----------------------------------- |
| **SPT**              | Tax & Accounting | Surat Pemberitahuan Tahunan pajak   |
| **PKP**              | Tax & Accounting | Pengusaha Kena Pajak                |
| **Pendirian PT**     | Legal            | Proses pendirian Perseroan Terbatas |
| **NIB**              | Legal            | Nomor Induk Berusaha                |
| **SIUP**             | Legal            | Surat Izin Usaha Perdagangan        |
| **Content Calendar** | Commerce         | Jadwal konten media sosial          |
| **Brief**            | Commerce         | Dokumen arahan untuk desain/konten  |

---

## Struktur Divisi PartnersHub

PartnersHub memiliki 4 divisi utama:

### 1. Tax & Accounting (Pajak & Akuntansi)

**Model:** The Factory Line (Repetitif, Deadline Ketat, Volume Tinggi)

Menangani semua urusan perpajakan dan pembukuan klien.

**Layanan:**

- Pelaporan SPT Tahunan (Pribadi & Badan)
- Pelaporan SPT Masa (PPN, PPh 21, PPh 23, dll)
- Pembukuan dan Laporan Keuangan
- Konsultasi Pajak
- Pengajuan PKP
- Tax Planning

### 2. Legal (Hukum & Perizinan)

**Model:** The Relay Race (Ketergantungan Pihak Ketiga, Sequential)

Menangani pendirian badan usaha dan perizinan.

**Layanan:**

- Pendirian PT (Perseroan Terbatas)
- Pendirian CV
- Pendirian Yayasan/Perkumpulan
- NIB (Nomor Induk Berusaha)
- Izin Usaha (SIUP, TDP, dll)
- Perubahan Akta
- HAKI (Merek, Paten)

### 3. Commerce (Pemasaran Digital)

**Model:** The Creative Studio (Ide Abstrak, Revisi Klien, Kerja Lapangan)

Menangani media sosial dan desain grafis untuk klien.

**Layanan:**

- Social Media Management
- Content Creation
- Graphic Design
- Branding
- Digital Marketing Campaign

### 4. Finance (Keuangan)

**Model:** The Gatekeeper (Validasi, Eksekusi Cepat, Anti-Error)

Menangani pembayaran dan keuangan internal.

**Layanan:**

- Pembayaran Gaji
- Reimbursement
- Pembayaran Vendor
- Validasi Bukti Transaksi

---

## Panduan untuk Head Division (Kepala Divisi)

### Akses dan Hak Istimewa

Sebagai Head Division (Kepala Divisi Tax/Legal/Commerce), Anda memiliki:

- ✅ Akses ke project divisi Anda
- ✅ Bisa assign ticket ke anggota tim
- ✅ Bisa menambahkan anggota ke project
- ✅ Melihat daily todo anggota divisi Anda
- ✅ Membuat dan mengelola project klien

### Alur Kerja Head Division

```
Manajemen (CEO, COO)
    ↓ (assign klien baru)
Head Division (Anda)
    ↓ (distribusi tugas)
Anggota Tim Divisi
```

### Tugas Utama Head Division

1. **Koordinasi Tim**
    - Menerima klien baru dari manajemen
    - Mendistribusikan tugas ke anggota tim
    - Memantau progress pekerjaan klien

2. **Manajemen Daily Todo**
    - Melihat todo semua anggota divisi
    - Membuat todo untuk anggota tim
    - Memantau produktivitas tim

---

## Panduan untuk Staff (Anggota Tim)

### Akses dan Hak Istimewa

Sebagai anggota tim (Staff Tax/Legal/Commerce), Anda dapat:

- ✅ Melihat project yang Anda ikuti
- ✅ Membuat dan mengupdate ticket
- ✅ Memindahkan ticket yang di-assign ke Anda
- ✅ Menambahkan komentar
- ✅ Mengelola daily todo pribadi

### Batasan

- ❌ Tidak bisa melihat project divisi lain
- ❌ Tidak bisa melihat daily todo orang lain

---

## Cara Membuat Project

### Langkah-langkah Membuat Project Baru

**Siapa yang bisa membuat project?**

- Head Division
- Manajemen

**Step by Step:**

#### Langkah 1: Buka Menu Projects

1. Login ke sistem di `http://pm.partnershub.co/admin`
2. Klik menu **"Projects"** di sidebar kiri
3. Anda akan melihat daftar semua project

#### Langkah 2: Klik Tombol New Project

1. Klik tombol **"New Project"** di pojok kanan atas
2. Form pembuatan project akan terbuka

#### Langkah 3: Isi Informasi Project

**Contoh untuk Divisi Tax & Accounting:**

| Field             | Contoh                                                        |
| ----------------- | ------------------------------------------------------------- |
| **Name**          | "PT Maju Jaya - SPT Tahunan 2025"                             |
| **Description**   | "Pelaporan SPT Tahunan Badan untuk PT Maju Jaya periode 2025" |
| **Ticket Prefix** | "TAX"                                                         |
| **Start Date**    | 01/01/2026                                                    |
| **End Date**      | 30/04/2026                                                    |

**Contoh untuk Divisi Legal:**

| Field             | Contoh                                                   |
| ----------------- | -------------------------------------------------------- |
| **Name**          | "Pendirian PT - CV Berkah Sentosa"                       |
| **Description**   | "Proses pendirian PT baru untuk klien CV Berkah Sentosa" |
| **Ticket Prefix** | "LEG"                                                    |
| **Start Date**    | 15/01/2026                                               |
| **End Date**      | 15/03/2026                                               |

**Contoh untuk Divisi Commerce:**

| Field             | Contoh                                                                |
| ----------------- | --------------------------------------------------------------------- |
| **Name**          | "Social Media - Toko Roti Manis"                                      |
| **Description**   | "Pengelolaan media sosial Instagram dan TikTok untuk Toko Roti Manis" |
| **Ticket Prefix** | "COM"                                                                 |
| **Start Date**    | 01/02/2026                                                            |
| **End Date**      | 31/12/2026                                                            |

**Tips untuk Ticket Prefix:**

- **TAX** - untuk project Tax & Accounting
- **LEG** - untuk project Legal
- **COM** - untuk project Commerce
- Atau gunakan inisial klien: **MJY** (Maju Jaya), **BKS** (Berkah Sentosa)

#### Langkah 4: Simpan Project

1. Klik tombol **"Create"** untuk menyimpan
2. Project baru akan muncul di daftar

#### Langkah 5: Tambahkan Anggota Tim

1. Buka project yang baru dibuat
2. Klik tab **"Members"**
3. Klik **"Attach"** atau **"Add Member"**
4. Pilih anggota tim dari divisi yang sesuai
5. Klik **"Attach"** untuk konfirmasi

---

## Cara Membuat Epic

### Apa itu Epic?

Epic adalah kumpulan ticket yang saling berhubungan. Untuk PartnersHub, Epic biasanya digunakan untuk:

- Mengelompokkan tahapan pekerjaan klien
- Mengelompokkan jenis layanan
- Mengelompokkan periode waktu

### Contoh Epic per Divisi

**Divisi Tax & Accounting:**

- Epic "Persiapan Dokumen" - berisi ticket pengumpulan data
- Epic "Input & Rekonsiliasi" - berisi ticket input data
- Epic "Pelaporan" - berisi ticket submit SPT
- Epic "SPT Masa Januari" - berisi semua SPT Masa bulan Januari

**Divisi Legal:**

- Epic "Persiapan Dokumen" - berisi ticket pengumpulan syarat
- Epic "Proses Notaris" - berisi ticket pembuatan akta
- Epic "Proses OSS" - berisi ticket pengurusan NIB
- Epic "Pengesahan Kemenkumham" - berisi ticket pengesahan

**Divisi Commerce:**

- Epic "Content Februari 2026" - berisi semua konten bulan Februari
- Epic "Branding Package" - berisi ticket logo, color palette, dll
- Epic "Campaign Ramadhan" - berisi ticket khusus campaign

### Langkah-langkah Membuat Epic

#### Langkah 1: Buka Project

1. Buka project yang ingin ditambahkan epic
2. Klik tab **"Epics"**

#### Langkah 2: Buat Epic Baru

1. Klik tombol **"Create"**
2. Isi form epic:

| Field           | Contoh (Tax)                               | Contoh (Legal)                        | Contoh (Commerce)                          |
| --------------- | ------------------------------------------ | ------------------------------------- | ------------------------------------------ |
| **Name**        | "Persiapan Dokumen"                        | "Proses Notaris"                      | "Content Februari 2026"                    |
| **Description** | "Pengumpulan dan verifikasi dokumen klien" | "Pembuatan akta pendirian di notaris" | "Semua konten media sosial bulan Februari" |
| **Start Date**  | 01/01/2026                                 | 20/01/2026                            | 01/02/2026                                 |
| **End Date**    | 15/01/2026                                 | 10/02/2026                            | 28/02/2026                                 |

#### Langkah 3: Simpan Epic

1. Klik **"Create"** untuk menyimpan
2. Epic akan muncul di daftar

---

## Cara Membuat Ticket

### Apa itu Ticket?

Ticket adalah unit pekerjaan terkecil dalam sistem. Setiap tugas harus dibuat sebagai ticket agar bisa dilacak dan dikelola.

### Contoh Ticket per Divisi

**Divisi Tax & Accounting:**

| Ticket                                 | Priority | Due Date   |
| -------------------------------------- | -------- | ---------- |
| "Minta data penjualan 2025 ke klien"   | High     | 10/01/2026 |
| "Input faktur pajak ke e-Faktur"       | Medium   | 20/01/2026 |
| "Rekonsiliasi PPh 21 dengan slip gaji" | Medium   | 25/01/2026 |
| "Submit SPT Masa PPN Januari"          | Critical | 31/01/2026 |
| "Review laporan keuangan draft"        | High     | 15/03/2026 |

**Divisi Legal:**

| Ticket                             | Priority | Due Date   |
| ---------------------------------- | -------- | ---------- |
| "Kumpulkan KTP & NPWP pendiri"     | High     | 17/01/2026 |
| "Draft Anggaran Dasar PT"          | Medium   | 22/01/2026 |
| "Koordinasi jadwal dengan notaris" | High     | 25/01/2026 |
| "Penandatanganan akta di notaris"  | Critical | 30/01/2026 |
| "Input data di OSS untuk NIB"      | High     | 05/02/2026 |
| "Upload dokumen ke AHU Online"     | Medium   | 10/02/2026 |

**Divisi Commerce:**

| Ticket                                    | Priority | Due Date   |
| ----------------------------------------- | -------- | ---------- |
| "Buat content calendar Februari"          | High     | 28/01/2026 |
| "Design feed Instagram - Promo Valentine" | Medium   | 05/02/2026 |
| "Buat caption untuk 10 post Februari"     | Medium   | 03/02/2026 |
| "Edit video Reels produk baru"            | High     | 10/02/2026 |
| "Design story template mingguan"          | Low      | 07/02/2026 |
| "Report engagement bulanan Januari"       | Medium   | 05/02/2026 |

### Langkah-langkah Membuat Ticket

#### Cara 1: Dari Menu Tickets

**Langkah 1:** Buka menu **"Tickets"** di sidebar

**Langkah 2:** Klik tombol **"New Ticket"**

**Langkah 3:** Isi form ticket:

| Field           | Penjelasan              | Contoh                                                                                        |
| --------------- | ----------------------- | --------------------------------------------------------------------------------------------- |
| **Project**     | Pilih project klien     | "PT Maju Jaya - SPT Tahunan 2025"                                                             |
| **Name**        | Judul ticket yang jelas | "Submit SPT Masa PPN Januari"                                                                 |
| **Description** | Deskripsi detail        | "Submit SPT Masa PPN periode Januari 2026 via e-Filing. Pastikan semua faktur sudah diinput." |
| **Status**      | Status awal             | "To Do"                                                                                       |
| **Priority**    | Tingkat urgensi         | "Critical" (karena ada deadline)                                                              |
| **Epic**        | Kelompok epic           | "Pelaporan"                                                                                   |
| **Assignees**   | Penanggung jawab        | Pilih staff tax                                                                               |
| **Due Date**    | Tenggat waktu           | 31/01/2026                                                                                    |

**Langkah 4:** Klik **"Create"** untuk menyimpan

### Tips Membuat Ticket yang Baik

**✅ Judul yang Baik:**

- "Submit SPT Masa PPN Januari 2026 - PT Maju Jaya"
- "Draft Akta Pendirian PT - CV Berkah Sentosa"
- "Design Feed Instagram Valentine - Toko Roti Manis"
- "Rekonsiliasi PPh 21 dengan data payroll"

**❌ Judul yang Buruk:**

- "SPT"
- "Akta"
- "Design"
- "Kerjakan ini"

**Tips Description:**

- Jelaskan apa yang harus dilakukan
- Sertakan nama klien jika perlu
- Tambahkan deadline resmi (misal: deadline DJP)
- Sertakan link dokumen jika ada

---

## Cara Menggunakan Kanban Board

### Apa itu Kanban Board?

Kanban Board adalah tampilan visual berbentuk kolom untuk melihat semua ticket berdasarkan statusnya.

**PENTING:** Kanban bukan sekadar papan tulis. Kanban adalah **sistem manajemen aliran (Flow Management)**.

### Analogi Jalan Tol

**Tanpa Kanban:** Semua mobil (tugas) masuk sekaligus → MACET TOTAL!

**Dengan Kanban:** Batasi jumlah mobil yang boleh masuk → LANCAR!

### Prinsip Dasar Kanban

1. **Visualisasikan Kerja** - Kalau tidak terlihat, tidak bisa diatur
2. **Batasi WIP (Work In Progress)** - Jangan kerjakan semua sekaligus
3. **Fokus pada Aliran (Flow)** - Seberapa cepat tugas SELESAI, bukan seberapa SIBUK

### Aturan WIP Limit (WAJIB!)

**WIP Limit** adalah batasan jumlah tugas yang boleh dikerjakan bersamaan.

**Mantra:** "Stop Starting, Start Finishing." (Berhenti memulai hal baru, selesaikan dulu yang sedang berjalan)

| Situasi                | WIP Limit      | Penjelasan                                    |
| ---------------------- | -------------- | --------------------------------------------- |
| Per Orang (Processing) | **Maksimal 3** | Satu orang maksimal mengerjakan 3 klien aktif |
| Kolom Review           | **3-5 kartu**  | Mencegah penumpukan di tahap review           |
| Kolom Client Review    | **4 kartu**    | Mencegah desain menumpuk tanpa feedback       |

**Mengapa Harus Dibatasi?**

1. **Mencegah Context Switching** - Otak butuh 20 menit untuk fokus kembali setelah terganggu
2. **Mendeteksi Masalah** - Kalau kolom macet, masalah langsung terlihat
3. **Mencegah Kerja Sia-sia** - Jangan buat desain ke-5 kalau 4 desain belum ada feedback

### Mengakses Kanban Board

1. Klik menu **"Project Board"** di sidebar
2. Pilih project klien dari dropdown
3. Board akan menampilkan semua ticket dalam kolom-kolom status

### Memahami Tampilan Board

```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│   TO DO     │ PROCESSING  │   REVIEW    │    DONE     │
│    (∞)      │   (3/3)     │   (2/3)     │     ✓       │
├─────────────┼─────────────┼─────────────┼─────────────┤
│ [Minta data]│ [Input      │ [Review     │ [Submit SPT]│
│ [Draft akta]│  faktur]    │  laporan]   │ [NIB selesai│
│             │ [Design IG] │             │             │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

### Cara Memindahkan Ticket (Drag and Drop)

#### Di Desktop (Mouse):

1. Klik dan tahan kartu ticket
2. Seret ke kolom status yang diinginkan
3. Lepaskan mouse
4. Status ticket otomatis berubah

#### Di Mobile (Touch):

1. Tekan dan tahan kartu ticket (sekitar 0.5 detik)
2. Seret ke kolom yang diinginkan
3. Lepaskan
4. Status otomatis berubah

**PENTING:** Jangan pindahkan ticket ke "Processing" kalau WIP limit sudah penuh!

**Catatan:** Anda hanya bisa memindahkan ticket yang:

- Di-assign ke Anda, ATAU
- Dibuat oleh Anda

### Fitur Sorting (Pengurutan)

Klik ikon titik tiga (⋮) di header kolom untuk mengurutkan:

- **Due date**: Berdasarkan tenggat waktu (paling urgent di atas)
- **Priority**: Berdasarkan prioritas
- **Date created**: Berdasarkan tanggal dibuat
- **Card name**: Urut abjad

### Fitur Filter

**Filter by User:**

1. Klik tombol **"Filter by User"**
2. Pilih nama staff
3. Board hanya menampilkan ticket staff tersebut

---

## Cara Mengatur Status Kanban

### Status Default yang Umum

| Status         | Warna  | Penjelasan                             |
| -------------- | ------ | -------------------------------------- |
| **To Do**      | Kuning | Ticket baru, belum dikerjakan          |
| **Processing** | Biru   | Sedang dikerjakan (WIP Limit: 3/orang) |
| **Review**     | Ungu   | Selesai, menunggu review kepala divisi |
| **Done**       | Hijau  | Selesai dan terverifikasi ✓            |

### Contoh Konfigurasi Status per Divisi

**Divisi Tax & Accounting (Model: The Factory Line):**

```
To Do → Gathering Data → Processing → Review → Submitted → Done
```

| Status         | WIP Limit   | Penjelasan                                   |
| -------------- | ----------- | -------------------------------------------- |
| To Do          | ∞           | Pekerjaan yang dijadwalkan                   |
| Gathering Data | -           | Menunggu data dari klien                     |
| Processing     | **3/orang** | Sedang dikerjakan (BATASI!)                  |
| Review         | 3-5         | Menunggu review supervisor                   |
| Submitted      | -           | Sudah disubmit ke DJP                        |
| Done           | ∞           | Selesai, bukti terima sudah dikirim ke klien |

**Divisi Legal (Model: The Relay Race):**

```
Draft Akta → Tanda Tangan → SK Kemenkumham → OSS/NIB → Done
```

| Status         | WIP Limit | Penjelasan                              |
| -------------- | --------- | --------------------------------------- |
| Draft Akta     | -         | Sedang membuat draft dokumen            |
| Tanda Tangan   | **5**     | Proses di notaris (BATASI!)             |
| SK Kemenkumham | -         | Menunggu pengesahan                     |
| OSS/NIB        | -         | Proses di sistem OSS                    |
| Done           | ∞         | Selesai, dokumen sudah dikirim ke klien |

**Divisi Commerce (Model: The Creative Studio):**

```
Idea/Backlog → Production → Client Review → Scheduled/Posted
```

| Status           | WIP Limit | Penjelasan                         |
| ---------------- | --------- | ---------------------------------- |
| Idea/Backlog     | ∞         | Brief sudah diterima               |
| Production       | 3/orang   | Sedang dikerjakan (design/content) |
| Client Review    | **4**     | Menunggu approval klien (BATASI!)  |
| Scheduled/Posted | ∞         | Sudah dipublish/selesai            |

**Divisi Finance (Model: The Gatekeeper):**

```
Inbox → Validasi → Ready to Pay → Paid
```

| Status       | WIP Limit | Penjelasan                           |
| ------------ | --------- | ------------------------------------ |
| Inbox        | ∞         | Request masuk                        |
| Validasi     | -         | Cek bukti dan kelengkapan            |
| Ready to Pay | **10**    | Menunggu approval/transfer (BATASI!) |
| Paid         | ∞         | Sudah dibayar                        |

### Cara Membuat Status Baru

1. Buka project
2. Klik tab **"Ticket Statuses"**
3. Klik **"Create"**
4. Isi form:
    - **Name**: Nama status
    - **Color**: Warna untuk identifikasi
    - **Sort Order**: Urutan kolom (1 = paling kiri)
    - **Is Completed**: Centang untuk status akhir
5. Klik **"Create"**

---

## Panduan per Divisi

### Divisi Tax & Accounting

#### Workflow Umum

```
1. Klien baru masuk
   ↓
2. Buat Project untuk klien
   ↓
3. Buat Epic per jenis layanan (SPT Tahunan, SPT Masa, dll)
   ↓
4. Buat Ticket untuk setiap tugas
   ↓
5. Assign ke staff
   ↓
6. Kerjakan dan update status
   ↓
7. Review oleh supervisor
   ↓
8. Submit ke DJP
   ↓
9. Kirim bukti ke klien
```

#### Contoh Project Tax

**Project:** "PT Maju Jaya - Layanan Pajak 2026"

**Epic:**

1. "SPT Tahunan Badan 2025"
2. "SPT Masa PPN 2026"
3. "SPT Masa PPh 21 2026"
4. "Pembukuan Bulanan"

**Ticket dalam Epic "SPT Tahunan Badan 2025":**

- "Minta laporan keuangan 2025 ke klien"
- "Rekonsiliasi fiskal"
- "Hitung PPh Badan terutang"
- "Input SPT di e-Filing"
- "Review oleh supervisor"
- "Submit SPT Tahunan"
- "Kirim BPE ke klien"

#### Tips untuk Tax

- Set due date sesuai deadline DJP
- Gunakan priority "Critical" untuk deadline yang dekat
- Tambahkan komentar untuk catatan penting
- Update status segera setelah submit

---

### Divisi Legal

#### Workflow Umum

```
1. Klien request pendirian PT/izin
   ↓
2. Buat Project untuk klien
   ↓
3. Buat Epic per tahapan (Dokumen, Notaris, OSS, dll)
   ↓
4. Buat Ticket untuk setiap tugas
   ↓
5. Assign ke staff
   ↓
6. Koordinasi dengan notaris/instansi
   ↓
7. Proses dokumen
   ↓
8. Serahkan dokumen ke klien
```

#### Contoh Project Legal

**Project:** "Pendirian PT - Berkah Sentosa"

**Epic:**

1. "Persiapan Dokumen"
2. "Proses Notaris"
3. "Proses OSS"
4. "Pengesahan Kemenkumham"

**Ticket dalam Epic "Persiapan Dokumen":**

- "Kumpulkan KTP semua pendiri"
- "Kumpulkan NPWP semua pendiri"
- "Verifikasi nama PT di AHU"
- "Siapkan draft Anggaran Dasar"
- "Konfirmasi modal dasar dengan klien"

**Ticket dalam Epic "Proses Notaris":**

- "Kirim draft AD ke notaris"
- "Jadwalkan penandatanganan akta"
- "Penandatanganan akta pendirian"
- "Terima minuta akta dari notaris"

#### Tips untuk Legal

- Catat nomor akta dan tanggal di komentar
- Upload dokumen penting sebagai attachment
- Set reminder untuk follow-up ke notaris/instansi
- Gunakan status "Waiting Docs" untuk tracking dokumen klien

---

### Divisi Commerce

#### Workflow Umum

```
1. Klien request layanan social media/design
   ↓
2. Buat Project untuk klien
   ↓
3. Buat Epic per periode/campaign
   ↓
4. Buat Ticket untuk setiap konten/design
   ↓
5. Assign ke designer/content creator
   ↓
6. Buat konten
   ↓
7. Internal review
   ↓
8. Client approval
   ↓
9. Publish/deliver
```

#### Contoh Project Commerce

**Project:** "Social Media - Toko Roti Manis 2026"

**Epic:**

1. "Content Januari 2026"
2. "Content Februari 2026"
3. "Campaign Valentine"
4. "Branding Refresh"

**Ticket dalam Epic "Content Februari 2026":**

- "Buat content calendar Februari"
- "Design feed 1 - Promo Weekend"
- "Design feed 2 - New Product"
- "Design feed 3 - Behind the Scene"
- "Buat caption untuk semua post"
- "Edit Reels produk baru"
- "Design story template"
- "Report engagement Januari"

**Ticket dalam Epic "Campaign Valentine":**

- "Brainstorm konsep Valentine"
- "Design special Valentine series (5 post)"
- "Buat caption Valentine"
- "Design story countdown"
- "Koordinasi promo dengan klien"

#### Tips untuk Commerce

- Gunakan Epic per bulan untuk content calendar
- Set due date 2-3 hari sebelum publish untuk approval
- Tambahkan link ke file design di komentar
- Gunakan status "Client Review" untuk tracking approval

---

## Fitur HRIS

### Modul HRIS yang Tersedia

### 1. HRIS Core

| Menu                   | Fungsi                               |
| ---------------------- | ------------------------------------ |
| **Departments**        | Kelola divisi (Tax, Legal, Commerce) |
| **Employees**          | Data karyawan lengkap                |
| **Positions**          | Jabatan (Staff Tax, Designer, dll)   |
| **Leave Types**        | Jenis cuti (Annual, Sick, dll)       |
| **Leave Requests**     | Pengajuan cuti karyawan              |
| **Attendance Logs**    | Catatan kehadiran                    |
| **Approval Workflows** | Alur persetujuan                     |

### 2. Payroll

| Menu                  | Fungsi                      |
| --------------------- | --------------------------- |
| **Salary Structures** | Struktur gaji karyawan      |
| **Payrun Batches**    | Batch penggajian bulanan    |
| **Reimbursements**    | Pengajuan penggantian biaya |

### Cara Mengajukan Cuti

1. Klik menu **"Leave Requests"**
2. Klik **"New Leave Request"**
3. Isi form:
    - **Leave Type**: Pilih jenis cuti
    - **Start Date**: Tanggal mulai cuti
    - **End Date**: Tanggal selesai cuti
    - **Reason**: Alasan cuti
4. Klik **"Create"**
5. Tunggu approval dari atasan

### Cara Mengajukan Reimbursement

1. Klik menu **"Reimbursements"**
2. Klik **"New Reimbursement"**
3. Isi form:
    - **Type**: Jenis (Transport, Meal, dll)
    - **Amount**: Jumlah
    - **Date**: Tanggal pengeluaran
    - **Description**: Keterangan
    - **Receipt**: Upload bukti
4. Klik **"Create"**
5. Tunggu approval

---

## Fitur Daily Todo

### Apa itu Daily Todo?

Daily Todo adalah fitur untuk mencatat tugas harian personal. Berbeda dengan ticket project, daily todo lebih bersifat personal.

### Siapa yang Bisa Melihat Daily Todo?

| Role              | Akses                  |
| ----------------- | ---------------------- |
| **HR**            | Semua todo karyawan    |
| **Head Division** | Todo anggota divisinya |
| **Staff**         | Hanya todo pribadi     |

### Cara Membuat Daily Todo

1. Klik menu **"Daily Todos"**
2. Klik **"New To-Do"**
3. Isi form:
    - **Date**: Tanggal
    - **Title**: Judul tugas
    - **Description**: Detail (opsional)
    - **Status**: Pending/In Progress/Done
4. Klik **"Create"**

### Contoh Daily Todo per Divisi

**Staff Tax:**

- "Follow up data penjualan PT Maju Jaya"
- "Input faktur pajak klien A"
- "Cek status e-Filing klien B"

**Staff Legal:**

- "Hubungi notaris untuk jadwal"
- "Cek status NIB di OSS"
- "Kirim draft akta ke klien"

**Staff Commerce:**

- "Upload konten Instagram klien A"
- "Revisi design berdasarkan feedback"
- "Buat report engagement mingguan"

### Status Daily Todo

| Status          | Warna   | Penjelasan        |
| --------------- | ------- | ----------------- |
| **Pending**     | Abu-abu | Belum dimulai     |
| **In Progress** | Kuning  | Sedang dikerjakan |
| **Done**        | Hijau   | Selesai           |

---

## Fitur Tambahan

### 1. Komentar di Ticket

Gunakan komentar untuk:

- Update progress ke klien
- Catatan penting (nomor akta, tanggal submit, dll)
- Diskusi dengan tim
- Dokumentasi feedback klien

**Contoh Komentar:**

- "SPT sudah disubmit, BPE nomor: XXX-XXX-XXX"
- "Akta sudah ditandatangani tanggal 30/01/2026, nomor akta: 123"
- "Klien request revisi warna, lihat attachment"

### 2. Ticket History

Setiap perubahan status tercatat otomatis:

- Siapa yang mengubah
- Kapan diubah
- Status sebelum dan sesudah

### 3. Timeline View

**Project Timeline:**

- Lihat semua project klien dalam tampilan Gantt
- Identifikasi project yang overlap

**Ticket Timeline:**

- Lihat jadwal ticket dalam satu project
- Identifikasi deadline yang mendekat

### 4. Leaderboard

- Lihat ranking produktivitas tim
- Filter per project atau keseluruhan

### 5. Export Data

Head Division bisa export data ticket ke Excel untuk:

- Laporan ke manajemen
- Backup data
- Analisis workload

---

## Tips dan Trik

### 3 Aturan Main Kanban (WAJIB!)

#### 1. VISUALISASIKAN

> "Masukkan semua ke pm.partnershub.co. Tidak ada tiket = Tidak dikerjakan."

- Semua pekerjaan HARUS ada tiketnya
- Kalau tidak ada di sistem, dianggap tidak ada
- Ini membantu manajemen melihat beban kerja sebenarnya

#### 2. BATASI (LIMIT)

> "Jangan bangga kalau status 'Processing' kamu ada 10. Itu artinya kamu tidak fokus. Maksimal 3."

- Batasi pekerjaan yang sedang dikerjakan
- Fokus selesaikan yang ada sebelum ambil baru
- Kalau WIP penuh, selesaikan dulu atau minta bantuan

#### 3. TARIK (PULL), JANGAN DORONG (PUSH)

> "Ambil tugas baru (Pull) HANYA jika tugas lama sudah selesai atau geser ke kanan. Jangan terima dorongan tugas (Push) kalau tanganmu penuh."

- Ambil tugas baru hanya kalau ada slot kosong
- Jangan terima tugas baru kalau masih penuh
- Kalau dipaksa, bilang: "Slot saya penuh, mana yang harus saya tinggalkan?"

### FAQ: "Tapi Nanti Kerjaan Numpuk di To-Do?"

**Pertanyaan:** "Pak, kalau saya dibatasi cuma boleh kerjakan 3, nanti antrean di 'To Do' makin panjang dong?"

**Jawaban:**

Antrean panjang di "To Do" itu **JUJUR**. Itu menunjukkan beban kerja kita yang sebenarnya.

**Lebih baik:** Antrean panjang di depan pintu (belum dimulai)

**Daripada:** Macet di dalam ruangan (setengah jadi)

Kalau antrean di To Do panjang tapi WIP sudah maksimal, itu **sinyal buat Management** untuk:

- Menambah orang
- Menolak klien baru
- Memprioritaskan ulang

Tapi kalau dipaksakan semua masuk "Processing", seolah-olah semua sedang dikerjakan padahal macet, Management jadi **buta**. Dikira semua aman, padahal bom waktu.

### Tips Produktivitas Lainnya

1. **Update Status Secara Rutin**
    - Pindahkan ticket saat mulai mengerjakan
    - Update ke "Done" segera setelah selesai

2. **Gunakan Priority dengan Bijak**
    - **Critical**: Deadline hari ini/besok
    - **High**: Deadline minggu ini
    - **Medium**: Normal
    - **Low**: Bisa ditunda

3. **Manfaatkan Komentar**
    - Catat informasi penting
    - Dokumentasi komunikasi dengan klien

4. **Set Due Date Realistis**
    - Pertimbangkan waktu review
    - Sisakan buffer untuk revisi

### Troubleshooting Umum

**Tidak bisa melihat project:**

- Pastikan Anda sudah ditambahkan sebagai member
- Hubungi Head Division untuk ditambahkan

**Tidak bisa memindahkan ticket:**

- Pastikan ticket di-assign ke Anda
- Atau Anda yang membuat ticket tersebut

**Ticket tidak muncul di board:**

- Cek filter yang aktif
- Pastikan project yang dipilih benar

---

## Referensi Cepat

### Ticket Prefix Standar

| Divisi           | Prefix | Contoh UUID |
| ---------------- | ------ | ----------- |
| Tax & Accounting | TAX    | TAX-ABC123  |
| Legal            | LEG    | LEG-XYZ789  |
| Commerce         | COM    | COM-DEF456  |

### Alur Kerja Standar

```
1. Klien baru masuk
   ↓
2. Buat Project
   ↓
3. Tambah Anggota Tim
   ↓
4. Buat Epic (per layanan/periode)
   ↓
5. Buat Ticket (per tugas)
   ↓
6. Assign ke Staff
   ↓
7. Kerjakan & Update Status
   ↓
8. Review
   ↓
9. Done
```

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

---

## Kontak Support

| Masalah            | Hubungi               |
| ------------------ | --------------------- |
| Akses & Permission | HR atau Head Division |
| Fitur & Penggunaan | Head Division         |
| Teknis & Bug       | IT Support            |

---

**Dokumen ini terakhir diupdate:** Januari 2026
**Versi:** 2.0
