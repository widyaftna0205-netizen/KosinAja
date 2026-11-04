<?php
// pembayaran.php - Alur Pembayaran Calon Penghuni Kos & Penghuni Kos (E-Commerce KosinAja)
// Mitra: Kos Putri Anak Lanang (Ibu Yeni, Pasir Pengaraian, Riau)
require_once __DIR__ . '/config.php';

$kodeBooking = trim($_GET['booking'] ?? '');
$tipeAkses = trim($_GET['tipe'] ?? '');
$pesanan = null;
$kamar = null;
$penyewaUser = $_SESSION['user'] ?? null;

$suksesMsg = '';
$errorMsg = '';

// 1. Jika ada kode booking (Alur Calon Penghuni)
if (!empty($kodeBooking)) {
    $stmtP = $pdo->prepare("
        SELECT p.*, k.nomor_kamar, k.tipe as tipe_kamar, k.harga, k.ukuran, k.status as status_kamar,
               ks.nama_kos, ks.no_wa_kontak, ks.alamat
        FROM pemesanan p
        JOIN kamar k ON p.id_kamar = k.id_kamar
        JOIN kos ks ON k.id_kos = ks.id_kos
        WHERE p.kode_booking = ?
        LIMIT 1
    ");
    $stmtP->execute([$kodeBooking]);
    $pesanan = $stmtP->fetch();

    if ($pesanan) {
        $kamar = [
            'id_kamar' => $pesanan['id_kamar'],
            'nomor_kamar' => $pesanan['nomor_kamar'],
            'tipe' => $pesanan['tipe_kamar'],
            'harga' => $pesanan['harga'],
            'ukuran' => $pesanan['ukuran']
        ];
    } else {
        $errorMsg = "Kode booking '$kodeBooking' tidak ditemukan. Silakan periksa kembali kode Anda.";
    }
}
// 2. Jika penghuni aktif yang login ingin bayar sewa bulanan (Alur Penghuni Kos)
elseif ($penyewaUser && (!empty($penyewaUser['id_kamar_huni']) || $tipeAkses === 'bulanan')) {
    $idKamarHuni = $penyewaUser['id_kamar_huni'];
    if ($idKamarHuni) {
        $stmtK = $pdo->prepare("
            SELECT k.*, ks.nama_kos, ks.no_wa_kontak, ks.alamat
            FROM kamar k
            JOIN kos ks ON k.id_kos = ks.id_kos
            WHERE k.id_kamar = ?
        ");
        $stmtK->execute([$idKamarHuni]);
        $kamar = $stmtK->fetch();
    }
}

// Handle Form Submission Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi_bayar') {
    $idKamarTarget = (int)($_POST['id_kamar'] ?? ($kamar['id_kamar'] ?? 0));
    $idPemesananTarget = !empty($_POST['id_pemesanan']) ? (int)$_POST['id_pemesanan'] : ($pesanan['id_pemesanan'] ?? null);
    $idPenggunaTarget = $penyewaUser['id_pengguna'] ?? (!empty($pesanan['id_pengguna']) ? $pesanan['id_pengguna'] : null);
    
    $kategori = trim($_POST['kategori_pembayaran'] ?? 'dp_booking');
    $periodeBulan = trim($_POST['periode_bulan'] ?? '');
    $nominal = (float)($_POST['nominal'] ?? 0);
    $metode = trim($_POST['metode_pembayaran'] ?? 'transfer_bri');
    $namaPengirim = trim($_POST['nama_pengirim'] ?? '');
    $bankAsal = trim($_POST['bank_asal'] ?? '');
    $noRekPengirim = trim($_POST['nomor_rekening_pengirim'] ?? '');
    $tanggalBayar = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
    $catatan = trim($_POST['catatan_pembayar'] ?? '');

    // Validasi
    if ($idKamarTarget <= 0) {
        $errorMsg = 'Kamar tidak valid. Silakan pilih kamar atau booking Anda.';
    } elseif ($nominal <= 0) {
        $errorMsg = 'Nominal pembayaran harus lebih dari Rp 0!';
    } elseif (empty($namaPengirim)) {
        $errorMsg = 'Nama penyetor / pengirim pembayaran wajib diisi!';
    } else {
        // Upload Bukti Bayar jika metode transfer
        $urlBukti = null;
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['bukti_transfer']['tmp_name'];
            $fileName = $_FILES['bukti_transfer']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'bukti_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $fileExt;
                $targetPath = __DIR__ . '/uploads/bukti/' . $newFileName;
                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $urlBukti = 'uploads/bukti/' . $newFileName;
                }
            } else {
                $errorMsg = 'Format file bukti harus berupa gambar (JPG, PNG, WEBP) atau PDF!';
            }
        }

        if (empty($errorMsg)) {
            // Generate Kode Transaksi
            $kodeTrx = generateKodeTransaksi('TRX');

            // Status default: 'menunggu' (karena studi kasus pencatatan manual perlu diverifikasi pemilik kos)
            $stmtInsert = $pdo->prepare("
                INSERT INTO pembayaran (
                    kode_transaksi, id_pemesanan, id_pengguna, id_kamar, kategori_pembayaran,
                    periode_bulan, nominal, metode_pembayaran, nama_pengirim, bank_asal,
                    nomor_rekening_pengirim, tanggal_bayar, bukti_bayar, status_pembayaran,
                    catatan_pembayar
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu', ?)
            ");
            $stmtInsert->execute([
                $kodeTrx, $idPemesananTarget, $idPenggunaTarget, $idKamarTarget, $kategori,
                $periodeBulan, $nominal, $metode, $namaPengirim, $bankAsal,
                $noRekPengirim, $tanggalBayar, $urlBukti, $catatan
            ]);

            $suksesMsg = "Konfirmasi pembayaran berhasil dikirim dengan Kode Transaksi <strong>$kodeTrx</strong>! Ibu Yeni akan memverifikasi pembayaran Anda.";
            // Refresh URL bila ada booking
            if (!empty($kodeBooking)) {
                header("Location: pembayaran.php?booking=" . urlencode($kodeBooking) . "&sukses=1&trx=" . urlencode($kodeTrx));
                exit;
            } else {
                header("Location: pembayaran.php?tipe=bulanan&sukses=1&trx=" . urlencode($kodeTrx));
                exit;
            }
        }
    }
}

