<?php
require_once __DIR__ . '/../config/guard.php';
require_login([2, 5, 99]); // Sesuai hak akses menu upload_excel/laporan_selisih

// Hapus seluruh data di tabel temp_import
mysqli_query($conn, "TRUNCATE TABLE temp_import");

// Redirect kembali ke halaman view_dokumen di luar folder
header("Location: ../index.php?page=view_dokumen");
exit;