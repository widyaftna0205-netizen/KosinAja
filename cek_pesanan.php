<?php
// cek_pesanan.php - UC-07 Cek Status Kamar & Riwayat Pengajuan Sewa
require_once __DIR__ . '/config.php';

$query = trim($_GET['q'] ?? '');
$hasil = [];

if (!empty($query)) {
    $stmt = $pdo->prepare("
        SELECT p.*, k.nomor_kamar, k.tipe, k.harga, k.status as status_kamar, ks.nama_kos, ks.no_wa_kontak
        FROM pemesanan p
        JOIN kamar k ON p.id_kamar = k.id_kamar
        JOIN kos ks ON k.id_kos = ks.id_kos
        WHERE p.kode_booking = ? OR p.no_wa_penyewa = ?
        ORDER BY p.id_pemesanan DESC
    ");
    $stmt->execute([$query, $query]);
    $hasil = $stmt->fetchAll();

    $bookingBayar = [];
    if (!empty($hasil)) {
        $ids = array_column($hasil, 'id_pemesanan');
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmtBayar = $pdo->prepare("SELECT * FROM pembayaran WHERE id_pemesanan IN ($placeholders)");
        $stmtBayar->execute($ids);
        foreach ($stmtBayar->fetchAll() as $b) {
            $bookingBayar[$b['id_pemesanan']] = $b;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Booking - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F8FAFC; color: #0F172A; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .btn-primary-custom { background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none; border-radius: 12px; color: #FFF; font-weight: 700; padding: 10px 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3">
    <div class="container px-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php">
            <span class="btn btn-sm btn-outline-light rounded-pill px-2 py-1"><i class="bi bi-arrow-left"></i></span>
            <span>Katalog KosinAja</span>
        </a>
        <span class="badge bg-light text-dark rounded-pill px-3 py-2">UC-07: Cek Status Kamar</span>
    </div>
</nav>

<div class="container py-5" style="max-width: 720px;">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-dark">Pantau Status Pengajuan Sewa</h3>
        <p class="text-muted small">Masukkan <strong>Kode Booking</strong> atau <strong>Nomor WhatsApp</strong> Anda saat mengajukan sewa.</p>
    </div>

    <!-- Form Pencarian -->
    <div class="card card-custom p-4 mb-4">
        <form method="GET" action="cek_pesanan.php">
            <div class="input-group">
                <input type="text" name="q" class="form-control form-control-lg rounded-start-4 border-2" 
                       placeholder="Contoh: BKG-20261104-123 atau 08123456789" value="<?= htmlspecialchars($query) ?>" required autofocus>
                <button type="submit" class="btn btn-primary-custom rounded-end-4 px-4 fs-6">
                    <i class="bi bi-search me-1"></i> Lacak Status
                </button>
            </div>
        </form>
    </div>

    <!-- Hasil Pencarian -->
    <?php if (!empty($query)): ?>
        <?php if (empty($hasil)): ?>
            <div class="card card-custom p-5 text-center text-muted">
                <i class="bi bi-search display-5 text-secondary mb-3"></i>
                <h5 class="fw-bold">Pengajuan Tidak Ditemukan</h5>
                <p class="small mb-0">Tidak ada data pemesanan dengan kode atau nomor WhatsApp "<strong><?= htmlspecialchars($query) ?></strong>". Silakan periksa kembali.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($hasil as $p): ?>
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                            <div>
                                <span class="badge bg-light text-dark border mb-1">Kode: <?= htmlspecialchars($p['kode_booking']) ?></span>
                                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($p['nomor_kamar']) ?> (<?= ucfirst(htmlspecialchars($p['tipe'])) ?>)</h5>
                                <div class="text-muted small"><?= htmlspecialchars($p['nama_kos']) ?></div>
                            </div>
                            <div>
                                <?php if ($p['status_pemesanan'] === 'menunggu'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                        <i class="bi bi-hourglass-split me-1"></i>Menunggu Konfirmasi Ibu Yeni
                                    </span>
                                <?php elseif ($p['status_pemesanan'] === 'disetujui'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i>Disetujui! Siap Survei/Masuk
                                    </span>
                                <?php elseif ($p['status_pemesanan'] === 'ditolak'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                                        <i class="bi bi-x-circle-fill me-1"></i>Pengajuan Ditolak / Kamar Penuh
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill">
                                        Selesai / Sudah Aktif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-2 small text-secondary mb-3">
                            <div class="col-6"><strong>Nama Pemesan:</strong> <?= htmlspecialchars($p['nama_penyewa']) ?></div>
                            <div class="col-6"><strong>Rencana Masuk:</strong> <?= htmlspecialchars($p['tanggal_masuk']) ?></div>
                            <div class="col-6"><strong>Durasi Sewa:</strong> <?= htmlspecialchars($p['durasi_sewa']) ?></div>
                            <div class="col-6"><strong>Status Fisik Kamar:</strong> 
                                <span class="badge <?= $p['status_kamar'] === 'kosong' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= ucfirst(htmlspecialchars($p['status_kamar'])) ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($p['catatan'])): ?>
                            <div class="p-2 bg-light rounded-3 small mb-3">
                                <strong>Catatan Pemesan:</strong> <?= htmlspecialchars($p['catatan']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Bagian Status & Aksi Pembayaran DP Calon Penghuni -->
                        <?php 
                        $byr = $bookingBayar[$p['id_pemesanan']] ?? null;
                        ?>
                        <div class="p-3 bg-light rounded-4 mb-3 border">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <span class="fw-bold small text-dark"><i class="bi bi-wallet2 text-success me-1"></i>Status Pembayaran DP / Sewa:</span>
                                <?php if ($byr): ?>
                                    <?php if ($byr['status_pembayaran'] === 'lunas'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i>DP Lunas (<?= formatRupiah($byr['nominal']) ?>)
                                        </span>
                                    <?php elseif ($byr['status_pembayaran'] === 'menunggu'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-1 rounded-pill">
                                            <i class="bi bi-clock-history me-1"></i>Menunggu Verifikasi Manual Ibu Yeni
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1 rounded-pill">
                                            <i class="bi bi-x-circle-fill me-1"></i>Pembayaran Ditolak
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-3 py-1 rounded-pill">
                                        Belum Ada Pembayaran
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($byr && $byr['status_pembayaran'] === 'lunas'): ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Kamar Anda telah diamankan. Silakan unduh kuitansi resmi.</small>
                                    <a href="kuitansi.php?id=<?= $byr['id_pembayaran'] ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                                        <i class="bi bi-receipt me-1"></i>Cetak Kuitansi Resmi
                                    </a>
                                </div>
                            <?php elseif ($byr && $byr['status_pembayaran'] === 'menunggu'): ?>
                                <small class="text-muted">
                                    Konfirmasi pembayaran telah dikirimkan. Ibu Yeni akan memeriksa mutasi bank Anda. Hubungi WA Ibu Yeni jika butuh konfirmasi cepat.
                                </small>
                            <?php elseif ($p['status_pemesanan'] === 'disetujui'): ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <small class="text-success fw-semibold">Pengajuan Anda telah disetujui! Segera bayar DP untuk mengunci kamar.</small>
                                    <a href="pembayaran.php?booking=<?= urlencode($p['kode_booking']) ?>" class="btn btn-success btn-sm rounded-pill px-4 fw-bold">
                                        <i class="bi bi-credit-card me-1"></i>Bayar DP / Sewa Kamar Sekarang
                                    </a>
                                </div>
                            <?php else: ?>
                                <small class="text-muted">Pembayaran DP dapat dilakukan setelah Ibu Yeni menyetujui pengajuan sewa Anda.</small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="text-muted small">Diajukan: <?= htmlspecialchars($p['created_at']) ?></span>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['no_wa_kontak']) ?>?text=Halo%20Ibu%20Yeni,%20saya%20ingin%20konfirmasi%20booking%20<?= urlencode($p['kode_booking']) ?>" 
                               target="_blank" class="btn btn-success btn-sm rounded-pill px-3">
                                <i class="bi bi-whatsapp me-1"></i>Hubungi Ibu Yeni
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
