<?php
// kamar.php - UC-09 Kelola Data Kos dan Kamar & UC-10 Ubah Status Kamar Instan
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$user = $_SESSION['user'];

// Ambil info kos milik Ibu Yeni
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmtKos->fetch();

// Ambil total pengajuan sewa yang menunggu
$pendingCount = $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_pemesanan = 'menunggu'")->fetchColumn();
$pendingBayarCount = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_pembayaran = 'menunggu'")->fetchColumn();

// Ambil semua kamar beserta fasilitas dan foto utama
$sqlKamar = "
    SELECT k.*, 
           (SELECT url_file FROM media_kamar mk WHERE mk.id_kamar = k.id_kamar AND mk.is_utama = 1 LIMIT 1) as foto_utama,
           (SELECT COUNT(*) FROM media_kamar mk WHERE mk.id_kamar = k.id_kamar) as total_foto
    FROM kamar k 
    ORDER BY k.id_kamar DESC
";
$kamars = $pdo->query($sqlKamar)->fetchAll();

// Notifikasi pesan
$notif = '';
if (isset($_GET['sukses'])) {
    if ($_GET['sukses'] === 'tambah') $notif = 'Data kamar baru berhasil ditambahkan dan langsung aktif di katalog!';
    if ($_GET['sukses'] === 'ubah') $notif = 'Data kamar berhasil diperbarui!';
    if ($_GET['sukses'] === 'hapus') $notif = 'Kamar berhasil dihapus dari sistem.';
    if ($_GET['sukses'] === 'status_diubah') $notif = 'Status ketersediaan kamar berhasil diubah (UC-10)!';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Kelola Kamar - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F8FAFC; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .table-img { width: 75px; height: 55px; object-fit: cover; border-radius: 10px; }
        .btn-status-drop { font-size: 11px; font-weight: 700; border-radius: 20px; padding: 4px 10px; }
    </style>
</head>
<body>

<!-- Navbar Admin - tampilan berbeda untuk developer vs mitra -->
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
            <span class="text-white small d-none d-md-inline me-1">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['nama']) ?>
                <span class="badge <?= isDeveloper() ? 'bg-danger' : 'bg-warning text-dark' ?> ms-1">
                    <?= ucfirst($user['role']) ?>
                </span>
            </span>

            <?php if (isDeveloper()): ?>
                <!-- Developer: tombol kembali ke Developer Panel -->
                <a href="developer_panel.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                    <i class="bi bi-code-slash me-1"></i>Dev Panel
                </a>
                <!-- Developer: bisa juga akses Panel Penyewa -->
                <a href="penyewa_dashboard.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    <i class="bi bi-person-check me-1"></i>Panel Penyewa
                </a>
            <?php endif; ?>

            <a href="admin_pemesanan.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold position-relative">
                <i class="bi bi-inbox-fill me-1"></i>Pengajuan (UC-11)
                <?php if ($pendingCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin_keuangan.php" class="btn btn-outline-light btn-sm rounded-pill px-3 position-relative">
                <i class="bi bi-book-half me-1"></i>Rekap Keuangan
                <?php if ($pendingBayarCount > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $pendingBayarCount ?></span>
                <?php endif; ?>
            </a>
            <a href="index.php" target="_blank" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i>Katalog
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3" onclick="return confirm('Keluar sesi?')">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<?php if (isDeveloper()): ?>
<!-- Banner Developer Mode di Panel Mitra -->
<div class="alert alert-danger border-0 rounded-0 mb-0 py-2 text-center" style="background:rgba(220,38,38,.1);border-top:2px solid #DC2626 !important;">
    <i class="bi bi-shield-fill-check me-2"></i>
    <strong>Developer Mode</strong> — Anda melihat Panel Mitra sebagai Developer. Semua aksi (tambah/ubah/hapus kamar, ubah status) dapat dilakukan.
    <a href="developer_panel.php" class="ms-3 text-danger fw-bold">← Kembali ke Dev Panel</a>
</div>
<?php endif; ?>

<div class="container-fluid px-4 py-4">
    <!-- Header Kos & Legalitas Permendag 19/2026 -->
    <div class="card card-custom mb-4 p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($kos['nama_kos']) ?></h4>
                    <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-1 small">
                        <i class="bi bi-patch-check-fill me-1"></i>Terverifikasi
                    </span>
                </div>
                <p class="text-muted small mb-2">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($kos['alamat']) ?> | 
                    <i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($kos['no_wa_kontak']) ?>
                </p>
                <div class="small text-secondary d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-file-earmark-text me-1"></i><strong>NIB:</strong> <?= htmlspecialchars($kos['nib']) ?>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-shield-check me-1"></i><strong>Standar:</strong> Permendag 19/2026 (<?= htmlspecialchars($kos['asal_layanan']) ?>)
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="profil_kos.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-gear-fill me-1"></i>Kelola Profil &amp; Katalog (UC-12)
                </a>
                <a href="kamar_form.php" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle-fill me-1"></i>Tambah Kamar (UC-09)
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    <?php if (!empty($notif)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 py-3 px-4 small mb-4 d-flex align-items-center shadow-sm">
            <i class="bi bi-check-circle-fill text-success me-2 fs-5"></i>
            <div><?= htmlspecialchars($notif) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabel Daftar Kamar -->
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-grid-fill text-primary me-2"></i>Daftar Unit Kamar Kos (UC-09 &amp; UC-10)</h5>
                <p class="text-muted small mb-0">Klik pada status ketersediaan untuk mengubah status kamar secara instan (UC-10).</p>
            </div>
            <span class="badge bg-primary px-3 py-2 rounded-pill">Total: <?= count($kamars) ?> Kamar</span>
        </div>

        <?php if (empty($kamars)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-door-closed display-4 text-secondary mb-2"></i>
                <p>Belum ada data kamar. Silakan klik tombol <strong>Tambah Kamar Baru</strong>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th style="width: 85px;">FOTO</th>
                            <th>NO. KAMAR</th>
                            <th>TIPE &amp; UKURAN</th>
                            <th>HARGA / BULAN</th>
                            <th>FASILITAS</th>
                            <th style="width: 170px;">STATUS (UC-10 GANTI CEPAT)</th>
                            <th class="text-center" style="width: 130px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kamars as $km): ?>
                            <?php
                            $stmtF = $pdo->prepare("SELECT f.nama_fasilitas FROM kamar_fasilitas kf JOIN fasilitas f ON kf.id_fasilitas = f.id_fasilitas WHERE kf.id_kamar = ?");
                            $stmtF->execute([$km['id_kamar']]);
                            $fasList = $stmtF->fetchAll(PDO::FETCH_COLUMN);

                            $fotoUrl = $km['foto_utama'] ?: 'https://placehold.co/400x300?text=No+Photo';
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Kamar" class="table-img border">
                                    <div class="text-muted" style="font-size: 10px;">(<?= $km['total_foto'] ?> foto)</div>
                                </td>
                                <td>
                                    <strong class="text-dark fs-6"><?= htmlspecialchars($km['nomor_kamar']) ?></strong>
                                    <div class="text-muted small">ID: #KMR-<?= str_pad($km['id_kamar'], 3, '0', STR_PAD_LEFT) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $km['tipe'] === 'berperabot' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                        <?= ucfirst(htmlspecialchars($km['tipe'])) ?>
                                    </span>
                                    <div class="text-muted small mt-1"><?= htmlspecialchars($km['ukuran']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-6"><?= formatRupiah($km['harga']) ?></div>
                                    <div class="text-muted" style="font-size: 11px;">Bebas Ongkir</div>
                                </td>
                                <td>
                                    <div class="small text-secondary" style="max-width: 220px;">
                                        <?= !empty($fasList) ? htmlspecialchars(implode(', ', array_slice($fasList, 0, 3))) . (count($fasList) > 3 ? '...' : '') : '<span class="text-muted">Belum ada</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <!-- UC-10 Ubah Status Kamar (Cantika) - 1-Click Dropdown Switcher -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm dropdown-toggle btn-status-drop <?= $km['status'] === 'kosong' ? 'btn-success' : ($km['status'] === 'dipesan' ? 'btn-warning text-dark' : 'btn-secondary') ?>" 
                                                type="button" data-bs-toggle="dropdown">
                                            <?php if ($km['status'] === 'kosong'): ?>
                                                <i class="bi bi-check-circle-fill me-1"></i>Kosong
                                            <?php elseif ($km['status'] === 'dipesan'): ?>
                                                <i class="bi bi-clock-fill me-1"></i>Dipesan
                                            <?php else: ?>
                                                <i class="bi bi-person-fill me-1"></i>Terisi
                                            <?php endif; ?>
                                        </button>
                                        <ul class="dropdown-menu shadow-sm border-0 small">
                                            <li><h6 class="dropdown-header">Ubah Status (UC-10):</h6></li>
                                            <li><a class="dropdown-item text-success fw-bold" href="quick_status.php?id=<?= $km['id_kamar'] ?>&status=kosong"><i class="bi bi-check-circle me-2"></i>Kosong (Siap Huni)</a></li>
                                            <li><a class="dropdown-item text-warning-emphasis fw-bold" href="quick_status.php?id=<?= $km['id_kamar'] ?>&status=dipesan"><i class="bi bi-clock me-2"></i>Dipesan (Booking)</a></li>
                                            <li><a class="dropdown-item text-secondary fw-bold" href="quick_status.php?id=<?= $km['id_kamar'] ?>&status=terisi"><i class="bi bi-person-lock me-2"></i>Terisi (Penuh)</a></li>
                                        </ul>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="detail.php?id=<?= $km['id_kamar'] ?>" target="_blank" class="btn btn-outline-secondary" title="Lihat Tampilan Publik">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="kamar_form.php?id=<?= $km['id_kamar'] ?>" class="btn btn-outline-primary" title="Ubah Data Kamar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="kamar_hapus.php?id=<?= $km['id_kamar'] ?>" class="btn btn-outline-danger" title="Hapus Kamar" 
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus <?= htmlspecialchars($km['nomor_kamar']) ?>?')">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
