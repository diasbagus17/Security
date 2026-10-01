<?php
require_once __DIR__ . '/config/guard.php';

// Jika sudah login, langsung lempar ke dashboard (tidak perlu login lagi)
if (is_logged_in()) {
    header("Location: index");
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // Batasi panjang input untuk mencegah penyalahgunaan
    if ($username === '' || $password === '') {
        $error = "Username dan Password wajib diisi!";
    } else {
        // Query pakai prepared statement (aman dari SQL Injection)
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;

        $passwordValid = false;
        if ($row) {
            // Mendukung password lama yang masih plaintext di database (migrasi bertahap)
            // sekaligus password baru yang sudah di-hash dengan password_hash().
            if (password_verify($password, $row['password'])) {
                $passwordValid = true;
            } elseif (hash_equals((string) $row['password'], $password)) {
                // Password lama (plaintext) cocok -> upgrade otomatis ke hash yang aman
                $passwordValid = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, "si", $newHash, $row['id']);
                mysqli_stmt_execute($upd);
            }
        }

        if ($passwordValid) {
            // Cegah session fixation: buat session ID baru setelah login berhasil
            session_regenerate_id(true);

            $db_bagian = isset($row['bagian']) ? trim($row['bagian']) : '';
            $db_role   = (int) ($row['role'] ?? 0);

            $final_bagian = $db_bagian;
            if ($db_bagian === '' || is_numeric($db_bagian)) {
                $final_bagian = ($db_role === 99) ? 'Administrator' : 'Staff';
            }

            $_SESSION['login']    = true;
            $_SESSION['id']       = $row['id'];
            $_SESSION['nama']     = $row['nama'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['bagian']   = $final_bagian;
            $_SESSION['role']     = $db_role;

            header("Location: index.php");
            exit;
        } else {
            $error = "Username atau Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D-OUT | Login</title>

    <!-- Favicon / Ikon Tab Web -->
    <link rel="icon" type="image/png" href="logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-body">
    <div class="container px-3">
        <div class="login-container row g-0">

            <!-- SISI KIRI: WALLPAPER / LOGO -->
            <div class="col-md-6 d-none d-md-block login-image-side" style="background-image: url('logo.png'); background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #ffffff;">
            </div>

            <!-- SISI KANAN: FORM LOGIN -->
            <div class="col-md-6 login-form-side">
                <div class="login-header-text text-center mb-4">
                    <h4 class="fw-bold text-dark">D-OUT</h4>
                    <p class="text-muted small mb-0">Document Out Tracking System</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 small"><?= h($error); ?></div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username..." required autofocus maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password..." required maxlength="255">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="login" class="btn btn-dark py-2 fw-bold">Login</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</body>
</html>
