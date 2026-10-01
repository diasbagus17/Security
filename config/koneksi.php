<?php
/**
 * koneksi.php
 * File ini sebaiknya TIDAK diakses langsung — sudah otomatis dipanggil
 * oleh guard.php. Sesuaikan kredensial di bawah dengan environment Anda.
 */

// Sembunyikan detail error database dari pengunjung, tapi tetap catat di log server.
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_security";

$conn = @mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // Jangan tampilkan mysqli_connect_error() ke pengguna (bisa membocorkan info server).
    error_log('DB connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    die("Koneksi database gagal. Silakan hubungi administrator sistem.");
}

mysqli_set_charset($conn, 'utf8mb4');

// Fungsi untuk menerjemahkan angka role ke nama departemen
function getNamaDepartemen($role)
{
    switch ((int) $role) {
        case 1:
            return "Security";
        case 2:
            return "Logistik";
        case 3:
            return "PGA";
        case 4:
            return "Lain-lain";
        case 5:
            return "Accounting";
        case 99:
            return "Administrator";
        default:
            return "Umum";
    }
}
