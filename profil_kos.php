<?php
// profil_kos.php - UC-09 Kelola Profil Kos & Legalitas Usaha (Permendag 19/2026)
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$stmt = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmt->fetch();

$error = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kos = trim($_POST['nama_kos'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_wa_kontak = trim($_POST['no_wa_kontak'] ?? '');
    $nib = trim($_POST['nib'] ?? '');
    $asal_layanan = trim($_POST['asal_layanan'] ?? 'Layanan Dalam Negeri');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $aturan_kos = trim($_POST['aturan_kos'] ?? '');

    if (empty($nama_kos) || empty($alamat) || empty($no_wa_kontak)) {
        $error = 'Nama kos, alamat lengkap, dan nomor WhatsApp wajib diisi!';
    } else {
        $stmtUp = $pdo->prepare("
            UPDATE kos 
            SET nama_kos = ?, alamat = ?, no_wa_kontak = ?, nib = ?, asal_layanan = ?, deskripsi = ?, aturan_kos = ?, tanggal_pembaruan = CURRENT_TIMESTAMP
            WHERE id_kos = ?
        ");
        $stmtUp->execute([$nama_kos, $alamat, $no_wa_kontak, $nib, $asal_layanan, $deskripsi, $aturan_kos, $kos['id_kos']]);
        
        $sukses = 'Profil kos & identitas kepatuhan Permendag 19/2026 berhasil diperbarui!';
        // Refresh data
        $stmt = $pdo->query("SELECT * FROM kos LIMIT 1");
        $kos = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Profil Kos & Legalitas - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: system-ui, -apple-system, sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark <?= isDeveloper() ? 'bg-dark border-bottom border-danger border-2' : 'bg-primary' ?> sticky-top shadow-sm">
    <div class="container px-4 d-flex align-items-center justify-content-between">
        <a class="navbar-brand fw-bold" href="<?= isDeveloper() ? 'developer_panel.php' : 'kamar.php' ?>">
            <i class="bi bi-arrow-left me-2"></i>
            <?= isDeveloper() ? 'Dev Panel' : 'Kembali ke Panel Kamar' ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white small">UC-09: Profil Kos &amp; Kepatuhan Legalitas</span>
            <?php if (isDeveloper()): ?>
                <span class="badge bg-danger">Developer Mode</span>
                <a href="kamar.php" class="btn btn-sm btn-outline-warning rounded-pill px-2">
                    <i class="bi bi-tools me-1"></i>Panel Mitra
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if (isDeveloper()): ?>
<div class="alert alert-danger border-0 rounded-0 mb-0 py-2 text-center" style="background:rgba(220,38,38,.08);border-top:2px solid #DC2626 !important;">
    <i class="bi bi-shield-fill-check me-2"></i>
    <strong>Developer Mode</strong> — Anda mengedit Profil Kos sebagai Developer. Perubahan akan tersimpan ke database.
    <a href="developer_panel.php" class="ms-3 text-danger fw-bold">← Dev Panel</a>
</div>
<?php endif; ?>

<div class="container py-4" style="max-width: 800px;">
    <div class="card p-4">
        <div class="border-bottom pb-3 mb-4">
            <h4 class="fw-bold mb-1 text-primary">
                <i class="bi bi-building-gear me-2"></i>Ubah Profil Kos & Legalitas Usaha
            </h4>
            <p class="text-muted small mb-0">Informasi ini akan langsung ditampilkan pada footer dan identitas katalog toko online.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($sukses)): ?>
            <div class="alert alert-success py-2 px-3 small mb-3"><?= htmlspecialchars($sukses) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Nama Kos / Brand Usaha <span class="text-danger">*</span></label>
                <input type="text" name="nama_kos" class="form-control" value="<?= htmlspecialchars($kos['nama_kos']) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nomor WhatsApp Pengelola <span class="text-danger">*</span></label>
                    <input type="text" name="no_wa_kontak" class="form-control" value="<?= htmlspecialchars($kos['no_wa_kontak']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nomor Induk Berusaha (NIB OSS)</label>
                    <input type="text" name="nib" class="form-control" placeholder="NIB-1928374650001" value="<?= htmlspecialchars($kos['nib']) ?>">
                    <div class="form-text small">Wajib sesuai Permendag 19/2026 tentang identitas pelaku usaha.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Alamat Lengkap Kos <span class="text-danger">*</span></label>
                <textarea name="alamat" class="form-control" rows="2" required><?= htmlspecialchars($kos['alamat']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Asal Layanan / Produk</label>
                <input type="text" name="asal_layanan" class="form-control" value="<?= htmlspecialchars($kos['asal_layanan']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Deskripsi Singkat Kos</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($kos['deskripsi']) ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Tata Tertib & Aturan Kos</label>
                <textarea name="aturan_kos" class="form-control" rows="3"><?= htmlspecialchars($kos['aturan_kos']) ?></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                <a href="kamar.php" class="btn btn-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Pembaruan Profil
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
