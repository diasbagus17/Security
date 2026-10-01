<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../config/guard.php';
}

// Pastikan jam (date('H:i')) sesuai waktu Indonesia, bukan waktu default server
date_default_timezone_set('Asia/Jakarta');

// Helper escape HTML (jaga-jaga jika belum ada di guard.php)
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

// Helper tampilkan jam format HH:MM
function jam_fmt($v) {
    return empty($v) ? '-' : substr((string) $v, 0, 5);
}

$role = isset($_SESSION['role']) ? (int) $_SESSION['role'] : 0;
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 0);

$user_bagian = '';
$stmt_user = mysqli_prepare($conn, "SELECT bagian FROM users WHERE id = ?");
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "i", $current_user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($row_user = mysqli_fetch_assoc($result_user)) {
        $user_bagian = $row_user['bagian'];
    }
    mysqli_stmt_close($stmt_user);
}

$is_allowed_input  = in_array($role, [1, 99], true);
$is_allowed_action = in_array($role, [1, 99], true);
$is_allowed_delete = in_array($role, [1, 99], true);
$is_super_admin    = ($role === 99);

// Whitelist tab (mencegah injeksi lewat parameter URL)
$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], ['aktif', 'riwayat'], true)) ? $_GET['tab'] : 'aktif';
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

$flash_error = '';
$old = []; // data form lama jika simpan gagal

