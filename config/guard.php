<?php
/**
 * guard.php
 * ---------------------------------------------------------------------
 * Pusat keamanan aplikasi D-OUT (Versi URL Bersih Tanpa .php)
 * ---------------------------------------------------------------------
 */

// --- 1. Konfigurasi session yang aman (harus sebelum session_start) ---
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // otomatis true kalau situs sudah pakai HTTPS
        'httponly' => true,       // cookie tidak bisa dibaca lewat JavaScript
        'samesite' => 'Lax',      // mengurangi risiko CSRF lintas situs
    ]);
    session_start();
}

// --- 2. Pastikan koneksi database selalu ada ---
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/koneksi.php';
}

// --- 3. Helper status login ---
function is_logged_in(): bool
{
    return isset($_SESSION['login'], $_SESSION['role']) && $_SESSION['login'] === true;
}

/**
 * Panggil di baris paling atas setiap halaman (setelah include guard.php).
 * Mengarah ke 'login' dan 'index' tanpa ekstensi .php
 */
function require_login(array $allowedRoles = []): void
{
    if (!is_logged_in()) {
        header('Location: login'); // Tanpa .php
        exit;
    }

    if (!empty($allowedRoles) && !in_array((int) $_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Akses Ditolak</title></head><body style='font-family:sans-serif;text-align:center;padding-top:60px;'>";
        echo "<h3>403 - Akses Ditolak</h3><p>Anda tidak memiliki hak akses ke halaman ini.</p>";
        echo "<a href='index'>Kembali ke Dashboard</a></body></html>"; // Tanpa .php
        exit;
    }
}

// --- 4. CSRF Protection ---
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Cetak input hidden CSRF di dalam <form> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Panggil di awal proses POST/GET yang mengubah data */
function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
        http_response_code(403);
        die('Permintaan ditolak: token keamanan (CSRF) tidak valid atau kadaluarsa. Silakan muat ulang halaman dan coba lagi.');
    }
}

/** Escape singkat supaya konsisten dipakai di semua halaman */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * --- FUNGSI CATAT LOG AKTIVITAS ---
 */
function catat_log(mysqli $conn, string $modul, string $aksi, string $deskripsi): void
{
    $username = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'System';
    $sql = "INSERT INTO log_aktivitas (username, modul, aksi, deskripsi) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $username, $modul, $aksi, $deskripsi);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}