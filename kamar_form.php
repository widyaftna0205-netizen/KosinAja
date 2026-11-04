<?php
// kamar_form.php - UC-09 Form Tambah & Ubah Kamar Modern
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$idKamar = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = !empty($idKamar);

$error = '';
$kamar = [
    'nomor_kamar' => '',
    'tipe' => 'berperabot',
    'ukuran' => '3x4 m',
    'harga' => '',
    'status' => 'kosong'
];
$selectedFasilitas = [];
$biayaList = [];
$mediaList = [];

$allFasilitas = $pdo->query("SELECT * FROM fasilitas ORDER BY id_fasilitas ASC")->fetchAll();

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM kamar WHERE id_kamar = ?");
    $stmt->execute([$idKamar]);
    $kamar = $stmt->fetch();
    if (!$kamar) {
        header("Location: kamar.php");
        exit;
    }

    $stmtF = $pdo->prepare("SELECT id_fasilitas FROM kamar_fasilitas WHERE id_kamar = ?");
    $stmtF->execute([$idKamar]);
    $selectedFasilitas = $stmtF->fetchAll(PDO::FETCH_COLUMN);

    $stmtB = $pdo->prepare("SELECT * FROM biaya_kamar WHERE id_kamar = ?");
    $stmtB->execute([$idKamar]);
    $biayaList = $stmtB->fetchAll();

    $stmtM = $pdo->prepare("SELECT * FROM media_kamar WHERE id_kamar = ?");
    $stmtM->execute([$idKamar]);
    $mediaList = $stmtM->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_kamar = trim($_POST['nomor_kamar'] ?? '');
    $tipe = $_POST['tipe'] ?? 'berperabot';
    $ukuran = trim($_POST['ukuran'] ?? '3x4 m');
    $harga = trim($_POST['harga'] ?? '');
    $status = $_POST['status'] ?? 'kosong';
    $fasilitas_ids = $_POST['fasilitas'] ?? [];
    $foto_urls = $_POST['foto_urls'] ?? [];
    $biaya_nama = $_POST['biaya_nama'] ?? [];
    $biaya_nominal = $_POST['biaya_nominal'] ?? [];

    if (empty($nomor_kamar) || empty($harga)) {
        $error = 'Nomor kamar dan harga sewa wajib diisi!';
    } elseif (!is_numeric($harga) || $harga <= 0) {
        $error = 'Harga sewa harus berupa angka positif!';
    } else {
        $pdo->beginTransaction();
        try {
            if ($isEdit) {
                $stmtUp = $pdo->prepare("
                    UPDATE kamar 
                    SET nomor_kamar = ?, tipe = ?, ukuran = ?, harga = ?, status = ?, tanggal_pembaruan = CURRENT_TIMESTAMP
                    WHERE id_kamar = ?
                ");
                $stmtUp->execute([$nomor_kamar, $tipe, $ukuran, $harga, $status, $idKamar]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO kamar (id_kos, nomor_kamar, tipe, ukuran, harga, status, tanggal_pembaruan)
                    VALUES (1, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtIns->execute([$nomor_kamar, $tipe, $ukuran, $harga, $status]);
                $idKamar = $pdo->lastInsertId();
            }

            $pdo->prepare("DELETE FROM kamar_fasilitas WHERE id_kamar = ?")->execute([$idKamar]);
            if (!empty($fasilitas_ids)) {
                $stmtKF = $pdo->prepare("INSERT INTO kamar_fasilitas (id_kamar, id_fasilitas) VALUES (?, ?)");
                foreach ($fasilitas_ids as $fId) {
                    $stmtKF->execute([$idKamar, (int)$fId]);
                }
            }

            $pdo->prepare("DELETE FROM biaya_kamar WHERE id_kamar = ?")->execute([$idKamar]);
            if (!empty($biaya_nama)) {
                $stmtInsB = $pdo->prepare("INSERT INTO biaya_kamar (id_kamar, nama_biaya, nominal) VALUES (?, ?, ?)");
                for ($i = 0; $i < count($biaya_nama); $i++) {
                    if (!empty(trim($biaya_nama[$i])) && is_numeric($biaya_nominal[$i])) {
                        $stmtInsB->execute([$idKamar, trim($biaya_nama[$i]), (float)$biaya_nominal[$i]]);
                    }
                }
            }

            $pdo->prepare("DELETE FROM media_kamar WHERE id_kamar = ?")->execute([$idKamar]);
            $stmtInsM = $pdo->prepare("INSERT INTO media_kamar (id_kamar, jenis, url_file, is_utama) VALUES (?, 'foto', ?, ?)");
            $isFirst = true;
            foreach ($foto_urls as $url) {
                $cleanUrl = trim($url);
                if (!empty($cleanUrl)) {
                    $stmtInsM->execute([$idKamar, $cleanUrl, $isFirst ? 1 : 0]);
                    $isFirst = false;
                }
            }

            if ($isFirst) {
                $stmtInsM->execute([$idKamar, 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&q=80', 1]);
            }

            $pdo->commit();
            header("Location: kamar.php?sukses=" . ($isEdit ? 'ubah' : 'tambah'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Ubah' : 'Tambah' ?> Kamar - Panel KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F8FAFC; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0F172A; }
        .navbar-custom { background: #FFFFFF; border-bottom: 1px solid #E2E8F0; }
        .card-custom { border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #FFFFFF; }
        .section-header { font-size: 14px; font-weight: 800; color: #1E293B; letter-spacing: -0.2px; margin-bottom: 12px; }
        .btn-status-radio input[type="radio"] { display: none; }
        .btn-status-radio label {
            cursor: pointer;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            border: 1.5px solid #E2E8F0;
            background: #F8FAFC;
            transition: all 0.2s ease;
            width: 100%;
            text-align: center;
        }
        .btn-status-radio input[value="kosong"]:checked + label {
            background: #ECFDF5; border-color: #10B981; color: #065F46;
        }
        .btn-status-radio input[value="dipesan"]:checked + label {
            background: #FFFBEB; border-color: #F59E0B; color: #92400E;
        }
        .btn-status-radio input[value="terisi"]:checked + label {
            background: #F1F5F9; border-color: #94A3B8; color: #475569;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%);
            border: none;
            border-radius: 14px;
            color: #FFFFFF;
            font-weight: 700;
            padding: 12px 24px;
            box-shadow: 0 8px 18px -4px rgba(79, 70, 229, 0.4);
            transition: all 0.2s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 22px -4px rgba(79, 70, 229, 0.5);
            color: #FFFFFF;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 10px 14px;
            border: 1.5px solid #E2E8F0;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-custom sticky-top py-3">
    <div class="container px-4">
        <a class="navbar-brand fw-bold text-dark d-flex align-items-center gap-2" href="kamar.php">
            <span class="btn btn-sm btn-light border rounded-pill px-2 py-1"><i class="bi bi-arrow-left"></i></span>
            <span>Panel Kelola Kamar</span>
        </a>
        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill small fw-bold">
            Kos Putri Anak Lanang (Ibu Yeni)
        </span>
    </div>
</nav>

<div class="container py-4" style="max-width: 820px;">
    <div class="card card-custom p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <?= $isEdit ? 'Ubah Data Unit: ' . htmlspecialchars($kamar['nomor_kamar']) : 'Tambah Unit Kamar Baru' ?>
                </h4>
                <p class="text-muted small mb-0">Disesuaikan dengan standar e-commerce Bab 6 &amp; hasil evaluasi usability.</p>
            </div>
            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">Target NF-04: &lt;10s</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- 1. Informasi Kamar -->
            <div class="section-header"><i class="bi bi-1-circle-fill text-primary me-2"></i>Informasi Unit Kamar</div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Nomor / Nama Kamar *</label>
                    <input type="text" name="nomor_kamar" class="form-control" placeholder="Misal: Kamar 04" 
                           value="<?= htmlspecialchars($kamar['nomor_kamar']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Tipe Fasilitas</label>
                    <select name="tipe" class="form-select">
                        <option value="berperabot" <?= $kamar['tipe'] === 'berperabot' ? 'selected' : '' ?>>Berperabot (Furnished)</option>
                        <option value="kosongan" <?= $kamar['tipe'] === 'kosongan' ? 'selected' : '' ?>>Kosongan (Non-Furnished)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Ukuran Ruangan</label>
                    <input type="text" name="ukuran" class="form-control" placeholder="3x4 m" 
                           value="<?= htmlspecialchars($kamar['ukuran']) ?>">
                </div>
            </div>

            <!-- 2. Status Ketersediaan (Pill Buttons) -->
            <div class="section-header"><i class="bi bi-2-circle-fill text-primary me-2"></i>Status Ketersediaan Unit (1-Click Sentuh)</div>
            <div class="row g-2 mb-4">
                <div class="col-4 btn-status-radio">
                    <input type="radio" name="status" id="st_kosong" value="kosong" <?= $kamar['status'] === 'kosong' ? 'checked' : '' ?>>
                    <label for="st_kosong"><i class="bi bi-check-circle-fill me-1"></i>Kosong (Siap Huni)</label>
                </div>
                <div class="col-4 btn-status-radio">
                    <input type="radio" name="status" id="st_dipesan" value="dipesan" <?= $kamar['status'] === 'dipesan' ? 'checked' : '' ?>>
                    <label for="st_dipesan"><i class="bi bi-clock-fill me-1"></i>Dipesan (Booking)</label>
                </div>
                <div class="col-4 btn-status-radio">
                    <input type="radio" name="status" id="st_terisi" value="terisi" <?= $kamar['status'] === 'terisi' ? 'checked' : '' ?>>
                    <label for="st_terisi"><i class="bi bi-person-fill me-1"></i>Terisi (Penuh)</label>
                </div>
            </div>

            <!-- 3. Tarif Sewa & Deposit -->
            <div class="section-header"><i class="bi bi-3-circle-fill text-primary me-2"></i>Tarif Sewa &amp; Jaminan (Transparan)</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Tarif Sewa Bulanan (Rp) *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">Rp</span>
                        <input type="number" name="harga" class="form-control" placeholder="850000" 
                               value="<?= htmlspecialchars($kamar['harga']) ?>" required>
                    </div>
                    <div class="form-text small text-muted">Bebas ongkir logistik kurir (produk sewa jasa).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Uang Deposit Kunci (Dikembalikan Saat Keluar)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">Rp</span>
                        <input type="number" name="biaya_nominal[]" class="form-control" 
                               value="<?= htmlspecialchars($biayaList[0]['nominal'] ?? '200000') ?>">
                    </div>
                    <input type="hidden" name="biaya_nama[]" value="Uang Jaminan/Deposit Kunci">
                    <div class="form-text small text-success"><i class="bi bi-shield-check me-1"></i>Transparansi biaya (Slide 7 Bab 6)</div>
                </div>
            </div>

            <!-- 4. Checklist Fasilitas -->
            <div class="section-header"><i class="bi bi-4-circle-fill text-primary me-2"></i>Fasilitas Kamar</div>
            <div class="row g-2 mb-4 p-3 bg-light rounded-3 border">
                <?php foreach ($allFasilitas as $fas): ?>
                    <div class="col-md-4 col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fasilitas[]" 
                                   value="<?= $fas['id_fasilitas'] ?>" id="fas_<?= $fas['id_fasilitas'] ?>"
                                   <?= in_array($fas['id_fasilitas'], $selectedFasilitas) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="fas_<?= $fas['id_fasilitas'] ?>">
                                <i class="bi <?= $fas['icon'] ?> text-primary me-1"></i> <?= htmlspecialchars($fas['nama_fasilitas']) ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 5. Foto Kamar (Min 3 Foto) -->
            <div class="section-header"><i class="bi bi-5-circle-fill text-primary me-2"></i>Galeri Foto (Min. 3 Foto Sesuai Slide 8 Bab 5)</div>
            <div class="row g-2 mb-4">
                <?php 
                $foto1 = $mediaList[0]['url_file'] ?? 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&q=80';
                $foto2 = $mediaList[1]['url_file'] ?? 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80';
                $foto3 = $mediaList[2]['url_file'] ?? 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&q=80';
                ?>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Foto 1 (Sampul Utama)</label>
                    <input type="url" name="foto_urls[]" class="form-control form-control-sm" value="<?= htmlspecialchars($foto1) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Foto 2 (Kamar Mandi / Detail)</label>
                    <input type="url" name="foto_urls[]" class="form-control form-control-sm" value="<?= htmlspecialchars($foto2) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Foto 3 (Ventilasi / Sudut Lain)</label>
                    <input type="url" name="foto_urls[]" class="form-control form-control-sm" value="<?= htmlspecialchars($foto3) ?>">
                </div>
                <div class="col-12">
                    <div class="text-muted small mt-1">
                        <i class="bi bi-info-circle me-1"></i>Panduan kompresi: Format JPEG/WebP ukuran &lt; 500 KB per gambar.
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 border-top pt-4">
                <a href="kamar.php" class="btn btn-light px-4 rounded-3 border fw-semibold">Batal</a>
                <button type="submit" class="btn btn-primary-custom px-4">
                    <i class="bi bi-cloud-upload-fill me-1"></i> Simpan &amp; Publikasikan Kamar
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
