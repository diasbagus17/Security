<?php
// Pastikan file diakses melalui index.php dan sudah melewati guard
if (!defined('__DIR__') && !isset($conn)) {
    require_once __DIR__ . '/config/guard.php';
}
require_login([99]); // Hanya Administrator yang boleh melihat log aktivitas

// Ambil nilai filter dari parameter GET
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$filter_aksi    = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$filter_modul   = isset($_GET['modul']) ? $_GET['modul'] : '';

// Susun query dasar dengan filter dinamis (Ditingkatkan batasnya agar scroll optimal)
$query = "SELECT * FROM log_aktivitas WHERE 1=1";

if (!empty($filter_tanggal)) {
    $tgl_aman = mysqli_real_escape_string($conn, $filter_tanggal);
    $query .= " AND DATE(waktu) = '$tgl_aman'";
}

if (!empty($filter_aksi)) {
    $aksi_aman = mysqli_real_escape_string($conn, $filter_aksi);
    $query .= " AND aksi = '$aksi_aman'";
}

if (!empty($filter_modul)) {
    $modul_aman = mysqli_real_escape_string($conn, $filter_modul);
    $query .= " AND modul = '$modul_aman'";
}

// Urutkan dari yang terbaru dan batasi maksimal 500 baris agar performa tabel tetap ringan
$query .= " ORDER BY waktu DESC LIMIT 500";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid px-0">
    <!-- Header Minimalis & Tombol Hapus Masal -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-primary me-2"></i> Log Aktivitas Sistem</h4>
        </div>
        <div>
            <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalHapusMasal">
                <i class="fas fa-trash-alt me-1"></i> Hapus Log Berdasarkan Tanggal
            </button>
        </div>
    </div>

    <!-- FORM FILTER PENCARIAN -->
    <div class="card shadow-sm border-0 rounded-2 mb-3">
        <div class="card-body p-2">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="log_aktivitas">

                <!-- Filter Tanggal -->
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= h($filter_tanggal); ?>">
                </div>

                <!-- Filter Modul / Menu -->
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="modul">
                        <option value="">-- Semua Menu --</option>
                        <option value="Delivery Order" <?= ($filter_modul === 'Delivery Order') ? 'selected' : ''; ?>>Delivery Order</option>
                        <option value="SJ Manual" <?= ($filter_modul === 'SJ Manual') ? 'selected' : ''; ?>>SJ Manual</option>
                    </select>
                </div>

                <!-- Filter Aksi -->
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="aksi">
                        <option value="">-- Semua Aksi --</option>
                        <option value="TAMBAH" <?= ($filter_aksi === 'TAMBAH') ? 'selected' : ''; ?>>TAMBAH</option>
                        <option value="EDIT" <?= ($filter_aksi === 'EDIT') ? 'selected' : ''; ?>>EDIT</option>
                        <option value="HAPUS" <?= ($filter_aksi === 'HAPUS') ? 'selected' : ''; ?>>HAPUS</option>
                        <option value="IMPORT" <?= ($filter_aksi === 'IMPORT') ? 'selected' : ''; ?>>IMPORT</option>
                        <option value="UPDATE" <?= ($filter_aksi === 'UPDATE') ? 'selected' : ''; ?>>UPDATE</option>
                        <option value="VERIFIKASI" <?= ($filter_aksi === 'VERIFIKASI') ? 'selected' : ''; ?>>VERIFIKASI</option>
                    </select>
                </div>

                <!-- Tombol Cari & Reset -->
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="index.php?page=log_aktivitas" class="btn btn-outline-secondary btn-sm px-2">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL DATA LOG (Dengan Scroll Vertikal dan Header Sticky) -->
    <div class="card shadow-sm border-0 rounded-2">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-striped table-hover table-sm align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-dark text-uppercase sticky-top" style="font-size: 11px; z-index: 1;">
                        <tr>
                            <th width="4%" class="text-center py-2">No</th>
                            <th width="15%" class="py-2">Waktu</th>
                            <th width="12%" class="py-2">User</th>
                            <th width="15%" class="py-2">Modul</th>
                            <th width="10%" class="text-center py-2">Aksi</th>
                            <th class="py-2">Deskripsi Aktivitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="text-center text-muted"><?= $no++; ?></td>
                                    <td class="text-secondary text-nowrap"><?= h($row['waktu']); ?></td>
                                    <td><span class="fw-semibold text-dark"><?= h($row['username']); ?></span></td>
                                    <td><span class="badge bg-light text-dark border"><?= h($row['modul'] ?? 'Delivery Order'); ?></span></td>
                                    <td class="text-center">
                                        <?php 
                                            $aksi = strtoupper($row['aksi']);
                                            $badge_bg = 'bg-secondary';
                                            if ($aksi === 'TAMBAH') $badge_bg = 'bg-success';
                                            elseif ($aksi === 'EDIT') $badge_bg = 'bg-warning text-dark';
                                            elseif ($aksi === 'HAPUS') $badge_bg = 'bg-danger';
                                            elseif ($aksi === 'IMPORT') $badge_bg = 'bg-info text-dark';
                                            elseif ($aksi === 'UPDATE') $badge_bg = 'bg-primary';
                                            elseif ($aksi === 'VERIFIKASI') $badge_bg = 'bg-dark';
                                        ?>
                                        <span class="badge <?= $badge_bg; ?>" style="font-size: 10px; padding: 3px 6px;"><?= h($aksi); ?></span>
                                    </td>
                                    <td class="text-dark"><?= h($row['deskripsi']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Tidak ada catatan log aktivitas yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL HAPUS MASAL BERDASARKAN RENTANG TANGGAL ================= -->
<div class="modal fade" id="modalHapusMasal" tabindex="-1" aria-labelledby="modalHapusMasalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="hapus_log_masal.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus seluruh log dalam rentang tanggal tersebut? Data yang dihapus tidak dapat dikembalikan.');">
                <?= csrf_field(); ?>
                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title fs-6" id="modalHapusMasalLabel"><i class="fas fa-trash-alt me-1"></i> Hapus Log Masal Berdasarkan Tanggal</h6>
                    <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">DARI TANGGAL</label>
                            <input type="date" name="tanggal_mulai" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">SAMPAI TANGGAL</label>
                            <input type="date" name="tanggal_selesai" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="alert alert-warning p-2 mb-0 mt-2" style="font-size: 11px;">
                        <i class="fas fa-exclamation-triangle me-1"></i> Seluruh data log aktivitas dalam rentang tanggal yang dipilih akan dihapus permanen dari database.
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Hapus Massal</button>
                </div>
            </form>
        </div>
    </div>
</div>