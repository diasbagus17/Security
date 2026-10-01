<?php
require_once __DIR__ . '/../config/guard.php';
require_login([2, 5, 99]); // Bagian Logistik, Accounting   & Administrator

$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$aksi_cek = isset($_GET['aksi_cek']) ? true : false;

$map_dokumen = [];
$nomor_bolong_analisis = [];
$lembar_tidak_sesuai = [];
$perbedaan_nomor_do = [];

if ($aksi_cek) {
    // 1. Tarik SELURUH data dokumen ke memori PHP (Array)
    $query_all_db = mysqli_query($conn, "SELECT nomor_urut, tanggal_dokumen, nomor_do, plat_nomor, status FROM dokumen_keluar");
    while ($row = mysqli_fetch_assoc($query_all_db)) {
        $no_num = (int)$row['nomor_urut'];
        if ($no_num > 0) {
            $tgl_db_raw = $row['tanggal_dokumen'];
            if (strpos($tgl_db_raw, '/') !== false) {
                $p = explode('/', $tgl_db_raw);
                $tgl_db = count($p) == 3 ? "{$p[2]}-{$p[1]}-{$p[0]}" : $tgl_db_raw;
            } else {
                $tgl_db = $tgl_db_raw;
            }

            $map_dokumen[$no_num] = [
                'tanggal_dokumen' => $row['tanggal_dokumen'],
                'tgl_standard' => $tgl_db,
                'nomor_do' => $row['nomor_do'],
                'plat_nomor' => $row['plat_nomor'],
                'db_status' => $row['status']
            ];
        }
    }

    $list_angka_range = [];
    foreach ($map_dokumen as $no_num => $dt) {
        if ($dt['tgl_standard'] == $tanggal) {
            $list_angka_range[] = $no_num;
        }
    }

    $min_no = !empty($list_angka_range) ? min($list_angka_range) : 0;
    $max_no = !empty($list_angka_range) ? max($list_angka_range) : 0;

    // 2. Analisis Nomerator Bolong
    if ($min_no > 0 && $max_no > 0) {
        $last_valid_date = '-';
        for ($i = $min_no; $i <= $max_no; $i++) {
            if (isset($map_dokumen[$i])) {
                $last_valid_date = $map_dokumen[$i]['tgl_standard'];
            } else {
                $next_valid_date = '-';
                for ($j = $i + 1; $j <= $max_no; $j++) {
                    if (isset($map_dokumen[$j])) {
                        $next_valid_date = $map_dokumen[$j]['tgl_standard'];
                        break;
                    }
                }

                $nomor_bolong_analisis[] = [
                    'nomor_urut' => $i,
                    'tanggal_sebelumnya' => $last_valid_date,
                    'tanggal_berikutnya' => $next_valid_date
                ];
            }
        }
    }

    // 3. Ambil data dari tabel sementara (temp_import)
    $query_temp = mysqli_query($conn, "SELECT no_do, material_number FROM temp_import");
    $item_counts = [];
    $excel_dos_unique = [];
    while ($row = mysqli_fetch_assoc($query_temp)) {
        $do = trim($row['no_do']);
        if (!empty($do)) {
            if (!isset($item_counts[$do])) {
                $item_counts[$do] = 0;
            }
            $item_counts[$do]++;
            $excel_dos_unique[$do] = true;
        }
    }
    $excel_dos_list = array_keys($excel_dos_unique);

    // 4. Pengecekan Lembar (Excel vs DB)
    foreach ($item_counts as $do => $total_material_excel) {
        $lembar_excel = ceil($total_material_excel / 8); 
        $jumlah_baris_db = 0;
        foreach ($map_dokumen as $dt) {
            if ($dt['nomor_do'] == $do && $dt['tgl_standard'] == $tanggal) {
                $jumlah_baris_db++;
            }
        }

        if ($jumlah_baris_db > 0 && $jumlah_baris_db < $lembar_excel) {
            $lembar_tidak_sesuai[] = [
                'no_do' => $do,
                'lembar_excel' => $lembar_excel,
                'lembar_db' => $jumlah_baris_db,
                'selisih' => $lembar_excel - $jumlah_baris_db
            ];
        }
    }

    // 5. Perbedaan Nomor DO
    $db_dos_unique = [];
    foreach ($map_dokumen as $dt) {
        if ($dt['tgl_standard'] == $tanggal && !empty($dt['nomor_do'])) {
            $db_dos_unique[$dt['nomor_do']] = true;
        }
    }
    $db_dos_list = array_keys($db_dos_unique);

    foreach ($db_dos_list as $do_db) {
        if (!isset($excel_dos_unique[$do_db])) {
            $perbedaan_nomor_do[] = ['no_do' => $do_db, 'status' => 'Ada di Inputan (Sistem), Tidak ada di Excel'];
        }
    }
    foreach ($excel_dos_list as $do_ex) {
        if (!isset($db_dos_unique[$do_ex])) {
            $perbedaan_nomor_do[] = ['no_do' => $do_ex, 'status' => 'Ada di Excel, Tidak ada di Inputan (Sistem)'];
        }
    }
}
?>

