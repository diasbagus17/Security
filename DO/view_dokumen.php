<?php
require_once __DIR__ . '/../config/guard.php';
require_login([1, 2, 3, 5, 99]);

// Hak hapus: role 99 bebas; role 5 hanya data yang dicatat dalam 7 hari terakhir
$role_hapus       = (int) ($_SESSION['role'] ?? 0);
$boleh_hapus_menu = in_array($role_hapus, [5, 99], true);
$batas_hapus_hari = 7;
$batas_hapus_tgl  = date('Y-m-d', strtotime("-$batas_hapus_hari days"));

// Parameter Pencarian & Filter
$cari        = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$tgl_dokumen  = isset($_GET['tgl_dokumen']) ? trim($_GET['tgl_dokumen']) : '';
$tgl_catat    = isset($_GET['tgl_catat']) ? trim($_GET['tgl_catat']) : '';

$conditions = [];
$params = [];
$types = '';

// 1. Filter Pencarian Teks Bebas (DO, No Urut, Plat, Sopir, No Timbang, No Segel, Status)
if ($cari !== '') {
    $conditions[] = "(nomor_do LIKE ? OR nomor_urut LIKE ? OR plat_nomor LIKE ? OR nama_sopir LIKE ? OR no_timbang LIKE ? OR no_segel LIKE ? OR tanggal_dokumen LIKE ? OR status LIKE ?)";
    $likeParam = "%$cari%";
    $types .= 'ssssssss';
    array_push($params, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam);
}

// 2. Filter Pencarian Berdasarkan Tanggal Dokumen
if ($tgl_dokumen !== '') {
    $formatted_tgl_dokumen = date('d/m/Y', strtotime($tgl_dokumen));
    $conditions[] = "(tanggal_dokumen = ? OR tanggal_dokumen LIKE ?)";
    $types .= 'ss';
    array_push($params, $formatted_tgl_dokumen, "%$tgl_dokumen%");
}

// 3. Filter Pencarian Berdasarkan Tanggal Catat
if ($tgl_catat !== '') {
    $conditions[] = "tanggal_catat LIKE ?";
    $types .= 's';
    array_push($params, "$tgl_catat%");
}

$where = "";
if (!empty($conditions)) {
    $where = "WHERE " . implode(" AND ", $conditions);
}

// =========================================================================
// OPTIMALASI DATA BESAR (RATUSAN RIBU BARIS)
// =========================================================================
if ($cari === '' && $tgl_dokumen === '' && $tgl_catat === '') {
    $limit_query = "LIMIT 300";
} else {
    $limit_query = "LIMIT 1000";
}

// Eksekusi Kueri dengan Parameter & Batasan Aman
if (!empty($conditions)) {
    $sql = "SELECT * FROM dokumen_keluar $where ORDER BY CAST(nomor_urut AS UNSIGNED) DESC $limit_query";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $query = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT * FROM dokumen_keluar ORDER BY CAST(nomor_urut AS UNSIGNED) DESC $limit_query";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $query = mysqli_stmt_get_result($stmt);
}
?>

