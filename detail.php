<?php
// detail.php - UC-03 Lihat Detail Kos, UC-04 Lihat Video Tur, UC-05 Hubungi WhatsApp, UC-06 Ajukan Pemesanan
require_once __DIR__ . '/config.php';

$idKamar = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT k.*, ks.nama_kos, ks.alamat, ks.deskripsi as desc_kos, ks.no_wa_kontak, ks.nib, ks.aturan_kos, ks.asal_layanan FROM kamar k JOIN kos ks ON k.id_kos = ks.id_kos WHERE k.id_kamar = ?");
$stmt->execute([$idKamar]);
$kamar = $stmt->fetch();

if (!$kamar) {
    header("Location: index.php");
    exit;
}

// Media Foto
$stmtM = $pdo->prepare("SELECT * FROM media_kamar WHERE id_kamar = ? ORDER BY is_utama DESC, id_media ASC");
$stmtM->execute([$idKamar]);
$fotos = $stmtM->fetchAll();

// Fasilitas
$stmtF = $pdo->prepare("SELECT f.nama_fasilitas, f.icon FROM kamar_fasilitas kf JOIN fasilitas f ON kf.id_fasilitas = f.id_fasilitas WHERE kf.id_kamar = ?");
$stmtF->execute([$idKamar]);
$fasilitas = $stmtF->fetchAll();

// Biaya Kamar / Deposit
$stmtB = $pdo->prepare("SELECT * FROM biaya_kamar WHERE id_kamar = ?");
$stmtB->execute([$idKamar]);
$biayas = $stmtB->fetchAll();

