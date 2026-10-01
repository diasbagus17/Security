<?php
require_once __DIR__ . '/config/guard.php';
require_login([99]); // Hanya Administrator

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $tanggal_mulai   = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');

    if (!empty($tanggal_mulai) && !empty($tanggal_selesai)) {
        // Pastikan tanggal mulai tidak lebih besar dari tanggal selesai
        if ($tanggal_mulai > $tanggal_selesai) {
            echo "<script>alert('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!'); window.location='index.php?page=log_aktivitas';</script>";
            exit;
        }

        $tgl_mulai_aman   = mysqli_real_escape_string($conn, $tanggal_mulai);
        $tgl_selesai_aman = mysqli_real_escape_string($conn, $tanggal_selesai);
        
        // Query hapus berdasarkan rentang tanggal
        $stmt = mysqli_prepare($conn, "DELETE FROM log_aktivitas WHERE DATE(waktu) BETWEEN ? AND ?");
        mysqli_stmt_bind_param($stmt, "ss", $tgl_mulai_aman, $tgl_selesai_aman);
        
        if (mysqli_stmt_execute($stmt)) {
            $jml_terhapus = mysqli_stmt_affected_rows($stmt);
            if (function_exists('catat_log')) {
                catat_log($conn, 'Log Aktivitas', 'HAPUS', "Menghapus massal log aktivitas dari tanggal $tanggal_mulai s.d $tanggal_selesai ($jml_terhapus baris)");
            }
            echo "<script>alert('Berhasil menghapus $jml_terhapus data log dari tanggal $tanggal_mulai sampai $tanggal_selesai.'); window.location='index.php?page=log_aktivitas';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal menghapus data log.'); window.location='index.php?page=log_aktivitas';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Silakan isi tanggal mulai dan tanggal selesai dengan lengkap!'); window.location='index.php?page=log_aktivitas';</script>";
        exit;
    }
}