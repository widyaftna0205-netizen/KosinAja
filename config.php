<?php
// config.php - Konfigurasi Database SQLite, Session & 3 Role Pengguna KosinAja

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @session_save_path($sessionDir);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$dbDir = __DIR__ . '/database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$buktiDir = $uploadDir . '/bukti';
if (!is_dir($buktiDir)) {
    mkdir($buktiDir, 0777, true);
}

$dbPath = $dbDir . '/kosinaja.db';
$isFirstInit = !file_exists($dbPath);

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// 1. Tabel Terpadu 3 Role Pengguna (Developer, Mitra Pemilik, Calon/Penghuni)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pengguna (
        id_pengguna INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        kata_sandi_hash TEXT NOT NULL,
        no_wa TEXT NOT NULL,
        role TEXT NOT NULL, -- 'developer', 'mitra', 'penyewa'
        id_kamar_huni INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS kos (
        id_kos INTEGER PRIMARY KEY AUTOINCREMENT,
        id_pemilik INTEGER NOT NULL,
        nama_kos TEXT NOT NULL,
        alamat TEXT NOT NULL,
        deskripsi TEXT,
        no_wa_kontak TEXT NOT NULL,
        nib TEXT,
        asal_layanan TEXT DEFAULT 'Layanan Dalam Negeri',
        aturan_kos TEXT,
        banner_promo TEXT,
        tanggal_pembaruan DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS kamar (
        id_kamar INTEGER PRIMARY KEY AUTOINCREMENT,
        id_kos INTEGER NOT NULL,
        nomor_kamar TEXT NOT NULL,
        tipe TEXT NOT NULL,
        ukuran TEXT DEFAULT '3x4 m',
        harga REAL NOT NULL,
        status TEXT DEFAULT 'kosong', -- 'kosong', 'dipesan', 'terisi'
        video_url TEXT,
        tanggal_pembaruan DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_kos) REFERENCES kos(id_kos)
    );

    CREATE TABLE IF NOT EXISTS biaya_kamar (
        id_biaya INTEGER PRIMARY KEY AUTOINCREMENT,
        id_kamar INTEGER NOT NULL,
        nama_biaya TEXT NOT NULL,
        nominal REAL NOT NULL,
        FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS fasilitas (
        id_fasilitas INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_fasilitas TEXT NOT NULL,
        icon TEXT
    );

    CREATE TABLE IF NOT EXISTS kamar_fasilitas (
        id_kamar INTEGER NOT NULL,
        id_fasilitas INTEGER NOT NULL,
        PRIMARY KEY (id_kamar, id_fasilitas),
        FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE,
        FOREIGN KEY (id_fasilitas) REFERENCES fasilitas(id_fasilitas)
    );

    CREATE TABLE IF NOT EXISTS media_kamar (
        id_media INTEGER PRIMARY KEY AUTOINCREMENT,
        id_kamar INTEGER NOT NULL,
        jenis TEXT DEFAULT 'foto',
        url_file TEXT NOT NULL,
        is_utama INTEGER DEFAULT 0,
        FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS pemesanan (
        id_pemesanan INTEGER PRIMARY KEY AUTOINCREMENT,
        id_kamar INTEGER NOT NULL,
        id_pengguna INTEGER DEFAULT NULL,
        kode_booking TEXT UNIQUE NOT NULL,
        nama_penyewa TEXT NOT NULL,
        no_wa_penyewa TEXT NOT NULL,
        kampus_instansi TEXT,
        tanggal_masuk DATE NOT NULL,
        durasi_sewa TEXT DEFAULT '1 Bulan',
        catatan TEXT,
        status_pemesanan TEXT DEFAULT 'menunggu', -- 'menunggu', 'disetujui', 'ditolak', 'selesai'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE,
        FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
    );

    -- 9. Entitas Pembayaran (Studi Kasus Pencatatan Pembayaran Manual: Calon, Penghuni, Rekap Pemilik)
    CREATE TABLE IF NOT EXISTS pembayaran (
        id_pembayaran INTEGER PRIMARY KEY AUTOINCREMENT,
        kode_transaksi TEXT UNIQUE NOT NULL,
        id_pemesanan INTEGER DEFAULT NULL,
        id_pengguna INTEGER DEFAULT NULL,
        id_kamar INTEGER NOT NULL,
        kategori_pembayaran TEXT NOT NULL, -- 'dp_booking', 'sewa_pertama', 'sewa_bulanan', 'deposit_jaminan', 'denda_lainnya'
        periode_bulan TEXT DEFAULT NULL,   -- e.g. 'November 2026'
        nominal REAL NOT NULL,
        metode_pembayaran TEXT NOT NULL,   -- 'transfer_bri', 'transfer_bca', 'transfer_mandiri', 'transfer_bsi', 'tunai_cash'
        nama_pengirim TEXT DEFAULT NULL,
        bank_asal TEXT DEFAULT NULL,
        nomor_rekening_pengirim TEXT DEFAULT NULL,
        tanggal_bayar DATE NOT NULL,
        bukti_bayar TEXT DEFAULT NULL,
        status_pembayaran TEXT DEFAULT 'menunggu', -- 'menunggu', 'lunas', 'ditolak'
        catatan_pembayar TEXT DEFAULT NULL,
        catatan_pemilik TEXT DEFAULT NULL,
        diverifikasi_oleh TEXT DEFAULT NULL,
        tanggal_verifikasi DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_pemesanan) REFERENCES pemesanan(id_pemesanan) ON DELETE SET NULL,
        FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL,
        FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE
    );
");

// Tambah kolom id_pengguna di pemesanan jika tabel sudah ada sebelumnya
try {
    $pdo->exec("ALTER TABLE pemesanan ADD COLUMN id_pengguna INTEGER DEFAULT NULL");
} catch (Exception $e) {}

// Seed Data Pembayaran Awal (Studi Kasus Pencatatan Manual Pemilik Kos)
$checkBayar = $pdo->query("SELECT COUNT(*) FROM pembayaran")->fetchColumn();
if ($checkBayar == 0) {
    $stmtBayar = $pdo->prepare("
        INSERT INTO pembayaran (
            kode_transaksi, id_pemesanan, id_pengguna, id_kamar, kategori_pembayaran,
            periode_bulan, nominal, metode_pembayaran, nama_pengirim, bank_asal,
            nomor_rekening_pengirim, tanggal_bayar, bukti_bayar, status_pembayaran,
            catatan_pembayar, catatan_pemilik, diverifikasi_oleh, tanggal_verifikasi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // 1. Deposit Jaminan Kunci Nadia Safitri (Kamar 01) - LUNAS
    $stmtBayar->execute([
        'TRX-202609-001',
        1,
        3,
        1,
        'deposit_jaminan',
        null,
        200000,
        'transfer_bri',
        'Nadia Safitri',
        'BRI',
        '5421-01-998877-50-2',
        '2026-09-25',
        'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500',
        'lunas',
        'Deposit jaminan kunci kamar 01 awal masuk',
        'Pencatatan manual: Mutasi rekening BRI dicek dan sesuai. Uang deposit masuk ke rekening penampung.',
        'Ibu Yeni (Mitra Pemilik)',
        '2026-09-25 14:30:00'
    ]);

    // 2. Sewa Bulan Oktober 2026 Nadia Safitri (Kamar 01) - LUNAS
    $stmtBayar->execute([
        'TRX-202610-001',
        1,
        3,
        1,
        'sewa_bulanan',
        'Oktober 2026',
        850000,
        'transfer_bri',
        'Nadia Safitri',
        'BRI',
        '5421-01-998877-50-2',
        '2026-10-08',
        'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500',
        'lunas',
        'Sewa kamar 01 bulan Oktober 2026',
        'Pencatatan manual: Mutasi BRI masuk Rp 850.000 atas nama Nadia Safitri.',
        'Ibu Yeni (Mitra Pemilik)',
        '2026-10-08 10:15:00'
    ]);

    // 3. Sewa Bulan November 2026 Nadia Safitri (Kamar 01) - MENUNGGU VERIFIKASI (untuk uji coba verifikasi Ibu Yeni)
    $stmtBayar->execute([
        'TRX-202611-001',
        1,
        3,
        1,
        'sewa_bulanan',
        'November 2026',
        850000,
        'transfer_bri',
        'Nadia Safitri',
        'BRI',
        '5421-01-998877-50-2',
        '2026-11-04',
        'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500',
        'menunggu',
        'Pembayaran sewa kamar 01 periode November 2026 via BRI Mobile',
        null,
        null,
        null
    ]);

    // 4. DP Booking Calon Penghuni Nadia Safitri untuk Kamar 02 - MENUNGGU VERIFIKASI (untuk uji alur calon penghuni)
    $stmtBayar->execute([
        'TRX-202611-002',
        3,
        3,
        2,
        'dp_booking',
        null,
        250000,
        'transfer_bca',
        'Nadia Safitri',
        'BCA',
        '821-9988776',
        '2026-11-04',
        'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500',
        'menunggu',
        'Uang muka / DP tanda jadi booking Kamar 02 Kosongan',
        null,
        null,
        null
    ]);

    // 5. Pembayaran Tunai Manual (Cash) Kamar 12 - LUNAS (Dicatat langsung oleh Ibu Yeni di buku manual)
    $stmtBayar->execute([
        'TRX-202611-003',
        null,
        null,
        4,
        'sewa_bulanan',
        'November 2026',
        700000,
        'tunai_cash',
        'Rina Melati (Penghuni Kamar 12)',
        null,
        null,
        '2026-11-02',
        null,
        'lunas',
        'Bayar tunai sewa kamar 12 periode November 2026',
        'Pencatatan manual Ibu Yeni: Uang tunai Rp 700.000 diserahkan langsung di teras rumah. Nota kuitansi kertas #084 telah diserahkan.',
        'Ibu Yeni (Mitra Pemilik)',
        '2026-11-02 16:45:00'
    ]);
}

// Seed 3 Role Pengguna Awal
$checkUsers = $pdo->query("SELECT COUNT(*) FROM pengguna")->fetchColumn();
if ($checkUsers == 0) {
    $stmtUser = $pdo->prepare("INSERT INTO pengguna (nama, email, kata_sandi_hash, no_wa, role, id_kamar_huni) VALUES (?, ?, ?, ?, ?, ?)");
    $passHashDev = password_hash('admin123', PASSWORD_BCRYPT);
    $passHashMitra = password_hash('admin123', PASSWORD_BCRYPT);
    $passHashPenyewa = password_hash('user123', PASSWORD_BCRYPT);

    // 1. Role Developer
    $stmtUser->execute([
        'Tim Developer KosinAja (Mahasiswa SI)',
        'dev@kosinaja.id',
        $passHashDev,
        '081234567899',
        'developer',
        null
    ]);

    // 2. Role Mitra (Ibu Yeni)
    $stmtUser->execute([
        'Ibu Yeni (Mitra Pemilik Kos)',
        'yeni@kosinaja.id',
        $passHashMitra,
        '081275998811',
        'mitra',
        null
    ]);

    // 3. Role Penghuni / Calon Penghuni
    $stmtUser->execute([
        'Nadia Safitri (Mahasiswi Rantau / Penghuni)',
        'penyewa@kosinaja.id',
        $passHashPenyewa,
        '081399887766',
        'penyewa',
        1 // Menghuni Kamar 01
    ]);
}

// Helper Functions
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function namaBulanIndo($bulan) {
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $b = (int)$bulan;
    return $namaBulan[$b] ?? '';
}

function penyebutTerbilang($nilai) {
    $nilai = abs($nilai);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $temp = "";
    if ($nilai < 12) {
        $temp = " " . $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = penyebutTerbilang($nilai - 10) . " Belas";
    } else if ($nilai < 100) {
        $temp = penyebutTerbilang(floor($nilai / 10)) . " Puluh" . penyebutTerbilang($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . penyebutTerbilang($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = penyebutTerbilang(floor($nilai / 100)) . " Ratus" . penyebutTerbilang($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . penyebutTerbilang($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = penyebutTerbilang(floor($nilai / 1000)) . " Ribu" . penyebutTerbilang($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = penyebutTerbilang(floor($nilai / 1000000)) . " Juta" . penyebutTerbilang($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = penyebutTerbilang(floor($nilai / 1000000000)) . " Milyar" . penyebutTerbilang(fmod($nilai, 1000000000));
    }
    return $temp;
}

function terbilangRupiah($nilai) {
    if ($nilai < 0) {
        $hasil = "Minus " . trim(penyebutTerbilang($nilai));
    } else {
        $hasil = trim(penyebutTerbilang($nilai));
    }
    return preg_replace('/\s+/', ' ', $hasil) . " Rupiah";
}

function labelMetodePembayaran($metode) {
    switch ($metode) {
        case 'transfer_bri':
            return 'Transfer Bank BRI (5421-01-018829-53-1 a.n. Yeni)';
        case 'transfer_bca':
            return 'Transfer Bank BCA (821-0938-112 a.n. Yeni)';
        case 'transfer_mandiri':
            return 'Transfer Bank Mandiri (108-00-1928374-1 a.n. Yeni)';
        case 'transfer_bsi':
            return 'Transfer Bank BSI (718-293-8472 a.n. Yeni)';
        case 'tunai_cash':
            return 'Tunai / Cash (Langsung ke Ibu Yeni)';
        default:
            return ucwords(str_replace('_', ' ', $metode));
    }
}

function labelKategoriPembayaran($kat) {
    switch ($kat) {
        case 'dp_booking':
            return 'Uang Muka (DP Booking)';
        case 'sewa_pertama':
            return 'Sewa Masuk Pertama';
        case 'sewa_bulanan':
            return 'Sewa Kos Bulanan';
        case 'deposit_jaminan':
            return 'Deposit Jaminan Kunci';
        case 'denda_lainnya':
            return 'Biaya Lain / Denda';
        default:
            return ucwords(str_replace('_', ' ', $kat));
    }
}

function generateKodeTransaksi($prefix = 'TRX') {
    return $prefix . '-' . date('Ym') . '-' . rand(100, 999);
}

// =====================================================================
// RBAC Helper Functions
// Hierarki Akses: developer > mitra > penyewa
// - developer  : akses semua panel (developer, mitra, penyewa)
// - mitra      : akses panel mitra saja (kelola kamar, pemesanan, profil kos)
// - penyewa    : akses panel penyewa saja (dasbor, cek status)
// =====================================================================

function isDeveloper() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'developer';
}

function isMitra() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'mitra';
}

function isPenyewa() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'penyewa';
}

function isMitraOrDeveloper() {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['mitra', 'developer']);
}

/**
 * Cek autentikasi & otorisasi role.
 * Developer SELALU lolos karena punya akses super.
 * @param array $allowedRoles role yang diizinkan selain developer
 */
function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php?pesan=harus_login");
        exit;
    }
    // Developer selalu bisa akses halaman manapun
    if ($_SESSION['user']['role'] === 'developer') {
        return;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['user']['role'], $allowedRoles)) {
        // Redirect sesuai role jika tidak punya izin
        switch ($_SESSION['user']['role']) {
            case 'penyewa':
                header("Location: penyewa_dashboard.php?akses=ditolak");
                break;
            case 'mitra':
                header("Location: kamar.php?akses=ditolak");
                break;
            default:
                header("Location: index.php");
        }
        exit;
    }
}

/**
 * Redirect setelah login berdasarkan role
 */
function redirectAfterLogin($role) {
    switch ($role) {
        case 'developer':
            header("Location: developer_panel.php?login=sukses");
            break;
        case 'mitra':
            header("Location: kamar.php?login=sukses");
            break;
        case 'penyewa':
            header("Location: penyewa_dashboard.php?login=sukses");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}
