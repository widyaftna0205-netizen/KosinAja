<?php
// penyewa_dashboard.php - Dasbor Khusus Calon Penghuni & Penghuni Aktif KosinAja
require_once __DIR__ . '/config.php';
checkAuth(['penyewa', 'developer']);

$user = $_SESSION['user'];

// Ambil info kos
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmtKos->fetch();

// Ambil data kamar yang sedang dihuni (jika sudah resmi menghuni)
$kamarHuni = null;
if (!empty($user['id_kamar_huni'])) {
    $stmtK = $pdo->prepare("SELECT k.*, (SELECT url_file FROM media_kamar mk WHERE mk.id_kamar = k.id_kamar AND mk.is_utama = 1 LIMIT 1) as foto_utama FROM kamar k WHERE k.id_kamar = ?");
    $stmtK->execute([$user['id_kamar_huni']]);
    $kamarHuni = $stmtK->fetch();
}

// Ambil riwayat pengajuan sewa calon penghuni ini (berdasarkan nomor WA atau id_pengguna)
$stmtP = $pdo->prepare("
    SELECT p.*, k.nomor_kamar, k.tipe, k.harga, k.ukuran
    FROM pemesanan p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    WHERE p.no_wa_penyewa = ? OR p.id_pengguna = ?
    ORDER BY p.id_pemesanan DESC
");
$stmtP->execute([$user['no_wa'], $user['id_pengguna']]);
$riwayatBooking = $stmtP->fetchAll();

// Ambil riwayat pembayaran sewa bulanan penghuni
$riwayatSewaBulanan = [];
$statusBulanIni = 'belum';
$trxBulanIni = null;
$periodeSekarang = namaBulanIndo((int)date('m')) . ' ' . date('Y');

if ($kamarHuni) {
    $stmtSB = $pdo->prepare("
        SELECT * FROM pembayaran 
        WHERE (id_pengguna = ? OR id_kamar = ?) AND kategori_pembayaran IN ('sewa_bulanan', 'deposit_jaminan')
        ORDER BY tanggal_bayar DESC, id_pembayaran DESC
    ");
    $stmtSB->execute([$user['id_pengguna'], $kamarHuni['id_kamar']]);
    $riwayatSewaBulanan = $stmtSB->fetchAll();

    foreach ($riwayatSewaBulanan as $sb) {
        if ($sb['kategori_pembayaran'] === 'sewa_bulanan') {
            if ((!empty($sb['periode_bulan']) && stripos($sb['periode_bulan'], $periodeSekarang) !== false) || (!empty($sb['tanggal_bayar']) && stripos($sb['tanggal_bayar'], date('Y-m')) !== false)) {
                $statusBulanIni = $sb['status_pembayaran'];
                $trxBulanIni = $sb;
                break;
            }
        }
    }
}

// Cek data pembayaran untuk setiap booking calon penghuni
$bookingBayarMap = [];
$stmtBkgBayar = $pdo->prepare("SELECT * FROM pembayaran WHERE id_pemesanan IS NOT NULL");
$stmtBkgBayar->execute();
foreach ($stmtBkgBayar->fetchAll() as $bb) {
    $bookingBayarMap[$bb['id_pemesanan']] = $bb;
}

$waIbuYeni = "https://wa.me/" . preg_replace('/[^0-9]/', '', $kos['no_wa_kontak']) . "?text=" . urlencode("Halo Ibu Yeni, saya " . $user['nama'] . " (Penghuni/Calon Penghuni Kos Putri Anak Lanang) ingin bertanya...");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Penghuni - Kos Putri Anak Lanang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .room-huni-img { height: 160px; object-fit: cover; border-radius: 14px; width: 100%; }
    </style>
</head>
<body>

<!-- Navbar Penghuni - berbeda untuk developer vs penyewa -->
<nav class="navbar navbar-expand-lg navbar-dark <?= isDeveloper() ? 'bg-dark border-bottom border-danger border-2' : 'bg-success' ?> sticky-top py-3 shadow-sm">
    <div class="container px-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="penyewa_dashboard.php">
            <span class="rounded-3 <?= isDeveloper() ? 'bg-danger' : 'bg-white text-success' ?> p-1 px-2">
                <i class="bi <?= isDeveloper() ? 'bi-code-slash text-white' : 'bi-houses-fill' ?>"></i>
            </span>
            <span>KosinAja
                <?php if (isDeveloper()): ?>
                    <span class="badge bg-danger fs-6 ms-1">Developer</span>
                    <span class="badge bg-success fs-6 ms-1">Portal Penghuni</span>
                <?php else: ?>
                    <span class="badge bg-light text-success fs-6">Portal Penghuni</span>
                <?php endif; ?>
            </span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
            <span class="text-white small d-none d-md-inline me-2">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['nama']) ?>
                <?php if (isDeveloper()): ?>
                    <span class="badge bg-danger ms-1">Developer</span>
                <?php endif; ?>
            </span>
            <?php if (isDeveloper()): ?>
                <!-- Developer: tombol kembali ke Dev Panel dan ke Panel Mitra -->
                <a href="developer_panel.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                    <i class="bi bi-code-slash me-1"></i>Dev Panel
                </a>
                <a href="kamar.php" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                    <i class="bi bi-tools me-1"></i>Panel Mitra
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-search me-1"></i>Katalog Kos
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3" onclick="return confirm('Keluar sesi akun?')">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (isDeveloper()): ?>
<div class="alert alert-danger border-0 rounded-0 mb-0 py-2 text-center" style="background:rgba(220,38,38,.08);border-top:2px solid #DC2626 !important;">
    <i class="bi bi-shield-fill-check me-2"></i>
    <strong>Developer Mode</strong> — Anda melihat Portal Penghuni sebagai Developer. Data yang tampil adalah riwayat booking akun Anda sendiri.
    <a href="developer_panel.php" class="ms-3 text-danger fw-bold">← Kembali ke Dev Panel</a>
</div>
<?php endif; ?>

<div class="container px-4 py-4" style="max-width: 1000px;">
    <!-- Welcome Header -->
    <div class="card card-custom p-4 mb-4 bg-white">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold mb-0 text-dark">Selamat Datang, <?= htmlspecialchars($user['nama']) ?>!</h4>
                    <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-1 small">
                        Akun Penghuni / Calon
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    <i class="bi bi-telephone text-success me-1"></i>WhatsApp: <?= htmlspecialchars($user['no_wa']) ?> • 
                    <i class="bi bi-envelope text-primary me-1"></i>Email: <?= htmlspecialchars($user['email']) ?>
                </p>
            </div>
            <a href="<?= $waIbuYeni ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-whatsapp"></i> Chat Ibu Yeni Langsung
            </a>
        </div>
    </div>

    <!-- Status Kamar yang Dihuni Saat Ini (Jika Ada) -->
    <?php if ($kamarHuni): ?>
        <div class="card card-custom p-4 mb-4 border-success border-opacity-50">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <h5 class="fw-bold mb-0 text-success"><i class="bi bi-door-open-fill me-2"></i>Kamar yang Sedang Anda Huni</h5>
                <span class="badge bg-success px-3 py-2 rounded-pill">Status: Aktif Menghuni</span>
            </div>
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <img src="<?= htmlspecialchars($kamarHuni['foto_utama'] ?: 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800') ?>" 
                         alt="Kamar" class="room-huni-img border">
                </div>
                <div class="col-md-5">
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($kamarHuni['nomor_kamar']) ?> (<?= ucfirst(htmlspecialchars($kamarHuni['tipe'])) ?>)</h5>
                    <div class="text-muted small mb-2"><i class="bi bi-aspect-ratio me-1"></i>Ukuran: <?= htmlspecialchars($kamarHuni['ukuran']) ?></div>
                    <div class="fw-bold text-success fs-5"><?= formatRupiah($kamarHuni['harga']) ?> <span class="fs-6 text-muted fw-normal">/ bulan</span></div>
                </div>
                <div class="col-md-4 p-3 bg-light rounded-4 border">
                    <div class="small fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Deposit Jaminan Kunci:</div>
                    <div class="small text-muted mb-2">Rp 200.000 (Tersimpan aman, dikembalikan 100% saat masa sewa selesai).</div>
                    <div class="small fw-bold text-primary"><i class="bi bi-calendar-event me-1"></i>Jatuh Tempo Sewa:</div>
                    <div class="small text-muted">Tanggal 10 setiap bulan.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODUL ALUR PEMBAYARAN PENGHUNI KOS (PEMBAYARAN SEWA BULANAN) -->
    <?php if ($kamarHuni): ?>
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-wallet2 text-success me-2"></i>Status Tagihan &amp; Pembayaran Sewa Kos Bulanan
                    </h5>
                    <p class="text-muted small mb-0">Kelola kewajiban pembayaran sewa bulanan Anda secara manual via transfer atau tunai langsung ke Ibu Yeni.</p>
                </div>
                <a href="pembayaran.php?tipe=bulanan" class="btn btn-success btn-sm rounded-pill px-3 py-2 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle me-1"></i>Bayar Sewa Kos Sekarang
                </a>
            </div>

            <!-- Kartu Status Tagihan Bulan Berjalan -->
            <div class="p-4 rounded-4 mb-4 <?= $statusBulanIni === 'lunas' ? 'bg-success-subtle border border-success' : ($statusBulanIni === 'menunggu' ? 'bg-warning-subtle border border-warning' : 'bg-light border border-danger border-opacity-50') ?>">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="small fw-bold text-uppercase text-secondary mb-1">Tagihan Periode Bulan Berjalan:</div>
                        <h4 class="fw-bold text-dark mb-1"><?= $periodeSekarang ?></h4>
                        <div class="fw-bold text-success fs-5">Nominal: <?= formatRupiah($kamarHuni['harga']) ?></div>
                    </div>
                    <div>
                        <?php if ($statusBulanIni === 'lunas'): ?>
                            <div class="text-end">
                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill mb-2 d-inline-block">
                                    <i class="bi bi-check-circle-fill me-1"></i>LUNAS (Terverifikasi)
                                </span>
                                <div>
                                    <a href="kuitansi.php?id=<?= $trxBulanIni['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                                        <i class="bi bi-receipt me-1"></i>Cetak Kuitansi Resmi
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($statusBulanIni === 'menunggu'): ?>
                            <div class="text-end">
                                <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill mb-2 d-inline-block">
                                    <i class="bi bi-hourglass-split me-1"></i>Menunggu Verifikasi Ibu Yeni
                                </span>
                                <div class="small text-muted">Konfirmasi pembayaran telah dikirimkan, menunggu Ibu Yeni memeriksa rekening / uang kas.</div>
                            </div>
                        <?php else: ?>
                            <div class="text-end">
                                <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill mb-2 d-inline-block">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Dibayar
                                </span>
                                <div class="small text-danger fw-semibold mb-2">Jatuh tempo: Tanggal 10 <?= $periodeSekarang ?></div>
                                <a href="pembayaran.php?tipe=bulanan" class="btn btn-success btn-sm rounded-pill px-4 fw-bold">
                                    <i class="bi bi-arrow-right-circle me-1"></i>Lakukan Pembayaran
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabel Riwayat Pembayaran Bulanan -->
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clock-history me-1"></i>Riwayat Pembayaran Kos Anda:</h6>
            <?php if (empty($riwayatSewaBulanan)): ?>
                <div class="p-3 bg-light rounded-3 text-center text-muted small">
                    Belum ada riwayat pembayaran sewa kos yang tercatat.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>KODE TRX</th>
                                <th>KATEGORI &amp; PERIODE</th>
                                <th>TANGGAL BAYAR</th>
                                <th>METODE</th>
                                <th>NOMINAL</th>
                                <th>STATUS</th>
                                <th class="text-center">KUITANSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatSewaBulanan as $sb): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace fw-bold"><?= htmlspecialchars($sb['kode_transaksi']) ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?= labelKategoriPembayaran($sb['kategori_pembayaran']) ?></strong>
                                        <?php if (!empty($sb['periode_bulan'])): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($sb['periode_bulan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($sb['tanggal_bayar'])) ?></td>
                                    <td>
                                        <span class="badge bg-light text-secondary border">
                                            <?= $sb['metode_pembayaran'] === 'tunai_cash' ? '💵 Tunai' : '💳 Transfer Bank' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?= formatRupiah($sb['nominal']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($sb['status_pembayaran'] === 'lunas'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">Lunas</span>
                                        <?php elseif ($sb['status_pembayaran'] === 'menunggu'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">Menunggu</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">Ditolak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sb['status_pembayaran'] === 'lunas'): ?>
                                            <a href="kuitansi.php?id=<?= $sb['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="bi bi-receipt me-1"></i>Lihat Kuitansi
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
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

    <!-- Riwayat Pengajuan Sewa / Booking Kamar (UC-06 & UC-07 & ALUR PEMBAYARAN CALON PENGHUNI) -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Riwayat Pengajuan Booking Kamar Saya</h5>
                <p class="text-muted small mb-0">Pantau pengajuan booking dan bayar DP untuk mengamankan kamar kos Anda.</p>
            </div>
            <a href="index.php" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-plus-circle me-1"></i>Pesan Kamar Lain
            </a>
        </div>

        <?php if (empty($riwayatBooking)): ?>
            <div class="p-4 text-center text-muted bg-light rounded-4">
                <i class="bi bi-inbox display-5 text-secondary mb-2"></i>
                <p class="mb-2">Anda belum memiliki pengajuan sewa kamar aktif.</p>
                <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">Lihat Katalog Kamar Kos</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE BOOKING</th>
                            <th>KAMAR</th>
                            <th>TGL MASUK &amp; DURASI</th>
                            <th>STATUS PERSETUJUAN</th>
                            <th>PEMBAYARAN DP</th>
                            <th class="text-center">TINDAKAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatBooking as $rb): ?>
                            <?php 
                            $bPayment = $bookingBayarMap[$rb['id_pemesanan']] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($rb['kode_booking']) ?></span>
                                    <div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($rb['created_at']) ?></div>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($rb['nomor_kamar']) ?></strong>
                                    <div class="text-muted small"><?= ucfirst(htmlspecialchars($rb['tipe'])) ?> • <?= formatRupiah($rb['harga']) ?></div>
                                </td>
                                <td>
                                    <div><strong><?= htmlspecialchars($rb['tanggal_masuk']) ?></strong></div>
                                    <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars($rb['durasi_sewa']) ?></span>
                                </td>
                                <td>
                                    <?php if ($rb['status_pemesanan'] === 'menunggu'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i>Menunggu Konfirmasi
                                        </span>
                                    <?php elseif ($rb['status_pemesanan'] === 'disetujui'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i>Disetujui! Siap Masuk
                                        </span>
                                    <?php elseif ($rb['status_pemesanan'] === 'ditolak'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i>Ditolak / Kamar Penuh
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Selesai Menghuni</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bPayment): ?>
                                        <?php if ($bPayment['status_pembayaran'] === 'lunas'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>DP Lunas (<?= formatRupiah($bPayment['nominal']) ?>)
                                            </span>
                                            <div><a href="kuitansi.php?id=<?= $bPayment['id_pembayaran'] ?>" target="_blank" class="small fw-bold text-success">Lihat Kuitansi</a></div>
                                        <?php elseif ($bPayment['status_pembayaran'] === 'menunggu'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">
                                                <i class="bi bi-clock-history me-1"></i>Menunggu Verifikasi DP
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                                <i class="bi bi-x-circle-fill me-1"></i>DP Ditolak
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($rb['status_pemesanan'] === 'disetujui'): ?>
                                            <a href="pembayaran.php?booking=<?= urlencode($rb['kode_booking']) ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                                                <i class="bi bi-wallet2 me-1"></i>Bayar DP / Sewa
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Menunggu persetujuan</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $kos['no_wa_kontak']) ?>?text=Halo%20Ibu%20Yeni,%20saya%20ingin%20tanya%20status%20booking%20<?= urlencode($rb['kode_booking']) ?>" 
                                       target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                        <i class="bi bi-whatsapp me-1"></i>Konfirmasi WA
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tata Tertib Kos Putri Anak Lanang -->
    <div class="card card-custom p-4 bg-white">
        <h5 class="fw-bold mb-2"><i class="bi bi-card-checklist text-success me-2"></i>Tata Tertib Kos Putri Anak Lanang</h5>
        <p class="text-muted small mb-3">Harap ditaati oleh seluruh penghuni demi kenyamanan dan keamanan bersama:</p>
        <div class="p-3 bg-light rounded-4 small text-secondary" style="white-space: pre-line;">
            <?= htmlspecialchars($kos['aturan_kos']) ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