<div class="card shadow-sm border-0">
    <div class="card-header card-header-custom d-flex flex-column flex-md-row justify-content-between align-items-center py-3 gap-2">
        
        <!-- Form Pencarian -->
        <form action="index" method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="view_dokumen">
            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari..." value="<?= h($cari); ?>" style="width: 280px;">
            <button type="submit" class="btn btn-light btn-sm fw-bold">Cari</button>
            <?php if($cari != ''): ?>
                <a href="index?page=view_dokumen" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Tombol Pemicu Modal (Dibungkus rapi dalam 1 container flex) -->
        <div class="d-flex gap-2 flex-wrap align-items-center">
            
            <!-- Tombol Input: Bisa untuk Role 1, 2, 5, dan 99 -->
            <?php if(in_array((int)$_SESSION['role'], [1, 2, 5, 99])): ?>
                <button type="button" class="btn btn-light btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    + Input
                </button>
            <?php endif; ?>
            
            <!-- Tombol Import Excel: Hanya untuk Role 1 dan 99 -->
            <?php if(in_array((int)$_SESSION['role'], [1, 5, 99])): ?>
                <button type="button" class="btn btn-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#importModal">
                    📥 Import Excel
                </button>
            <?php endif; ?>

            <!-- Tombol Export Excel: Untuk Role 2 dan 99 -->
            <?php if(in_array((int)$_SESSION['role'], [2, 5, 99])): ?>
                <button type="button" class="btn btn-outline-success btn-sm fw-bold bg-white text-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                    📤 Export Excel
                </button>
            <?php endif; ?>

        </div>
        
    </div>
    
    <div class="card-body">
    <!-- Form Hapus Banyak Membungkus Tabel (Role 99 bebas; Role 5 maksimal 7 hari dari tanggal catat) -->
    <?php if($boleh_hapus_menu): ?>
    <form action="DO/hapus.php" method="POST" id="formHapusBanyak">
        <?= csrf_field(); ?>
    <?php endif; ?>

        <!-- Kontainer Tabel dengan Scroll Horizontal & Vertikal -->
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto; position: relative;">
            <table class="table table-sm table-bordered table-striped align-middle table-custom mb-0 text-nowrap" style="font-size: 12px;">
                <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <?php if($boleh_hapus_menu): ?>
                            <th width="3%" class="text-center">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                        <?php endif; ?>
                        <th width="3%">No</th>
                        <th width="9%">Tgl Catat</th>
                        <th width="9%">Tgl Dokumen</th>
                        <th width="8%">Nomerator</th>
                        <th width="11%">Nomor DO</th>
                        <th width="9%">Plat Nomor</th>
                        <th width="11%">Nama Sopir</th>
                        <th width="9%">No Timbang</th>
                        <th width="9%">No Segel</th>
                        <th width="9%">Status</th>
                        <th width="10%">User Input</th>
                        <th width="9%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(isset($query) && mysqli_num_rows($query) > 0): ?>
                        <?php $no = 1; while($data = mysqli_fetch_assoc($query)): 
                            $status = isset($data['status']) ? $data['status'] : 'Aktif';
                            $badge_color = 'bg-success';
                            if ($status == 'Belum Dipakai') {
                                $badge_color = 'bg-secondary';
                            } elseif ($status == 'Batal') {
                                $badge_color = 'bg-danger';
                            }
                        ?>
                        <tr>
                            <?php if($boleh_hapus_menu):
                                $tgl_catat_hapus = (!empty($data['tanggal_catat']) && $data['tanggal_catat'] != '0000-00-00 00:00:00') ? substr($data['tanggal_catat'], 0, 10) : '';
                                $boleh_hapus_baris = ($role_hapus === 99) || ($tgl_catat_hapus !== '' && $tgl_catat_hapus >= $batas_hapus_tgl);
                            ?>
                                <td class="text-center">
                                    <?php if($boleh_hapus_baris): ?>
                                        <input type="checkbox" name="id_dokumen[]" value="<?= (int)$data['id']; ?>" class="form-check-input checkItem">
                                    <?php else: ?>
                                        <input type="checkbox" class="form-check-input" disabled title="Tidak bisa dihapus (lebih dari <?= (int)$batas_hapus_hari; ?> hari sejak tanggal catat)">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-center">
                                <?= (!empty($data['tanggal_catat']) && $data['tanggal_catat'] != '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($data['tanggal_catat'])) : '-'; ?>
                            </td>
                            <td class="text-center">
                                <?= (!empty($data['tanggal_dokumen'])) ? h($data['tanggal_dokumen']) : '<span class="text-muted fst-italic">-</span>'; ?>
                            </td>
                            <td class="text-center"><strong><?= h($data['nomor_urut']); ?></strong></td>
                            <td><strong><?= !empty($data['nomor_do']) ? h($data['nomor_do']) : '<span class="text-muted fst-italic">(Kosong)</span>'; ?></strong></td>
                            <td class="text-center"><?= !empty($data['plat_nomor']) ? h($data['plat_nomor']) : '-'; ?></td>
                            <td><?= !empty($data['nama_sopir']) ? h($data['nama_sopir']) : '-'; ?></td>
                            <td class="text-center"><?= !empty($data['no_timbang']) ? h($data['no_timbang']) : '-'; ?></td>
                            <td class="text-center"><?= !empty($data['no_segel']) ? h($data['no_segel']) : '-'; ?></td>
                            <td class="text-center">
                                <span class="badge <?= h($badge_color); ?>"><?= h($status); ?></span>
                            </td>
                            <td><?= h($data['user_input']); ?></td>
                            
                            <!-- Kolom Aksi dengan Logika Hak Akses Edit -->
                            <td class="text-center">
                            <?php
                            $role_login  = $_SESSION['role'] ?? 0;
                            $user_login  = trim($_SESSION['username'] ?? ''); 
                            $hari_ini    = date('Y-m-d');
                            
                            $tgl_catat_data = !empty($data['tanggal_catat']) ? substr($data['tanggal_catat'], 0, 10) : '';
                            $pembuat_data   = trim($data['user_input'] ?? '');

                            $boleh_edit = false;

                            if ($role_login == 99) {
                                $boleh_edit = true; // Role 99 bebas edit semua data
                            } else {
                                // Cek apakah teks username yang login ada di dalam kolom user_input database
                                // DAN tanggal catatnya adalah hari ini
                                $is_user_match = ($user_login !== '' && stripos($pembuat_data, $user_login) !== false);
                                
                                if ($is_user_match && $tgl_catat_data === $hari_ini) {
                                    $boleh_edit = true; 
                                }
                            }

                            if ($boleh_edit):
                            ?>
                                <button type="button" class="btn btn-warning btn-sm py-0 px-2" style="font-size: 11px;" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal" 
                                    data-id="<?= h($data['id']); ?>"
                                    data-tanggal="<?= h($data['tanggal_dokumen']); ?>"
                                    data-urut="<?= h($data['nomor_urut']); ?>"
                                    data-do="<?= h($data['nomor_do']); ?>"
                                    data-plat="<?= h($data['plat_nomor']); ?>"
                                    data-sopir="<?= h($data['nama_sopir'] ?? ''); ?>"
                                    data-timbang="<?= h($data['no_timbang'] ?? ''); ?>"
                                    data-segel="<?= h($data['no_segel'] ?? ''); ?>"
                                    data-status="<?= h($status); ?>">
                                    Edit
                                </button>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 10px;" title="Terkunci (Hanya bisa diedit pembuat pada hari yang sama)">Terkunci</span>
                            <?php endif; ?>
                        </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $boleh_hapus_menu ? 13 : 12; ?>" class="text-center text-muted py-4">Tidak ada data dokumen.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tombol Hapus Pilihan (tetap DI DALAM form #formHapusBanyak) -->
        <?php if($boleh_hapus_menu): ?>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <button type="submit" name="hapus_pilihan" value="1" id="btnHapusPilih" class="btn btn-danger btn-sm fw-bold" disabled>
                🗑️ Hapus Data Terpilih
            </button>
            <span class="text-muted small" id="infoPilih">Belum ada data yang dipilih</span>
            <?php if($role_hapus === 5): ?>
                <span class="text-muted small fst-italic">Hapus hanya untuk data yang dicatat <?= (int)$batas_hapus_hari; ?> hari terakhir.</span>
            <?php endif; ?>
        </div>
    </form>
        <?php endif; ?>
    </div><!-- /card-body -->
</div><!-- /card -->

<!-- ================= MODAL TAMBAH BARU ================= -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formTambah" action="index?page=tambah" method="POST">
                <?= csrf_field(); ?>
                <div class="modal-header bg-dark text-white py-2 px-3">
                    <h6 class="modal-title fs-6" id="tambahModalLabel">
                        <?= ($_SESSION['role'] == 99 || $_SESSION['role'] == 5) ? 'Catat Dokumen' : 'Catat Dokumen Satuan'; ?>
                    </h6>
                    <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-3">
                    
                    <div id="tambahAlert" class="d-none" role="alert"></div>

                    <?php if($_SESSION['role'] == 99 || $_SESSION['role'] == 5): ?>
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">JENIS INPUT</label>
                        <select name="jenis_input" id="jenis_input" class="form-select form-select-sm" onchange="toggleInputMode()">
                            <option value="satuan">Satuan (Satu Nomor)</option>
                            <option value="rentang">Rentang (Banyak Nomor Sekaligus)</option>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="jenis_input" value="satuan">
                    <?php endif; ?>

                   <!-- FORM SATUAN (GAYA MINIMALIS 2 KOLOM) -->
                <div id="form-satuan">
                    <!-- Baris 1: TGL DOKUMEN & NO TIMBANG -->
                    <div class="row g-2">
                        <div class="col-6 mb-2" id="group-tanggal">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">TGL DOKUMEN</label>
                            <input type="text" name="tanggal_dokumen" id="tanggal_dokumen" class="form-control form-control-sm" value="<?= date('d/m/Y'); ?>">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NO TIMBANG</label>
                            <input type="text" name="no_timbang" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Baris 2: PLAT NO & NAMA SOPIR -->
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">PLAT NOMOR</label>
                            <input type="text" name="plat_nomor" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NAMA SOPIR</label>
                            <input type="text" name="nama_sopir" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Baris 3: NO SEGEL & NOMERATOR -->
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NO SEGEL</label>
                            <input type="text" name="no_segel" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NOMERATOR</label>
                            <input type="text" name="nomor_urut" id="nomor_urut" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Baris 4: NO DO & STATUS -->
                    <div class="row g-2">
                        <div class="col-6 mb-1">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NOMOR DO</label>
                            <input type="text" name="nomor_do" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">STATUS</label>
                            <?php if(in_array((int)$_SESSION['role'], [2, 99])): ?>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="Aktif">Aktif</option>
                                    <option value="Belum Dipakai">Belum dipakai</option>
                                    <option value="Batal">Batal</option>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control form-control-sm bg-light" value="Aktif" disabled>
                                <input type="hidden" name="status" value="Aktif">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                    <!-- FORM RENTANG -->
                    <?php if($_SESSION['role'] == 99 || $_SESSION['role'] == 5): ?>
                    <div id="form-rentang" style="display: none;">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">AWAL</label>
                                <input type="number" name="nomor_awal" id="nomor_awal" class="form-control form-control-sm" placeholder="Misal: 11">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">AKHIR</label>
                                <input type="number" name="nomor_akhir" id="nomor_akhir" class="form-control form-control-sm" placeholder="Misal: 14">
                            </div>
                        </div>
                        <div class="form-text text-muted" style="font-size: 10px;">
                            *Rentang otomatis berstatus Cadangan (Belum Dipakai).
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" id="btnSimpanTambah" class="btn btn-success btn-sm px-3">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL IMPORT EXCEL ================= -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="DO/import.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field(); ?>
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title" id="importModalLabel">Import Data dari Excel</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih File (.xlsx / .csv)</label>
                        <input type="file" name="file_excel" class="form-control form-control-sm" accept=".xlsx,.csv" required>
                        <div class="form-text text-muted small mt-1">Pastikan format kolom sesuai dengan template sistem (mencakup Tanggal, DO, Material, Sopir, Timbang, Segel).</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="import" class="btn btn-success btn-sm">Proses Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL EXPORT EXCEL ================= -->
<?php if(in_array($_SESSION['role'], [99, 2, 5])): ?>
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="DO/export_excel.php" method="GET">
                <div class="modal-header bg-success text-white py-2 px-3">
                    <h6 class="modal-title fs-6" id="exportModalLabel">Export Data ke Excel</h6>
                    <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="cari" value="<?= htmlspecialchars($cari); ?>">

                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">KRITERIA FILTER EXPORT</label>
                        <select name="jenis_kriteria" id="jenis_kriteria_export" class="form-select form-select-sm" onchange="toggleExportMode()">
                            <option value="semua">Semua Data (Tanpa Filter Khusus)</option>
                            <option value="tanggal">Berdasarkan Tanggal Dokumen</option>
                            <option value="nomor">Berdasarkan Rentang Nomerator</option>
                        </select>
                    </div>

                    <div class="mb-2" id="group-filter-tanggal" style="display: none;">
                        <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">RENTANG TANGGAL DOKUMEN</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" name="tanggal_mulai" class="form-control form-control-sm">
                                <div class="form-text text-muted" style="font-size: 10px;">Dari Tanggal</div>
                            </div>
                            <div class="col-6">
                                <input type="date" name="tanggal_selesai" class="form-control form-control-sm">
                                <div class="form-text text-muted" style="font-size: 10px;">Sampai Tanggal</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2" id="group-filter-nomor" style="display: none;">
                        <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">RENTANG NOMOR URUT</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" name="nomor_awal" class="form-control form-control-sm" placeholder="Misal: 1">
                                <div class="form-text text-muted" style="font-size: 10px;">Nomor Awal</div>
                            </div>
                            <div class="col-6">
                                <input type="number" name="nomor_akhir" class="form-control form-control-sm" placeholder="Misal: 100">
                                <div class="form-text text-muted" style="font-size: 10px;">Nomor Akhir</div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-3">Download Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL EDIT DATA (DISAMAKAN) ================= -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formEditModal" action="" method="POST">
                <?= csrf_field(); ?>
                <div class="modal-header bg-dark text-white py-2 px-3">
                    <h6 class="modal-title fs-6" id="editModalLabel">Edit Dokumen Keluar</h6>
                    <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-3">
                    <!-- Baris 1: Tgl Dokumen & No Timbang -->
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">TGL DOKUMEN</label>
                            <input type="text" name="tanggal_dokumen" id="edit-tanggal" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NO TIMBANG</label>
                            <input type="text" name="no_timbang" id="edit-timbang" class="form-control form-control-sm" placeholder="No Timbang...">
                        </div>
                    </div>

                    <!-- Baris 2: Plat Nomor & Nama Sopir -->
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">PLAT NOMOR</label>
                            <input type="text" name="plat_nomor" id="edit-plat" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NAMA SOPIR</label>
                            <input type="text" name="nama_sopir" id="edit-sopir" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Baris 3: No Segel & Nomerator -->
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NO SEGEL</label>
                            <input type="text" name="no_segel" id="edit-segel" class="form-control form-control-sm" placeholder="No Segel...">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NOMERATOR</label>
                            <input type="text" name="nomor_urut" id="edit-urut" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Baris 4: Nomor DO & Status -->
                    <div class="row g-2">
                        <div class="col-6 mb-1">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">NOMOR DO</label>
                            <input type="text" name="nomor_do" id="edit-do" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-1">
                            <label class="form-label text-muted small fw-bold mb-1" style="font-size: 11px;">STATUS</label>
                            <select name="status" id="edit-status" class="form-select form-select-sm">
                                <option value="Aktif">Aktif</option>
                                <option value="Belum Dipakai">Belum dipakai</option>
                                <option value="Batal">Batal</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update" class="btn btn-success btn-sm px-3">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/view_dokumen.js"></script>