// =====================================================================
// HANDLE POST (masuk / keluar / tambah)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Paksa mysqli melempar exception agar error DB tertangkap & tampil jelas
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // ---------- Proses kendaraan masuk ----------
        if (isset($_POST['proses_masuk']) && $is_allowed_action) {
            $id = intval($_POST['kendaraan_id'] ?? 0);
            $jam_masuk = trim($_POST['jam_masuk'] ?? '');
            if ($id <= 0 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_masuk)) {
                throw new Exception('Data jam masuk tidak valid.');
            }

            $stmt = mysqli_prepare($conn, "UPDATE kendaraan SET jam_masuk = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $jam_masuk, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            try {
                if (function_exists('catat_log')) {
                    catat_log($conn, 'Kendaraan', 'Update', 'Memproses kendaraan masuk ID: ' . $id . ' pada jam ' . $jam_masuk);
                }
            } catch (Throwable $e) { /* log gagal tidak boleh membatalkan proses */ }

            echo "<script>window.location='index.php?page=kendaraan&tab=aktif&notif=simpan';</script>";
            exit;
        }

        // ---------- Proses kendaraan keluar ----------
        if (isset($_POST['proses_keluar']) && $is_allowed_action) {
            $id = intval($_POST['kendaraan_id'] ?? 0);
            $jam_keluar = trim($_POST['jam_keluar'] ?? '');
            if ($id <= 0 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_keluar)) {
                throw new Exception('Data jam keluar tidak valid.');
            }

            $stmt = mysqli_prepare($conn, "UPDATE kendaraan SET jam_keluar = ?, keterangan_keluar = 'Keluar' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $jam_keluar, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            try {
                if (function_exists('catat_log')) {
                    catat_log($conn, 'Kendaraan', 'Update', 'Memproses kendaraan keluar ID: ' . $id . ' pada jam ' . $jam_keluar);
                }
            } catch (Throwable $e) { }

            echo "<script>window.location='index.php?page=kendaraan&tab=aktif&notif=simpan';</script>";
            exit;
        }

        // ---------- Tambah kendaraan ----------
        if (isset($_POST['tambah_kendaraan']) && $is_allowed_input) {
            $old = $_POST; // simpan untuk mengisi ulang form bila gagal

            $tanggal       = trim($_POST['tanggal'] ?? '');
            $plat_no       = strtoupper(trim($_POST['plat_no'] ?? ''));
            $ekspedisi     = trim($_POST['ekspedisi'] ?? '');
            $sopir         = trim($_POST['sopir'] ?? '');
            $jenis         = trim($_POST['jenis'] ?? '');
            $tujuan_bagian = trim($_POST['tujuan_bagian'] ?? '');
            $status        = trim($_POST['status'] ?? '');
            $jam_daftar    = trim($_POST['jam_daftar'] ?? '');
            $keterangan_keluar = 'Belum Keluar';

            if ($tanggal === '' || $plat_no === '' || $ekspedisi === '' || $sopir === '' || $jenis === '' || $tujuan_bagian === '' || $status === '' || $jam_daftar === '') {
                throw new Exception('Semua field utama wajib diisi.');
            }
            if (!in_array($status, ['Bongkar', 'Muat'], true)) {
                throw new Exception('Status harus Bongkar atau Muat.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam_daftar)) {
                throw new Exception('Format tanggal / jam daftar tidak valid.');
            }

            // Simpan kendaraan + detail dalam satu transaksi (semua berhasil atau tidak sama sekali)
            mysqli_begin_transaction($conn);

            $stmt = mysqli_prepare($conn, "INSERT INTO kendaraan (tanggal, plat_no, ekspedisi, sopir, jenis, tujuan_bagian, status, jam_daftar, jam_masuk, keterangan_keluar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)");
            mysqli_stmt_bind_param($stmt, "sssssssss", $tanggal, $plat_no, $ekspedisi, $sopir, $jenis, $tujuan_bagian, $status, $jam_daftar, $keterangan_keluar);
            mysqli_stmt_execute($stmt);
            $kendaraan_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if ($status === 'Bongkar') {
                $list_sj = $_POST['no_surat_jalan'] ?? [];
                $list_jb = $_POST['jenis_barang'] ?? [];
                $list_jm = $_POST['jumlah_barang'] ?? [];

                if (is_array($list_jb) && count($list_jb) > 0) {
                    $stmt_detail = mysqli_prepare($conn, "INSERT INTO kendaraan_detail_barang (kendaraan_id, no_surat_jalan, jenis_barang, jumlah) VALUES (?, ?, ?, ?)");

                    foreach ($list_jb as $i => $nama) {
                        $nama_barang    = trim($nama);
                        $jml_barang     = trim($list_jm[$i] ?? '');
                        $no_surat_jalan = trim($list_sj[$i] ?? '');

                        // Lewati baris yang benar-benar kosong
                        if ($nama_barang === '' && $jml_barang === '' && $no_surat_jalan === '') {
                            continue;
                        }
                        if ($nama_barang === '' || $jml_barang === '') {
                            throw new Exception('Jenis barang dan jumlah wajib diisi pada setiap baris rincian.');
                        }

                        mysqli_stmt_bind_param($stmt_detail, "isss", $kendaraan_id, $no_surat_jalan, $nama_barang, $jml_barang);
                        mysqli_stmt_execute($stmt_detail);
                    }
                    mysqli_stmt_close($stmt_detail);
                }
            }

            mysqli_commit($conn);

            try {
                if (function_exists('catat_log')) {
                    catat_log($conn, 'Kendaraan', 'Tambah', 'Menambah kendaraan daftar plat nomor: ' . $plat_no);
                }
            } catch (Throwable $e) { }

            echo "<script>window.location='index.php?page=kendaraan&tab=aktif&notif=simpan';</script>";
            exit;
        }
    } catch (Throwable $e) {
        try { mysqli_rollback($conn); } catch (Throwable $e2) { }
        $flash_error = $e->getMessage();
    }
}

// =====================================================================
// AMBIL DATA
// =====================================================================
$data_aktif = [];
$data_riwayat = [];
$search_query = "%" . $keyword . "%";

