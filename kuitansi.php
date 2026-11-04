<?php
// kuitansi.php - Kuitansi Resmi Tanda Terima Pembayaran Digital (Print-Ready)
// Mitra: Kos Putri Anak Lanang (Ibu Yeni, Pasir Pengaraian, Riau)
require_once __DIR__ . '/config.php';

$idPembayaran = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, k.nomor_kamar, k.tipe as tipe_kamar, k.harga as harga_kamar,
           ks.nama_kos, ks.alamat as alamat_kos, ks.no_wa_kontak, ks.nib,
           u.nama as nama_akun, u.no_wa as no_wa_akun
    FROM pembayaran p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    JOIN kos ks ON k.id_kos = ks.id_kos
    LEFT JOIN pengguna u ON p.id_pengguna = u.id_pengguna
    WHERE p.id_pembayaran = ?
    LIMIT 1
");
$stmt->execute([$idPembayaran]);
$data = $stmt->fetch();

if (!$data) {
    die("Kuitansi tidak ditemukan atau pembayaran belum terdaftar.");
}

$nomorKuitansi = str_replace('TRX', 'KWT', $data['kode_transaksi']);
$namaPembayar = !empty($data['nama_pengirim']) ? $data['nama_pengirim'] : ($data['nama_akun'] ?? 'Penghuni Kos');
$terbilangText = terbilangRupiah($data['nominal']);

