<?php
// admin_pemesanan.php - UC-11 Tanggapi Pengajuan Pemesanan (Roja)
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$user = $_SESSION['user'];

// Aksi Tanggapi Pemesanan
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $idPemesanan = (int)$_GET['id'];
    $aksi = $_GET['aksi'];

    $stmtP = $pdo->prepare("SELECT * FROM pemesanan WHERE id_pemesanan = ?");
    $stmtP->execute([$idPemesanan]);
    $pesanan = $stmtP->fetch();

    if ($pesanan) {
        if ($aksi === 'setujui') {
            // Setujui pemesanan & otomatis ubah status kamar jadi 'dipesan' (UC-10)
            $pdo->prepare("UPDATE pemesanan SET status_pemesanan = 'disetujui' WHERE id_pemesanan = ?")->execute([$idPemesanan]);
            $pdo->prepare("UPDATE kamar SET status = 'dipesan' WHERE id_kamar = ?")->execute([$pesanan['id_kamar']]);
            header("Location: admin_pemesanan.php?sukses=disetujui");
            exit;
        } elseif ($aksi === 'tolak') {
            $pdo->prepare("UPDATE pemesanan SET status_pemesanan = 'ditolak' WHERE id_pemesanan = ?")->execute([$idPemesanan]);
            header("Location: admin_pemesanan.php?sukses=ditolak");
            exit;
        } elseif ($aksi === 'selesai') {
            // Ditandai sudah resmi menghuni, kamar jadi 'terisi'
            $pdo->prepare("UPDATE pemesanan SET status_pemesanan = 'selesai' WHERE id_pemesanan = ?")->execute([$idPemesanan]);
            $pdo->prepare("UPDATE kamar SET status = 'terisi' WHERE id_kamar = ?")->execute([$pesanan['id_kamar']]);
            header("Location: admin_pemesanan.php?sukses=selesai");
            exit;
        }
    }
}

// Ambil semua daftar pemesanan
$sql = "
    SELECT p.*, k.nomor_kamar, k.tipe, k.harga, k.status as status_kamar
    FROM pemesanan p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    ORDER BY p.id_pemesanan DESC
";
$listPemesanan = $pdo->query($sql)->fetchAll();

// Cek data pembayaran untuk setiap pemesanan
$bayarBookingMap = [];
$stmtBMap = $pdo->query("SELECT * FROM pembayaran WHERE id_pemesanan IS NOT NULL");
foreach ($stmtBMap->fetchAll() as $bm) {
    $bayarBookingMap[$bm['id_pemesanan']] = $bm;
}
$pendingBayarCount = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_pembayaran = 'menunggu'")->fetchColumn();

