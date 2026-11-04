<?php
// developer_panel.php - Panel Super Admin Developer (Akses Penuh Semua Fitur)
// Hanya bisa diakses oleh role 'developer'
require_once __DIR__ . '/config.php';
checkAuth(['developer']); // HANYA developer, mitra/penyewa ditolak

$user = $_SESSION['user'];

// Statistik Global
$totalKamar      = $pdo->query("SELECT COUNT(*) FROM kamar")->fetchColumn();
$kamarKosong     = $pdo->query("SELECT COUNT(*) FROM kamar WHERE status = 'kosong'")->fetchColumn();
$kamarDipesan    = $pdo->query("SELECT COUNT(*) FROM kamar WHERE status = 'dipesan'")->fetchColumn();
$kamarTerisi     = $pdo->query("SELECT COUNT(*) FROM kamar WHERE status = 'terisi'")->fetchColumn();
$totalPengguna   = $pdo->query("SELECT COUNT(*) FROM pengguna")->fetchColumn();
$totalPemesanan  = $pdo->query("SELECT COUNT(*) FROM pemesanan")->fetchColumn();
$pendingPemesanan= $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_pemesanan = 'menunggu'")->fetchColumn();
$totalRevenue    = $pdo->query("SELECT COALESCE(SUM(k.harga),0) FROM pemesanan p JOIN kamar k ON p.id_kamar=k.id_kamar WHERE p.status_pemesanan IN ('disetujui','selesai')")->fetchColumn();
$totalKasLunasReal = $pdo->query("SELECT COALESCE(SUM(nominal),0) FROM pembayaran WHERE status_pembayaran = 'lunas'")->fetchColumn();
$pendingBayarTrx = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_pembayaran = 'menunggu'")->fetchColumn();