$keteranganUntuk = labelKategoriPembayaran($data['kategori_pembayaran']) . ' untuk ' . $data['nomor_kamar'] . ' (' . ucfirst($data['tipe_kamar']) . ')';
if (!empty($data['periode_bulan'])) {
    $keteranganUntuk .= ' - Periode ' . $data['periode_bulan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi Pembayaran #<?= htmlspecialchars($nomorKuitansi) ?> - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #F1F5F9; color: #0F172A; }
        .kuitansi-wrapper { max-width: 820px; margin: 40px auto; background: #FFF; border: 2px solid #0F172A; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 40px; position: relative; }
        .kuitansi-header { border-bottom: 2px solid #0F172A; padding-bottom: 20px; margin-bottom: 24px; }
        .kuitansi-title { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700; letter-spacing: 2px; }
        .terbilang-box { background: #F8FAFC; border: 1px dashed #94A3B8; border-radius: 10px; padding: 12px 16px; font-style: italic; color: #1E293B; font-weight: 600; }
        .stempel-lunas { position: absolute; right: 220px; bottom: 85px; transform: rotate(-12deg); border: 3px solid #16A34A; color: #16A34A; padding: 6px 16px; font-size: 18px; font-weight: 800; letter-spacing: 3px; border-radius: 8px; text-transform: uppercase; background: rgba(22, 163, 74, 0.05); }
        .stempel-menunggu { position: absolute; right: 200px; bottom: 85px; transform: rotate(-12deg); border: 3px solid #D97706; color: #D97706; padding: 6px 16px; font-size: 16px; font-weight: 800; letter-spacing: 2px; border-radius: 8px; text-transform: uppercase; background: rgba(217, 119, 6, 0.05); }
        .ttd-box { width: 220px; text-align: center; }
        .ttd-line { border-bottom: 1px solid #0F172A; margin-top: 60px; margin-bottom: 6px; }
        
        @media print {
            body { background: #FFF !important; }
            .no-print { display: none !important; }
            .kuitansi-wrapper { border: 2px solid #000; box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; padding: 30px; }
        }
    </style>
</head>
<body>

<!-- Bar Tombol Aksi di Atas (Disembunyikan saat cetak) -->
<div class="container no-print mt-4 mb-2" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center">
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-1"></i>Cetak Kuitansi / Simpan PDF
            </button>
            <?php if (isMitraOrDeveloper()): ?>
                <a href="admin_keuangan.php" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                    <i class="bi bi-book me-1"></i>Rekap Keuangan
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="kuitansi-wrapper">
    <!-- Header Kos Putri Anak Lanang -->
    <div class="kuitansi-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="letter-spacing: 0.5px;">
                <i class="bi bi-houses-fill text-success me-2"></i><?= htmlspecialchars($data['nama_kos']) ?>
            </h4>
            <div class="text-secondary small mb-1">
                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($data['alamat_kos']) ?>
            </div>
            <div class="text-muted" style="font-size: 11px;">
                Kontak Pengelola: <strong><?= htmlspecialchars($data['no_wa_kontak']) ?></strong> (Ibu Yeni) 
                • NIB: <?= htmlspecialchars($data['nib'] ?: '5102240091823') ?>
            </div>
        </div>
        <div class="text-end mt-2 mt-sm-0">
            <div class="kuitansi-title text-success">KUITANSI</div>
            <div class="fw-bold text-dark font-monospace">NO: <?= htmlspecialchars($nomorKuitansi) ?></div>
            <div class="text-muted small">Tgl: <?= date('d/m/Y', strtotime($data['tanggal_bayar'])) ?></div>
        </div>
    </div>

    <!-- Badan Kuitansi -->
    <div class="py-2">
        <div class="row mb-3 align-items-center">
            <div class="col-sm-3 text-muted fw-bold small text-uppercase">Telah Diterima Dari</div>
            <div class="col-sm-9">
                <div class="fw-bold text-dark fs-5 border-bottom pb-1"><?= htmlspecialchars($namaPembayar) ?></div>
            </div>
        </div>

        <div class="row mb-3 align-items-start">
            <div class="col-sm-3 text-muted fw-bold small text-uppercase">Uang Sejumlah</div>
            <div class="col-sm-9">
                <div class="terbilang-box">
                    " <?= htmlspecialchars($terbilangText) ?> "
                </div>
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-sm-3 text-muted fw-bold small text-uppercase">Untuk Pembayaran</div>
            <div class="col-sm-9">
                <div class="fw-semibold text-dark border-bottom pb-1"><?= htmlspecialchars($keteranganUntuk) ?></div>
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-sm-3 text-muted fw-bold small text-uppercase">Metode Bayar</div>
            <div class="col-sm-9">
                <div class="text-secondary border-bottom pb-1">
                    <?= htmlspecialchars(labelMetodePembayaran($data['metode_pembayaran'])) ?>
                    <?php if (!empty($data['catatan_pemilik'])): ?>
                        <div class="text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>Catatan: <?= htmlspecialchars($data['catatan_pemilik']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cap Stempel -->
    <?php if ($data['status_pembayaran'] === 'lunas'): ?>
        <div class="stempel-lunas">
            <i class="bi bi-check2-all me-1"></i>L U N A S
            <div style="font-size: 9px; letter-spacing: 1px; font-weight: normal; color: #16A34A; text-align: center;">
                VERIFIKASI MANUAL IBU YENI
            </div>
        </div>
    <?php else: ?>
        <div class="stempel-menunggu">
            <i class="bi bi-clock-history me-1"></i>MENUNGGU VERIFIKASI
        </div>
    <?php endif; ?>

    <!-- Bagian Nominal & Tanda Tangan -->
    <div class="d-flex justify-content-between align-items-end mt-4 pt-3 border-top">
        <div class="p-3 bg-light border border-dark rounded-3" style="min-width: 260px;">
            <div class="text-muted small fw-bold text-uppercase">Jumlah Pembayaran:</div>
            <div class="fs-3 fw-bold text-success font-monospace"><?= formatRupiah($data['nominal']) ?></div>
            <div class="text-muted" style="font-size: 10px;">Status: <?= strtoupper($data['status_pembayaran']) ?></div>
        </div>

        <div class="ttd-box">
            <div class="small text-muted mb-1">Pasir Pengaraian, <?= date('d F Y', strtotime($data['tanggal_bayar'])) ?></div>
            <div class="small fw-bold text-dark">Penerima Pembayaran,</div>
            <div class="ttd-line"></div>
            <div class="fw-bold text-dark"><?= htmlspecialchars($data['diverifikasi_oleh'] ?: 'Ibu Yeni (Pemilik Kos)') ?></div>
            <div class="text-muted" style="font-size: 11px;">Pengelola Kos Putri Anak Lanang</div>
        </div>
    </div>
</div>

<div class="container text-center no-print pb-5 text-muted small">
    <i class="bi bi-shield-lock-fill text-success me-1"></i>Kuitansi sah yang diterbitkan melalui Sistem E-Commerce KosinAja.
</div>

</body>
</html>