<style>
    .compact-report .card-body { padding: 1rem; }
    .compact-report table th, .compact-report table td { padding: 0.35rem 0.5rem !important; font-size: 0.82rem; }
    .compact-report h6 { font-size: 0.9rem; }
    .compact-report .alert { padding: 0.4rem 0.75rem; font-size: 0.82rem; }
    .compact-report .badge { font-size: 0.72rem; padding: 0.25em 0.5em; }
</style>

<div class="card shadow-sm border-0 compact-report mt-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
        <h6 class="mb-0 fw-bold fs-6">📊 Laporan Analisis Selisih & Nomor DO</h6>
        <a href="index.php?page=analisa" class="btn btn-light btn-sm py-0 px-2 fw-bold" style="font-size: 0.78rem;"><i class="fas fa-arrow-left me-1"></i> Kembali ke Analisa</a>
    </div>
    
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-end bg-light p-2 rounded mb-3 border">
            <input type="hidden" name="page" value="laporan_selisih">
            <input type="hidden" name="aksi_cek" value="1">
            
            <div class="col-md-8">
                <label class="form-label small fw-semibold mb-1">Tanggal Dokumen:</label>
                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal); ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-dark btn-sm w-100 py-1"><i class="fas fa-search me-1"></i> Perbarui Analisis</button>
            </div>
        </form>

        <?php if ($aksi_cek): ?>
            <!-- KOTAK 1: NOMOR BOLONG & ESTIMASI TANGGAL -->
            <div class="mb-3">
                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">1. Celah Nomerator (Nomerator Bolong / Lompat) & Estimasi Tanggal</h6>
                <?php if (empty($nomor_bolong_analisis)): ?>
                    <div class="alert alert-success mb-0">Sempurna! Tidak ada celah nomerator yang bolong pada tanggal ini.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th width="15%">Nomerator Lompat</th>
                                    <th width="25%">Tanggal Sebelum (Prev)</th>
                                    <th width="25%">Tanggal Sesudah (Next)</th>
                                    <th width="35%">Keterangan Perkiraan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nomor_bolong_analisis as $b): ?>
                                <tr>
                                    <td class="text-center fw-bold text-danger"><?= $b['nomor_urut']; ?></td>
                                    <td class="text-center"><?= $b['tanggal_sebelumnya']; ?></td>
                                    <td class="text-center"><?= $b['tanggal_berikutnya']; ?></td>
                                    <td class="small">
                                        Nomor ini kosong di antara tanggal <strong><?= $b['tanggal_sebelumnya']; ?></strong> dan <strong><?= $b['tanggal_berikutnya']; ?></strong>.
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- KOTAK 2: JUMLAH LEMBAR TIDAK SESUAI -->
            <div class="mb-3">
                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">2. Nomor DO dengan Jumlah Lembar Kurang</h6>
                <?php if (empty($lembar_tidak_sesuai)): ?>
                    <div class="alert alert-success mb-0">Semua nomor DO sudah sesuai jumlah lembarnya dengan perhitungan material di Excel.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="8%" class="text-center">No</th>
                                    <th width="37%">Nomor DO</th>
                                    <th width="20%" class="text-center">Seharusnya (Excel)</th>
                                    <th width="20%" class="text-center">Sudah Diinput</th>
                                    <th width="15%" class="text-center">Kekurangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach ($lembar_tidak_sesuai as $item): ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td><strong><?= htmlspecialchars($item['no_do']); ?></strong></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int) $item['lembar_excel']; ?> Lembar</span></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark"><?= (int) $item['lembar_db']; ?> Lembar</span></td>
                                    <td class="text-center"><span class="badge bg-danger">Kurang <?= (int) $item['selisih']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- KOTAK 3: PERBEDAAN NOMOR DO -->
            <div class="mb-1">
                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">3. Perbedaan Nomor DO (Inputan vs Excel)</h6>
                <?php if (empty($perbedaan_nomor_do)): ?>
                    <div class="alert alert-success mb-0">Sempurna! Semua daftar Nomor DO pada sistem dan file Excel sudah sinkron.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="8%" class="text-center">No</th>
                                    <th width="42%">Nomor DO</th>
                                    <th width="50%">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach ($perbedaan_nomor_do as $diff): ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td><strong><?= htmlspecialchars($diff['no_do']); ?></strong></td>
                                    <td>
                                        <?php if (strpos($diff['status'], 'Tidak ada di Excel') !== false): ?>
                                            <span class="badge bg-warning text-dark w-100 py-1"><?= htmlspecialchars($diff['status']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark w-100 py-1"><?= htmlspecialchars($diff['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <p class="mb-0">Silakan lakukan <a href="index.php?page=analisa">Upload File</a> atau pilih tanggal lalu klik <strong>Perbarui Analisis</strong>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>