-- ====================================================================
-- SKEMA BASIS DATA TOKO E-COMMERCE KOSINAJA (TERPADU 3 ROLE)
-- Mitra: Kos Putri Anak Lanang (Ibu Yeni - Pasir Pengaraian, Riau)
-- Role Pengguna: 1. Developer, 2. Mitra (Ibu Yeni), 3. Penyewa (Anak Kos)
-- Mata Kuliah: E-Commerce (Bab 5 & Bab 6)
-- Target Deploy: Hostinger (PHP 8.x & MySQL, Domain .com, SSL HTTPS)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS kosinaja_db;
USE kosinaja_db;

-- 1. Entitas Pengguna (Autentikasi Terpadu 3 Role - UC-08 Login)
CREATE TABLE IF NOT EXISTS pengguna (
    id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    kata_sandi_hash VARCHAR(255) NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    role ENUM('developer', 'mitra', 'penyewa') NOT NULL,
    id_kamar_huni INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Entitas Kos (Profil & UC-12 Kelola Data Katalog - Roja)
CREATE TABLE IF NOT EXISTS kos (
    id_kos INT AUTO_INCREMENT PRIMARY KEY,
    id_pemilik INT NOT NULL,
    nama_kos VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    deskripsi TEXT,
    no_wa_kontak VARCHAR(20) NOT NULL,
    nib VARCHAR(50), -- Kepatuhan Permendag 19/2026
    asal_layanan VARCHAR(50) DEFAULT 'Layanan Dalam Negeri',
    aturan_kos TEXT,
    banner_promo TEXT,
    tanggal_pembaruan TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Entitas Kamar (UC-01, UC-02, UC-03, UC-07, UC-09, UC-10)
CREATE TABLE IF NOT EXISTS kamar (
    id_kamar INT AUTO_INCREMENT PRIMARY KEY,
    id_kos INT NOT NULL,
    nomor_kamar VARCHAR(20) NOT NULL,
    tipe ENUM('kosongan', 'berperabot') NOT NULL,
    ukuran VARCHAR(20) DEFAULT '3x4 m',
    harga DECIMAL(12,2) NOT NULL,
    status ENUM('kosong', 'dipesan', 'terisi') DEFAULT 'kosong',
    video_url TEXT, -- UC-04 Video Tur
    tanggal_pembaruan TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kos) REFERENCES kos(id_kos) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Entitas Biaya_Kamar (Deposit Jaminan Transparan - Slide 7)
CREATE TABLE IF NOT EXISTS biaya_kamar (
    id_biaya INT AUTO_INCREMENT PRIMARY KEY,
    id_kamar INT NOT NULL,
    nama_biaya VARCHAR(100) NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Entitas Fasilitas (Master Data Fasilitas)
CREATE TABLE IF NOT EXISTS fasilitas (
    id_fasilitas INT AUTO_INCREMENT PRIMARY KEY,
    nama_fasilitas VARCHAR(100) NOT NULL,
    icon VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Entitas Kamar_Fasilitas (Relasi Many-to-Many)
CREATE TABLE IF NOT EXISTS kamar_fasilitas (
    id_kamar INT NOT NULL,
    id_fasilitas INT NOT NULL,
    PRIMARY KEY (id_kamar, id_fasilitas),
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE,
    FOREIGN KEY (id_fasilitas) REFERENCES fasilitas(id_fasilitas) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Entitas Media_Kamar (Minimal 3 Foto Terkompresi - Bab 5 Slide 8)
CREATE TABLE IF NOT EXISTS media_kamar (
    id_media INT AUTO_INCREMENT PRIMARY KEY,
    id_kamar INT NOT NULL,
    jenis ENUM('foto', 'video') DEFAULT 'foto',
    url_file TEXT NOT NULL,
    is_utama TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Entitas Pemesanan (UC-06 Ajukan Pemesanan & UC-11 Tanggapi Pemesanan)
CREATE TABLE IF NOT EXISTS pemesanan (
    id_pemesanan INT AUTO_INCREMENT PRIMARY KEY,
    id_kamar INT NOT NULL,
    id_pengguna INT DEFAULT NULL,
    kode_booking VARCHAR(30) UNIQUE NOT NULL,
    nama_penyewa VARCHAR(100) NOT NULL,
    no_wa_penyewa VARCHAR(20) NOT NULL,
    kampus_instansi VARCHAR(100),
    tanggal_masuk DATE NOT NULL,
    durasi_sewa VARCHAR(20) DEFAULT '1 Bulan',
    catatan TEXT,
    status_pemesanan ENUM('menunggu', 'disetujui', 'ditolak', 'selesai') DEFAULT 'menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE,
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Entitas Pembayaran (Studi Kasus Pencatatan Pembayaran Manual: Calon, Penghuni, Rekap Bulanan Pemilik)
CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) UNIQUE NOT NULL,
    id_pemesanan INT DEFAULT NULL,
    id_pengguna INT DEFAULT NULL,
    id_kamar INT NOT NULL,
    kategori_pembayaran ENUM('dp_booking', 'sewa_pertama', 'sewa_bulanan', 'deposit_jaminan', 'denda_lainnya') NOT NULL,
    periode_bulan VARCHAR(50) DEFAULT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    metode_pembayaran VARCHAR(50) NOT NULL, -- 'transfer_bri', 'transfer_bca', 'transfer_mandiri', 'transfer_bsi', 'tunai_cash'
    nama_pengirim VARCHAR(100),
    bank_asal VARCHAR(50),
    nomor_rekening_pengirim VARCHAR(50),
    tanggal_bayar DATE NOT NULL,
    bukti_bayar TEXT,
    status_pembayaran ENUM('menunggu', 'lunas', 'ditolak') DEFAULT 'menunggu',
    catatan_pembayar TEXT,
    catatan_pemilik TEXT,
    diverifikasi_oleh VARCHAR(100),
    tanggal_verifikasi DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pemesanan) REFERENCES pemesanan(id_pemesanan) ON DELETE SET NULL,
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL,
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
