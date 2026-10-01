<?php
require_once __DIR__ . '/../config/guard.php';
require_once __DIR__ . '/../config/koneksi.php';

if (function_exists('require_login')) {
    require_login();
}

$role = (int) ($_SESSION['role'] ?? 0);
// Role 5 diperlakukan sama persis dengan Administrator (99) di modul SJ Manual.
// Semua cek hak akses di file ini memakai $role, jadi cukup disamakan di sini.
if ($role === 5) {
    $role = 99;
}
$user_bagian = $_SESSION['bagian'] ?? '';
$pesan = '';
$error = '';

// Hitung nomor SJ berikutnya (nomor setelah data terakhir di DB). Kosong jika DB belum berisi data.
function sj_berikutnya($conn) {
    $q = mysqli_query($conn, "SELECT nomor_sj FROM sj_manual ORDER BY id DESC LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        return '';
    }
    $last = mysqli_fetch_assoc($q)['nomor_sj'];
    if (preg_match('/(\d+)$/', $last, $m)) {
        $next = str_pad((int) $m[1] + 1, strlen($m[1]), '0', STR_PAD_LEFT);
        return preg_replace('/\d+$/', $next, $last);
    }
    return $last;
}

// Cek apakah tabel sj_manual masih kosong
function sj_db_kosong($conn) {
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM sj_manual");
    return ((int) (mysqli_fetch_assoc($q)['total'] ?? 0) === 0);
}

