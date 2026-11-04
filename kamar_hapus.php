<?php
// kamar_hapus.php - UC-09 Hapus Kamar
// Hanya mitra dan developer yang boleh menghapus kamar
require_once __DIR__ . '/config.php';
checkAuth(['mitra', 'developer']);

$idKamar = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idKamar > 0) {
    // Menghapus data kamar (relasi ke biaya, fasilitas, media terhapus karena ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM kamar WHERE id_kamar = ?");
    $stmt->execute([$idKamar]);
}

header("Location: kamar.php?sukses=hapus");
exit;
