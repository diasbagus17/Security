<?php
require_once __DIR__ . '/config/guard.php';

// Kosongkan tabel temp_import saat logout (jika user memang sedang login)
if (is_logged_in()) {
    mysqli_query($conn, "TRUNCATE TABLE temp_import");
}

// Hapus semua variabel sesi & hancurkan sesi sepenuhnya
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit();