// Transaksi Pembayaran Terkini
$recentPayments = $pdo->query("
    SELECT p.*, k.nomor_kamar
    FROM pembayaran p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    ORDER BY p.id_pembayaran DESC LIMIT 6
")->fetchAll();

// Daftar Semua Pengguna
$allUsers = $pdo->query("SELECT * FROM pengguna ORDER BY role ASC, id_pengguna ASC")->fetchAll();

// Semua Pemesanan Terkini (10 terakhir)
$recentOrders = $pdo->query("
    SELECT p.*, k.nomor_kamar, k.harga
    FROM pemesanan p
    JOIN kamar k ON p.id_kamar = k.id_kamar
    ORDER BY p.id_pemesanan DESC LIMIT 10
")->fetchAll();

// Notifikasi login
$notifLogin = isset($_GET['login']) && $_GET['login'] === 'sukses';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Panel - KosinAja Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #0F172A; color: #E2E8F0; min-height: 100vh; }
        .dev-navbar { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border-bottom: 1px solid #334155; }
        .dev-badge { background: linear-gradient(135deg, #DC2626, #B91C1C); font-size: 10px; letter-spacing: 1px; padding: 3px 8px; border-radius: 4px; }
        .stat-card { background: #1E293B; border: 1px solid #334155; border-radius: 16px; padding: 20px; transition: border-color .2s; }
        .stat-card:hover { border-color: #6366F1; }
        .stat-card .stat-num { font-size: 2rem; font-weight: 800; }
        .panel-card { background: #1E293B; border: 1px solid #334155; border-radius: 16px; }
        .panel-card .card-header { background: #0F172A; border-bottom: 1px solid #334155; border-radius: 16px 16px 0 0; padding: 16px 20px; }
        .access-btn { border-radius: 12px; padding: 14px 20px; border: 1px solid #334155; background: #0F172A; color: #CBD5E1; text-decoration: none; display: flex; align-items: center; gap: 12px; transition: all .2s; }
        .access-btn:hover { background: #1E293B; border-color: #6366F1; color: #A5B4FC; transform: translateX(4px); }
        .access-btn .icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .role-badge-dev { background: #DC2626; color: #fff; }
        .role-badge-mitra { background: #D97706; color: #fff; }
        .role-badge-penyewa { background: #059669; color: #fff; }
        .table-dark-custom { background: transparent; }
        .table-dark-custom th { background: #0F172A; color: #94A3B8; font-size: 11px; letter-spacing: .5px; border-color: #334155 !important; }
        .table-dark-custom td { background: transparent; border-color: #1E293B !important; color: #CBD5E1; font-size: 13px; vertical-align: middle; }
        .table-dark-custom tr:hover td { background: #0F172A; }
        .user-row-dev td { border-left: 3px solid #DC2626 !important; }
        .user-row-mitra td:first-child { border-left: 3px solid #D97706 !important; }
        .user-row-penyewa td:first-child { border-left: 3px solid #059669 !important; }
        .dev-info-box { background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.3); border-radius: 12px; padding: 16px; }
    </style>
</head>
<body>

<!-- Navbar Developer -->
<nav class="dev-navbar sticky-top py-2 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="fs-5 fw-bold text-white"><i class="bi bi-code-slash me-1"></i>KosinAja</span>
            <span class="dev-badge fw-bold text-white">DEVELOPER PANEL</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="text-secondary small"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($user['nama']) ?></span>
        <!-- Akses Panel Lain (Simulasi) -->
        <a href="kamar.php" class="btn btn-sm btn-outline-warning rounded-pill px-3">
            <i class="bi bi-tools me-1"></i>Panel Mitra
        </a>
        <a href="penyewa_dashboard.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
            <i class="bi bi-person-check me-1"></i>Panel Penyewa
        </a>
        <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-box-arrow-up-right me-1"></i>Katalog
        </a>
        <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3" onclick="return confirm('Keluar sesi developer?')">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <?php if ($notifLogin): ?>
    <div class="alert d-flex align-items-center gap-2 mb-4" style="background:#052e16;border:1px solid #16a34a;border-radius:12px;color:#bbf7d0;">
        <i class="bi bi-shield-check-fill text-success fs-5"></i>
        <span><strong>Login Developer Berhasil!</strong> Anda memiliki akses penuh ke seluruh sistem KosinAja.</span>
    </div>
    <?php endif; ?>

    <!-- Dev Info Box -->
    <div class="dev-info-box mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-info-circle-fill text-indigo-400 fs-4" style="color:#818CF8"></i>
            <div>
                <div class="fw-bold text-white mb-1">Hak Akses Developer (Super Admin)</div>
                <div class="text-secondary small">
                    Role <code style="color:#A5B4FC">developer</code> memiliki akses penuh ke seluruh panel: 
                    <strong class="text-warning">Panel Mitra</strong> (kelola kamar, pemesanan, profil kos),
                    <strong class="text-success">Panel Penyewa</strong> (dasbor penghuni, riwayat booking), dan
                    <strong class="text-info">Katalog Publik</strong>. Gunakan tombol di navbar atas untuk berpindah panel.
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Ringkasan -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-num text-primary"><?= $totalKamar ?></div>
                <div class="text-secondary small">Total Kamar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-num text-success"><?= $kamarKosong ?></div>
                <div class="text-secondary small">Kamar Kosong</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-num text-warning"><?= $pendingPemesanan ?></div>
                <div class="text-secondary small">Menunggu Approval</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-num text-purple" style="color:#A78BFA;"><?= $totalPengguna ?></div>
                <div class="text-secondary small">Total Pengguna</div>
            </div>
        </div>
    </div>

    <!-- Statistik Kamar Detail -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-secondary small mb-1">Kamar Dipesan</div>
                    <div class="stat-num text-warning"><?= $kamarDipesan ?></div>
                </div>
                <i class="bi bi-clock-fill text-warning opacity-25" style="font-size:2.5rem;"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-secondary small mb-1">Kamar Terisi</div>
                    <div class="stat-num text-secondary"><?= $kamarTerisi ?></div>
                </div>
                <i class="bi bi-person-fill text-secondary opacity-25" style="font-size:2.5rem;"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-secondary small mb-1">Total Pemesanan Masuk</div>
                    <div class="stat-num text-info"><?= $totalPemesanan ?></div>
                </div>
                <i class="bi bi-inbox-fill text-info opacity-25" style="font-size:2.5rem;"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Akses Cepat ke Semua Panel -->
        <div class="col-lg-4">
            <div class="panel-card h-100">
                <div class="card-header">
                    <div class="fw-bold text-white"><i class="bi bi-grid-fill me-2 text-indigo-400" style="color:#818CF8;"></i>Akses Semua Panel</div>
                    <div class="text-secondary" style="font-size:12px;">Developer dapat membuka panel manapun</div>
                </div>
                <div class="p-3 d-flex flex-column gap-2">
                    <!-- Panel Developer Only -->
                    <div class="mb-1 text-secondary" style="font-size:11px;letter-spacing:.5px;">▸ EKSKLUSIF DEVELOPER</div>
                    <a href="developer_panel.php" class="access-btn">
                        <div class="icon" style="background:rgba(220,38,38,.15);color:#F87171;"><i class="bi bi-code-slash"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#F87171;">Developer Panel</div>
                            <div style="font-size:11px;color:#64748B;">Statistik, manajemen user, log sistem</div>
                        </div>
                    </a>

                    <!-- Panel Mitra (dev bisa akses) -->
                    <div class="mb-1 mt-2 text-secondary" style="font-size:11px;letter-spacing:.5px;">▸ PANEL MITRA (DEVELOPER JUGA BISA)</div>
                    <a href="kamar.php" class="access-btn">
                        <div class="icon" style="background:rgba(217,119,6,.15);color:#FCD34D;"><i class="bi bi-door-closed-fill"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#FCD34D;">Kelola Kamar</div>
                            <div style="font-size:11px;color:#64748B;">UC-09: Tambah, ubah, hapus kamar</div>
                        </div>
                    </a>
                    <a href="admin_pemesanan.php" class="access-btn">
                        <div class="icon" style="background:rgba(217,119,6,.15);color:#FCD34D;"><i class="bi bi-inbox-fill"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#FCD34D;">Kelola Pemesanan</div>
                            <div style="font-size:11px;color:#64748B;">UC-11: Setujui / tolak pengajuan sewa</div>
                        </div>
                    </a>
                    <a href="admin_keuangan.php" class="access-btn">
                        <div class="icon" style="background:rgba(217,119,6,.15);color:#FCD34D;"><i class="bi bi-book-half"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#FCD34D;">Rekap Keuangan &amp; Pembayaran</div>
                            <div style="font-size:11px;color:#64748B;">Buku kas manual, verifikasi, kuitansi digital</div>
                        </div>
                    </a>
                    <a href="profil_kos.php" class="access-btn">
                        <div class="icon" style="background:rgba(217,119,6,.15);color:#FCD34D;"><i class="bi bi-building-fill"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#FCD34D;">Profil Kos</div>
                            <div style="font-size:11px;color:#64748B;">UC-12: Edit nama, alamat, NIB, aturan</div>
                        </div>
                    </a>

                    <!-- Panel Penyewa (dev bisa akses) -->
                    <div class="mb-1 mt-2 text-secondary" style="font-size:11px;letter-spacing:.5px;">▸ PANEL PENYEWA (DEVELOPER JUGA BISA)</div>
                    <a href="penyewa_dashboard.php" class="access-btn">
                        <div class="icon" style="background:rgba(5,150,105,.15);color:#34D399;"><i class="bi bi-person-check-fill"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#34D399;">Dasbor Penghuni</div>
                            <div style="font-size:11px;color:#64748B;">Lihat kamar aktif & riwayat booking</div>
                        </div>
                    </a>
                    <a href="cek_pesanan.php" class="access-btn">
                        <div class="icon" style="background:rgba(5,150,105,.15);color:#34D399;"><i class="bi bi-search"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#34D399;">Cek Status Pesanan</div>
                            <div style="font-size:11px;color:#64748B;">UC-07: Lacak pesanan by kode / WA</div>
                        </div>
                    </a>

                    <!-- Publik -->
                    <div class="mb-1 mt-2 text-secondary" style="font-size:11px;letter-spacing:.5px;">▸ HALAMAN PUBLIK</div>
                    <a href="index.php" class="access-btn" target="_blank">
                        <div class="icon" style="background:rgba(99,102,241,.15);color:#A5B4FC;"><i class="bi bi-shop"></i></div>
                        <div>
                            <div class="fw-bold" style="color:#A5B4FC;">Katalog Publik</div>
                            <div style="font-size:11px;color:#64748B;">UC-01, UC-02: Daftar & filter kamar</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Manajemen Pengguna -->
        <div class="col-lg-8">
            <div class="panel-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-white"><i class="bi bi-people-fill me-2" style="color:#818CF8;"></i>Manajemen Pengguna Sistem</div>
                        <div class="text-secondary" style="font-size:12px;">Total <?= $totalPengguna ?> pengguna terdaftar (developer, mitra, penyewa)</div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="mb-3 d-flex gap-2 flex-wrap">
                        <span class="badge px-2 py-1 rounded-pill" style="background:rgba(220,38,38,.2);color:#F87171;border:1px solid rgba(220,38,38,.4);">
                            <i class="bi bi-code-slash me-1"></i>Developer = Akses Penuh
                        </span>
                        <span class="badge px-2 py-1 rounded-pill" style="background:rgba(217,119,6,.2);color:#FCD34D;border:1px solid rgba(217,119,6,.4);">
                            <i class="bi bi-tools me-1"></i>Mitra = Kelola Kamar & Pemesanan
                        </span>
                        <span class="badge px-2 py-1 rounded-pill" style="background:rgba(5,150,105,.2);color:#34D399;border:1px solid rgba(5,150,105,.4);">
                            <i class="bi bi-person-check me-1"></i>Penyewa = Dasbor & Booking
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>NAMA</th>
                                    <th>EMAIL</th>
                                    <th>ROLE</th>
                                    <th>NO WA</th>
                                    <th>KAMAR HUNI</th>
                                    <th>TERDAFTAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $u): ?>
                                <tr class="user-row-<?= $u['role'] ?>">
                                    <td>
                                        <i class="bi <?= $u['role']==='developer' ? 'bi-shield-fill-check text-danger' : ($u['role']==='mitra' ? 'bi-tools text-warning' : 'bi-person-fill text-success') ?> me-2"></i>
                                        <?= htmlspecialchars($u['nama']) ?>
                                    </td>
                                    <td><code style="color:#94A3B8;font-size:12px;"><?= htmlspecialchars($u['email']) ?></code></td>
                                    <td>
                                        <span class="badge role-badge-<?= $u['role'] ?> rounded-pill px-2">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><span style="font-size:12px;"><?= htmlspecialchars($u['no_wa']) ?></span></td>
                                    <td>
                                        <?php if ($u['id_kamar_huni']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success">Kamar #<?= $u['id_kamar_huni'] ?></span>
                                        <?php else: ?>
                                            <span class="text-secondary" style="font-size:11px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size:11px;color:#64748B;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 10 Pemesanan Terkini -->
            <div class="panel-card">
                <div class="card-header">
                    <div class="fw-bold text-white"><i class="bi bi-clock-history me-2" style="color:#818CF8;"></i>10 Pemesanan Terkini</div>
                </div>
                <div class="p-3">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>KODE</th>
                                    <th>NAMA</th>
                                    <th>KAMAR</th>
                                    <th>MASUK</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                <?php
                                    $badgeClass = match($o['status_pemesanan']) {
                                        'menunggu'  => 'bg-warning text-dark',
                                        'disetujui' => 'bg-success',
                                        'ditolak'   => 'bg-danger',
                                        'selesai'   => 'bg-secondary',
                                        default     => 'bg-secondary'
                                    };
                                ?>
                                <tr>
                                    <td><code style="font-size:11px;color:#818CF8;"><?= htmlspecialchars($o['kode_booking']) ?></code></td>
                                    <td><?= htmlspecialchars($o['nama_penyewa']) ?></td>
                                    <td><strong>#<?= htmlspecialchars($o['nomor_kamar']) ?></strong></td>
                                    <td style="font-size:12px;"><?= htmlspecialchars($o['tanggal_masuk']) ?></td>
                                    <td><span class="badge <?= $badgeClass ?> rounded-pill px-2"><?= ucfirst($o['status_pemesanan']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="5" class="text-center text-secondary py-3">Belum ada pemesanan masuk</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-end">
                        <a href="admin_pemesanan.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            Lihat Semua Pemesanan <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Rekap Pembayaran Terkini -->
            <div class="panel-card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-bold text-white"><i class="bi bi-wallet2 me-2 text-success"></i>Pembayaran &amp; Kas Terkini (Manual &amp; Transfer)</div>
                    <span class="badge bg-success-subtle text-success border border-success">Total Kas: <?= formatRupiah($totalKasLunasReal) ?></span>
                </div>
                <div class="p-3">
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>KODE</th>
                                    <th>PEMBAYAR</th>
                                    <th>KAMAR</th>
                                    <th>NOMINAL</th>
                                    <th>METODE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $rp): ?>
                                <tr>
                                    <td><code style="font-size:11px;color:#34D399;"><?= htmlspecialchars($rp['kode_transaksi']) ?></code></td>
                                    <td><?= htmlspecialchars($rp['nama_pengirim']) ?></td>
                                    <td><strong>#<?= htmlspecialchars($rp['nomor_kamar']) ?></strong></td>
                                    <td class="fw-bold text-success"><?= formatRupiah($rp['nominal']) ?></td>
                                    <td style="font-size:11px;"><?= $rp['metode_pembayaran'] === 'tunai_cash' ? '💵 Tunai' : '💳 Bank' ?></td>
                                    <td>
                                        <span class="badge <?= $rp['status_pembayaran'] === 'lunas' ? 'bg-success' : ($rp['status_pembayaran'] === 'menunggu' ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-2">
                                            <?= ucfirst($rp['status_pembayaran']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-end">
                        <a href="admin_keuangan.php" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                            Buka Rekap Keuangan Bulanan <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
