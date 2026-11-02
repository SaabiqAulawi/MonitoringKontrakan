# 🏢 Monitoring Kontrakan - Residential Tenant & Billing Monitoring System

## Konfigurasi awal

Saat database pertama kali dibuat, aplikasi membuat akun `administrator` dan `owner`.
Atur `APP_ADMIN_PASSWORD` dan `APP_OWNER_PASSWORD` pada environment server sebelum
akses pertama. Masing-masing harus minimal 12 karakter; kata sandi tidak disimpan
di source code atau README. Setiap perubahan pembayaran mencatat akun yang melakukan perubahan.

Kedua role yang sudah login dapat menambah, mengedit, dan menghapus data tenant/unit,
serta mengubah status pembayaran tenant.

## Model data terbaru

Selain data tenant dan pembayaran lama, aplikasi membuat tabel `units` untuk alamat, kondisi, foto, jumlah kamar/toilet, luas, dan jumlah lantai. Tabel `tenant_contacts` mendukung lebih dari satu kontak per tenant. Data lama dimigrasikan ke unit berdasarkan nilai `room_number` tanpa menghapus riwayat.

Database SQLite berada di `data/kos.db` secara default. Untuk deployment produksi,
set `APP_DATA_DIR` ke folder di luar document root; sebagai perlindungan tambahan,
folder `data/` diblokir dari akses web melalui `.htaccess`. Jangan menghapus aturan `.gitignore` yang mengecualikan database,
foto, upload, spreadsheet berisi data penghuni, dan konfigurasi lokal.

Periode tarif sewa tenant tersedia dalam pilihan `Perbulan`, `Per 6 Bulan`, atau `Pertahun`. Perpanjangan tenggat pembayaran mengikuti periode yang dipilih.
NIK/No. KTP tenant bersifat opsional; jika diisi, nilainya tetap digunakan untuk mencegah data tenant ganda.
Penambahan tenant dan unit tersedia pada halaman terpisah melalui menu **Tambah Tenant**
dan **Tambah Unit**; halaman **Penghuni** dan **Unit** digunakan untuk melihat, mengedit,
dan menghapus data yang sudah ada.