if (isset($_GET['sukses']) && isset($_GET['trx'])) {
    $suksesMsg = "Konfirmasi pembayaran berhasil dikirim dengan Kode Transaksi <strong>" . htmlspecialchars($_GET['trx']) . "</strong>! Ibu Yeni akan memverifikasi mutasi / uang tunai Anda.";
}

// Ambil Riwayat Pembayaran Terkait
$riwayatBayar = [];
if ($pesanan) {
    $stmtR = $pdo->prepare("
        SELECT p.*, k.nomor_kamar
        FROM pembayaran p
        JOIN kamar k ON p.id_kamar = k.id_kamar
        WHERE p.id_pemesanan = ? OR (p.id_kamar = ? AND p.nama_pengirim LIKE ?)
        ORDER BY p.id_pembayaran DESC
    ");
    $stmtR->execute([$pesanan['id_pemesanan'], $pesanan['id_kamar'], '%' . $pesanan['nama_penyewa'] . '%']);
    $riwayatBayar = $stmtR->fetchAll();
} elseif ($kamar && $penyewaUser) {
    $stmtR = $pdo->prepare("
        SELECT p.*, k.nomor_kamar
        FROM pembayaran p
        JOIN kamar k ON p.id_kamar = k.id_kamar
        WHERE p.id_pengguna = ? OR p.id_kamar = ?
        ORDER BY p.id_pembayaran DESC
    ");
    $stmtR->execute([$penyewaUser['id_pengguna'], $kamar['id_kamar']]);
    $riwayatBayar = $stmtR->fetchAll();
}

// Ambil data kontak kos
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kosInfo = $stmtKos->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran Sewa Kos - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .bank-card { border-radius: 16px; border: 1px dashed #CBD5E1; background: #F1F5F9; padding: 14px; transition: all 0.2s; }
        .bank-card:hover { border-color: #0284C7; background: #E0F2FE; }
        .btn-primary-custom { background: linear-gradient(135deg, #059669 0%, #10B981 100%); border: none; border-radius: 12px; color: #FFF; font-weight: 700; padding: 12px 24px; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }
        .btn-primary-custom:hover { background: linear-gradient(135deg, #047857 0%, #059669 100%); color: #FFF; }
        .nav-back-btn { background: rgba(255,255,255,0.15); border-radius: 10px; padding: 6px 12px; color: #FFF; text-decoration: none; }
        .nav-back-btn:hover { background: rgba(255,255,255,0.25); color: #FFF; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top py-3 shadow-sm">
    <div class="container px-4">
        <div class="d-flex align-items-center gap-2">
            <?php if ($penyewaUser): ?>
                <a href="penyewa_dashboard.php" class="nav-back-btn me-2"><i class="bi bi-arrow-left me-1"></i>Dasbor Penghuni</a>
            <?php else: ?>
                <a href="cek_pesanan.php" class="nav-back-btn me-2"><i class="bi bi-arrow-left me-1"></i>Cek Booking</a>
            <?php endif; ?>
            <span class="navbar-brand fw-bold mb-0">
                <i class="bi bi-wallet2 me-2"></i>KosinAja Pay
            </span>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-houses me-1"></i>Katalog
            </a>
            <?php if ($penyewaUser): ?>
                <a href="penyewa_dashboard.php" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-success">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($penyewaUser['nama']) ?>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container px-4 py-4" style="max-width: 1000px;">

    <!-- Heading Info -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill mb-2">
                    <i class="bi bi-shield-check me-1"></i>Alur Pembayaran Resmi Kos Putri Anak Lanang
                </span>
                <h3 class="fw-bold text-dark mb-1">
                    <?= $pesanan ? 'Pembayaran DP / Sewa Pertama Calon Penghuni' : 'Pembayaran Sewa Kos Bulanan' ?>
                </h3>
                <p class="text-muted small mb-0">
                    Sistem pencatatan manual digital terpadu: Bayar via <strong>Transfer Bank</strong> (unggah bukti) atau <strong>Tunai / Cash</strong> langsung kepada Ibu Yeni.
                </p>
            </div>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $kosInfo['no_wa_kontak'] ?? '081275998811') ?>?text=Halo%20Ibu%20Yeni,%20saya%20ingin%20konfirmasi%20pembayaran%20kos" 
               target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3">
                <i class="bi bi-whatsapp me-1"></i>Bantuan WhatsApp Ibu Yeni
            </a>
        </div>
    </div>

    <!-- Alert Sukses / Error -->
    <?php if (!empty($suksesMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 p-4 mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-start">
                <i class="bi bi-check-circle-fill text-success fs-3 me-3"></i>
                <div>
                    <h5 class="fw-bold mb-1">Konfirmasi Pembayaran Diterima!</h5>
                    <p class="mb-2"><?= $suksesMsg ?></p>
                    <div class="small text-muted">
                        Ibu Yeni akan memeriksa mutasi bank atau uang tunai yang diterima, lalu memvalidasi status pembayaran menjadi <strong>Lunas</strong>. Kuitansi resmi dapat dilihat di bagian riwayat bawah.
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger rounded-4 p-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-4 me-2"></i>
            <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
    <?php endif; ?>

    <!-- Jika belum memilih booking atau kamar -->
    <?php if (!$pesanan && !$kamar): ?>
        <div class="card card-custom p-5 text-center mb-4">
            <i class="bi bi-receipt display-4 text-secondary mb-3"></i>
            <h4 class="fw-bold text-dark">Pilih Pengajuan Booking Anda</h4>
            <p class="text-muted small mb-4">Masukkan Kode Booking yang Anda peroleh saat mengajukan kamar untuk melanjutkan pembayaran.</p>
            <form method="GET" action="pembayaran.php" class="mx-auto" style="max-width: 450px;">
                <div class="input-group">
                    <input type="text" name="booking" class="form-control rounded-start-pill px-3" placeholder="Contoh: BKG-20261104-556" required>
                    <button class="btn btn-success rounded-end-pill px-4 fw-bold" type="submit">Cari Tagihan</button>
                </div>
            </form>
            <div class="mt-4">
                <span class="text-muted small">Atau penghuni aktif yang sudah punya kamar? </span>
                <a href="login.php" class="fw-bold text-success">Login ke Dasbor Penghuni</a>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-4 mb-4">
            <!-- Kolom Kiri: Rincian Tagihan & Rekening Pembayaran Manual -->
            <div class="col-lg-5">
                <!-- Rincian Unit Kamar -->
                <div class="card card-custom p-4 mb-3">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-door-closed text-success me-2"></i>Rincian Unit Kamar</h5>
                    <div class="p-3 bg-light rounded-4 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($kamar['nomor_kamar']) ?></span>
                            <span class="badge bg-primary rounded-pill px-3 py-1"><?= ucfirst(htmlspecialchars($kamar['tipe'])) ?></span>
                        </div>
                        <div class="text-muted small mb-2"><i class="bi bi-geo-alt text-danger me-1"></i>Kos Putri Anak Lanang, Pasir Pengaraian</div>
                        <div class="d-flex justify-content-between border-top pt-2 small">
                            <span>Tarif Sewa Kamar:</span>
                            <strong class="text-success"><?= formatRupiah($kamar['harga']) ?> / bulan</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-1 small">
                            <span>Deposit Jaminan Kunci:</span>
                            <strong class="text-secondary">Rp 200.000 (sekali di awal)</strong>
                        </div>
                    </div>

                    <?php if ($pesanan): ?>
                        <div class="small border-start border-3 border-success ps-3 mb-2">
                            <div><strong>Kode Booking:</strong> <span class="badge bg-light text-dark border"><?= htmlspecialchars($pesanan['kode_booking']) ?></span></div>
                            <div><strong>Nama Pemesan:</strong> <?= htmlspecialchars($pesanan['nama_penyewa']) ?></div>
                            <div><strong>Rencana Masuk:</strong> <?= htmlspecialchars($pesanan['tanggal_masuk']) ?> (<?= htmlspecialchars($pesanan['durasi_sewa']) ?>)</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informasi Rekening Bank & Pembayaran Manual Ibu Yeni -->
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-dark mb-2"><i class="bi bi-credit-card-2-front text-primary me-2"></i>Rekening Pembayaran Manual</h5>
                    <p class="text-muted small mb-3">Silakan lakukan transfer ke salah satu rekening resmi <strong>Ibu Yeni</strong> berikut, lalu unggah struk/bukti transfer:</p>

                    <div class="d-flex flex-column gap-2 mb-3">
                        <div class="bank-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary"><i class="bi bi-bank me-1"></i>BANK BRI</strong>
                                <span class="badge bg-primary-subtle text-primary small">Rekomendasi</span>
                            </div>
                            <div class="fw-bold font-monospace fs-6 text-dark">5421-01-018829-53-1</div>
                            <div class="small text-muted">Atas Nama: <strong>YENI</strong></div>
                        </div>

                        <div class="bank-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-info-emphasis"><i class="bi bi-bank me-1"></i>BANK BCA</strong>
                            </div>
                            <div class="fw-bold font-monospace fs-6 text-dark">821-0938-112</div>
                            <div class="small text-muted">Atas Nama: <strong>YENI</strong></div>
                        </div>

                        <div class="bank-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-success"><i class="bi bi-cash-stack me-1"></i>BAYAR TUNAI / CASH</strong>
                                <span class="badge bg-success-subtle text-success small">Tatap Muka</span>
                            </div>
                            <div class="small text-dark">Dapat dibayarkan langsung secara tunai kepada <strong>Ibu Yeni</strong> saat survei lokasi kos atau serah terima kunci kamar.</div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-0">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Pencatatan manual akan divalidasi Ibu Yeni sesuai mutasi rekening / uang tunai fisik yang diserahkan.
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Form Konfirmasi Pembayaran -->
            <div class="col-lg-7">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-pencil-square text-success me-2"></i>Form Konfirmasi Pembayaran
                    </h5>
                    <p class="text-muted small mb-4">Isi data pembayaran yang telah Anda lakukan agar dicatat ke buku pembukuan kas kos.</p>

                    <form method="POST" action="pembayaran.php<?= $pesanan ? '?booking=' . urlencode($kodeBooking) : '?tipe=bulanan' ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="konfirmasi_bayar">
                        <input type="hidden" name="id_kamar" value="<?= (int)$kamar['id_kamar'] ?>">
                        <input type="hidden" name="id_pemesanan" value="<?= $pesanan ? (int)$pesanan['id_pemesanan'] : '' ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Kategori Pembayaran <span class="text-danger">*</span></label>
                            <select name="kategori_pembayaran" id="kategoriPembayaran" class="form-select rounded-3 py-2" required onchange="ubahKategori()">
                                <?php if ($pesanan): ?>
                                    <option value="dp_booking" selected>Uang Muka (DP Booking Tanda Jadi) - Disarankan</option>
                                    <option value="sewa_pertama">Sewa Bulan Pertama Penuh (<?= formatRupiah($kamar['harga']) ?>)</option>
                                    <option value="deposit_jaminan">Deposit Jaminan Kunci (Rp 200.000)</option>
                                <?php else: ?>
                                    <option value="sewa_bulanan" selected>Sewa Kos Bulanan</option>
                                    <option value="deposit_jaminan">Deposit Jaminan Kunci</option>
                                    <option value="denda_lainnya">Biaya Tambahan / Denda</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Periode Bulan (Khusus Sewa Bulanan) -->
                        <div class="mb-3" id="groupPeriodeBulan">
                            <label class="form-label fw-bold small text-dark">Periode Tagihan Bulan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                <input type="text" name="periode_bulan" class="form-control" 
                                       value="<?= namaBulanIndo((int)date('m')) . ' ' . date('Y') ?>" 
                                       placeholder="Contoh: <?= namaBulanIndo((int)date('m')) . ' ' . date('Y') ?>">
                            </div>
                            <small class="text-muted">Tentukan untuk sewa bulan apa pembayaran ini ditujukan.</small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark">Nominal Pembayaran (Rp) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">Rp</span>
                                    <input type="number" name="nominal" id="inputNominal" class="form-control rounded-end-3 py-2 fw-bold text-success" 
                                           value="<?= $pesanan ? 250000 : (int)$kamar['harga'] ?>" min="50000" step="10000" required>
                                </div>
                                <small class="text-muted" id="nominalHint"><?= $pesanan ? 'Minimal DP tanda jadi: Rp 200.000' : 'Sesuai tarif bulanan kamar' ?></small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark">Tanggal Pembayaran <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_bayar" class="form-control rounded-3 py-2" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="metode_pembayaran" id="metodePembayaran" class="form-select rounded-3 py-2" required onchange="toggleMetode()">
                                <option value="transfer_bri">Transfer Bank BRI (5421-01-018829-53-1 a.n. Yeni)</option>
                                <option value="transfer_bca">Transfer Bank BCA (821-0938-112 a.n. Yeni)</option>
                                <option value="transfer_mandiri">Transfer Bank Mandiri (108-00-1928374-1 a.n. Yeni)</option>
                                <option value="transfer_bsi">Transfer Bank BSI (718-293-8472 a.n. Yeni)</option>
                                <option value="tunai_cash">Tunai / Cash (Diserahkan Langsung ke Ibu Yeni)</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-dark">Nama Pengirim / Penyetor <span class="text-danger">*</span></label>
                                <input type="text" name="nama_pengirim" class="form-control rounded-3 py-2" 
                                       value="<?= htmlspecialchars($pesanan['nama_penyewa'] ?? ($penyewaUser['nama'] ?? '')) ?>" 
                                       placeholder="Nama pemilik rekening / orang yg bayar" required>
                            </div>
                            <div class="col-md-6" id="fieldBankAsal">
                                <label class="form-label fw-bold small text-dark">Bank Asal Pengirim</label>
                                <input type="text" name="bank_asal" class="form-control rounded-3 py-2" 
                                       placeholder="Contoh: BCA, BRI, Dana, Mandiri, dll">
                            </div>
                        </div>

                        <!-- Unggah Bukti Struk Transfer -->
                        <div class="mb-3" id="fieldBukti">
                            <label class="form-label fw-bold small text-dark">
                                Unggah Bukti Transfer / Struk Bank <span class="badge bg-light text-secondary border">JPG, PNG, PDF</span>
                            </label>
                            <input type="file" name="bukti_transfer" class="form-control rounded-3 py-2" accept="image/*,.pdf">
                            <small class="text-muted">Unggah foto struk ATM / tangkapan layar m-Banking untuk mempermudah pengecekan manual oleh Ibu Yeni.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-dark">Catatan Tambahan (Opsional)</label>
                            <textarea name="catatan_pembayar" rows="2" class="form-control rounded-3" 
                                      placeholder="Contoh: Pembayaran DP via m-banking jam 14.30, serah kunci rencana tgl 10..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 py-3 fs-6">
                            <i class="bi bi-send-check-fill me-2"></i>Kirim Konfirmasi Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Riwayat Pembayaran Terkait Unit / Booking Ini -->
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-clock-history text-primary me-2"></i>Riwayat &amp; Status Pembayaran Anda
                </h5>
                <span class="badge bg-secondary rounded-pill px-3 py-1"><?= count($riwayatBayar) ?> Transaksi</span>
            </div>

            <?php if (empty($riwayatBayar)): ?>
                <div class="p-4 bg-light rounded-4 text-center text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    Belum ada riwayat pembayaran yang dikirimkan untuk unit ini. Silakan lakukan konfirmasi pembayaran di atas.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>KODE TRX</th>
                                <th>KATEGORI &amp; PERIODE</th>
                                <th>TANGGAL &amp; METODE</th>
                                <th>NOMINAL</th>
                                <th>STATUS VERIFIKASI</th>
                                <th class="text-center">KUITANSI / AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatBayar as $rb): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($rb['kode_transaksi']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= labelKategoriPembayaran($rb['kategori_pembayaran']) ?></strong>
                                        <?php if (!empty($rb['periode_bulan'])): ?>
                                            <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($rb['periode_bulan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><strong><?= htmlspecialchars($rb['tanggal_bayar']) ?></strong></div>
                                        <span class="badge bg-light text-secondary border small">
                                            <?= $rb['metode_pembayaran'] === 'tunai_cash' ? '💵 Tunai' : '💳 Transfer Bank' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success fs-6"><?= formatRupiah($rb['nominal']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($rb['status_pembayaran'] === 'menunggu'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">
                                                <i class="bi bi-hourglass-split me-1"></i>Menunggu Verifikasi Ibu Yeni
                                            </span>
                                        <?php elseif ($rb['status_pembayaran'] === 'lunas'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>Terverifikasi Lunas
                                            </span>
                                            <div class="text-muted" style="font-size:11px;">Oleh: <?= htmlspecialchars($rb['diverifikasi_oleh'] ?? 'Ibu Yeni') ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                                <i class="bi bi-x-circle-fill me-1"></i>Ditolak
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rb['status_pembayaran'] === 'lunas'): ?>
                                            <a href="kuitansi.php?id=<?= $rb['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="bi bi-receipt me-1"></i>Kuitansi Resmi
                                            </a>
                                        <?php else: ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $kosInfo['no_wa_kontak'] ?? '081275998811') ?>?text=Halo%20Ibu%20Yeni,%20saya%20sudah%20kirim%20pembayaran%20dengan%20Kode%20<?= urlencode($rb['kode_transaksi']) ?>.%20Mohon%20diverifikasi,%20terima%20kasih." 
                                               target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                                <i class="bi bi-whatsapp me-1"></i>Konfirmasi WA
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMetode() {
    const metode = document.getElementById('metodePembayaran').value;
    const fieldBukti = document.getElementById('fieldBukti');
    const fieldBankAsal = document.getElementById('fieldBankAsal');
    if (metode === 'tunai_cash') {
        fieldBukti.style.display = 'none';
        fieldBankAsal.style.display = 'none';
    } else {
        fieldBukti.style.display = 'block';
        fieldBankAsal.style.display = 'block';
    }
}

function ubahKategori() {
    const kat = document.getElementById('kategoriPembayaran').value;
    const inputNominal = document.getElementById('inputNominal');
    const groupPeriode = document.getElementById('groupPeriodeBulan');
    const hint = document.getElementById('nominalHint');

    if (kat === 'dp_booking') {
        inputNominal.value = 250000;
        hint.innerText = 'Minimal DP tanda jadi: Rp 200.000';
        groupPeriode.style.display = 'none';
    } else if (kat === 'deposit_jaminan') {
        inputNominal.value = 200000;
        hint.innerText = 'Deposit kunci standar: Rp 200.000';
        groupPeriode.style.display = 'none';
    } else if (kat === 'sewa_pertama' || kat === 'sewa_bulanan') {
        inputNominal.value = <?= (int)($kamar['harga'] ?? 850000) ?>;
        hint.innerText = 'Tarif sewa bulanan kamar penuh';
        groupPeriode.style.display = 'block';
    } else {
        groupPeriode.style.display = 'block';
    }
}
</script>
</body>
</html>
