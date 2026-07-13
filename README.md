# 🎫 HaloTiket — Sistem Manajemen E-Ticketing

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-CDN-38B2AC?style=for-the-badge&logo=tailwind-css)
![Midtrans](https://img.shields.io/badge/Midtrans-Payment_Gateway-003366?style=for-the-badge)

**Platform e-ticketing modern berbasis PHP & MySQL dengan integrasi Midtrans, sistem approval event, QR Code, dan manajemen multi-role pengguna.**

</div>

---

## 📋 Daftar Isi

- [Fitur Unggulan](#-fitur-unggulan)
- [Peran Pengguna](#-peran-pengguna)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#️-konfigurasi)
- [Struktur Direktori](#-struktur-direktori)
- [Alur Sistem](#-alur-sistem)
- [Akun Default](#-akun-default)
- [Teknologi](#-teknologi)

---

## ✨ Fitur Unggulan

### 🌐 Halaman Publik
| Fitur | Keterangan |
|---|---|
| Beranda Event | Menampilkan event terbaru & akan datang dengan filter kategori dan pencarian |
| Detail Event | Info lengkap event: deskripsi, jadwal, lokasi, vendor, link Google Maps |
| Pembelian Tiket | Form checkout dengan integrasi pembayaran Midtrans (Maks. 1 tiket per akun) |
| Cek Tiket | Pencarian E-Ticket berdasarkan email pembeli (dilengkapi proteksi anti-scraping) |
| Unduh E-Ticket | Download tiket dalam format PDF lengkap dengan QR Code |
| Riwayat Pembelian | Lihat seluruh history transaksi berdasarkan email |

### 🛡️ Dashboard Admin
| Fitur | Keterangan |
|---|---|
| Kelola Event | Buat, edit, hapus event dengan upload hingga 4 foto |
| Approval Event | Setujui atau tolak event yang diajukan oleh Panitia |
| Kelola Pengguna | Tambah, edit, nonaktifkan akun Panitia & Validator |
| Kelola Kategori | Buat kategori event kustom |
| Pengaturan Sistem | Upload logo, favicon, konfigurasi kontak & sosial media |
| Biaya Platform | Atur markup harga (Admin Fee) dinamis dalam Persentase (%) atau Nominal (Rp) |
| Log Aktivitas | Pantau semua aktivitas pengguna di sistem |
| Estimasi Pendapatan | Lihat ringkasan penjualan tiket per event |

### 🎪 Dashboard Panitia
| Fitur | Keterangan |
|---|---|
| Buat Event | Submit event baru (memerlukan persetujuan Admin) |
| Status Event | Pantau status event: Pending, Disetujui, Ditolak |
| Laporan Penjualan | Lihat data tiket terjual & estimasi pendapatan event |

### ✅ Dashboard Validator
| Fitur | Keterangan |
|---|---|
| Scan QR Code | Validasi tiket pengunjung menggunakan kamera perangkat |
| Update Status | Tiket otomatis berubah status menjadi "Scanned" setelah diverifikasi |

---

## 👥 Peran Pengguna

```
Admin ──── Semua akses penuh + Approval event Panitia
  │
  ├── Panitia ── Buat & kelola event sendiri (perlu approval Admin)
  │
  └── Validator ── Scan & verifikasi QR Code tiket di pintu masuk
```

---

## 📌 Persyaratan Sistem

- **Web Server:** Apache (direkomendasikan via Laragon / XAMPP / WAMP)
- **PHP:** versi 8.0 ke atas
- **MySQL / MariaDB:** versi 5.7 ke atas
- **Composer:** untuk manajemen dependensi (Midtrans SDK)
- **Koneksi Internet:** untuk mengakses CDN Tailwind CSS & integrasi Midtrans

---

## 🚀 Instalasi

### Langkah 1: Kloning / Ekstrak Proyek

Letakkan folder `Halo_Tiket` di dalam root direktori web server Anda.

**Laragon:** `C:\laragon\www\Halo_Tiket\`
**XAMPP:** `C:\xampp\htdocs\Halo_Tiket\`

### Langkah 2: Buat Database

1. Buka **phpMyAdmin** (http://localhost/phpmyadmin)
2. Buat database baru dengan nama `halotiket_db`
3. Pilih database tersebut, klik tab **Import**
4. Pilih file `database.sql` dari folder proyek ini
5. Klik **Go** / **Impor**

### Langkah 3: Install Dependensi (Composer)

Buka terminal di folder proyek, lalu jalankan:

```bash
composer install
```

> Perintah ini akan mengunduh **Midtrans PHP SDK** secara otomatis ke folder `vendor/`.

### Langkah 4: Konfigurasi Database

Buka file `config/koneksi.php` dan sesuaikan nilai berikut:

```php
$host = "localhost";
$user = "root";       // username database Anda
$pass = "";           // password database Anda
$db   = "halotiket_db";
```

### Langkah 5: Buat Folder Upload

Pastikan folder-folder berikut ada dan dapat ditulis (*writable*):

```
assets/
└── images/
    ├── events/      ← untuk banner/foto event
    ├── logo/        ← untuk logo situs
    └── favicon/     ← untuk favicon situs
```

> Di Windows dengan Laragon, folder ini biasanya sudah bisa ditulis secara default.

### Langkah 6: Akses Aplikasi

Buka browser dan akses:
```
http://localhost/Halo_Tiket/
```

---

## ⚙️ Konfigurasi

### Konfigurasi Midtrans (Payment Gateway)

1. Daftar akun di [https://midtrans.com](https://midtrans.com) (tersedia akun Sandbox gratis)
2. Login ke Dashboard Midtrans → **Settings → Access Keys**
3. Salin **Server Key** dan **Client Key**
4. Login sebagai Admin di aplikasi → Buka menu **Pengaturan**
5. Masukkan kedua key tersebut pada form yang tersedia

Untuk Notifikasi Pembayaran, atur **Payment Notification URL** di Dashboard Midtrans ke:
```
http://[domain-anda]/Halo_Tiket/midtrans_notification.php
```

### Konfigurasi Tampilan (Logo, Kontak, Sosmed)

Masuk sebagai Admin → **Pengaturan**. Di sini Anda dapat mengatur:
- **Logo** & **Favicon** situs (upload file PNG/JPG)
- **Alamat Kantor** yang tampil di footer
- **Nomor Customer Service** (format internasional, misal: `628123456789`)
- **Link Instagram** & **TikTok**

---

## 📁 Struktur Direktori

```
Halo_Tiket/
│
├── 📁 admin/                  # Halaman dashboard Admin
│   ├── index.php              # Ringkasan & statistik
│   ├── manage_events.php      # Kelola & approval event
│   ├── manage_users.php       # Kelola pengguna
│   ├── manage_categories.php  # Kelola kategori event
│   ├── settings.php           # Pengaturan sistem
│   ├── system_logs.php        # Log aktivitas
│   ├── profile.php            # Profil admin
│   └── api_settings.php       # API untuk pengaturan
│
├── 📁 panitia/                # Halaman dashboard Panitia
│   ├── index.php              # Ringkasan event & penjualan
│   ├── manage_events.php      # Buat & kelola event
│   └── laporan_sales.php      # Laporan penjualan
│
├── 📁 validator/              # Halaman dashboard Validator
│   ├── index.php              # Scanner QR Code
│   └── proses_scan.php        # Proses validasi token QR
│
├── 📁 auth/                   # Autentikasi pengguna
│   ├── login.php              # Halaman login
│   ├── register.php           # Halaman registrasi
│   ├── proses_login.php       # Logic proses login
│   ├── proses_register.php    # Logic proses registrasi
│   └── logout.php             # Proses logout
│
├── 📁 config/
│   └── koneksi.php            # Koneksi database & konfigurasi global
│
├── 📁 assets/
│   ├── css/style.css          # Custom stylesheet
│   └── images/
│       ├── events/            # Foto/banner event
│       ├── logo/              # File logo situs
│       └── favicon/           # File favicon situs
│
├── 📁 vendor/                 # Dependensi Composer (Midtrans SDK)
│
├── index.php                  # Halaman beranda publik
├── detail_event.php           # Halaman detail event
├── checkout.php               # Halaman pembelian tiket
├── proses_checkout.php        # Logic proses pembelian
├── midtrans_notification.php  # Webhook notifikasi Midtrans
├── cek_tiket.php              # Halaman cek tiket via email
├── riwayat_pembelian.php      # Riwayat pembelian tiket
├── download_tiket.php         # Download E-Ticket PDF
├── database.sql               # Skema database lengkap
├── composer.json              # Konfigurasi dependensi
└── README.md                  # Dokumentasi ini
```

---

## 🔄 Alur Sistem

### Alur Pembelian Tiket

```
Pengunjung → Pilih Event → Isi Form Checkout
    → Pilih Metode Bayar (Midtrans) → Pembayaran Berhasil
    → E-Ticket dikirim ke Email → Download PDF + QR Code
    → Validator Scan QR di Pintu Masuk → Status: Scanned ✓
```

### Alur Pembuatan Event (Panitia)

```
Panitia Login → Buat Event (isi form + upload foto)
    → Status: PENDING → Admin Menerima Notifikasi
    → Admin Review → Disetujui / Ditolak
    → Jika Disetujui: Event tampil di halaman publik ✓
```

> **Catatan:** Event yang dibuat langsung oleh **Admin** akan otomatis berstatus **Disetujui** tanpa perlu melalui proses approval.

---

## 🔑 Akun Default

| Role | Email | Password |
|---|---|---|
| **Admin** | `admin@halotiket.com` | `password` |
| **Panitia** | `panitia@gmail.com` | `panitia123` |
| **Validator** | `validator@gmail.com` | `validator123` |

> ⚠️ **Penting:** Segera ganti password default akun-akun ini setelah instalasi pertama!

Untuk membuat akun Panitia atau Validator lainnya, Admin dapat melakukannya melalui menu **Kelola Pengguna** di dashboard Admin.

---

## 🛠 Teknologi

| Komponen | Teknologi |
|---|---|
| **Backend** | PHP 8.x (Native / Procedural) |
| **Database** | MySQL 5.7+ / MariaDB |
| **Frontend** | HTML5, Vanilla CSS, Tailwind CSS (via CDN) |
| **Payment Gateway** | Midtrans (mendukung QRIS, Transfer Bank, dll.) |
| **QR Code** | PHP QR Code Library (via Composer) |
| **PDF Generation** | HTML to PDF (native PHP) |
| **Web Server** | Apache (Laragon / XAMPP) |

---

## 📝 Catatan Penting

- **Mode Sandbox Midtrans:** Secara default, aplikasi berjalan dalam mode **Sandbox** (testing). Ganti ke mode Production di pengaturan Admin setelah aplikasi siap diluncurkan.
- **Biaya Layanan (Markup):** Platform membebankan biaya layanan kepada pembeli. Nilai markup ini ditambahkan ke harga tiket dasar dan dapat diatur oleh Admin (Pilih antara % atau flat rate).
- **Pembatasan Pembelian:** Sistem membatasi pembelian maksimal **1 tiket per event untuk 1 alamat email** guna mencegah *overselling* atau *scalping*.
- **Upload Gambar:** Ukuran file gambar yang disarankan untuk banner event adalah maksimal **2MB** dengan format **JPG, JPEG, atau PNG**.
- **QR Code:** Token QR Code setiap tiket bersifat unik dan hanya dapat di-scan satu kali.
- **Keamanan:** Password pengguna disimpan menggunakan enkripsi **bcrypt** (`password_hash`), dan seluruh *query* menggunakan **Prepared Statements** anti-SQL Injection.

---

<div align="center">

**HaloTiket** — *Simplifying Event Ticketing*

</div>