// Handle Form Pengajuan Pemesanan (UC-06)
$pesanSukses = '';
$pesanError = '';
$bookingCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_sewa') {
    $nama = trim($_POST['nama_penyewa'] ?? '');
    $no_wa = trim($_POST['no_wa_penyewa'] ?? '');
    $kampus = trim($_POST['kampus_instansi'] ?? '');
    $tgl_masuk = trim($_POST['tanggal_masuk'] ?? '');
    $durasi = trim($_POST['durasi_sewa'] ?? '1 Bulan');
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($nama) || empty($no_wa) || empty($tgl_masuk)) {
        $pesanError = 'Mohon lengkapi Nama, Nomor WhatsApp, dan Rencana Tanggal Masuk!';
    } elseif ($kamar['status'] === 'terisi') {
        $pesanError = 'Maaf, unit kamar ini saat ini berstatus terisi dan belum dapat dipesan.';
    } else {
        $bookingCode = 'BKG-' . date('Ymd') . '-' . rand(100, 999);
        $idPengguna = $_SESSION['user']['id_pengguna'] ?? null;
        $stmtInsP = $pdo->prepare("
            INSERT INTO pemesanan (id_kamar, id_pengguna, kode_booking, nama_penyewa, no_wa_penyewa, kampus_instansi, tanggal_masuk, durasi_sewa, catatan, status_pemesanan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu')
        ");
        $stmtInsP->execute([$idKamar, $idPengguna, $bookingCode, $nama, $no_wa, $kampus, $tgl_masuk, $durasi, $catatan]);
        $pesanSukses = 'Pengajuan sewa Anda berhasil dikirim! Silakan simpan Kode Booking Anda: ' . $bookingCode;
    }
}

// Format Pesan WhatsApp (UC-05)
$waText = urlencode("Halo Ibu Yeni, saya tertarik dengan " . $kamar['nomor_kamar'] . " (Tipe " . ucfirst($kamar['tipe']) . " - " . formatRupiah($kamar['harga']) . "/bln) di Kos Putri Anak Lanang. Apakah masih tersedia untuk survei lokasi?");
$waLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', $kamar['no_wa_kontak']) . "?text=" . $waText;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($kamar['nomor_kamar']) ?> - <?= htmlspecialchars($kamar['nama_kos']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .hero-img { height: 380px; object-fit: cover; border-radius: 20px; width: 100%; }
        .thumb-img { height: 95px; object-fit: cover; border-radius: 12px; cursor: pointer; border: 2px solid transparent; width: 100%; }
        .thumb-img:hover { border-color: #4F46E5; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .btn-primary-custom { background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none; border-radius: 14px; color: #FFF; font-weight: 700; padding: 12px 24px; box-shadow: 0 8px 18px -4px rgba(79, 70, 229, 0.4); }
        .btn-primary-custom:hover { transform: translateY(-2px); color: #FFF; }
        .badge-facility { background: #EEF2FF; border: 1px solid #C7D2FE; color: #3730A3; border-radius: 10px; padding: 8px 14px; font-weight: 600; font-size: 13px; }
    </style>
</head>
<body>

<!-- Navbar Publik -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top py-3">
    <div class="container px-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php">
            <span class="btn btn-sm btn-outline-light rounded-pill px-2 py-1"><i class="bi bi-arrow-left"></i></span>
            <span>KosinAja</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="cek_pesanan.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-search me-1"></i>Cek Status Booking
            </a>
            <a href="login.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-person-lock me-1"></i>Panel Ibu Yeni
            </a>
        </div>
    </div>
</nav>

<div class="container px-4 py-4" style="max-width: 1050px;">
    <!-- Alert Notifikasi -->
    <?php if (!empty($pesanSukses)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 p-4 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-2 text-success me-3"></i>
                <div>
                    <h5 class="fw-bold mb-1">Pengajuan Sewa Berhasil Dikirim!</h5>
                    <p class="mb-1 text-secondary"><?= htmlspecialchars($pesanSukses) ?></p>
                    <small class="text-muted">Gunakan kode di atas untuk memantau status pesanan Anda di menu <a href="cek_pesanan.php" class="fw-bold text-success">Cek Status Booking</a>.</small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesanError)): ?>
        <div class="alert alert-danger rounded-4 p-3 mb-4 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 text-danger me-2"></i>
            <div><?= htmlspecialchars($pesanError) ?></div>
        </div>
    <?php endif; ?>

    <!-- Title & Status Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($kamar['nomor_kamar']) ?></h2>
                <span class="badge <?= $kamar['tipe'] === 'berperabot' ? 'bg-primary' : 'bg-info text-dark' ?> rounded-pill px-3 py-2">
                    <?= ucfirst(htmlspecialchars($kamar['tipe'])) ?>
                </span>
                <?php if ($kamar['status'] === 'kosong'): ?>
                    <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i>Kosong (Siap Huni)
                    </span>
                <?php elseif ($kamar['status'] === 'dipesan'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill px-3 py-2">
                        <i class="bi bi-clock-fill me-1"></i>Dipesan
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary rounded-pill px-3 py-2">
                        <i class="bi bi-person-fill me-1"></i>Terisi Penuh
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-muted small mb-0">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($kamar['alamat']) ?> • Ukuran: <?= htmlspecialchars($kamar['ukuran']) ?>
            </p>
        </div>

        <div class="text-md-end">
            <div class="text-muted small">Tarif Sewa:</div>
            <div class="fw-bold text-success fs-3"><?= formatRupiah($kamar['harga']) ?> <span class="fs-6 text-muted fw-normal">/ bulan</span></div>
        </div>
    </div>

    <!-- Foto & Media (UC-03 & UC-04) -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <img id="mainImage" src="<?= htmlspecialchars($fotos[0]['url_file'] ?? 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800') ?>" 
                 alt="Foto Kamar" class="hero-img shadow-sm border">
        </div>
        <div class="col-lg-4 d-flex flex-column gap-2">
            <!-- Galeri Mini (Min 3 foto sesuai Slide 8) -->
            <div class="row g-2">
                <?php foreach ($fotos as $idx => $f): ?>
                    <div class="col-4 col-lg-6">
                        <img src="<?= htmlspecialchars($f['url_file']) ?>" alt="Thumbnail" class="thumb-img border" 
                             onclick="document.getElementById('mainImage').src = this.src;">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Card Video Tur (UC-04 Lihat Video Tur - Widya) -->
            <div class="card card-custom p-3 mt-auto bg-light border-primary border-opacity-25">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="bi bi-play-circle-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">Video Tur Kamar (UC-04)</div>
                        <div class="text-muted" style="font-size:11px;">Jelajahi sudut kamar secara virtual</div>
                    </div>
                    <button class="btn btn-outline-danger btn-sm ms-auto rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#videoModal">
                        Putar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Konten Detail & Aksi Pemesanan -->
    <div class="row g-4">
        <!-- Kolom Kiri: Spesifikasi, Fasilitas, Aturan -->
        <div class="col-lg-7">
            <!-- Fasilitas Kamar -->
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-stars text-primary me-2"></i>Fasilitas Kamar Ini</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($fasilitas as $fas): ?>
                        <div class="badge-facility d-flex align-items-center gap-2">
                            <i class="bi <?= $fas['icon'] ?>"></i>
                            <span><?= htmlspecialchars($fas['nama_fasilitas']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Transparansi Deposit & Biaya Tambahan (Slide 7 Bab 6) -->
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-shield-check text-success me-2"></i>Rincian Biaya & Jaminan (Transparan)</h5>
                <p class="text-muted small mb-3">Sesuai prinsip transparansi e-commerce, seluruh komponen biaya dijabarkan tanpa biaya tersembunyi:</p>
                <div class="list-group list-group-flush rounded-3 border">
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span>Sewa Kamar Pokok / Bulan</span>
                        <span class="fw-bold text-dark"><?= formatRupiah($kamar['harga']) ?></span>
                    </div>
                    <?php foreach ($biayas as $b): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 bg-light">
                            <div>
                                <span><?= htmlspecialchars($b['nama_biaya']) ?></span>
                                <div class="text-muted" style="font-size: 11px;">(Dikembalikan 100% saat masa sewa berakhir)</div>
                            </div>
                            <span class="fw-semibold text-secondary"><?= formatRupiah($b['nominal']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tata Tertib & Aturan Kos Putri Anak Lanang -->
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-card-checklist text-primary me-2"></i>Tata Tertib Kos Putri Anak Lanang</h5>
                <div class="p-3 bg-light rounded-3 small text-secondary" style="white-space: pre-line;">
                    <?= htmlspecialchars($kamar['aturan_kos']) ?>
                </div>
            </div>

            <!-- Legalitas Usaha Permendag 19/2026 -->
            <div class="p-3 bg-white rounded-4 border text-muted small">
                <strong><i class="bi bi-patch-check-fill text-success me-1"></i>Legalitas Usaha Resmi:</strong>
                <div>NIB: <?= htmlspecialchars($kamar['nib']) ?> • <?= htmlspecialchars($kamar['asal_layanan']) ?></div>
            </div>
        </div>

        <!-- Kolom Kanan: Aksi Pemesanan (UC-06) & Hubungi WA (UC-05) -->
        <div class="col-lg-5">
            <div class="card card-custom p-4 sticky-top" style="top: 90px;">
                <h5 class="fw-bold mb-1">Berminat dengan Kamar Ini?</h5>
                <p class="text-muted small mb-3">Ajukan pemesanan langsung secara online atau hubungi Ibu Yeni melalui WhatsApp.</p>

                <!-- Tombol Hubungi WhatsApp (UC-05 - Widya) -->
                <a href="<?= $waLink ?>" target="_blank" class="btn btn-success w-100 py-3 rounded-4 fw-bold mb-3 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                    <i class="bi bi-whatsapp fs-5"></i>
                    <span>Tanya &amp; Survei via WhatsApp</span>
                </a>

                <div class="position-relative text-center my-3">
                    <hr>
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 small text-muted">ATAU</span>
                </div>

                <!-- Formulir Ajukan Pemesanan (UC-06 - Ketua) -->
                <div class="border rounded-4 p-3 bg-light">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar2-check-fill me-1"></i>Form Pengajuan Sewa (UC-06)</h6>
                    
                    <?php if ($kamar['status'] === 'terisi'): ?>
                        <div class="alert alert-secondary small mb-0">Unit kamar ini sedang terisi. Silakan pilih nomor kamar lain.</div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="ajukan_sewa">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Nama Calon Penyewa *</label>
                                <input type="text" name="nama_penyewa" class="form-control form-control-sm rounded-3" placeholder="Nama lengkap Anda" required value="<?= htmlspecialchars($_SESSION['user']['nama'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Nomor WhatsApp Aktif *</label>
                                <input type="tel" name="no_wa_penyewa" class="form-control form-control-sm rounded-3" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($_SESSION['user']['no_wa'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Kampus / Instansi</label>
                                <input type="text" name="kampus_instansi" class="form-control form-control-sm rounded-3" placeholder="Contoh: Mahasiswi UPP">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Rencana Masuk *</label>
                                    <input type="date" name="tanggal_masuk" class="form-control form-control-sm rounded-3" required value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Durasi Sewa</label>
                                    <select name="durasi_sewa" class="form-select form-select-sm rounded-3">
                                        <option value="1 Bulan">1 Bulan</option>
                                        <option value="3 Bulan">3 Bulan</option>
                                        <option value="6 Bulan">6 Bulan</option>
                                        <option value="1 Tahun">1 Tahun</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Catatan Khusus</label>
                                <textarea name="catatan" class="form-control form-control-sm rounded-3" rows="2" placeholder="Pertanyaan atau jadwal survei..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary-custom w-100 py-2 fs-6">
                                <i class="bi bi-send-check me-1"></i> Kirim Pengajuan Sewa
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Video Tur Kamar (UC-04 - Widya) -->
<div class="modal fade" id="videoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-camera-video-fill text-danger me-2"></i>Video Tur: <?= htmlspecialchars($kamar['nomor_kamar']) ?></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-black text-center">
                <div class="ratio ratio-16x9">
                    <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=0" title="Video Tur Kamar Kos" allowfullscreen></iframe>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <span class="small text-muted me-auto"><i class="bi bi-info-circle me-1"></i>Video tur realistik interior Kos Putri Anak Lanang (UC-04).</span>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