// PROSES POST: Tahap 1 - Input oleh Logistik
// - Role 99  : selalu boleh input (nomor default = nomor setelah data terakhir di DB)
// - Role 2   : hanya boleh input jika tabel sj_manual masih KOSONG
if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_logistik') {
    $nomor_sj   = trim($_POST['nomor_sj'] ?? '');
    $departemen = trim($_POST['departemen'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($role !== 2 && $role !== 99 && $role !== 5) {
        $error = "Akses ditolak! Anda tidak memiliki hak akses untuk tindakan ini.";
    } else {
        // Penentuan nomor SJ:
        // - Role 99            : boleh mengetik sendiri; jika kosong pakai nomor setelah data terakhir
        // - Role 2, DB kosong  : mengetik nomor pertama sendiri
        // - Role 2, DB ada isi : nomor DIKUNCI, selalu nomor setelah data terakhir (input form diabaikan)
        if ($role === 99) {
            if ($nomor_sj === '') {
                $nomor_sj = sj_berikutnya($conn);
            }
        } elseif (!sj_db_kosong($conn)) {
            $nomor_sj = sj_berikutnya($conn);
        }

        if (!empty($nomor_sj) && !empty($departemen) && !empty($keterangan)) {
            $query = "INSERT INTO sj_manual (nomor_sj, departemen, keterangan, status) VALUES (?, ?, ?, 'Belum Dijadwalkan')";
            $stmt  = mysqli_prepare($conn, $query);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sss", $nomor_sj, $departemen, $keterangan);

                if (mysqli_stmt_execute($stmt)) {
                    $pesan = "Data Surat Jalan berhasil ditambahkan!";

                    // Catat ke log_aktivitas
                    $username_log = $_SESSION['username'] ?? 'System';
                    $deskripsi_log = "Menambahkan Surat Jalan Manual nomor: $nomor_sj";
                    $stmt_log = mysqli_prepare($conn, "INSERT INTO log_aktivitas (username, aksi, modul, deskripsi, waktu) VALUES (?, 'TAMBAH', 'SJ Manual', ?, NOW())");
                    if ($stmt_log) {
                        mysqli_stmt_bind_param($stmt_log, "ss", $username_log, $deskripsi_log);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    }
                    $reset_post = true;
                } else {
                    if (mysqli_errno($conn) === 1062) {
                        $error = "Gagal: Nomor SJ '$nomor_sj' sudah terdaftar di dalam sistem!";
                    } else {
                        $error = "Gagal menyimpan data: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Terjadi kesalahan pada sistem database: " . mysqli_error($conn);
            }
        } else {
            $error = "Semua kolom pada Tahap 1 wajib diisi!";
        }
    }
}

// PROSES POST: Tahap 2 - Input Jadwal & Kendaraan
if (isset($_POST['aksi']) && $_POST['aksi'] === 'update_user') {
    if ($role === 2 || $role === 3 || $role === 4 || $role === 99 || $role === 5) {
        $id = (int) ($_POST['id'] ?? 0);
        $estimasi_keluar = trim($_POST['estimasi_keluar'] ?? '');
        $plat_nomor = trim($_POST['plat_nomor'] ?? '');
        $keterangan_baru = trim($_POST['keterangan'] ?? '');

        if (!empty($id) && !empty($estimasi_keluar) && !empty($plat_nomor) && !empty($keterangan_baru)) {
            $stmt = mysqli_prepare($conn, "UPDATE sj_manual SET estimasi_keluar = ?, plat_nomor = ?, keterangan = ?, status = 'Siap Berangkat' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $estimasi_keluar, $plat_nomor, $keterangan_baru, $id);
            if (mysqli_stmt_execute($stmt)) {
                $pesan = "Jadwal, kendaraan & keterangan berhasil diperbarui (Status: Siap Berangkat).";
                
                // Catat ke log_aktivitas
                $username_log = $_SESSION['username'] ?? 'System';
                $deskripsi_log = "Memperbarui jadwal, kendaraan & keterangan SJ ID: $id (Plat: $plat_nomor)";
                $stmt_log = mysqli_prepare($conn, "INSERT INTO log_aktivitas (username, aksi, modul, deskripsi, waktu) VALUES (?, 'UPDATE', 'SJ Manual', ?, NOW())");
                if ($stmt_log) {
                    mysqli_stmt_bind_param($stmt_log, "ss", $username_log, $deskripsi_log);
                    mysqli_stmt_execute($stmt_log);
                    mysqli_stmt_close($stmt_log);
                }
            } else {
                $error = "Gagal memperbarui data: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Estimasi keluar, plat nomor, dan keterangan wajib diisi!";
        }
    } else {
        $error = "Akses ditolak!";
    }
}

// PROSES POST: Tahap 3 - Verifikasi oleh Security (Role 1 & 99) atau Role 2 (khusus plat batal)
if (isset($_POST['aksi']) && $_POST['aksi'] === 'verifikasi_security') {
    $id = (int) $_POST['id'];
    $plat_nomor_baru = trim($_POST['plat_nomor_baru'] ?? '');

    // Cek apakah plat nomor baru mengandung kata "batal"
    $is_batal = (stripos($plat_nomor_baru, 'batal') !== false);

    // Tentukan Hak Akses:
    // - Role 1 atau 99 bisa mengakses apa saja.
    // - Role 2 HANYA BISA jika plat nomor mengandung kata "batal".
    $akses_diizinkan = ($role === 1 || $role === 5|| $role === 99) || ($role === 2 && $is_batal);

    if ($akses_diizinkan) {
        if (!empty($id)) {
            
            // Tentukan status akhir: jika mengandung kata "batal" jadi 'Batal', jika normal jadi 'Selesai'
            $status_akhir = $is_batal ? 'Batal' : 'Selesai';

            if ($is_batal) {
                // Jika Batal: Kosongkan plat nomor dan keterangan, atau set ke null/string kosong
                $plat_kosong = '';
                $ket_kosong = '';

                $stmt = mysqli_prepare($conn, "UPDATE sj_manual SET plat_nomor = ?, keterangan = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $plat_kosong, $ket_kosong, $status_akhir, $id);
            } else {
                // Jika Normal (Selesai): Update plat_nomor dan status saja
                if (empty($plat_nomor_baru)) {
                    $error = "Plat nomor wajib diisi!";
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE sj_manual SET plat_nomor = ?, status = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ssi", $plat_nomor_baru, $status_akhir, $id);
                }
            }

            // Lanjutkan eksekusi jika tidak ada error validasi
            if ($error === '' && !empty($stmt)) {
                if (mysqli_stmt_execute($stmt)) {
                    $pesan = "Data berhasil diperbarui (Status: $status_akhir).";
                    
                    // Catat ke log_aktivitas
                    $username_log = $_SESSION['username'] ?? 'System';
                    $deskripsi_log = "Memverifikasi SJ ID: $id dengan Status: $status_akhir";
                    $stmt_log = mysqli_prepare($conn, "INSERT INTO log_aktivitas (username, aksi, modul, deskripsi, waktu) VALUES (?, 'VERIFIKASI', 'SJ Manual', ?, NOW())");
                    if ($stmt_log) {
                        mysqli_stmt_bind_param($stmt_log, "ss", $username_log, $deskripsi_log);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    }
                    
                    // Tidak perlu redirect (header sudah terkirim oleh index.php).
                    // Data terbaru otomatis terbaca oleh query di bawah.
                    // Cegah submit ulang form saat halaman di-refresh (F5):
                    $reset_post = true;
                } else {
                    $error = "Gagal memperbarui data: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "ID tidak valid!";
        }
    } else {
        $error = "Akses ditolak! Role Anda tidak diizinkan memverifikasi data ini.";
    }
}

// AMBIL NOMOR SJ OTOMATIS (nomor setelah data terakhir di DB) & status DB
$suggested_sj = sj_berikutnya($conn);
$db_kosong    = sj_db_kosong($conn);
// Kolom nomor SJ dikunci untuk role 2 kecuali DB masih kosong
$nomor_terkunci = ($role === 2 && !$db_kosong);

// Ambil daftar bagian unik dari tabel users
$result_bagian = mysqli_query($conn, "SELECT DISTINCT bagian FROM users WHERE bagian != '' AND bagian != 'Administrator' ORDER BY bagian ASC");

// Hitung badge Belum Dijadwalkan
if ($role === 99) {
    $res_badge = mysqli_query($conn, "SELECT COUNT(*) as total FROM sj_manual WHERE status = 'Belum Dijadwalkan'");
} else {
    $stmt_badge = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM sj_manual WHERE status = 'Belum Dijadwalkan' AND departemen = ?");
    mysqli_stmt_bind_param($stmt_badge, "s", $user_bagian);
    mysqli_stmt_execute($stmt_badge);
    $res_badge = mysqli_stmt_get_result($stmt_badge);
}
$row_badge = mysqli_fetch_assoc($res_badge);
$jumlah_belum_dijadwalkan = $row_badge['total'] ?? 0;


// Data untuk Tahap 2
if ($role === 99) {
    $result_tahap2 = mysqli_query($conn, "SELECT * FROM sj_manual WHERE status IN ('Belum Dijadwalkan', 'Siap Berangkat') ORDER BY id DESC");
} else {
    $stmt_tahap2 = mysqli_prepare($conn, "SELECT * FROM sj_manual WHERE status IN ('Belum Dijadwalkan', 'Siap Berangkat') AND departemen = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt_tahap2, "s", $user_bagian);
    mysqli_stmt_execute($stmt_tahap2);
    $result_tahap2 = mysqli_stmt_get_result($stmt_tahap2);
}
$data_tahap2 = mysqli_fetch_all($result_tahap2, MYSQLI_ASSOC);

// Data untuk Security
$result_siap = mysqli_query($conn, "SELECT * FROM sj_manual WHERE status = 'Siap Berangkat' ORDER BY estimasi_keluar ASC");
$data_siap = mysqli_fetch_all($result_siap, MYSQLI_ASSOC);

// Filter data verifikasi sesuai role (dipakai oleh tabel DAN angka badge tab):
// - Role 2       : HANYA yang plat nomornya mengandung kata "batal"
// - Role 1 / 99  : yang normal (TIDAK mengandung kata "batal")
$filtered_siap = [];
foreach ($data_siap as $row_siap) {
    $is_batal_siap = (stripos($row_siap['plat_nomor'] ?? '', 'batal') !== false);
    if ($role === 2) {
        if ($is_batal_siap) { $filtered_siap[] = $row_siap; }
    } else {
        if (!$is_batal_siap) { $filtered_siap[] = $row_siap; }
    }
}

// Ambil data Riwayat Selesai & Batal (Khusus Role 1, 2, 5, dan 99)
if ($role === 1 || $role === 2 || $role === 5 || $role === 99) {
    // Mengambil status 'Selesai' maupun 'Batal' untuk riwayat mereka
    $result_selesai = mysqli_query($conn, "SELECT * FROM sj_manual WHERE status IN ('Selesai', 'Batal') ORDER BY updated_at DESC LIMIT 100");
} else {
    // Untuk role lain, tetap batasi hanya status 'Selesai' dan sesuai departemen mereka
    $stmt_selesai = mysqli_prepare($conn, "SELECT * FROM sj_manual WHERE status = 'Selesai' AND departemen = ? ORDER BY updated_at DESC LIMIT 100");
    mysqli_stmt_bind_param($stmt_selesai, "s", $user_bagian);
    mysqli_stmt_execute($stmt_selesai);
    $result_selesai = mysqli_stmt_get_result($stmt_selesai);
}
$data_selesai = mysqli_fetch_all($result_selesai, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D-OUT | SJ Manual</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/sj_manual.css">
    <style>
        .card { border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table-scroll-container {
            max-height: 380px;
            overflow-y: auto;
            position: relative;
        }
        .table-scroll-container thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
        }
        .table th, .table td { padding: 0.4rem 0.6rem; font-size: 0.85rem; }
        .form-control, .form-select { font-size: 0.85rem; padding: 0.35rem 0.6rem; }
        .nav-tabs .nav-link { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
        
        /* Lebar kustom agar pas setara ukuran 8 karakter */
        .input-8char {
            max-width: 135px;
        }
    </style>
</head>
<body>

<div class="container-fluid px-2 py-2">
    
    <!-- Header & Universal Search -->
    <div class="row align-items-center mb-2 px-1 g-2">
        <div class="col-md-5">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-file-pen text-primary me-2"></i> Surat Jalan Manual</h5>
        </div>
        <div class="col-md-7">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="globalSearch" class="form-control" placeholder="Cari data apa saja di semua tabel aktif...">
                <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-success alert-dismissible fade show py-1 px-2 small mb-2" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($pesan); ?>
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show py-1 px-2 small mb-2" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error); ?>
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<!-- NAV TABS -->
    <ul class="nav nav-tabs mb-2" id="sjTab" role="tablist">
        <?php if ($role === 2 || $role === 99): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tahap1-tab" data-bs-toggle="tab" data-bs-target="#tahap1" type="button" role="tab">
                <i class="fas fa-plus-circle me-1"></i> Input Logistik
            </button>
        </li>
        <?php endif; ?>

        <?php if ($role === 2 || $role === 3 || $role === 4 || $role === 5 || $role === 99): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= (($role === 3 || $role === 4) && $role !== 99 && $role !== 2) ? 'active' : ''; ?>" id="tahap2-tab" data-bs-toggle="tab" data-bs-target="#tahap2" type="button" role="tab">
                <i class="fas fa-clock me-1"></i> Jadwal & Kendaraan <span class="badge <?= ($jumlah_belum_dijadwalkan > 0) ? 'bg-warning text-dark' : 'bg-secondary'; ?> ms-1"><?= $jumlah_belum_dijadwalkan; ?></span>
            </button>
        </li>
        <?php endif; ?>

        <?php if ($role === 1 || $role === 2 || $role === 99): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($role === 1 && $role !== 99) ? 'show active' : ''; ?>" id="tahap3-tab" data-bs-toggle="tab" data-bs-target="#tahap3" type="button" role="tab">
                <i class="fas fa-shield-alt me-1"></i> Verifikasi Keluar <span class="badge <?= (count($filtered_siap) > 0) ? 'bg-warning text-dark' : 'bg-secondary'; ?> ms-1"><?= count($filtered_siap); ?></span>
            </button>
        </li>
        <?php endif; ?>

        <?php if ($role === 1 || $role === 2 || $role === 3 || $role === 4 || $role === 5 || $role === 99): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="riwayat-tab" data-bs-toggle="tab" data-bs-target="#riwayat" type="button" role="tab">
                <i class="fas fa-history me-1"></i> Riwayat
            </button>
        </li>
        <?php endif; ?>

    </ul>

    <!-- TAB CONTENT -->
    <div class="tab-content" id="sjTabContent">
        
    <!-- TAHAP 1: INPUT LOGISTIK -->
    <?php if ($role === 2 || $role === 5 || $role === 99): ?>
    <div class="tab-pane fade show active" id="tahap1" role="tabpanel">
        <div class="card border-0 mb-2">
            <div class="card-body p-2">
                <h6 class="fw-bold mb-2 text-secondary border-bottom pb-1" style="font-size: 0.9rem;">Form Pengambilan Surat Jalan Manual</h6>
                <form method="POST" action="">
                    <input type="hidden" name="aksi" value="tambah_logistik">
                    
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">No. Surat Jalan</label>
                                <input type="text" name="nomor_sj" class="form-control form-control-sm input-8char <?= $nomor_terkunci ? 'bg-light' : ''; ?>" maxlength="8" value="<?= htmlspecialchars($suggested_sj); ?>" placeholder="<?= ($role === 2 && $db_kosong) ? 'No. SJ pertama' : ''; ?>" <?= $nomor_terkunci ? 'readonly' : 'required autofocus'; ?>>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">Departemen</label>
                                <select name="departemen" class="form-select form-select-sm input-8char" required>
                                    <option value="" disabled selected>-- Pilih --</option>
                                    <?php 
                                    mysqli_data_seek($result_bagian, 0);
                                    while ($b = mysqli_fetch_assoc($result_bagian)): 
                                    ?>
                                        <option value="<?= htmlspecialchars($b['bagian']); ?>"><?= htmlspecialchars($b['bagian']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">Keterangan</label>
                                <textarea name="keterangan" class="form-control form-control-sm" rows="3" required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-1">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm px-3 py-1"><i class="fas fa-save me-1"></i> Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-2">
                <h6 class="fw-bold mb-2 text-secondary border-bottom pb-1" style="font-size: 0.9rem;">Daftar Surat Jalan Manual</h6>
                <div class="table-responsive table-scroll-container">
                    <table class="table table-bordered table-striped table-sm align-middle searchable-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40" class="text-center">No</th>
                                <th>Nomor SJ</th>
                                <th>Departemen</th>
                                <th>Keterangan</th>
                                <th>Status</th>
                                <th width="60" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_sj = mysqli_query($conn, "SELECT * FROM sj_manual WHERE status NOT IN ('Selesai', 'Batal') ORDER BY id DESC");
                            $no = 1;
                            if (mysqli_num_rows($query_sj) > 0):
                                while ($row = mysqli_fetch_assoc($query_sj)): 
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($row['nomor_sj']); ?></td>
                                <td><?= htmlspecialchars($row['departemen']); ?></td>
                                <td><?= htmlspecialchars($row['keterangan']); ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']); ?></span></td>
                                <td class="text-center">
                                    <?php if ($role === 99): ?>
                                    <a href="sj_manual/hapus.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-1" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-2">Tidak ada Surat Jalan yang sedang berjalan.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAHAP 2: JADWAL & KENDARAAN -->
    <?php if ($role === 2 || $role === 3 || $role === 4 || $role === 5 || $role === 99): ?>
    <div class="tab-pane fade <?= (((($role === 3 || $role === 4)) && $role !== 99 && $role !== 2) ? 'show active' : (($role === 2 || $role === 5 || $role === 99) ? '' : '')); ?>" id="tahap2" role="tabpanel">
        <div class="card border-0">
            <div class="card-body p-2">
                <h6 class="fw-bold mb-2 text-secondary border-bottom pb-1" style="font-size: 0.9rem;">
                    Daftar Jadwal & Kendaraan 
                    <?php if ($role !== 99): ?>
                        <span class="text-muted fw-normal small">- Bagian: <?= htmlspecialchars($user_bagian); ?></span>
                    <?php endif; ?>
                </h6>
                
                <div class="table-responsive table-scroll-container">
                    <table class="table table-hover table-bordered align-middle searchable-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 14%;">No. SJ</th>
                                <th style="width: 13%;">Departemen</th>
                                <th style="width: 30%;">Keterangan</th>
                                <th style="width: 15%;">Plat Nomor</th>
                                <th style="width: 16%;">Estimasi Keluar</th>
                                <th class="text-center" style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_tahap2)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-2 small">Tidak ada data untuk bagian Anda.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data_tahap2 as $row): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($row['nomor_sj']); ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['departemen']); ?></span></td>
                                        <td class="small"><?= htmlspecialchars($row['keterangan']); ?></td>
                                        <td>
                                            <?php if (!empty($row['plat_nomor'])): ?>
                                                <span class="badge bg-dark"><?= htmlspecialchars($row['plat_nomor']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small italic">Belum diisi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['estimasi_keluar'])): ?>
                                                <span class="small"><?= htmlspecialchars($row['estimasi_keluar']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small italic">Belum diatur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($row['status'] === 'Belum Dijadwalkan'): ?>
                                                <button class="btn btn-xs btn-success py-1 px-2 text-white" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalJadwal<?= $row['id']; ?>">
                                                    <i class="fas fa-calendar-alt me-1"></i> Jadwal
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success" style="font-size: 0.7rem;">Siap Berangkat</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Modal Input Jadwal -->
                                    <div class="modal fade" id="modalJadwal<?= $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header py-2 px-3 bg-light">
                                                        <h6 class="modal-title fw-bold">SJ: <?= htmlspecialchars($row['nomor_sj']); ?></h6>
                                                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-3 text-start">
                                                        <input type="hidden" name="aksi" value="update_user">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Estimasi Keluar</label>
                                                            <input type="date" name="estimasi_keluar" class="form-control form-control-sm" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Plat Nomor Kendaraan</label>
                                                            <input type="text" name="plat_nomor" class="form-control form-control-sm" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Keterangan</label>
                                                            <textarea name="keterangan" class="form-control form-control-sm" rows="3" required><?= htmlspecialchars($row['keterangan']); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-2 px-3 bg-light">
                                                        <button type="button" class="btn btn-secondary btn-xs" data-bs-dismiss="modal" style="font-size: 0.8rem;">Batal</button>
                                                        <button type="submit" class="btn btn-primary btn-xs" style="font-size: 0.8rem;">Simpan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAHAP 3: VERIFIKASI SECURITY / PEMBATALAN -->
    <?php if ($role === 1 || $role === 2 || $role === 5 || $role === 99): ?>
    <div class="tab-pane fade <?= (($role === 1 && $role !== 99) ? 'show active' : ''); ?>" id="tahap3" role="tabpanel">
        <div class="card border-0">
            <div class="card-body p-2">
                <h6 class="fw-bold mb-2 text-secondary border-bottom pb-1" style="font-size: 0.9rem;">
                    <?= ($role === 2) ? 'Konfirmasi Pembatalan Kendaraan' : 'Pos Security - Verifikasi Kendaraan Keluar'; ?>
                </h6>
                
                <div class="table-responsive table-scroll-container">
                    <table class="table table-hover table-bordered align-middle searchable-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. SJ</th>
                                <th>Departemen</th>
                                <th>Estimasi Keluar</th>
                                <th>Plat Nomor</th>
                                <th>Keterangan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filtered_siap)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-2 small">Belum ada data untuk verifikasi.</td></tr>
                            <?php else: ?>
                                <?php foreach ($filtered_siap as $row): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($row['nomor_sj']); ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['departemen']); ?></span></td>
                                        <td class="small"><?= htmlspecialchars($row['estimasi_keluar']); ?></td>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($row['plat_nomor'] !== '' ? $row['plat_nomor'] : '-'); ?></span></td>
                                        <td class="small"><?= htmlspecialchars($row['keterangan']); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-xs py-1 px-2 text-white" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalSecurity<?= $row['id']; ?>">
                                                <i class="fas fa-shield-alt me-1"></i> <?= ($role === 2) ? 'Konfirmasi Batal' : 'Verifikasi Keluar'; ?>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal Popup Verifikasi -->
                                    <div class="modal fade" id="modalSecurity<?= $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header py-2 px-3 bg-light">
                                                        <h6 class="modal-title fw-bold">Verifikasi SJ: <?= htmlspecialchars($row['nomor_sj']); ?></h6>
                                                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-3 text-start">
                                                        <input type="hidden" name="aksi" value="verifikasi_security">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small text-muted mb-1">Departemen: <strong><?= htmlspecialchars($row['departemen']); ?></strong></label>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">
                                                                <?= ($role === 2) ? 'Ketik "Batal" untuk Konfirmasi' : 'Plat Nomor / Status'; ?>
                                                            </label>
                                                            <input type="text" name="plat_nomor_baru" class="form-control form-control-sm text-uppercase fw-bold" value="<?= ($role === 2) ? 'Batal' : htmlspecialchars($row['plat_nomor']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-2 px-3 bg-light">
                                                        <button type="button" class="btn btn-secondary btn-xs" data-bs-dismiss="modal" style="font-size: 0.8rem;">Batal</button>
                                                        <button type="submit" class="btn btn-danger btn-xs" style="font-size: 0.8rem;" onclick="return confirm('Konfirmasi dan simpan perubahan?');">
                                                            <i class="fas fa-check-circle me-1"></i> Ya, Simpan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB RIWAYAT SELESAI -->
    <?php if ($role === 1 || $role === 2 || $role === 3 || $role === 4 || $role === 5 || $role === 99): ?>
    <div class="tab-pane fade" id="riwayat" role="tabpanel">
        <div class="card border-0">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-secondary border-bottom pb-1" style="font-size: 0.9rem;">Riwayat Pengiriman & Pembatalan</h6>
                    <?php if ($role === 2 || $role === 5 || $role === 99): ?>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalExportExcel">
                        <i class="fas fa-file-excel me-1"></i> Export ke Excel
                    </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive table-scroll-container">
                    <table class="table table-striped table-bordered align-middle searchable-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. SJ</th>
                                <th>Status</th>
                                <th>Departemen</th>
                                <th>Plat Nomor</th>
                                <th>Keterangan</th>
                                <th>Waktu Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_selesai)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-2 small">Belum ada riwayat untuk bagian Anda.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data_selesai as $row): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['nomor_sj']); ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Selesai'): ?>
                                                <span class="badge bg-success">Selesai</span>
                                            <?php elseif ($row['status'] === 'Batal'): ?>
                                                <span class="badge bg-danger">Batal</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($row['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['departemen']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['plat_nomor'] !== '' ? $row['plat_nomor'] : '-'); ?></span></td>
                                        <td class="small"><?= htmlspecialchars(($row['keterangan'] ?? '') !== '' ? $row['keterangan'] : '-'); ?></td>
                                        <td class="text-muted text-nowrap" style="font-size: 0.75rem;"><?= htmlspecialchars($row['updated_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Pop-up Export Excel -->
    <?php if ($role === 2 || $role === 5 || $role === 99): ?>
    <div class="modal fade" id="modalExportExcel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="sj_manual/export_excel.php" method="GET" target="_blank">
                    <div class="modal-header py-2 bg-light">
                        <h6 class="modal-title fw-bold"><i class="fas fa-file-excel text-success me-1"></i> Export Data ke Excel</h6>
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        <p class="text-muted small mb-3">Silakan tentukan rentang Nomor Surat Jalan yang ingin di-export ke dalam file Excel.</p>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">No. SJ Awal (Dari)</label>
                            <input type="text" name="start_sj" class="form-control form-control-sm" placeholder="Contoh: SJ0001" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">No. SJ Akhir (Sampai)</label>
                            <input type="text" name="end_sj" class="form-control form-control-sm" placeholder="Contoh: SJ0050" required>
                        </div>
                    </div>
                    <div class="modal-footer py-2 bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-download me-1"></i> Unduh Excel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($reset_post)): ?>
<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }</script>
<?php endif; ?>
<script>
    document.getElementById('globalSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let activeTabPane = document.querySelector('.tab-pane.fade.show.active');
        if (!activeTabPane) return;
        
        let table = activeTabPane.querySelector('.searchable-table');
        if (!table) return;
        
        let rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            let rowText = rows[i].textContent || rows[i].innerText;
            if (rowText.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    });

    function clearSearch() {
        let input = document.getElementById('globalSearch');
        input.value = '';
        input.dispatchEvent(new Event('keyup'));
    }
</script>
</body>
</html>