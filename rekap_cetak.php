<?php
// rekap_cetak.php - Cetak Dokumen Laporan Rekapitulasi Pembukuan Kas Pembayaran Bulanan
// Mitra: Kos Putri Anak Lanang (Ibu Yeni, Pasir Pengaraian, Riau)
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$bulanPilih = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahunPilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$namaBulanPilih = namaBulanIndo($bulanPilih);
$labelPeriode = "$namaBulanPilih $tahunPilih";
$patternBulan = sprintf('%04d-%02d', $tahunPilih, $bulanPilih);

// Info Kos
$stmtKos = $pdo->query("SELECT * FROM kos LIMIT 1");
$kos = $stmtKos->fetch();

// Ambil Transaksi Bulan Terpilih
$stmtTrx = $pdo->prepare("
    SELECT p.*, k.nomor_kamar, k.tipe, k.harga as harga_kamar
    FROM pembayaran p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    WHERE (p.tanggal_bayar LIKE ? OR p.periode_bulan LIKE ?)
    ORDER BY p.tanggal_bayar ASC, p.id_pembayaran ASC
");
$stmtTrx->execute(["$patternBulan%", "%$labelPeriode%"]);
$listTrx = $stmtTrx->fetchAll();

// Hitung Ringkasan
$totalKasLunas = 0;
$totalTunai = 0;
$totalTransfer = 0;
$totalTransaksi = 0;

foreach ($listTrx as $t) {
    if ($t['status_pembayaran'] === 'lunas') {
        $totalKasLunas += $t['nominal'];
        $totalTransaksi++;
        if ($t['metode_pembayaran'] === 'tunai_cash') {
            $totalTunai += $t['nominal'];
        } else {
            $totalTransfer += $t['nominal'];
        }
    }
}

// Cek Tunggakan Kamar Aktif
$stmtKamar = $pdo->query("
    SELECT k.*, u.nama as nama_penghuni
    FROM kamar k
    LEFT JOIN pengguna u ON u.id_kamar_huni = k.id_kamar
    WHERE k.status = 'terisi' OR u.id_pengguna IS NOT NULL
    ORDER BY k.nomor_kamar ASC
");
$kamarAktif = $stmtKamar->fetchAll();
$tunggakanList = [];
$totalTunggakan = 0;

foreach ($kamarAktif as $ka) {
    $stmtCek = $pdo->prepare("
        SELECT status_pembayaran FROM pembayaran 
        WHERE id_kamar = ? AND kategori_pembayaran = 'sewa_bulanan' 
          AND (periode_bulan LIKE ? OR tanggal_bayar LIKE ?)
        ORDER BY id_pembayaran DESC LIMIT 1
    ");
    $stmtCek->execute([$ka['id_kamar'], "%$labelPeriode%", "$patternBulan%"]);
    $st = $stmtCek->fetchColumn();

    if ($st !== 'lunas') {
        $tunggakanList[] = $ka;
        $totalTunggakan += $ka['harga'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembukuan Kas Bulanan - <?= $labelPeriode ?> - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #FFF; font-size: 13px; }
        .kop-surat { border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 20px; }
        .kop-logo { font-size: 26px; font-weight: bold; letter-spacing: 1px; }
        .table-laporan th { background-color: #F1F5F9 !important; border: 1px solid #000 !important; font-size: 11px; text-transform: uppercase; padding: 6px; }
        .table-laporan td { border: 1px solid #000 !important; font-size: 12px; padding: 6px; vertical-align: middle; }
        .box-summary { border: 1px solid #000; padding: 12px; border-radius: 6px; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="p-4">

<!-- Tombol Cetak (Tidak ikut tercetak) -->
<div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded border" style="font-family: sans-serif;">
    <div>
        <strong><i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>Format Dokumen Resmi Siap Cetak / Simpan PDF</strong>
        <div class="small text-muted">Periode: <?= $labelPeriode ?> • Mitra: Kos Putri Anak Lanang (Ibu Yeni)</div>
    </div>
    <div class="d-flex gap-2">
        <a href="admin_keuangan.php?bulan=<?= $bulanPilih ?>&tahun=<?= $tahunPilih ?>" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dasbor
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold">
            <i class="bi bi-printer-fill me-1"></i>Cetak Laporan / PDF
        </button>
    </div>
</div>

<div class="container-fluid px-0">

    <!-- Kop Surat Resmi Kos Putri Anak Lanang -->
    <div class="kop-surat text-center position-relative">
        <div class="kop-logo">KOS PUTRI ANAK LANANG</div>
        <div style="font-size: 14px; font-weight: bold;">MITRA RESMI E-COMMERCE KOSINAJA</div>
        <div style="font-size: 12px;"><?= htmlspecialchars($kos['alamat'] ?? 'Jl. Lingkar Pasir Pengaraian, Kec. Rambah, Kab. Rokan Hulu, Riau') ?></div>
        <div style="font-size: 11px;">
            Nomor Induk Berusaha (NIB): <strong><?= htmlspecialchars($kos['nib'] ?? '5102240091823') ?></strong> 
            • WhatsApp Pengelola: <strong><?= htmlspecialchars($kos['no_wa_kontak'] ?? '0812-7599-8811') ?></strong>
        </div>
    </div>

    <!-- Judul Dokumen -->
    <div class="text-center mb-4">
        <h5 class="fw-bold mb-1 text-uppercase" style="text-decoration: underline; letter-spacing: 1px;">
            LAPORAN REKAPITULASI PEMBUKUAN PEMBAYARAN KOS (BUKU KAS)
        </h5>
        <div>PERIODE BULAN: <strong><?= strtoupper($labelPeriode) ?></strong></div>
        <div class="text-muted" style="font-size: 11px;">Studi Kasus Pencatatan Manual &amp; Pembukuan Digital Terpadu</div>
    </div>

    <!-- Ringkasan Eksekutif Finansial -->
    <div class="box-summary mb-4">
        <div class="row text-center">
            <div class="col-3 border-end">
                <div class="small fw-bold text-uppercase">Total Kas Masuk (Lunas)</div>
                <div class="fs-5 fw-bold text-success font-monospace"><?= formatRupiah($totalKasLunas) ?></div>
            </div>
            <div class="col-3 border-end">
                <div class="small fw-bold text-uppercase">Penerimaan Tunai (Cash)</div>
                <div class="fs-6 fw-bold font-monospace"><?= formatRupiah($totalTunai) ?></div>
            </div>
            <div class="col-3 border-end">
                <div class="small fw-bold text-uppercase">Penerimaan Transfer Bank</div>
                <div class="fs-6 fw-bold font-monospace"><?= formatRupiah($totalTransfer) ?></div>
            </div>
            <div class="col-3">
                <div class="small fw-bold text-uppercase">Tunggakan Belum Bayar</div>
                <div class="fs-6 fw-bold text-danger font-monospace"><?= formatRupiah($totalTunggakan) ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekapitulasi Rinci Pembayaran -->
    <div class="mb-4">
        <div class="fw-bold mb-2">A. DAFTAR MUTASI PEMBAYARAN MASUK (KAS MASUK):</div>
        <table class="table table-laporan w-100">
            <thead>
                <tr class="text-center align-middle">
                    <th style="width: 40px;">No</th>
                    <th style="width: 120px;">No Kuitansi / TRX</th>
                    <th style="width: 85px;">Tanggal</th>
                    <th>Nama Pembayar / Penghuni</th>
                    <th>Unit Kamar</th>
                    <th>Kategori Pembayaran</th>
                    <th>Metode</th>
                    <th style="width: 110px;">Nominal (Rp)</th>
                    <th>Status</th>
                    <th>Keterangan / Buku Manual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listTrx)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-3 text-muted">Tidak ada transaksi pembayaran pada periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($listTrx as $t): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="font-monospace text-center"><?= htmlspecialchars(str_replace('TRX', 'KWT', $t['kode_transaksi'])) ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($t['tanggal_bayar'])) ?></td>
                            <td><strong><?= htmlspecialchars($t['nama_pengirim']) ?></strong></td>
                            <td class="text-center"><?= htmlspecialchars($t['nomor_kamar']) ?> (<?= ucfirst($t['tipe']) ?>)</td>
                            <td><?= labelKategoriPembayaran($t['kategori_pembayaran']) ?></td>
                            <td class="text-center">
                                <?= $t['metode_pembayaran'] === 'tunai_cash' ? 'Tunai (Cash)' : 'Transfer Bank' ?>
                            </td>
                            <td class="text-end fw-bold font-monospace"><?= formatRupiah($t['nominal']) ?></td>
                            <td class="text-center"><?= strtoupper($t['status_pembayaran']) ?></td>
                            <td style="font-size: 11px;"><?= htmlspecialchars($t['catatan_pemilik'] ?: ($t['catatan_pembayar'] ?: '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold" style="background-color: #F8FAFC;">
                    <td colspan="7" class="text-end">TOTAL KAS MASUK TERVERIFIKASI (LUNAS):</td>
                    <td class="text-end font-monospace text-success"><?= formatRupiah($totalKasLunas) ?></td>
                    <td colspan="2" class="text-center">(<?= $totalTransaksi ?> Transaksi Lunas)</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Tabel Monitoring Tunggakan -->
    <?php if (!empty($tunggakanList)): ?>
        <div class="mb-4">
            <div class="fw-bold mb-2 text-danger">B. DAFTAR PENGHUNI BELUM MEMBAYAR SEWA (TUNGGAKAN JATUH TEMPO):</div>
            <table class="table table-laporan w-100">
                <thead>
                    <tr class="text-center">
                        <th style="width: 40px;">No</th>
                        <th>Nomor Kamar</th>
                        <th>Nama Penghuni Terdata</th>
                        <th>Tarif Bulanan</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $noT = 1; foreach ($tunggakanList as $tk): ?>
                        <tr>
                            <td class="text-center"><?= $noT++ ?></td>
                            <td class="text-center fw-bold"><?= htmlspecialchars($tk['nomor_kamar']) ?> (<?= ucfirst($tk['tipe']) ?>)</td>
                            <td><?= htmlspecialchars($tk['nama_penghuni'] ?: 'Penghuni ' . $tk['nomor_kamar']) ?></td>
                            <td class="text-end font-monospace"><?= formatRupiah($tk['harga']) ?></td>
                            <td class="text-center">Tanggal 10 <?= $namaBulanPilih ?> <?= $tahunPilih ?></td>
                            <td class="text-center text-danger fw-bold">BELUM BAYAR</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">TOTAL TUNGGAKAN BULAN INI:</td>
                        <td class="text-end font-monospace text-danger"><?= formatRupiah($totalTunggakan) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <!-- Bagian Tanda Tangan & Pengesahan -->
    <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
        <div style="font-size: 11px; max-width: 350px;">
            <em>Catatan:</em> Laporan ini dicetak secara otomatis dari Sistem E-Commerce KosinAja berdasarkan data pembukuan pencatatan manual dan transaksi digital yang sah.
        </div>
        <div class="text-center" style="width: 260px;">
            <div>Pasir Pengaraian, <?= date('d F Y') ?></div>
            <div class="fw-bold">Pemilik / Pengelola Kos,</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold" style="text-decoration: underline;">IBU YENI</div>
            <div style="font-size: 11px;">Mitra Kos Putri Anak Lanang</div>
        </div>
    </div>

</div>

</body>
</html>