Setiap unit memiliki status operasional `Terisi`, `Kosong`, `Dalam perbaikan`, atau `Renovasi`. Status ini terpisah dari kondisi fisik unit dan dapat diubah dari halaman **Unit**.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-SQLite-003B57?style=flat&logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![UI Framework](https://img.shields.io/badge/UI-Bootstrap%205-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Code Quality](https://img.shields.io/badge/Code%20Quality-100%2F100%20Clean-success?style=flat&logo=checkmarx&logoColor=white)](https://github.com/scanaislop/aislop)
[![Architecture](https://img.shields.io/badge/Architecture-Zero--Dependency%20%7C%20Modular-blue)](#-arsitektur--teknologi)

Sistem informasi manajemen berbasis web untuk memonitor penghuni, siklus tenggat jatuh tempo pembayaran sewa, pencatatan log transaksi, cetak kuitansi digital otomatis, serta penagihan otomatis via WhatsApp Deep Linking dengan integrasi pengimporan data file spreadsheet Excel (`.xlsx`).

---

## 📌 Ringkasan Portofolio untuk CV / Resume

Bagian ini dirancang agar dapat langsung disalin ke CV, profil LinkedIn, maupun portofolio GitHub Anda:

### 💼 Deskripsi Proyek (Versi Bahasa Indonesia)
> **Full-Stack Developer — Sistem Monitoring & Penagihan Kontrakan Srini**
> * *Mengembangkan aplikasi web manajemen penghuni dan penagihan sewa berbasis PHP murni & SQLite dengan arsitektur zero-dependency tanpa ketergantungan library eksternal.*
> * *Merancang parser native OpenXML spreadsheet (`.xlsx`) berbasis `ZipArchive` dan `SimpleXML`, mengotomatisasi migrasi data dari format fisik/Excel ke database relasional.*
> * *Mengimplementasikan algoritma anti-duplikasi data berbasis identitas unik (NIK/KTP) yang secara otomatis memfilter dan melewati data redundan saat impor massal.*
> * *Membangun sistem peringatan dini jatuh tempo dinamis (H-2 hingga penunggakan) yang terintegrasi dengan WhatsApp Deep Linking API untuk pengiriman pesan tagihan instan 1-klik.*
> * *Merancang sistem audit log riwayat pembayaran, modul kuitansi digital dengan konversi angka ke terbilang rupiah otomatis, dan meraih skor kualitas kode **100/100** melalui quality gate `aislop`.*

### 💼 Project Description (English Version)
> **Full-Stack Web Developer — Residential Tenant & Billing Monitoring System**
> * *Engineered a lightweight, zero-dependency property and billing management web platform using native PHP 8 and SQLite.*
> * *Developed a custom, zero-dependency OpenXML `.xlsx` parser using PHP `ZipArchive` and `SimpleXML` to streamline tenant data ingestion from Excel spreadsheets.*
> * *Implemented an automated deduplication pipeline that verifies national identity numbers (NIK) during bulk imports to eliminate duplicate records.*
> * *Constructed an urgent due-date scheduling and alert engine (H-2 reminder system) integrated with WhatsApp Deep Linking API for automated 1-click invoice dispatch.*
> * *Engineered complete payment audit logging, automated digital receipt generator with Indonesian currency-to-words parsing, and maintained a deterministic **100/100** code health score verified by the `aislop` quality gate.*

---

## 🎯 Latar Belakang & Masalah (Problem Statement)
Pencatatan penghuni kos dan kontrakan secara manual melalui buku kas atau spreadsheet konvensional seringkali menimbulkan berbagai kendala operasional:
1. **Risiko Data Ganda**: Menggabungkan data penyewa dari berbagai file Excel sering menghasilkan duplikasi data penghuni.
2. **Keterlambatan Penagihan**: Sulit memantau kamar mana saja yang mendekati jatuh tempo sewa dalam 1–2 hari ke depan tanpa memeriksa baris data satu per satu.
3. **Ketiadaan Jejak Audit**: Tidak ada log riwayat ketika status sewa diubah atau ketika pembayaran dicicil/lunas.
4. **Proses Penagihan Lambat**: Pemilik harus mengetik pesan WhatsApp, rincian biaya, dan nomor rekening secara manual berulang kali.

**Solusi Kontrakan Srini**: Aplikasi web yang mengotomatisasi seluruh siklus monitoring sewa secara instan, portabel, dan tanpa perlu instalasi dependency server yang rumit.

---

## 🚀 Fitur Unggulan Sistem

### 1. 📊 Smart Dashboard & Pengurutan Jatuh Tempo Terdekat
- Algoritma query mengurutkan kamar belum bayar & membayar sebagian dengan tenggat waktu paling dekat di posisi teratas:
  $$\text{Priority: } \text{Status (Unpaid/Partial First)} \longrightarrow \text{Due Date (Ascending)}$$
- Indikator status visual berlapis:
  - 🔴 **Menunggak**: Tagihan melewati masa tenggang H+4 (mulai **H+5 ke atas**).
  - 🟠 **Belum Bayar (Hari H s/d H+4)**: Masa tenggang pembayaran sewa bulan berjalan.
  - 🟡 **H-2 & H-1 Mendekati Tenggat**: Peringatan dini sebelum jatuh tempo.
  - 💰 **Membayar Sebagian**: Pencatatan pembayaran parsial/DP dengan informasi nominal baru bayar dan sisa tagihan.
  - ⚪ **Belum Bayar (Aman)**: Jatuh tempo masih $> 2$ hari.
  - 🟢 **Sudah Lunas**: Pembayaran bulan berjalan telah selesai.

### 2. ⚡ WhatsApp Deep Linking Reminder (1-Click Notification)
- Tombol WhatsApp otomatis pada baris penghuni yang belum bayar maupun membayar sebagian.
- Menghasilkan tautan langsung ke WhatsApp dengan format pesan resmi:
  - Sapaan nama & nomor kamar
  - Rincian total tarif sewa, nominal baru bayar, dan sisa tagihan (untuk pembayaran sebagian)
  - Tanggal tenggat waktu & sisa hari / status keterlambatan
  - Nomor rekening bank penampung (dari pengaturan sistem)

### 3. 📥 Impor Spreadsheet .xlsx & Mesin Anti-Duplikasi
- **Native Parser**: Mengurai file `.xlsx` secara murni menggunakan XML node parsing tanpa memerlukan library berat seperti `PhpSpreadsheet`.
- **Deduplikasi Cerdas**: Memverifikasi nomor identitas (NIK 16 digit). Jika NIK sudah ada di database, baris dilewati (**skipped**) dan dilaporkan dalam rekapitulasi akhir impor.

### 4. 📝 Audit Trail & Log Riwayat Status
- Setiap kali status diubah (*Belum Bayar $\leftrightarrow$ Sudah Bayar*), sistem mencatat data ke tabel `payment_logs`:
  - Waktu perubahan (*timestamp*)
  - Nominal pembayaran
  - Metode pembayaran (*Transfer BCA, Mandiri, BRI, QRIS, Tunai*)
  - Catatan bukti transaksi

### 5. 🧾 Generator Kuitansi Pembayaran Digital
- Halaman cetak kuitansi resmi siap cetak / simpan ke format PDF.
- Dilengkapi fungsi rekursif **Terbilang Rupiah** otomatis (misal: *Rp 1.500.000* $\rightarrow$ *"Satu Juta Lima Ratus Ribu Rupiah"*), stempel digital **LUNAS**, dan tanda tangan pengelola.

---

## 🛠️ Arsitektur & Teknologi

| Komponen | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | PHP 8.2+ (Native) | Kode modular, tanpa bloatware framework |
| **Database** | SQLite via PDO | *Self-contained*, file lokal `data/kos.db` |
| **Frontend** | Bootstrap 5, FontAwesome 6 | Responsive, mobile-friendly UI |
| **Excel Parser** | `SimpleXLSX` (ZipArchive + SimpleXML) | Zero-dependency, konsumsi memori rendah |
| **Quality Gate**| `aislop` 0.19+ | Audit kode otomatis (Skor: 100/100 Clean) |

---

## 📂 Struktur Berkas Proyek

```text
D:\Project Mandiri\Monitoring Kos\
│
├── data/
│   └── kos.db                  # Database SQLite (dibuat & dimigrasi otomatis)
├── includes/
│   ├── header.php              # Navigasi utama & badge counter H-2
│   ├── payment_modal.php       # Komponen modal konfirmasi bayar
│   └── footer.php              # Footer template
├── db.php                      # Koneksi database PDO & schema database
├── SimpleXLSX.php              # Parser native OpenXML XLSX (Zero-dependency)
├── index.php                   # Dashboard monitoring & tabel tenggat
├── upload.php                  # Engine upload .xlsx & anti-duplikasi data
├── update_status.php           # Handler transaksi & pencatatan log
├── history.php                 # Log audit riwayat transaksi & filter
├── tenants.php                 # CRUD manajemen data penghuni
├── receipt.php                 # Template cetak kuitansi digital resmi
├── settings.php                # Konfigurasi nama kontrakan & rekening bank
├── download_template.php       # Endpoint download file template asli .xlsx
├── export_logs.php             # Export rekap riwayat pembayaran ke CSV
├── Format_Penghuni_Kost_Kontrakan.xlsx # Template Excel bawaan
└── README.md                   # Dokumentasi teknis & referensi portofolio
```

---

## 💻 Panduan Instalasi & Menjalankan

Aplikasi ini tidak membutuhkan instalasi Composer atau konfigurasi web server yang rumit.

### 1. Prasyarat
- PHP versi 8.0 ke atas dengan ekstensi `pdo_sqlite`, `zip`, dan `simplexml` (aktif secara default pada instalasi PHP standar, XAMPP, dan Laragon).

### 2. Menjalankan Server Lokal
Cukup buka terminal di folder proyek:

```bash
# Masuk ke direktori proyek
cd "D:\Project Mandiri\Monitoring Kos"

# Jalankan server bawaan PHP
php -S localhost:8000
```

Buka peramban (browser) dan akses:
👉 **`http://localhost:8000`**

---

## 🏆 Kualitas Kode & Standar Rekayasa (Code Quality)

Proyek ini telah diaudit menggunakan **`aislop`**, sebuah quality gate deterministik untuk menganalisis kompleksitas kode, deteksi *code smell*, dan kebersihan sintaksis:

```text
aislop 0.19.1 · the quality gate for agentic coding

Scan result · Monitoring Kos · php · 14 files

[ok] Code Quality: done (0 issues)
[ok] AI Slop: done (0 issues)
[ok] Security: done (0 issues)

✓ Clean run · 100 / 100 · Healthy · no issues
```

---

## 📄 Lisensi
Dikembangkan sebagai proyek mandiri dan dapat digunakan secara bebas untuk keperluan personal, portofolio, maupun komersial.
