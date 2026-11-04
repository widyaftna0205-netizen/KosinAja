<?php
// admin_keuangan.php - Rekap Data Bulanan & Pembukuan Pembayaran Pemilik Kos (Ibu Yeni)
// Sesuai Studi Kasus Pencatatan Pembayaran Secara Manual
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$user = $_SESSION['user'];

// Ambil info kos
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmtKos->fetch();

$notifSukses = '';
$notifError = '';

// 1. Aksi Verifikasi Lunas oleh Pemilik Kos
if (isset($_POST['action']) && $_POST['action'] === 'verifikasi_lunas') {
    $idTrx = (int)($_POST['id_pembayaran'] ?? 0);
    $catatanPemilik = trim($_POST['catatan_pemilik'] ?? 'Pencatatan manual: Pembayaran telah diperiksa dan dinyatakan LUNAS oleh Ibu Yeni.');
    
    // Ambil data pembayaran
    $stmtFind = $pdo->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ?");
    $stmtFind->execute([$idTrx]);
    $trx = $stmtFind->fetch();

    if ($trx) {
        $verifier = isDeveloper() ? 'Tim Developer (Super Admin)' : 'Ibu Yeni (Mitra Pemilik)';
        $stmtUp = $pdo->prepare("
            UPDATE pembayaran 
            SET status_pembayaran = 'lunas',
                catatan_pemilik = ?,
                diverifikasi_oleh = ?,
                tanggal_verifikasi = CURRENT_TIMESTAMP
            WHERE id_pembayaran = ?
        ");
        $stmtUp->execute([$catatanPemilik, $verifier, $idTrx]);

        // Jika ini pembayaran booking calon penghuni, update status pemesanan & kamar
        if (!empty($trx['id_pemesanan'])) {
            if ($trx['kategori_pembayaran'] === 'dp_booking') {
                $pdo->prepare("UPDATE pemesanan SET status_pemesanan = 'disetujui' WHERE id_pemesanan = ?")->execute([$trx['id_pemesanan']]);
                $pdo->prepare("UPDATE kamar SET status = 'dipesan' WHERE id_kamar = ?")->execute([$trx['id_kamar']]);
            } elseif ($trx['kategori_pembayaran'] === 'sewa_pertama') {
                $pdo->prepare("UPDATE pemesanan SET status_pemesanan = 'selesai' WHERE id_pemesanan = ?")->execute([$trx['id_pemesanan']]);
                $pdo->prepare("UPDATE kamar SET status = 'terisi' WHERE id_kamar = ?")->execute([$trx['id_kamar']]);
            }
        }

        $notifSukses = "Transaksi <strong>{$trx['kode_transaksi']}</strong> berhasil diverifikasi LUNAS dan dicatat ke buku kas!";
    }
}

// 2. Aksi Tolak Pembayaran
if (isset($_POST['action']) && $_POST['action'] === 'tolak_pembayaran') {
    $idTrx = (int)($_POST['id_pembayaran'] ?? 0);
    $alasanTolak = trim($_POST['alasan_tolak'] ?? 'Bukti transfer tidak sesuai atau dana belum masuk ke mutasi rekening.');

    $stmtFind = $pdo->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ?");
    $stmtFind->execute([$idTrx]);
    $trx = $stmtFind->fetch();

    if ($trx) {
        $verifier = isDeveloper() ? 'Tim Developer (Super Admin)' : 'Ibu Yeni (Mitra Pemilik)';
        $stmtUp = $pdo->prepare("
            UPDATE pembayaran 
            SET status_pembayaran = 'ditolak',
                catatan_pemilik = ?,
                diverifikasi_oleh = ?,
                tanggal_verifikasi = CURRENT_TIMESTAMP
            WHERE id_pembayaran = ?
        ");
        $stmtUp->execute([$alasanTolak, $verifier, $idTrx]);
        $notifSukses = "Transaksi <strong>{$trx['kode_transaksi']}</strong> telah ditolak dengan catatan: " . htmlspecialchars($alasanTolak);
    }
}

// 3. Aksi Catat Pembayaran Manual Langsung oleh Ibu Yeni (Uang Cash / Transfer Langsung)
if (isset($_POST['action']) && $_POST['action'] === 'catat_manual') {
    $idKamar = (int)($_POST['id_kamar'] ?? 0);
    $namaPembayar = trim($_POST['nama_pengirim'] ?? '');
    $kategori = trim($_POST['kategori_pembayaran'] ?? 'sewa_bulanan');
    $periodeBulan = trim($_POST['periode_bulan'] ?? '');
    $nominal = (float)($_POST['nominal'] ?? 0);
    $metode = trim($_POST['metode_pembayaran'] ?? 'tunai_cash');
    $tanggalBayar = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
    $catatanManual = trim($_POST['catatan_pemilik'] ?? 'Pencatatan manual langsung oleh Ibu Yeni.');

    if ($idKamar <= 0 || empty($namaPembayar) || $nominal <= 0) {
        $notifError = 'Kamar, Nama Pembayar, dan Nominal wajib diisi dengan benar!';
    } else {
        $kodeTrx = generateKodeTransaksi('TRX');
        $verifier = isDeveloper() ? 'Tim Developer (Super Admin)' : 'Ibu Yeni (Mitra Pemilik)';

        // Ambil ID pengguna jika ada penghuni di kamar tersebut
        $stmtUserKamar = $pdo->prepare("SELECT id_pengguna FROM pengguna WHERE id_kamar_huni = ? LIMIT 1");
        $stmtUserKamar->execute([$idKamar]);
        $idPenggunaTarget = $stmtUserKamar->fetchColumn() ?: null;

        $stmtIns = $pdo->prepare("
            INSERT INTO pembayaran (
                kode_transaksi, id_pemesanan, id_pengguna, id_kamar, kategori_pembayaran,
                periode_bulan, nominal, metode_pembayaran, nama_pengirim, tanggal_bayar,
                status_pembayaran, catatan_pemilik, diverifikasi_oleh, tanggal_verifikasi
            ) VALUES (?, null, ?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmtIns->execute([
            $kodeTrx, $idPenggunaTarget, $idKamar, $kategori,
            $periodeBulan, $nominal, $metode, $namaPembayar, $tanggalBayar,
            $catatanManual, $verifier
        ]);

        $notifSukses = "Pencatatan manual berhasil! Transaksi <strong>$kodeTrx</strong> sebesar " . formatRupiah($nominal) . " langsung tercatat LUNAS di buku kas bulanan.";
    }
}

// 4. Aksi Hapus Pembayaran (bila keliru input)
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    $idHapus = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM pembayaran WHERE id_pembayaran = ?")->execute([$idHapus]);
    header("Location: admin_keuangan.php?sukses=hapus");
    exit;
}

if (isset($_GET['sukses']) && $_GET['sukses'] === 'hapus') {
    $notifSukses = 'Data transaksi berhasil dihapus dari buku pembukuan kas.';
}

// =====================================================================
// FILTER BULAN & TAHUN REKAPITULASI
// =====================================================================
$bulanPilih = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahunPilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$kategoriFilter = trim($_GET['kategori'] ?? '');
$metodeFilter = trim($_GET['metode'] ?? '');

$namaBulanPilih = namaBulanIndo($bulanPilih);
$labelPeriode = "$namaBulanPilih $tahunPilih";

// Format pencarian tanggal (misal '2026-11-%')
$patternBulan = sprintf('%04d-%02d', $tahunPilih, $bulanPilih);

// 1. Ambil Semua Pembayaran yang MENUNGGU Verifikasi (Semua Periode, agar tidak terlewat oleh Ibu Yeni)
$sqlPending = "
    SELECT p.*, k.nomor_kamar, k.tipe, k.harga as harga_kamar, pm.kode_booking, pm.no_wa_penyewa
    FROM pembayaran p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    LEFT JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
    WHERE p.status_pembayaran = 'menunggu'
    ORDER BY p.id_pembayaran ASC
";
$listPending = $pdo->query($sqlPending)->fetchAll();

// 2. Ambil Transaksi Sesuai Filter Bulan & Tahun untuk Rekapitulasi Kas
$sqlRekap = "
    SELECT p.*, k.nomor_kamar, k.tipe, k.harga as harga_kamar, pm.kode_booking
    FROM pembayaran p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    LEFT JOIN pemesanan pm ON p.id_pemesanan = pm.id_pemesanan
    WHERE (p.tanggal_bayar LIKE ? OR p.periode_bulan LIKE ?)
";
$paramsRekap = ["$patternBulan%", "%$labelPeriode%"];

if (!empty($kategoriFilter)) {
    $sqlRekap .= " AND p.kategori_pembayaran = ?";
    $paramsRekap[] = $kategoriFilter;
}
if (!empty($metodeFilter)) {
    $sqlRekap .= " AND p.metode_pembayaran = ?";
    $paramsRekap[] = $metodeFilter;
}
$sqlRekap .= " ORDER BY p.tanggal_bayar DESC, p.id_pembayaran DESC";

$stmtRekap = $pdo->prepare($sqlRekap);
$stmtRekap->execute($paramsRekap);
$listRekap = $stmtRekap->fetchAll();

// 3. Statistik Finansial Bulan Ini
$totalKasLunas = 0;
$totalTunaiCash = 0;
$totalTransferBank = 0;
$countLunas = 0;
$countMenunggu = count($listPending);

foreach ($listRekap as $r) {
    if ($r['status_pembayaran'] === 'lunas') {
        $totalKasLunas += $r['nominal'];
        $countLunas++;
        if ($r['metode_pembayaran'] === 'tunai_cash') {
            $totalTunaiCash += $r['nominal'];
        } else {
            $totalTransferBank += $r['nominal'];
        }
    }
}

// 4. Monitoring Kepatuhan Bayar Penghuni Kos Bulan Berjalan (Tunggakan)
$sqlPenghuni = "
    SELECT k.id_kamar, k.nomor_kamar, k.tipe, k.harga, k.status,
           u.id_pengguna, u.nama as nama_penghuni, u.no_wa
    FROM kamar k
    LEFT JOIN pengguna u ON u.id_kamar_huni = k.id_kamar
    WHERE k.status = 'terisi' OR u.id_pengguna IS NOT NULL
    ORDER BY k.nomor_kamar ASC
";
$kamarTerisi = $pdo->query($sqlPenghuni)->fetchAll();

// Cek status bayar masing-masing kamar di bulan terpilih
$monitoringPenghuni = [];
$totalTunggakan = 0;

foreach ($kamarTerisi as $kt) {
    $stmtCekBayar = $pdo->prepare("
        SELECT * FROM pembayaran 
        WHERE id_kamar = ? 
          AND kategori_pembayaran = 'sewa_bulanan'
          AND (periode_bulan LIKE ? OR tanggal_bayar LIKE ?)
        ORDER BY id_pembayaran DESC LIMIT 1
    ");
    $stmtCekBayar->execute([$kt['id_kamar'], "%$labelPeriode%", "$patternBulan%"]);
    $bayarBulan = $stmtCekBayar->fetch();

    $statusBayar = 'belum';
    $idPembayaranRef = null;
    if ($bayarBulan) {
        $statusBayar = $bayarBulan['status_pembayaran'];
        $idPembayaranRef = $bayarBulan['id_pembayaran'];
    }

    if ($statusBayar !== 'lunas') {
        $totalTunggakan += $kt['harga'];
    }

    $monitoringPenghuni[] = [
        'kamar' => $kt,
        'status_bayar' => $statusBayar,
        'transaksi' => $bayarBulan,
        'id_pembayaran' => $idPembayaranRef
    ];
}

// Ambil list semua kamar untuk modal input manual
$allKamarList = $pdo->query("SELECT id_kamar, nomor_kamar, tipe, harga, status FROM kamar ORDER BY nomor_kamar ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Keuangan &amp; Pembayaran Kos - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .stat-card { border-radius: 18px; border: 1px solid #E2E8F0; padding: 20px; background: #FFF; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .bukti-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 1px solid #CBD5E1; }
        .badge-cash { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-weight: 600; }
        .badge-transfer { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; font-weight: 600; }
    </style>
</head>
<body>

<!-- Navbar Mitra / Developer -->
<nav class="navbar navbar-expand-lg navbar-dark <?= isDeveloper() ? 'bg-dark border-bottom border-danger border-2' : 'bg-primary' ?> sticky-top shadow-sm py-3">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="<?= isDeveloper() ? 'developer_panel.php' : 'kamar.php' ?>">
            <i class="bi bi-houses-fill me-2"></i>KosinAja
            <?php if (isDeveloper()): ?>
                <span class="badge bg-danger fs-6 ms-1">Developer</span>
                <span class="badge bg-primary fs-6 ms-1">Panel Keuangan</span>
            <?php else: ?>
                <span class="badge bg-light text-primary fs-6">Panel Ibu Yeni</span>
            <?php endif; ?>
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (isDeveloper()): ?>
                <a href="developer_panel.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                    <i class="bi bi-code-slash me-1"></i>Dev Panel
                </a>
            <?php endif; ?>
            <a href="kamar.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-door-closed me-1"></i>Kelola Kamar
            </a>
            <a href="admin_pemesanan.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-inbox me-1"></i>Pengajuan Sewa
            </a>
            <a href="admin_keuangan.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-book-half me-1"></i>Rekap Keuangan Bulanan
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3" onclick="return confirm('Keluar sesi?')">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (isDeveloper()): ?>
<div class="alert alert-danger border-0 rounded-0 mb-0 py-2 text-center" style="background:rgba(220,38,38,.08);border-top:2px solid #DC2626 !important;">
    <i class="bi bi-shield-fill-check me-2"></i>
    <strong>Developer Mode</strong> — Anda memiliki hak akses penuh untuk memverifikasi pembayaran, mencatat kas manual, dan mencetak laporan bulanan.
    <a href="developer_panel.php" class="ms-3 text-danger fw-bold">← Kembali ke Dev Panel</a>
</div>
<?php endif; ?>

<div class="container-fluid px-4 py-4">

    <!-- Notifikasi Alert -->
    <?php if (!empty($notifSukses)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 p-3 mb-4 d-flex align-items-center shadow-sm">
            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
            <div><?= $notifSukses ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($notifError)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 p-3 mb-4 d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill text-danger fs-4 me-3"></i>
            <div><?= htmlspecialchars($notifError) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header & Action Buttons -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 rounded-pill mb-1">
                    <i class="bi bi-journal-bookmark-fill me-1"></i>Buku Kas Digital Pemilik Kos
                </span>
                <h3 class="fw-bold mb-1 text-dark">
                    Rekapitulasi Pembayaran Bulanan - Kos Putri Anak Lanang
                </h3>
                <p class="text-muted small mb-0">
                    Studi kasus pencatatan pembayaran manual: Solusi verifikasi transfer bank, pencatatan tunai tatap muka langsung, dan deteksi tunggakan anak kos.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Tombol Input Pembayaran Manual oleh Ibu Yeni -->
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2 shadow-sm" 
                        data-bs-toggle="modal" data-bs-target="#modalCatatManual">
                    <i class="bi bi-plus-circle-fill"></i> + Catat Pembayaran Manual
                </button>
                <!-- Tombol Cetak Rekap Laporan Bulanan -->
                <a href="rekap_cetak.php?bulan=<?= $bulanPilih ?>&tahun=<?= $tahunPilih ?>" target="_blank" 
                   class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2 fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill"></i> Cetak Rekap Bulanan (PDF)
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bulan & Tahun Form -->
    <div class="card card-custom p-3 mb-4 bg-white">
        <form method="GET" action="admin_keuangan.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="fw-bold small text-secondary"><i class="bi bi-funnel-fill me-1"></i>Pilih Periode:</label>
            </div>
            <div class="col-md-3">
                <select name="bulan" class="form-select form-select-sm rounded-3">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $bulanPilih ? 'selected' : '' ?>>
                            Bulan <?= namaBulanIndo($m) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="tahun" class="form-select form-select-sm rounded-3">
                    <?php for ($y = 2025; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $tahunPilih ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="kategori" class="form-select form-select-sm rounded-3">
                    <option value="">-- Semua Kategori --</option>
                    <option value="sewa_bulanan" <?= $kategoriFilter === 'sewa_bulanan' ? 'selected' : '' ?>>Sewa Kos Bulanan</option>
                    <option value="dp_booking" <?= $kategoriFilter === 'dp_booking' ? 'selected' : '' ?>>DP Booking Calon</option>
                    <option value="sewa_pertama" <?= $kategoriFilter === 'sewa_pertama' ? 'selected' : '' ?>>Sewa Masuk Pertama</option>
                    <option value="deposit_jaminan" <?= $kategoriFilter === 'deposit_jaminan' ? 'selected' : '' ?>>Deposit Kunci</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="metode" class="form-select form-select-sm rounded-3">
                    <option value="">-- Semua Metode --</option>
                    <option value="tunai_cash" <?= $metodeFilter === 'tunai_cash' ? 'selected' : '' ?>>💵 Tunai / Cash</option>
                    <option value="transfer_bri" <?= $metodeFilter === 'transfer_bri' ? 'selected' : '' ?>>💳 Transfer Bank</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm rounded-3 px-3">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <!-- Ringkasan Statistik Finansial Bulanan -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card border-success border-opacity-25 bg-success-subtle bg-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-success fw-bold small text-uppercase">Total Kas Masuk Lunas</span>
                    <i class="bi bi-wallet-fill text-success fs-4"></i>
                </div>
                <h3 class="fw-bold text-success mb-1"><?= formatRupiah($totalKasLunas) ?></h3>
                <div class="text-muted small">Periode: <strong><?= $labelPeriode ?></strong></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-primary fw-bold small text-uppercase">Transfer Bank vs Tunai</span>
                    <i class="bi bi-pie-chart-fill text-primary fs-4"></i>
                </div>
                <div class="small mb-1">
                    <span class="text-primary fw-bold">Transfer:</span> <?= formatRupiah($totalTransferBank) ?>
                </div>
                <div class="small">
                    <span class="text-success fw-bold">Tunai (Cash):</span> <?= formatRupiah($totalTunaiCash) ?>
                </div>
                <div class="text-muted small mt-1"><?= $countLunas ?> transaksi berhasil</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card <?= $countMenunggu > 0 ? 'border-warning bg-warning-subtle bg-opacity-10' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-warning-emphasis fw-bold small text-uppercase">Menunggu Verifikasi</span>
                    <i class="bi bi-clock-history text-warning fs-4"></i>
                </div>
                <h3 class="fw-bold text-warning-emphasis mb-1"><?= $countMenunggu ?> Transaksi</h3>
                <div class="text-muted small">Memerlukan persetujuan manual</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card <?= $totalTunggakan > 0 ? 'border-danger bg-danger-subtle bg-opacity-10' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-danger fw-bold small text-uppercase">Estimasi Belum Bayar</span>
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                </div>
                <h3 class="fw-bold text-danger mb-1"><?= formatRupiah($totalTunggakan) ?></h3>
                <div class="text-muted small">Tunggakan sewa bulan ini</div>
            </div>
        </div>
    </div>

    <!-- 1. BAGIAN VERIFIKASI PEMBAYARAN MASUK (MENUNGGU PERSETUJUAN IBU YENI) -->
    <?php if (!empty($listPending)): ?>
        <div class="card card-custom p-4 mb-4 border-warning border-opacity-75" style="background:#FFFDF7;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-bell-fill text-warning me-2"></i>Konfirmasi Pembayaran Menunggu Verifikasi Manual (<?= count($listPending) ?>)
                    </h5>
                </div>
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill">Perlu Dicek Ibu Yeni</span>
            </div>
            <p class="text-muted small mb-3">
                Pembayaran yang diajukan oleh calon penghuni (DP booking) atau penghuni kos (sewa bulanan). Periksa mutasi rekening / uang tunai fisik lalu klik <strong>Verifikasi Lunas</strong> atau <strong>Tolak</strong>.
            </p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 bg-white rounded-3 shadow-sm">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE TRX</th>
                            <th>PEMBAYAR &amp; KAMAR</th>
                            <th>KATEGORI &amp; PERIODE</th>
                            <th>NOMINAL &amp; METODE</th>
                            <th>BUKTI TRANSFER</th>
                            <th>CATATAN PEMBAYAR</th>
                            <th class="text-center" style="width: 220px;">AKSI VERIFIKASI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listPending as $lp): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($lp['kode_transaksi']) ?></span>
                                    <div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($lp['tanggal_bayar']) ?></div>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($lp['nama_pengirim']) ?></strong>
                                    <div class="text-muted small">
                                        Unit: <strong class="text-primary"><?= htmlspecialchars($lp['nomor_kamar']) ?></strong> (<?= ucfirst($lp['tipe']) ?>)
                                    </div>
                                    <?php if (!empty($lp['kode_booking'])): ?>
                                        <div class="text-muted" style="font-size:11px;">Booking: <?= htmlspecialchars($lp['kode_booking']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border small"><?= labelKategoriPembayaran($lp['kategori_pembayaran']) ?></span>
                                    <?php if (!empty($lp['periode_bulan'])): ?>
                                        <div class="text-muted small fw-semibold"><?= htmlspecialchars($lp['periode_bulan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-6"><?= formatRupiah($lp['nominal']) ?></div>
                                    <span class="badge <?= $lp['metode_pembayaran'] === 'tunai_cash' ? 'badge-cash' : 'badge-transfer' ?> small">
                                        <?= $lp['metode_pembayaran'] === 'tunai_cash' ? '💵 Tunai' : '💳 ' . strtoupper(str_replace('transfer_', '', $lp['metode_pembayaran'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($lp['bukti_bayar'])): ?>
                                        <a href="<?= htmlspecialchars($lp['bukti_bayar']) ?>" target="_blank" title="Lihat Foto Bukti Struk">
                                            <img src="<?= htmlspecialchars($lp['bukti_bayar']) ?>" alt="Bukti" class="bukti-thumb">
                                        </a>
                                        <div class="small"><a href="<?= htmlspecialchars($lp['bukti_bayar']) ?>" target="_blank" style="font-size:11px;">Perbesar Struk</a></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border small">Tanpa Bukti (Tunai)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-secondary small" style="max-width: 180px;">
                                        <?= htmlspecialchars($lp['catatan_pembayar'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <!-- Tombol Verifikasi Lunas -->
                                        <form method="POST" action="admin_keuangan.php" style="display:inline;" 
                                              onsubmit="return confirm('Verifikasi pembayaran <?= htmlspecialchars($lp['kode_transaksi']) ?> sebagai LUNAS?')">
                                            <input type="hidden" name="action" value="verifikasi_lunas">
                                            <input type="hidden" name="id_pembayaran" value="<?= $lp['id_pembayaran'] ?>">
                                            <input type="hidden" name="catatan_pemilik" value="Pencatatan manual: Mutasi/uang tunai telah dicek Ibu Yeni dan sesuai. Dinyatakan LUNAS.">
                                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                                                <i class="bi bi-check-circle-fill me-1"></i>Verifikasi Lunas
                                            </button>
                                        </form>

                                        <!-- Tombol Tolak Modal -->
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2" 
                                                data-bs-toggle="modal" data-bs-target="#modalTolak<?= $lp['id_pembayaran'] ?>" title="Tolak Pembayaran">
                                            <i class="bi bi-x-circle"></i> Tolak
                                        </button>
                                    </div>

                                    <!-- Modal Tolak Pembayaran -->
                                    <div class="modal fade" id="modalTolak<?= $lp['id_pembayaran'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0">
                                                <form method="POST" action="admin_keuangan.php">
                                                    <input type="hidden" name="action" value="tolak_pembayaran">
                                                    <input type="hidden" name="id_pembayaran" value="<?= $lp['id_pembayaran'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Tolak Pembayaran #<?= htmlspecialchars($lp['kode_transaksi']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <p class="small text-muted mb-2">Tuliskan alasan penolakan agar pembayar dapat mengunggah bukti yang benar:</p>
                                                        <textarea name="alasan_tolak" rows="3" class="form-control rounded-3" required 
                                                                  placeholder="Contoh: Bukti transfer buram/nominal tidak sesuai dengan mutasi rekening."></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Tolak Transaksi</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. BAGIAN MONITORING STATUS PEMBAYARAN PENGHUNI KOS BULAN INI (DETEKSI TUNGGAKAN) -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-people-fill text-primary me-2"></i>Status Pembayaran Sewa Penghuni Aktif - Periode <?= $labelPeriode ?>
                </h5>
                <p class="text-muted small mb-0">
                    Memantau kepatuhan sewa bulanan setiap kamar. Klik <strong>Ingatkan WA</strong> untuk mengirim pesan jatuh tempo otomatis ke penghuni yang belum bayar.
                </p>
            </div>
            <span class="badge bg-secondary rounded-pill px-3 py-1"><?= count($monitoringPenghuni) ?> Kamar Dihuni</span>
        </div>

        <?php if (empty($monitoringPenghuni)): ?>
            <div class="p-3 bg-light rounded-3 text-muted text-center small">
                Belum ada data kamar berstatus terisi atau terhubung ke akun penghuni.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KAMAR</th>
                            <th>NAMA PENGHUNI</th>
                            <th>TARIF SEWA</th>
                            <th>JATUH TEMPO</th>
                            <th>STATUS PEMBAYARAN</th>
                            <th class="text-center">TINDAKAN IBU YENI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monitoringPenghuni as $mp): ?>
                            <?php 
                            $k = $mp['kamar'];
                            $namaPenghuni = $k['nama_penghuni'] ?: 'Penghuni ' . $k['nomor_kamar'];
                            $noWa = $k['no_wa'] ?: '';
                            $waReminderText = urlencode("Halo Kak $namaPenghuni, salam dari Ibu Yeni (Kos Putri Anak Lanang). Mengingatkan pembayaran sewa {$k['nomor_kamar']} untuk periode $labelPeriode sebesar " . formatRupiah($k['harga']) . " dengan jatuh tempo tanggal 10. Jika sudah bayar tunai/transfer, mohon konfirmasi ya. Terima kasih!");
                            $waLinkReminder = "https://wa.me/" . preg_replace('/[^0-9]/', '', $noWa) . "?text=" . $waReminderText;
                            ?>
                            <tr>
                                <td>
                                    <strong class="text-dark fs-6"><?= htmlspecialchars($k['nomor_kamar']) ?></strong>
                                    <div class="text-muted small"><?= ucfirst(htmlspecialchars($k['tipe'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($namaPenghuni) ?></div>
                                    <?php if (!empty($noWa)): ?>
                                        <div class="text-muted small"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($noWa) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= formatRupiah($k['harga']) ?></strong>
                                    <span class="text-muted small">/ bln</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">Tanggal 10 <?= $namaBulanPilih ?></span>
                                </td>
                                <td>
                                    <?php if ($mp['status_bayar'] === 'lunas'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i>LUNAS (Sudah Bayar)
                                        </span>
                                    <?php elseif ($mp['status_bayar'] === 'menunggu'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-1 rounded-pill">
                                            <i class="bi bi-hourglass-split me-1"></i>Menunggu Verifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1 rounded-pill">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i>BELUM BAYAR (Tunggakan)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($mp['status_bayar'] === 'lunas' && !empty($mp['id_pembayaran'])): ?>
                                            <a href="kuitansi.php?id=<?= $mp['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="bi bi-receipt me-1"></i>Kuitansi
                                            </a>
                                        <?php else: ?>
                                            <!-- Catat Manual Tunai Khusus Kamar Ini -->
                                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2" 
                                                    onclick="isiModalManual(<?= $k['id_kamar'] ?>, '<?= addslashes($namaPenghuni) ?>', <?= (int)$k['harga'] ?>, '<?= $labelPeriode ?>')">
                                                <i class="bi bi-cash me-1"></i>Catat Bayar
                                            </button>
                                            <!-- Tombol Tagih via WA -->
                                            <?php if (!empty($noWa)): ?>
                                                <a href="<?= $waLinkReminder ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Kirim Pengingat WhatsApp">
                                                    <i class="bi bi-whatsapp text-success"></i> Tagih WA
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. BAGIAN TABEL BUKU KAS REKAPITULASI PEMBAYARAN MASUK (SEMUA METODE & KATEGORI) -->
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-table text-success me-2"></i>Buku Kas Pembayaran Masuk - <?= $labelPeriode ?>
                </h5>
                <p class="text-muted small mb-0">Daftar seluruh mutasi pembayaran (tunai &amp; transfer) yang tercatat di sistem KosinAja.</p>
            </div>
            <span class="badge bg-light text-dark border px-3 py-2">Total Transaksi: <?= count($listRekap) ?> Data</span>
        </div>

        <?php if (empty($listRekap)): ?>
            <div class="p-5 text-center text-muted bg-light rounded-4">
                <i class="bi bi-inbox display-5 text-secondary mb-2"></i>
                <p class="mb-2">Tidak ada catatan pembayaran untuk periode <strong><?= $labelPeriode ?></strong> dengan filter yang dipilih.</p>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCatatManual">
                    <i class="bi bi-plus-circle me-1"></i>+ Catat Pembayaran Manual Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE TRX</th>
                            <th>TANGGAL</th>
                            <th>NAMA PEMBAYAR</th>
                            <th>UNIT KAMAR</th>
                            <th>KATEGORI &amp; PERIODE</th>
                            <th>METODE BAYAR</th>
                            <th>NOMINAL (RP)</th>
                            <th>STATUS</th>
                            <th class="text-center">KUITANSI / AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listRekap as $r): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fw-bold"><?= htmlspecialchars($r['kode_transaksi']) ?></span>
                                </td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($r['tanggal_bayar'])) ?></strong>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($r['nama_pengirim']) ?></strong>
                                    <?php if (!empty($r['catatan_pemilik'])): ?>
                                        <div class="text-muted" style="font-size:11px;"><i class="bi bi-chat-left-dots me-1"></i><?= htmlspecialchars($r['catatan_pemilik']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($r['nomor_kamar']) ?></span>
                                    <div class="text-muted small"><?= ucfirst(htmlspecialchars($r['tipe'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= labelKategoriPembayaran($r['kategori_pembayaran']) ?></span>
                                    <?php if (!empty($r['periode_bulan'])): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($r['periode_bulan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['metode_pembayaran'] === 'tunai_cash'): ?>
                                        <span class="badge badge-cash"><i class="bi bi-cash-stack me-1"></i>Tunai (Cash)</span>
                                    <?php else: ?>
                                        <span class="badge badge-transfer"><i class="bi bi-credit-card me-1"></i><?= strtoupper(str_replace('transfer_', '', $r['metode_pembayaran'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-success fs-6"><?= formatRupiah($r['nominal']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($r['status_pembayaran'] === 'lunas'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">Lunas</span>
                                    <?php elseif ($r['status_pembayaran'] === 'menunggu'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">Menunggu</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">Ditolak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($r['status_pembayaran'] === 'lunas'): ?>
                                            <a href="kuitansi.php?id=<?= $r['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3" title="Cetak Kuitansi">
                                                <i class="bi bi-printer me-1"></i>Kuitansi
                                            </a>
                                        <?php endif; ?>
                                        <a href="admin_keuangan.php?aksi=hapus&id=<?= $r['id_pembayaran'] ?>" 
                                           class="btn btn-outline-danger btn-sm rounded-pill px-2" 
                                           onclick="return confirm('Hapus catatan transaksi ini dari pembukuan?')" title="Hapus Data">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ===================================================================== -->
<!-- MODAL: CATAT PEMBAYARAN MANUAL LANGSUNG OLEH IBU YENI -->
<!-- ===================================================================== -->
<div class="modal fade" id="modalCatatManual" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <form method="POST" action="admin_keuangan.php">
                <input type="hidden" name="action" value="catat_manual">
                <div class="modal-header bg-success text-white rounded-top-4">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Catat Pembayaran Manual Baru (Ibu Yeni)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Gunakan formulir ini jika anak kos membayar sewa secara <strong>tunai langsung (cash)</strong> atau transfer tanpa lewat web, agar otomatis masuk ke buku kas pembukuan kos.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Pilih Unit Kamar <span class="text-danger">*</span></label>
                            <select name="id_kamar" id="manualKamarSelect" class="form-select rounded-3" required onchange="autoFillKamarHarga()">
                                <option value="">-- Pilih Kamar --</option>
                                <?php foreach ($allKamarList as $ak): ?>
                                    <option value="<?= $ak['id_kamar'] ?>" data-harga="<?= (int)$ak['harga'] ?>" data-nomor="<?= htmlspecialchars($ak['nomor_kamar']) ?>">
                                        <?= htmlspecialchars($ak['nomor_kamar']) ?> (<?= ucfirst($ak['tipe']) ?> - <?= formatRupiah($ak['harga']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nama Pembayar / Penghuni <span class="text-danger">*</span></label>
                            <input type="text" name="nama_pengirim" id="manualNamaPembayar" class="form-control rounded-3" placeholder="Contoh: Nadia Safitri" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Kategori Pembayaran <span class="text-danger">*</span></label>
                            <select name="kategori_pembayaran" id="manualKategori" class="form-select rounded-3" required>
                                <option value="sewa_bulanan" selected>Sewa Kos Bulanan</option>
                                <option value="dp_booking">Uang Muka (DP Booking Calon)</option>
                                <option value="sewa_pertama">Sewa Bulan Pertama Masuk</option>
                                <option value="deposit_jaminan">Deposit Jaminan Kunci (Rp 200.000)</option>
                                <option value="denda_lainnya">Biaya Tambahan / Denda</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Periode Tagihan Bulan</label>
                            <input type="text" name="periode_bulan" id="manualPeriode" class="form-control rounded-3" 
                                   value="<?= $labelPeriode ?>" placeholder="Contoh: <?= $labelPeriode ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nominal Diterima (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">Rp</span>
                                <input type="number" name="nominal" id="manualNominal" class="form-control rounded-end-3 fw-bold text-success" 
                                       value="850000" min="10000" step="10000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Tanggal Terima Uang <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_bayar" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Metode Pembayaran <span class="text-danger">*</span></label>
                        <select name="metode_pembayaran" class="form-select rounded-3" required>
                            <option value="tunai_cash" selected>💵 Tunai / Cash (Diterima Langsung oleh Ibu Yeni)</option>
                            <option value="transfer_bri">💳 Transfer Bank BRI (Ibu Yeni)</option>
                            <option value="transfer_bca">💳 Transfer Bank BCA (Ibu Yeni)</option>
                            <option value="transfer_mandiri">💳 Transfer Bank Mandiri (Ibu Yeni)</option>
                            <option value="transfer_bsi">💳 Transfer Bank BSI (Ibu Yeni)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Catatan Pembukuan Manual Ibu Yeni</label>
                        <textarea name="catatan_pemilik" rows="2" class="form-control rounded-3" 
                                  placeholder="Contoh: Uang tunai cash Rp 850.000 diserahkan di rumah, nota kuitansi fisik sudah diberikan."></textarea>
                    </div>

                    <div class="alert alert-success py-2 px-3 small rounded-3 mb-0">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Data yang disimpan akan langsung berstatus <strong>LUNAS</strong> dan dapat langsung dicetak kuitansi resminya.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                        <i class="bi bi-save-fill me-1"></i>Simpan ke Buku Kas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function autoFillKamarHarga() {
    const sel = document.getElementById('manualKamarSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.harga) {
        document.getElementById('manualNominal').value = opt.dataset.harga;
    }
}

function isiModalManual(idKamar, namaPenghuni, nominal, periode) {
    const sel = document.getElementById('manualKamarSelect');
    sel.value = idKamar;
    document.getElementById('manualNamaPembayar').value = namaPenghuni;
    document.getElementById('manualNominal').value = nominal;
    document.getElementById('manualPeriode').value = periode;
    
    const myModal = new bootstrap.Modal(document.getElementById('modalCatatManual'));
    myModal.show();
}
</script>
</body>
</html>
