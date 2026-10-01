<?php
require_once __DIR__ . '/config/guard.php';
require_login(); // Wajib login untuk mengakses halaman ganti password

$error = '';
$success = '';

// Tentukan ID user yang sedang login dari session
$current_user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? ($_SESSION['uid'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // Verifikasi token CSRF demi keamanan

    $password_lama       = $_POST['password_lama'] ?? '';
    $password_baru       = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error = "Semua kolom wajib diisi!";
    } elseif ($password_baru !== $konfirmasi_password) {
        $error = "Konfirmasi password baru tidak sesuai!";
    } elseif (strlen($password_baru) < 6) {
        $error = "Password baru minimal harus terdiri dari 6 karakter!";
    } else {
        // Ambil data password hash dari database berdasarkan ID user yang sedang login
        $stmt_check = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $current_user_id);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);

        if ($row = mysqli_fetch_assoc($result)) {
            // Verifikasi password lama dengan hash yang ada di database
            if (password_verify($password_lama, $row['password'])) {
                // Hash password baru
                $new_password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

                // Update password di database
                $stmt_update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $current_user_id);

                if (mysqli_stmt_execute($stmt_update)) {
                    $success = "Password berhasil diubah!";
                } else {
                    error_log('Gagal memperbarui password: ' . mysqli_error($conn));
                    $error = "Gagal memperbarui password. Silakan coba lagi.";
                }
            } else {
                $error = "Password lama yang Anda masukkan salah!";
            }
        } else {
            $error = "Data pengguna tidak ditemukan.";
        }
    }
}
?>

<div class="container-fluid px-0">
    <!-- Header Bagian -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-key me-2 text-primary"></i>Ganti Password</h5>
            <p class="text-muted small mb-0">Perbarui kata sandi akun Anda secara berkala demi keamanan</p>
        </div>
    </div>

    <!-- Notifikasi Alert -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 mb-3 small alert-dismissible fade show" role="alert">
            <?= h($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success py-2 mb-3 small alert-dismissible fade show" role="alert">
            <?= h($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form Ganti Password -->
    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <form action="index.php?page=ganti_password" method="POST" autocomplete="off">
                        <?= csrf_field(); ?>

                        <div class="mb-3">
                            <label for="password_lama" class="form-label small fw-bold mb-1">Password Lama</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="password_lama" name="password_lama" placeholder="Masukkan password saat ini" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_lama', 'icon_lama')">
                                    <i class="fas fa-eye" id="icon_lama"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_baru" class="form-label small fw-bold mb-1">Password Baru</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="password_baru" name="password_baru" placeholder="Masukkan password baru (minimal 6 karakter)" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_baru', 'icon_baru')">
                                    <i class="fas fa-eye" id="icon_baru"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="konfirmasi_password" class="form-label small fw-bold mb-1">Konfirmasi Password Baru</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password baru" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('konfirmasi_password', 'icon_konfirmasi')">
                                    <i class="fas fa-eye" id="icon_konfirmasi"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script JavaScript untuk Toggle Show/Hide Password -->
<script>
function togglePassword(fieldId, iconId) {
    const inputField = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    
    if (inputField.type === "password") {
        inputField.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        inputField.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>