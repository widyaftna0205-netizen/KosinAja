<?php
// quick_status.php - UC-10 Ubah Status Kamar Instan (Cantika)
// Hanya mitra dan developer yang boleh ubah status kamar
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$idKamar = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newStatus = trim($_GET['status'] ?? '');

$allowedStatus = ['kosong', 'dipesan', 'terisi'];

if ($idKamar > 0 && in_array($newStatus, $allowedStatus)) {
    $stmt = $pdo->prepare("UPDATE kamar SET status = ?, tanggal_pembaruan = CURRENT_TIMESTAMP WHERE id_kamar = ?");
    $stmt->execute([$newStatus, $idKamar]);
}

$redirect = $_GET['ref'] ?? 'kamar.php';
header("Location: " . $redirect . "?sukses=status_diubah");
exit;
