<?php
// index.php - Katalog E-Commerce KosinAja (Integrasi UC-01, UC-02, UC-04, UC-05, UC-07, UC-12)
require_once __DIR__ . '/config.php';

// Ambil Profil Kos
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmtKos->fetch();

// Filter Query (UC-02 Cari Kos dengan Filter - Cantika)
$search = trim($_GET['search'] ?? '');
$filterTipe = trim($_GET['tipe'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMaxHarga = trim($_GET['max_harga'] ?? '');

$sql = "
    SELECT k.*, 
           (SELECT url_file FROM media_kamar mk WHERE mk.id_kamar = k.id_kamar AND mk.is_utama = 1 LIMIT 1) as foto_utama,
           (SELECT COUNT(*) FROM media_kamar mk WHERE mk.id_kamar = k.id_kamar) as total_foto
    FROM kamar k 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (k.nomor_kamar LIKE ? OR k.ukuran LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filterTipe)) {
    $sql .= " AND k.tipe = ?";
    $params[] = $filterTipe;
}
if (!empty($filterStatus)) {
    $sql .= " AND k.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterMaxHarga) && is_numeric($filterMaxHarga)) {
    $sql .= " AND k.harga <= ?";
    $params[] = (float)$filterMaxHarga;
}

$sql .= " ORDER BY k.id_kamar ASC";
$stmtKamar = $pdo->prepare($sql);
$stmtKamar->execute($params);
$kamars = $stmtKamar->fetchAll();

// Format WhatsApp Link
$waLinkUmum = "https://wa.me/" . preg_replace('/[^0-9]/', '', $kos['no_wa_kontak']) . "?text=" . urlencode("Halo Ibu Yeni, saya ingin menanyakan informasi kamar di Kos Putri Anak Lanang.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($kos['nama_kos']) ?> - Katalog Resmi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .hero { background: radial-gradient(100% 100% at 50% 0%, #312E81 0%, #1E1B4B 100%); color: white; padding: 60px 0 50px; }
        .room-card { border-radius: 20px; overflow: hidden; border: 1px solid #E2E8F0; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #FFFFFF; }
        .room-card:hover { transform: translateY(-5px); box-shadow: 0 16px 30px rgba(0,0,0,0.08); }
        .room-img { height: 210px; object-fit: cover; width: 100%; }
        .filter-card { border-radius: 20px; border: 1px solid #E2E8F0; background: #FFFFFF; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        .btn-primary-custom { background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none; border-radius: 12px; color: #FFF; font-weight: 700; padding: 10px 18px; }
    </style>
</head>
<body>

<!-- Navbar Publik -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top py-3">
    <div class="container px-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php">
            <span class="rounded-3 bg-primary text-white p-1 px-2"><i class="bi bi-houses-fill"></i></span>
            <span>KosinAja</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="cek_pesanan.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-search me-1"></i>Cek Status Booking
            </a>
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($_SESSION['user']['role'] === 'developer'): ?>
                    <!-- Developer: tombol ke Dev Panel -->
                    <a href="developer_panel.php" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-code-slash me-1"></i>Dev Panel
                    </a>
                    <a href="kamar.php" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                        <i class="bi bi-tools me-1"></i>Panel Mitra
                    </a>
                <?php elseif ($_SESSION['user']['role'] === 'penyewa'): ?>
                    <!-- Penyewa: tombol ke Portal Penghuni -->
                    <a href="penyewa_dashboard.php" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-person-check-fill me-1"></i>Portal Penghuni (<?= htmlspecialchars($_SESSION['user']['nama']) ?>)
                    </a>
                <?php else: ?>
                    <!-- Mitra: tombol ke Panel Mitra -->
                    <a href="kamar.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i>Panel Mitra (<?= htmlspecialchars($_SESSION['user']['nama']) ?>)
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-lock-fill me-1"></i>Masuk Akun
                </a>
                <a href="register.php" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-person-plus-fill me-1"></i>Daftar Penghuni
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero Banner (UC-12 Kelola Data Katalog) -->
<section class="hero text-center text-md-start">
    <div class="container px-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill fw-bold mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-stars"></i> Mitra UMKM Resmi • Pasir Pengaraian, Riau
                </span>
                <h1 class="display-6 fw-bold mb-2"><?= htmlspecialchars($kos['nama_kos']) ?></h1>
                <p class="lead opacity-90 fs-6 mb-3"><?= htmlspecialchars($kos['deskripsi']) ?></p>
                <div class="d-flex flex-wrap gap-2 text-white-50 small mb-2">
                    <span><i class="bi bi-geo-alt-fill text-warning me-1"></i><?= htmlspecialchars($kos['alamat']) ?></span>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end mt-4 mt-lg-0">
                <div class="bg-white text-dark p-4 rounded-4 shadow-sm d-inline-block text-start w-100" style="max-width: 340px;">
                    <div class="small fw-bold text-primary mb-1"><i class="bi bi-shield-check me-1"></i>Kepatuhan Permendag 19/2026:</div>
                    <div class="small"><strong>NIB:</strong> <?= htmlspecialchars($kos['nib']) ?></div>
                    <div class="small text-muted mb-3"><?= htmlspecialchars($kos['asal_layanan']) ?></div>
                    
                    <a href="<?= $waLinkUmum ?>" target="_blank" class="btn btn-success w-100 py-2 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-whatsapp"></i> Hubungi Ibu Yeni (WA)
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Konten Utama: Filter (UC-02) & Daftar Kos (UC-01) -->
<div class="container px-4 py-5">
    <!-- Filter Pencarian (UC-02 Cari Kos dengan Filter - Cantika) -->
    <div class="filter-card p-4 mb-5">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter &amp; Pencarian Kamar (UC-02)</h5>
        <form method="GET" action="index.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Cari Nomor / Nama</label>
                    <input type="text" name="search" class="form-control rounded-3" placeholder="Contoh: Kamar 04" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Tipe Fasilitas</label>
                    <select name="tipe" class="form-select rounded-3">
                        <option value="">Semua Tipe</option>
                        <option value="berperabot" <?= $filterTipe === 'berperabot' ? 'selected' : '' ?>>Berperabot (Furnished)</option>
                        <option value="kosongan" <?= $filterTipe === 'kosongan' ? 'selected' : '' ?>>Kosongan (Non-Furnished)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">Status Ketersediaan</label>
                    <select name="status" class="form-select rounded-3">
                        <option value="">Semua Status</option>
                        <option value="kosong" <?= $filterStatus === 'kosong' ? 'selected' : '' ?>>Kosong (Siap Huni)</option>
                        <option value="dipesan" <?= $filterStatus === 'dipesan' ? 'selected' : '' ?>>Dipesan</option>
                        <option value="terisi" <?= $filterStatus === 'terisi' ? 'selected' : '' ?>>Terisi</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">Tarif Maksimal (Rp)</label>
                    <input type="number" name="max_harga" class="form-control rounded-3" placeholder="Contoh: 900000" value="<?= htmlspecialchars($filterMaxHarga) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-custom w-100"><i class="bi bi-search me-1"></i> Terapkan</button>
                    <a href="index.php" class="btn btn-light border rounded-3"><i class="bi bi-arrow-clockwise"></i></a>
                </div>
            </div>
        </form>
    </div>

    <!-- Daftar Kamar (UC-01 Lihat Daftar Kos - Salsabila Yata) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Daftar Pilihan Unit Kamar</h3>
            <p class="text-muted small mb-0">Dikelola langsung oleh mitra Ibu Yeni via modul <em>UC-09 Kelola Data Kos dan Kamar</em>.</p>
        </div>
        <span class="badge bg-primary px-3 py-2 rounded-pill"><?= count($kamars) ?> Unit Ditemukan</span>
    </div>

    <?php if (empty($kamars)): ?>
        <div class="text-center py-5 filter-card p-5">
            <i class="bi bi-search display-4 text-secondary mb-3"></i>
            <h5 class="fw-bold">Tidak ada kamar yang sesuai filter</h5>
            <p class="text-muted small mb-3">Coba reset filter atau ubah kata kunci pencarian Anda.</p>
            <a href="index.php" class="btn btn-primary-custom rounded-pill">Lihat Semua Kamar</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($kamars as $km): ?>
                <?php
                // Fasilitas
                $stmtF = $pdo->prepare("SELECT f.nama_fasilitas, f.icon FROM kamar_fasilitas kf JOIN fasilitas f ON kf.id_fasilitas = f.id_fasilitas WHERE kf.id_kamar = ?");
                $stmtF->execute([$km['id_kamar']]);
                $fasilitas = $stmtF->fetchAll();

                $fotoUrl = $km['foto_utama'] ?: 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800';
                $waPesanKamar = "https://wa.me/" . preg_replace('/[^0-9]/', '', $kos['no_wa_kontak']) . "?text=" . urlencode("Halo Ibu Yeni, saya berminat dengan " . $km['nomor_kamar'] . " (Kos Putri Anak Lanang). Apakah masih tersedia?");
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card room-card h-100 d-flex flex-column">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Kamar" class="room-img">
                            <span class="position-absolute top-0 start-0 m-3 badge <?= $km['tipe'] === 'berperabot' ? 'bg-primary' : 'bg-info text-dark' ?> rounded-pill px-3 py-2">
                                <?= ucfirst(htmlspecialchars($km['tipe'])) ?>
                            </span>
                            <span class="position-absolute top-0 end-0 m-3 badge bg-dark bg-opacity-75 rounded-pill px-3 py-2">
                                <i class="bi bi-camera me-1"></i><?= $km['total_foto'] ?> Foto
                            </span>
                        </div>

                        <div class="card-body p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($km['nomor_kamar']) ?></h5>
                                <!-- UC-07 Live Status -->
                                <?php if ($km['status'] === 'kosong'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success rounded-pill px-2 py-1">Kosong</span>
                                <?php elseif ($km['status'] === 'dipesan'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill px-2 py-1">Dipesan</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">Terisi</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-muted small mb-3">
                                <i class="bi bi-aspect-ratio me-1"></i>Ukuran: <?= htmlspecialchars($km['ukuran']) ?>
                            </div>

                            <!-- Fasilitas Singkat -->
                            <div class="mb-3">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach (array_slice($fasilitas, 0, 4) as $f): ?>
                                        <span class="badge bg-light text-secondary border small" style="font-size: 11px;">
                                            <i class="bi <?= $f['icon'] ?> text-primary me-1"></i><?= htmlspecialchars($f['nama_fasilitas']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="text-muted small">Tarif Sewa:</span>
                                        <div class="fw-bold text-success fs-5"><?= formatRupiah($km['harga']) ?> <span class="text-muted small fw-normal">/ bln</span></div>
                                    </div>
                                    <a href="<?= $waPesanKamar ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3" title="Chat WhatsApp (UC-05)">
                                        <i class="bi bi-whatsapp"></i> Chat
                                    </a>
                                </div>

                                <!-- Tombol Aksi Detail & Booking (UC-03 & UC-06) -->
                                <a href="detail.php?id=<?= $km['id_kamar'] ?>" class="btn btn-primary-custom w-100 rounded-3 text-center">
                                    <span>Lihat Detail &amp; Pesan (UC-03 / UC-06)</span> <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Footer Kepatuhan Konsumen (Permendag 19/2026) -->
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container px-4">
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="fw-bold text-primary"><i class="bi bi-houses-fill me-1"></i>KosinAja - Toko Uji Coba Terpasang</h6>
                <p class="small text-secondary mb-0">
                    Sistem informasi e-commerce sewa kamar kos berbasis web untuk pemberdayaan UMKM lokal Pasir Pengaraian, Riau.
                </p>
            </div>
            <div class="col-md-6 text-md-end small text-secondary">
                <div><strong>Identitas Usaha:</strong> <?= htmlspecialchars($kos['nama_kos']) ?></div>
                <div><strong>Nomor Induk Berusaha (NIB):</strong> <?= htmlspecialchars($kos['nib']) ?></div>
                <div><strong>Standar Regulasi:</strong> Permendag RI No. 19 Tahun 2026 tentang Perlindungan Konsumen &amp; PMSE.</div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