if (in_array($role, [1, 99], true)) {
    $stmt_aktif = mysqli_prepare($conn, "SELECT * FROM kendaraan WHERE keterangan_keluar = 'Belum Keluar' AND (plat_no LIKE ? OR ekspedisi LIKE ? OR sopir LIKE ? OR jenis LIKE ? OR tujuan_bagian LIKE ? OR status LIKE ?) ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt_aktif, "ssssss", $search_query, $search_query, $search_query, $search_query, $search_query, $search_query);
    mysqli_stmt_execute($stmt_aktif);
    $res_aktif = mysqli_stmt_get_result($stmt_aktif);
    if ($res_aktif) $data_aktif = mysqli_fetch_all($res_aktif, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_aktif);

    $stmt_riwayat = mysqli_prepare($conn, "SELECT * FROM kendaraan WHERE keterangan_keluar = 'Keluar' AND (plat_no LIKE ? OR ekspedisi LIKE ? OR sopir LIKE ? OR jenis LIKE ? OR tujuan_bagian LIKE ? OR status LIKE ?) ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt_riwayat, "ssssss", $search_query, $search_query, $search_query, $search_query, $search_query, $search_query);
    mysqli_stmt_execute($stmt_riwayat);
    $res_riwayat = mysqli_stmt_get_result($stmt_riwayat);
    if ($res_riwayat) $data_riwayat = mysqli_fetch_all($res_riwayat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_riwayat);
} else {
    $stmt_aktif = mysqli_prepare($conn, "SELECT * FROM kendaraan WHERE keterangan_keluar = 'Belum Keluar' AND tujuan_bagian = ? AND (plat_no LIKE ? OR ekspedisi LIKE ? OR sopir LIKE ? OR jenis LIKE ? OR status LIKE ?) ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt_aktif, "ssssss", $user_bagian, $search_query, $search_query, $search_query, $search_query, $search_query);
    mysqli_stmt_execute($stmt_aktif);
    $res_aktif = mysqli_stmt_get_result($stmt_aktif);
    if ($res_aktif) $data_aktif = mysqli_fetch_all($res_aktif, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_aktif);

    $stmt_riwayat = mysqli_prepare($conn, "SELECT * FROM kendaraan WHERE keterangan_keluar = 'Keluar' AND tujuan_bagian = ? AND (plat_no LIKE ? OR ekspedisi LIKE ? OR sopir LIKE ? OR jenis LIKE ? OR status LIKE ?) ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt_riwayat, "ssssss", $user_bagian, $search_query, $search_query, $search_query, $search_query, $search_query);
    mysqli_stmt_execute($stmt_riwayat);
    $res_riwayat = mysqli_stmt_get_result($stmt_riwayat);
    if ($res_riwayat) $data_riwayat = mysqli_fetch_all($res_riwayat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_riwayat);
}

// Ambil SEMUA detail barang dengan 1 query (sebelumnya 1 query per baris = lambat)
$detail_map = [];
$all_ids = array_merge(array_column($data_aktif, 'id'), array_column($data_riwayat, 'id'));
if (!empty($all_ids)) {
    $ids_sql = implode(',', array_map('intval', $all_ids));
    $res_detail = mysqli_query($conn, "SELECT kendaraan_id, no_surat_jalan, jenis_barang, jumlah FROM kendaraan_detail_barang WHERE kendaraan_id IN ($ids_sql)");
    if ($res_detail) {
        while ($d = mysqli_fetch_assoc($res_detail)) {
            $detail_map[$d['kendaraan_id']][] = [
                'no_surat_jalan' => $d['no_surat_jalan'],
                'jenis_barang'   => $d['jenis_barang'],
                'jumlah'         => $d['jumlah'],
            ];
        }
    }
}

$list_bagian = [];
$res_bagian = mysqli_query($conn, "SELECT DISTINCT bagian FROM users WHERE bagian IS NOT NULL AND bagian != '' AND bagian != 'Administrator' ORDER BY bagian ASC");
if ($res_bagian) {
    while ($row_b = mysqli_fetch_assoc($res_bagian)) {
        $list_bagian[] = $row_b['bagian'];
    }
}

// Helper isi ulang form saat simpan gagal
function old_val($old, $key, $default = '') {
    return h($old[$key] ?? $default);
}
function old_sel($old, $key, $val) {
    return (isset($old[$key]) && $old[$key] === $val) ? 'selected' : '';
}

$old_rows = [];
if (!empty($old['jenis_barang']) && is_array($old['jenis_barang'])) {
    foreach ($old['jenis_barang'] as $i => $jb) {
        $old_rows[] = [
            'sj' => $old['no_surat_jalan'][$i] ?? '',
            'jb' => $jb,
            'jm' => $old['jumlah_barang'][$i] ?? '',
        ];
    }
}
if (empty($old_rows)) {
    $old_rows[] = ['sj' => '', 'jb' => '', 'jm' => ''];
}
$old_is_bongkar = (($old['status'] ?? '') === 'Bongkar');

// Pesan notifikasi dari redirect
$notif = $_GET['notif'] ?? '';
$notif_map = [
    'simpan' => ['success', 'Data berhasil disimpan.'],
    'hapus'  => ['success', 'Data berhasil dihapus.'],
    'gagal'  => ['danger',  'Data gagal dihapus atau Anda tidak memiliki izin.'],
];
?>

<link rel="stylesheet" href="css/kendaraan.css">

<div class="container-fluid kendaraan-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0 text-gray-800"><i class="fas fa-truck me-2"></i> Manajemen Kendaraan</h2>
            <?php if (!in_array($role, [1, 99], true)): ?>
                <span class="text-muted small">Bagian: <strong><?= h($user_bagian); ?></strong></span>
            <?php endif; ?>
        </div>
        <?php if ($is_allowed_input): ?>
            <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKendaraan">
                <i class="fas fa-plus me-1"></i> Input Kendaraan Daftar
            </button>
        <?php endif; ?>
    </div>

    <?php if ($flash_error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <strong>Gagal menyimpan:</strong> <?= h($flash_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($notif_map[$notif])): ?>
        <div class="alert alert-<?= $notif_map[$notif][0]; ?> alert-dismissible fade show py-2" role="alert">
            <?= h($notif_map[$notif][1]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form Pencarian Global -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="kendaraan">
                <input type="hidden" name="tab" value="<?= h($active_tab); ?>">
                <div class="col-md-10">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="q" value="<?= h($keyword); ?>" placeholder="Cari Plat No, Ekspedisi, Sopir, Jenis, Tujuan, Status...">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Cari</button>
                    <?php if (!empty($keyword)): ?>
                        <a href="index.php?page=kendaraan&tab=<?= h($active_tab); ?>" class="btn btn-sm btn-secondary" title="Reset"><i class="fas fa-redo"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-2" id="kendaraanTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link py-1 px-3 <?= ($active_tab == 'aktif') ? 'active' : ''; ?>" href="index.php?page=kendaraan&tab=aktif&q=<?= urlencode($keyword); ?>">
                Belum Keluar <span class="badge bg-warning text-dark"><?= count($data_aktif); ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link py-1 px-3 <?= ($active_tab == 'riwayat') ? 'active' : ''; ?>" href="index.php?page=kendaraan&tab=riwayat&q=<?= urlencode($keyword); ?>">
                Riwayat <span class="badge bg-secondary"><?= count($data_riwayat); ?></span>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="kendaraanTabContent">

        <!-- ===================== Tab Kendaraan Belum Keluar ===================== -->
        <div class="tab-pane fade <?= ($active_tab == 'aktif') ? 'show active' : ''; ?>" id="aktif" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive table-scroll-container">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="4%">No</th>
                                    <th width="9%">Tanggal</th>
                                    <th width="10%">Plat No</th>
                                    <th width="11%">Ekspedisi</th>
                                    <th width="11%">Sopir</th>
                                    <th width="10%">Jenis</th>
                                    <th width="10%">Tujuan</th>
                                    <th width="13%">Status</th>
                                    <th width="7%">Daftar</th>
                                    <th width="7%">Masuk</th>
                                    <th width="8%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data_aktif)): ?>
                                    <tr><td colspan="11" class="text-center text-muted py-3">Tidak ada data kendaraan aktif.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data_aktif as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td><?= !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-'; ?></td>
                                        <td><strong><?= h($row['plat_no']); ?></strong></td>
                                        <td><?= h($row['ekspedisi']); ?></td>
                                        <td><?= h($row['sopir']); ?></td>
                                        <td><?= h($row['jenis']); ?></td>
                                        <td><?= h($row['tujuan_bagian']); ?></td>
                                        <td>
                                            <?= h($row['status']); ?>
                                            <?php if ($row['status'] === 'Bongkar'): ?>
                                                <button type="button" class="btn btn-sm btn-info text-white px-2 py-0 ms-1 btn-detail-barang"
                                                        data-plat="<?= h($row['plat_no']); ?>"
                                                        data-barang="<?= h(json_encode($detail_map[$row['id']] ?? [], JSON_UNESCAPED_UNICODE)); ?>"
                                                        title="Lihat Detail Barang">
                                                    <i class="fas fa-list"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h(jam_fmt($row['jam_daftar'] ?? null)); ?></td>
                                        <td><?= h(jam_fmt($row['jam_masuk'] ?? null)); ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($is_allowed_action): ?>
                                                    <?php if (empty($row['jam_masuk'])): ?>
                                                        <button type="button" class="btn btn-warning btn-sm btn-proses-masuk px-2 py-0 text-dark" data-id="<?= (int) $row['id']; ?>" data-plat="<?= h($row['plat_no']); ?>" title="Proses Masuk">
                                                            <i class="fas fa-sign-in-alt"></i> Masuk
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-danger btn-sm btn-proses-keluar px-2 py-0" data-id="<?= (int) $row['id']; ?>" data-plat="<?= h($row['plat_no']); ?>" title="Proses Keluar">
                                                            <i class="fas fa-sign-out-alt"></i> Keluar
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($is_allowed_delete): ?>
                                                    <a href="kendaraan/hapus.php?id=<?= (int) $row['id']; ?>&tab=aktif" class="btn btn-outline-danger btn-sm px-2 py-0" onclick="return confirm('Hapus data ini?')" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== Tab Riwayat Kendaraan ===================== -->
        <div class="tab-pane fade <?= ($active_tab == 'riwayat') ? 'show active' : ''; ?>" id="riwayat" role="tabpanel">
            <div class="card shadow mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive table-scroll-container">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="3%">No</th>
                                    <th width="8%">Tanggal</th>
                                    <th width="9%">Plat No</th>
                                    <th width="10%">Ekspedisi</th>
                                    <th width="10%">Sopir</th>
                                    <th width="9%">Jenis</th>
                                    <th width="8%">Tujuan</th>
                                    <th width="11%">Status</th>
                                    <th width="6%">Daftar</th>
                                    <th width="6%">Masuk</th>
                                    <th width="6%">Keluar</th>
                                    <th width="11%">Ket. Keluar</th>
                                    <th width="6%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data_riwayat)): ?>
                                    <tr><td colspan="13" class="text-center text-muted py-3">Belum ada riwayat kendaraan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data_riwayat as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td><?= !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-'; ?></td>
                                        <td><strong><?= h($row['plat_no']); ?></strong></td>
                                        <td><?= h($row['ekspedisi']); ?></td>
                                        <td><?= h($row['sopir']); ?></td>
                                        <td><?= h($row['jenis']); ?></td>
                                        <td><?= h($row['tujuan_bagian']); ?></td>
                                        <td>
                                            <?= h($row['status']); ?>
                                            <?php if ($row['status'] === 'Bongkar'): ?>
                                                <button type="button" class="btn btn-sm btn-info text-white px-2 py-0 ms-1 btn-detail-barang"
                                                        data-plat="<?= h($row['plat_no']); ?>"
                                                        data-barang="<?= h(json_encode($detail_map[$row['id']] ?? [], JSON_UNESCAPED_UNICODE)); ?>"
                                                        title="Lihat Detail Barang">
                                                    <i class="fas fa-list"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h(jam_fmt($row['jam_daftar'] ?? null)); ?></td>
                                        <td><?= h(jam_fmt($row['jam_masuk'] ?? null)); ?></td>
                                        <td><?= h(jam_fmt($row['jam_keluar'] ?? null)); ?></td>
                                        <td><?= h($row['keterangan_keluar']); ?></td>
                                        <td>
                                            <?php if ($is_super_admin): ?>
                                                <a href="kendaraan/hapus.php?id=<?= (int) $row['id']; ?>&tab=riwayat" class="btn btn-outline-danger btn-sm px-2 py-0" onclick="return confirm('Hapus riwayat ini?')" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Barang (satu modal dipakai bersama, diisi lewat JS) -->
<div class="modal fade" id="modalDetailBarang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-box-open me-2"></i>Rincian Barang Bongkar - <span id="detail_plat_no"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="8%">No</th>
                            <th>No Surat Jalan</th>
                            <th>Jenis Barang</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody id="body-table-detail-barang"></tbody>
                </table>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Input Kendaraan Daftar -->
<?php if ($is_allowed_input): ?>
<div class="modal fade" id="modalTambahKendaraan" tabindex="-1" aria-hidden="true" <?= (isset($_POST['tambah_kendaraan']) && $flash_error !== '') ? 'data-auto-open="1"' : ''; ?>>
    <div class="modal-dialog modal-lg">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-truck-loading me-2"></i>Input Kendaraan Daftar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">Tanggal</label>
                            <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= old_val($old, 'tanggal', date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">Jam Daftar</label>
                            <input type="time" class="form-control form-control-sm" name="jam_daftar" value="<?= old_val($old, 'jam_daftar', date('H:i')); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="form-label small">Plat Nomor</label>
                            <input type="text" class="form-control form-control-sm" name="plat_no" value="<?= old_val($old, 'plat_no'); ?>" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label small">Ekspedisi</label>
                            <input type="text" class="form-control form-control-sm" name="ekspedisi" value="<?= old_val($old, 'ekspedisi'); ?>" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label small">Sopir</label>
                            <input type="text" class="form-control form-control-sm" name="sopir" value="<?= old_val($old, 'sopir'); ?>" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label small">Jenis Kendaraan</label>
                            <select class="form-select form-select-sm" name="jenis" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach (['Pick-up', 'Box / Engkel / CDD', 'Wingbox', 'Kontainer'] as $jn): ?>
                                    <option value="<?= h($jn); ?>" <?= old_sel($old, 'jenis', $jn); ?>><?= h($jn); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">Tujuan Bagian</label>
                            <select class="form-select form-select-sm" name="tujuan_bagian" required>
                                <option value="">-- Pilih Bagian --</option>
                                <?php foreach ($list_bagian as $bg): ?>
                                    <option value="<?= h($bg); ?>" <?= old_sel($old, 'tujuan_bagian', $bg); ?>><?= h($bg); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" id="status_kendaraan" name="status" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="Bongkar" <?= old_sel($old, 'status', 'Bongkar'); ?>>Bongkar</option>
                                <option value="Muat" <?= old_sel($old, 'status', 'Muat'); ?>>Muat</option>
                            </select>
                        </div>
                    </div>

                    <div id="wrapper-detail-barang" class="card border-warning mb-2 <?= $old_is_bongkar ? '' : 'd-none'; ?>">
                        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-1">
                            <span class="fw-bold small">Rincian Surat Jalan, Jenis & Jumlah Barang (Bongkar)</span>
                            <button type="button" class="btn btn-sm btn-dark py-0 px-2" id="tambahBarisBarang"><i class="fas fa-plus"></i> Tambah</button>
                        </div>
                        <div class="card-body bg-light py-2">
                            <div id="container-baris-barang">
                                <?php foreach ($old_rows as $ri => $r): ?>
                                <div class="row input-barang-row mb-2 align-items-center">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" name="no_surat_jalan[]" placeholder="No Surat Jalan" value="<?= h($r['sj']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" name="jenis_barang[]" placeholder="Nama Jenis Barang" value="<?= h($r['jb']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" name="jumlah_barang[]" placeholder="Jumlah (Cth: 10 kg)" value="<?= h($r['jm']); ?>">
                                    </div>
                                    <div class="col-md-1 text-center">
                                        <?php if ($ri > 0): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-baris"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_kendaraan" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Input Jam Masuk Kendaraan -->
<?php if ($is_allowed_action): ?>
<div class="modal fade" id="modalProsesMasuk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-sign-in-alt me-2"></i>Proses Kendaraan Masuk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="kendaraan_id" id="masuk_kendaraan_id">
                    <div class="mb-2">
                        <label class="form-label small">Plat Nomor Kendaraan</label>
                        <input type="text" class="form-control form-control-sm fw-bold" id="masuk_plat_no" readonly>
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label small">Jam Masuk</label>
                        <input type="time" class="form-control form-control-sm" id="jam_masuk" name="jam_masuk" value="<?= date('H:i'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="proses_masuk" class="btn btn-warning btn-sm text-dark">Simpan & Proses Masuk</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Input Jam Keluar Kendaraan -->
<div class="modal fade" id="modalProsesKeluar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-sign-out-alt me-2"></i>Proses Kendaraan Keluar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="kendaraan_id" id="keluar_kendaraan_id">
                    <div class="mb-2">
                        <label class="form-label small">Plat Nomor Kendaraan</label>
                        <input type="text" class="form-control form-control-sm fw-bold" id="keluar_plat_no" readonly>
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label small">Jam Keluar</label>
                        <input type="time" class="form-control form-control-sm" id="jam_keluar" name="jam_keluar" value="<?= date('H:i'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="proses_keluar" class="btn btn-danger btn-sm">Simpan & Proses Keluar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="js/kendaraan.js"></script>