$notif = '';
if (isset($_GET['sukses'])) {
    if ($_GET['sukses'] === 'disetujui') $notif = 'Pengajuan sewa telah DISETUJUI dan status kamar otomatis diubah menjadi "Dipesan"!';
    if ($_GET['sukses'] === 'ditolak') $notif = 'Pengajuan sewa telah ditolak.';
    if ($_GET['sukses'] === 'selesai') $notif = 'Penyewa telah resmi aktif menghuni dan status kamar diubah menjadi "Terisi".';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengajuan Sewa - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark <?= isDeveloper() ? 'bg-dark border-bottom border-danger border-2' : 'bg-primary' ?> sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="<?= isDeveloper() ? 'developer_panel.php' : 'kamar.php' ?>">
            <i class="bi bi-houses-fill me-2"></i>KosinAja
            <?php if (isDeveloper()): ?>
                <span class="badge bg-danger fs-6 ms-1">Developer</span>
                <span class="badge bg-primary fs-6 ms-1">Panel Mitra</span>
            <?php else: ?>
                <span class="badge bg-light text-primary fs-6">Panel Mitra</span>
            <?php endif; ?>
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (isDeveloper()): ?>
                <a href="developer_panel.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                    <i class="bi bi-code-slash me-1"></i>Dev Panel
                </a>
            <?php endif; ?>
            <a href="kamar.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-door-closed me-1"></i>Kelola Kamar (UC-09)
            </a>
            <a href="admin_pemesanan.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-inbox-fill me-1"></i>Pengajuan Sewa (UC-11)
            </a>
            <a href="admin_keuangan.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-book-half me-1"></i>Rekap Keuangan
                <?php if ($pendingBayarCount > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $pendingBayarCount ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3" onclick="return confirm('Keluar sesi?')">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (isDeveloper()): ?>
<div class="alert alert-danger border-0 rounded-0 mb-0 py-2 text-center" style="background:rgba(220,38,38,.1);border-top:2px solid #DC2626 !important;">
    <i class="bi bi-shield-fill-check me-2"></i>
    <strong>Developer Mode</strong> — Anda melihat Panel Tanggapi Pemesanan sebagai Developer. Anda dapat menyetujui/menolak/menandai selesai semua pengajuan.
    <a href="developer_panel.php" class="ms-3 text-danger fw-bold">← Kembali ke Dev Panel</a>
</div>
<?php endif; ?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-inbox-fill text-primary me-2"></i>Tanggapi Pengajuan Pemesanan Kamar (UC-11)</h4>
                <p class="text-muted small mb-0">Daftar calon penghuni yang telah mengajukan sewa kamar dari katalog publik Kos Putri Anak Lanang.</p>
            </div>
            <span class="badge bg-primary px-3 py-2 rounded-pill">Total: <?= count($listPemesanan) ?> Pengajuan Masuk</span>
        </div>
    </div>

    <?php if (!empty($notif)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 p-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-5 text-success me-2"></i>
            <div><?= htmlspecialchars($notif) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Table of Bookings -->
    <div class="card card-custom p-4">
        <?php if (empty($listPemesanan)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 text-secondary mb-2"></i>
                <p class="mb-0">Belum ada pengajuan sewa yang masuk dari formulir publik.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>KODE BOOKING</th>
                            <th>CALON PENYEWA</th>
                            <th>UNIT KAMAR</th>
                            <th>TGL MASUK & DURASI</th>
                            <th>STATUS PENGAJUAN</th>
                            <th>PEMBAYARAN DP</th>
                            <th>STATUS KAMAR</th>
                            <th class="text-center" style="width: 220px;">AKSI TANGGAPAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listPemesanan as $pm): ?>
                            <?php
                            $waTextPenyewa = urlencode("Halo Kak " . $pm['nama_penyewa'] . ", ini Ibu Yeni dari Kos Putri Anak Lanang mengenai pengajuan sewa " . $pm['nomor_kamar'] . " (Kode: " . $pm['kode_booking'] . ")...");
                            $waUrlPenyewa = "https://wa.me/" . preg_replace('/[^0-9]/', '', $pm['no_wa_penyewa']) . "?text=" . $waTextPenyewa;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($pm['kode_booking']) ?></span>
                                    <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($pm['created_at']) ?></div>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($pm['nama_penyewa']) ?></strong>
                                    <div class="text-muted small"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($pm['no_wa_penyewa']) ?></div>
                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($pm['kampus_instansi'] ?: 'Umum') ?></div>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($pm['nomor_kamar']) ?></span>
                                    <div class="text-muted small"><?= ucfirst(htmlspecialchars($pm['tipe'])) ?> • <?= formatRupiah($pm['harga']) ?></div>
                                </td>
                                <td>
                                    <div><strong><?= htmlspecialchars($pm['tanggal_masuk']) ?></strong></div>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($pm['durasi_sewa']) ?></span>
                                </td>
                                <td>
                                    <?php if ($pm['status_pemesanan'] === 'menunggu'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">Menunggu</span>
                                    <?php elseif ($pm['status_pemesanan'] === 'disetujui'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">Disetujui</span>
                                    <?php elseif ($pm['status_pemesanan'] === 'ditolak'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">Ditolak</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Selesai Menghuni</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $pByr = $bayarBookingMap[$pm['id_pemesanan']] ?? null;
                                    ?>
                                    <?php if ($pByr): ?>
                                        <?php if ($pByr['status_pembayaran'] === 'lunas'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>DP Lunas (<?= formatRupiah($pByr['nominal']) ?>)
                                            </span>
                                            <div><a href="kuitansi.php?id=<?= $pByr['id_pembayaran'] ?>" target="_blank" class="small fw-bold text-success">Kuitansi Resmi</a></div>
                                        <?php elseif ($pByr['status_pembayaran'] === 'menunggu'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1">
                                                <i class="bi bi-clock-history me-1"></i>Menunggu Verifikasi
                                            </span>
                                            <div><a href="admin_keuangan.php" class="small fw-bold text-primary">Verifikasi Sekarang →</a></div>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">DP Ditolak</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary small">Belum Bayar DP</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $pm['status_kamar'] === 'kosong' ? 'bg-success' : ($pm['status_kamar'] === 'dipesan' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                        <?= ucfirst(htmlspecialchars($pm['status_kamar'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        <!-- Hubungi WA Penyewa -->
                                        <a href="<?= $waUrlPenyewa ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill" title="Kirim Chat WhatsApp">
                                            <i class="bi bi-whatsapp"></i> Chat
                                        </a>

                                        <?php if ($pm['status_pemesanan'] === 'menunggu'): ?>
                                            <!-- Setujui -->
                                            <a href="admin_pemesanan.php?aksi=setujui&id=<?= $pm['id_pemesanan'] ?>" 
                                               class="btn btn-success btn-sm rounded-pill" 
                                               onclick="return confirm('Setujui pesanan ini? Kamar akan otomatis diubah statusnya menjadi Dipesan.')">
                                                <i class="bi bi-check-lg"></i> Terima
                                            </a>
                                            <!-- Tolak -->
                                            <a href="admin_pemesanan.php?aksi=tolak&id=<?= $pm['id_pemesanan'] ?>" 
                                               class="btn btn-outline-danger btn-sm rounded-pill"
                                               onclick="return confirm('Tolak pengajuan sewa ini?')">
                                                <i class="bi bi-x-lg"></i> Tolak
                                            </a>
                                        <?php elseif ($pm['status_pemesanan'] === 'disetujui'): ?>
                                            <!-- Sudah Masuk Kamar -->
                                            <a href="admin_pemesanan.php?aksi=selesai&id=<?= $pm['id_pemesanan'] ?>" 
                                               class="btn btn-primary btn-sm rounded-pill"
                                               onclick="return confirm('Tandai penyewa ini sudah resmi masuk ke kamar? Status kamar akan berubah jadi Terisi.')">
                                                <i class="bi bi-door-open-fill"></i> Sudah Masuk
                                            </a>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
