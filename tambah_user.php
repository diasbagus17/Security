<?php
require_once __DIR__ . '/config/guard.php';
require_login([99]); // hanya Administrator

$error = '';
$success = '';

// Tentukan ID user yang sedang login dari berbagai kemungkinan nama session
$current_user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? ($_SESSION['uid'] ?? 0));

// Proses Hapus User
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    csrf_verify();
    $id_hapus = (int) $_GET['id'];

    // Mencegah admin menghapus akun sendiri yang sedang aktif
    if ($current_user_id > 0 && $id_hapus === (int) $current_user_id) {
        $error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!";
    } else {
        $stmt_del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_hapus);
        if (mysqli_stmt_execute($stmt_del)) {
            $success = "User berhasil dihapus!";
        } else {
            $error = "Gagal menghapus user.";
        }
    }
}

// Proses Tambah User Baru
if (isset($_POST['submit_user'])) {
    csrf_verify();

    $nip      = trim($_POST['nip'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $bagian   = trim($_POST['bagian'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role     = (int) ($_POST['role'] ?? 0);

    if ($nip === '' || $nama === '' || $bagian === '' || $username === '' || $password === '' || $role <= 0) {
        $error = "Semua kolom wajib diisi dengan benar!";
    } else {
        $cek = mysqli_prepare($conn, "SELECT id FROM users WHERE nip = ? OR username = ?");
        mysqli_stmt_bind_param($cek, "ss", $nip, $username);
        mysqli_stmt_execute($cek);
        $cekResult = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($cekResult) > 0) {
            $error = "NIP atau Username sudah digunakan!";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (nip, nama, bagian, username, password, role) VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "sssssi", $nip, $nama, $bagian, $username, $passwordHash, $role);

            if (mysqli_stmt_execute($stmt)) {
                $success = "User berhasil ditambahkan!";
            } else {
                error_log('Gagal menambah user: ' . mysqli_error($conn));
                $error = "Gagal menyimpan data user. Silakan coba lagi.";
            }
        }
    }
}

// Ambil daftar seluruh user dari database
$result_users = mysqli_query($conn, "SELECT id, nip, nama, username, bagian, role FROM users ORDER BY id DESC");

// Ambil daftar bagian unik dari database untuk pilihan Datalist
$result_bagian = mysqli_query($conn, "SELECT DISTINCT bagian FROM users WHERE bagian != '' ORDER BY bagian ASC");
?>

<!-- Panggil file CSS eksternal khusus halaman ini jika belum dimuat di layout utama -->
<link rel="stylesheet" href="css/tambah_user.css">

<div class="container-fluid px-0">
    <!-- Header Bagian & Tombol Tambah -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-users me-2 text-primary"></i>Manajemen Pengguna</h5>
            <p class="text-muted small mb-0">Kelola data akun pengguna sistem D-OUT</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="fas fa-user-plus me-1"></i> Tambah User Baru
        </button>
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

    <!-- Tabel Daftar User dengan Struktur Card & Header Hitam -->
    <div class="card user-card">
        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
            <span class="fs-6 m-0"><i class="fas fa-list me-2"></i>Daftar Pengguna Sistem</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark" style="font-size: 13px;">
                    <thead class="table-light text-uppercase text-muted border-bottom" style="font-size: 11px;">
                        <tr>
                            <th class="py-2.5 ps-3" style="width: 5%;">No</th>
                            <th class="py-2.5" style="width: 15%;">NIP</th>
                            <th class="py-2.5" style="width: 25%;">Nama Lengkap</th>
                            <th class="py-2.5" style="width: 20%;">Username</th>
                            <th class="py-2.5" style="width: 15%;">Bagian</th>
                            <th class="py-2.5 text-center" style="width: 10%;">Role</th>
                            <th class="py-2.5 text-center" style="width: 10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_users) > 0): ?>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($result_users)): ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?= $no++; ?></td>
                                    <td class="text-secondary"><?= h($row['nip']); ?></td>
                                    <td class="fw-semibold text-dark"><?= h($row['nama']); ?></td>
                                    <td class="text-muted"><?= h($row['username']); ?></td>
                                    <td class="text-muted"><?= h($row['bagian']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                            // Format role berupa teks warna bersih tanpa kotak/bingkai
                                            $role_styles = [
                                                1 => ['color' => 'text-secondary', 'text' => 'Security'],
                                                2 => ['color' => 'text-info', 'text' => 'Logistik'],
                                                3 => ['color' => 'text-warning', 'text' => 'PGA'],
                                                4 => ['color' => 'text-muted', 'text' => 'Lain-lain'],
                                                99 => ['color' => 'text-primary fw-bold', 'text' => 'Administrator']
                                            ];
                                            $r = (int)$row['role'];
                                            $style_info = $role_styles[$r] ?? ['color' => 'text-secondary', 'text' => 'Unknown'];
                                        ?>
                                        <span class="<?= $style_info['color']; ?>" style="font-size: 13px;">
                                            <?= $style_info['text']; ?> <span class="text-muted" style="font-size: 11px;">(<?= $r; ?>)</span>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="index.php?page=tambah_user&action=hapus&id=<?= $row['id']; ?>&csrf_token=<?= $_SESSION['csrf_token']; ?>" 
                                           class="btn btn-sm px-2 py-0 border-0" 
                                           onclick="return confirm('Yakin ingin menghapus user <?= h($row['nama']); ?>?');"
                                           title="Hapus User">
                                            <i class="fas fa-trash-alt text-danger"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data pengguna terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH USER BARU -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-labelledby="modalTambahUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fs-6 fw-bold" id="modalTambahUserLabel"><i class="fas fa-user-plus me-2"></i>Tambah User Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?page=tambah_user" method="POST" autocomplete="off">
                <div class="modal-body p-4">
                    <?= csrf_field(); ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold mb-1">NIP</label>
                            <input type="text" name="nip" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold mb-1">Username</label>
                            <input type="text" name="username" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control form-control-sm" required autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1">Bagian / Divisi</label>
                        <input type="text" name="bagian" class="form-control form-control-sm" list="listBagian" placeholder="Pilih atau ketik bagian baru..." required autocomplete="off">
                        <datalist id="listBagian">
                            <?php while ($b = mysqli_fetch_assoc($result_bagian)): ?>
                                <option value="<?= h($b['bagian']); ?>">
                            <?php endwhile; ?>
                        </datalist>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold mb-1">Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Masukkan password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold mb-1">Role</label>
                            <select name="role" class="form-select form-select-sm" required>
                                <option value="" selected disabled>-- Pilih Role --</option>
                                <option value="1">1 - Security</option>
                                <option value="2">2 - Logistik</option>
                                <option value="3">3 - PGA</option>
                                <option value="4">4 - Lain-lain</option>
                                <option value="5">5 - Accounting</option>
                                <option value="99">99 - Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_user" class="btn btn-primary btn-sm px-3 fw-bold">Simpan User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Otomatis membuka kembali modal jika terjadi error validasi saat pengiriman form
if ($error !== '' && isset($_POST['submit_user'])): 
?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var myModal = new bootstrap.Modal(document.getElementById('modalTambahUser'));
        myModal.show();
    });
</script>
<?php endif; ?>