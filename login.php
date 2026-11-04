<?php
// login.php - UC-08 Login Terpadu 3 Role (Developer, Mitra Ibu Yeni, Calon/Penghuni)
require_once __DIR__ . '/config.php';

// Jika sudah login, redirect sesuai role masing-masing
if (isset($_SESSION['user'])) {
    redirectAfterLogin($_SESSION['user']['role']);
}

$error = '';
$sukses = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'harus_login') {
        $error = 'Sesi Anda telah berakhir atau Anda belum login. Silakan masuk kembali.';
    } elseif ($_GET['pesan'] === 'logout_berhasil') {
        $sukses = 'Anda telah berhasil keluar dari sistem.';
    } elseif ($_GET['pesan'] === 'daftar_sukses') {
        $sukses = 'Pendaftaran akun berhasil! Silakan masuk dengan email dan kata sandi Anda.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['kata_sandi'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Kolom wajib diisi. Silakan lengkapi email dan kata sandi Anda.';
    } else {
        // Validasi ke tabel pengguna dengan Bcrypt hash
        $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['kata_sandi_hash'])) {
            $_SESSION['user'] = [
                'id_pengguna' => $user['id_pengguna'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
                'no_wa' => $user['no_wa'],
                'id_kamar_huni' => $user['id_kamar_huni']
            ];

            // Pengalihan Berdasarkan Peran (Role-Based Redirect)
            redirectAfterLogin($user['role']);
        } else {
            $error = 'Email atau kata sandi salah. Silakan periksa kembali data Anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - KosinAja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: radial-gradient(100% 100% at 50% 0%, #312E81 0%, #0F172A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-wrapper { max-width: 470px; width: 100%; }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            padding: 36px 32px;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, #4F46E5, #10B981, #06B6D4);
        }
        .form-control { border-radius: 12px; padding: 12px 14px; border: 1.5px solid #E2E8F0; font-size: 14px; }
        .form-control:focus { border-color: #4F46E5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12); }
        .btn-login {
            background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%);
            border: none;
            border-radius: 14px;
            padding: 13px;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }
        .btn-login:hover { transform: translateY(-2px); color: #ffffff; }
        .quick-role-btn {
            font-size: 11px;
            font-weight: 600;
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            border: 1px solid #E2E8F0;
            background: #F8FAFC;
            transition: all 0.2s ease;
            text-align: left;
        }
        .quick-role-btn:hover { background: #EEF2FF; border-color: #C7D2FE; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- Brand Title -->
    <div class="text-center mb-3">
        <span class="badge bg-white bg-opacity-10 text-light border border-white border-opacity-25 px-3 py-2 rounded-pill small mb-2">
            <i class="bi bi-shield-lock-fill text-success me-1"></i> Autentikasi Terpadu 3 Role
        </span>
        <h2 class="text-white fw-bold mb-0">KosinAja</h2>
        <p class="text-white-50 small">Kos Putri Anak Lanang • Pasir Pengaraian, Riau</p>
    </div>

    <div class="login-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0">Masuk ke Akun</h4>
                <p class="text-muted small mb-0">Pilih akses sesuai peran Anda di bawah.</p>
            </div>
            <span class="badge bg-light text-primary border rounded-pill px-3 py-2 small fw-bold">UC-08</span>
        </div>

        <!-- Tombol Cepat Pengujian 3 Role (Klik Otomatis Terisi) -->
        <div class="mb-3">
            <div class="small fw-bold text-secondary mb-2"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Pilih Role Uji Coba Cepat:</div>
            <div class="d-flex flex-column gap-1">
                <!-- Role 1: Developer -->
                <div class="quick-role-btn d-flex justify-content-between align-items-center" 
                     onclick="fillLogin('dev@kosinaja.id', 'admin123')">
                    <div>
                        <span class="badge bg-primary me-1">1. Developer</span> 
                        <span class="text-dark fw-bold">dev@kosinaja.id</span>
                    </div>
                    <span class="text-muted">admin123 &rarr;</span>
                </div>
                <!-- Role 2: Mitra Ibu Yeni -->
                <div class="quick-role-btn d-flex justify-content-between align-items-center" 
                     onclick="fillLogin('yeni@kosinaja.id', 'admin123')">
                    <div>
                        <span class="badge bg-indigo text-white me-1" style="background:#4338CA;">2. Mitra (Ibu Yeni)</span> 
                        <span class="text-dark fw-bold">yeni@kosinaja.id</span>
                    </div>
                    <span class="text-muted">admin123 &rarr;</span>
                </div>
                <!-- Role 3: Calon / Penghuni -->
                <div class="quick-role-btn d-flex justify-content-between align-items-center" 
                     onclick="fillLogin('penyewa@kosinaja.id', 'user123')">
                    <div>
                        <span class="badge bg-success me-1">3. Penghuni / Calon</span> 
                        <span class="text-dark fw-bold">penyewa@kosinaja.id</span>
                    </div>
                    <span class="text-muted">user123 &rarr;</span>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center mb-3">
                <i class="bi bi-exclamation-circle-fill me-2 fs-6"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($sukses)): ?>
            <div class="alert alert-success py-2 px-3 small rounded-3 d-flex align-items-center mb-3">
                <i class="bi bi-check-circle-fill me-2 fs-6"></i>
                <div><?= htmlspecialchars($sukses) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label small fw-bold text-secondary">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" 
                           placeholder="nama@kosinaja.id" value="<?= htmlspecialchars($_POST['email'] ?? 'yeni@kosinaja.id') ?>" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label for="kata_sandi" class="form-label small fw-bold text-secondary">Kata Sandi</label>
                    <span class="text-muted small" style="font-size:11px;">Enkripsi Bcrypt</span>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="kata_sandi" name="kata_sandi" 
                           placeholder="Masukkan kata sandi..." value="admin123" required>
                    <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePass()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 py-3 mt-2 fw-bold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
            </button>
        </form>

        <!-- Pendaftaran Khusus Calon Penghuni Baru -->
        <div class="mt-4 pt-3 border-top text-center">
            <p class="small text-muted mb-2">Calon penyewa baru dan belum punya akun?</p>
            <a href="register.php" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold">
                <i class="bi bi-person-plus-fill me-1"></i> Daftar Akun Calon Penghuni
            </a>
            <div class="mt-3">
                <a href="index.php" class="text-decoration-none small text-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Katalog Publik Toko
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('kata_sandi').value = pass;
}
function togglePass() {
    const input = document.getElementById('kata_sandi');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

</body>
</html>
