<?php
// register.php - Pendaftaran Akun Calon Penghuni / Penyewa KosinAja
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $password = $_POST['kata_sandi'] ?? '';

    if (empty($nama) || empty($email) || empty($no_wa) || empty($password)) {
        $error = 'Semua kolom wajib diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        // Cek apakah email sudah terdaftar
        $stmtC = $pdo->prepare("SELECT id_pengguna FROM pengguna WHERE email = ?");
        $stmtC->execute([$email]);
        if ($stmtC->fetch()) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain atau langsung masuk.';
        } else {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtIns = $pdo->prepare("
                INSERT INTO pengguna (nama, email, kata_sandi_hash, no_wa, role, id_kamar_huni)
                VALUES (?, ?, ?, ?, 'penyewa', NULL)
            ");
            $stmtIns->execute([$nama, $email, $passHash, $no_wa]);
            header("Location: login.php?pesan=daftar_sukses");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Calon Penghuni - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: radial-gradient(100% 100% at 50% 0%, #065F46 0%, #0F172A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card-custom {
            max-width: 480px; width: 100%;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            padding: 36px 32px;
        }
        .btn-register {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border: none; border-radius: 14px; padding: 13px; font-weight: 700; color: #FFF;
        }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="text-center mb-4">
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill small mb-2">
            <i class="bi bi-person-plus-fill me-1"></i> Registrasi Calon Penghuni
        </span>
        <h4 class="fw-bold text-dark mb-1">Buat Akun Penghuni</h4>
        <p class="text-muted small mb-0">Kos Putri Anak Lanang (Ibu Yeni)</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Nama Lengkap *</label>
            <input type="text" name="nama" class="form-control rounded-3" placeholder="Contoh: Siti Aisyah" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Alamat Email *</label>
            <input type="email" name="email" class="form-control rounded-3" placeholder="email@anda.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Nomor WhatsApp Aktif *</label>
            <input type="tel" name="no_wa" class="form-control rounded-3" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($_POST['no_wa'] ?? '') ?>">
            <div class="form-text small">Digunakan untuk konfirmasi ketersediaan kamar &amp; survei lokasi.</div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-secondary">Kata Sandi Baru *</label>
            <input type="password" name="kata_sandi" class="form-control rounded-3" placeholder="Minimal 6 karakter" required>
        </div>

        <button type="submit" class="btn btn-register w-100 py-3 fw-bold">
            <i class="bi bi-check-circle-fill me-1"></i> Daftar Akun Calon Penghuni
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top">
        <p class="small text-muted mb-0">Sudah punya akun? <a href="login.php" class="fw-bold text-success text-decoration-none">Masuk di sini</a></p>
    </div>
</div>

</body>
</html>
