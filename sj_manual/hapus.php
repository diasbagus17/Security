<?php
session_start();
include '../config/koneksi.php'; // Sesuaikan jika file koneksi Anda langsung di root

$role = $_SESSION['role'] ?? 0;
if ($role !== 99) {
    header("Location: ../index.php?error=" . urlencode("Akses ditolak! Anda tidak memiliki izin menghapus data."));
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = mysqli_prepare($conn, "DELETE FROM sj_manual WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header("Location: ../index.php?page=sj_manual&pesan=" . urlencode("Data Surat Jalan berhasil dihapus!"));
        exit();
    } else {
        $error = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        header("Location: ../index.php?page=sj_manual&error=" . urlencode("Gagal menghapus data: " . $error));
        exit();
    }
} else {
    header("Location: ../index.php?page=sj_manual&error=" . urlencode("ID Surat Jalan tidak valid!"));
    exit();
}