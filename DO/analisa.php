<?php
// Pastikan file diakses melalui index.php dan sudah melewati guard
if (!defined('__DIR__') && !isset($conn)) {
    require_once __DIR__ . '/../config/guard.php';
}
require_login([2, 5, 99]); // Bagian Logistik,Accounting & Administrator

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$upload_error = '';

// Fungsi helper untuk ubah format YYYY-MM-DD ke DD-MM-YYYY
function format_indo($tanggal) {
    if (empty($tanggal) || $tanggal == '-') return '-';
    $p = explode('-', $tanggal);
    if (count($p) == 3) {
        return "{$p[2]}-{$p[1]}-{$p[0]}";
    }
    return $tanggal;
}

// 1. PROSES JIKA FORM UPLOAD DITEKAN
if (isset($_POST['upload_excel_btn'])) {
    csrf_verify();

    $tanggal_upload = isset($_POST['tanggal_upload']) ? $_POST['tanggal_upload'] : date('Y-m-d');

    if (isset($_FILES['file_excel']['name']) && $_FILES['file_excel']['error'] == 0) {
        $file_tmp = $_FILES['file_excel']['tmp_name'];
        $file_name = $_FILES['file_excel']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['csv', 'txt', 'xlsx', 'xls'];

        if (in_array($file_ext, $allowed_extensions)) {
            try {
                $spreadsheet = IOFactory::load($file_tmp);
                $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

                // Kosongkan tabel sementara terlebih dahulu sebelum data baru dimasukkan
                mysqli_query($conn, "TRUNCATE TABLE temp_import");

                $is_header = true;
                foreach ($sheetData as $row) {
                    if ($is_header) {
                        $is_header = false;
                        continue;
                    }

                    if (!empty($row['A'])) {
                        $no_do = mysqli_real_escape_string($conn, $row['A']);
                        $material = isset($row['B']) ? mysqli_real_escape_string($conn, $row['B']) : '';
                        $tanggal_dokumen = $tanggal_upload;

                        mysqli_query($conn, "INSERT INTO temp_import (no_do, material_number, tanggal_dokumen) VALUES ('$no_do', '$material', '$tanggal_dokumen')");
                    }
                }

                echo "<script>alert('Upload berhasil! Menampilkan laporan selisih...'); window.location='index.php?page=laporan_selisih&tanggal_mulai=" . $tanggal_upload . "&tanggal_selesai=" . $tanggal_upload . "&aksi_cek=1';</script>";
                exit;

            } catch (Exception $e) {
                $upload_error = "Gagal membaca file: " . $e->getMessage();
            }
        } else {
            $upload_error = "Format file harus berjenis CSV, XLSX, atau XLS!";
        }
    } else {
        $upload_error = "Pilih file Excel terlebih dahulu!";
    }
}

// 2. PROSES ANALISIS NOMOR BOLONG
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$aksi_cek = isset($_GET['aksi_cek']) ? true : false;

$nomor_bolong_analisis = [];

if ($aksi_cek) {
    $map_dokumen = [];

    $query_all_db = mysqli_query($conn, "SELECT nomor_urut, tanggal_dokumen, nomor_do FROM dokumen_keluar");
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
                'nomor_do' => $row['nomor_do']
            ];
        }
    }

    $list_angka_range = [];
    foreach ($map_dokumen as $no_num => $dt) {
        if ($dt['tgl_standard'] >= $tanggal_mulai && $dt['tgl_standard'] <= $tanggal_selesai) {
            $list_angka_range[] = $no_num;
        }
    }

    $min_no = !empty($list_angka_range) ? min($list_angka_range) : 0;
    $max_no = !empty($list_angka_range) ? max($list_angka_range) : 0;

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
}
?>

<style>
    .compact-card .card-header { padding: 0.5rem 1rem; }
    .compact-card .card-body { padding: 0.75rem 1rem; }
    .form-label { margin-bottom: 0.2rem; font-size: 0.8rem; }
    .form-control-sm, .btn-sm { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
    /* Membuat tabel lebih rapat */
    table.table-sm th, table.table-sm td { padding: 0.25rem 0.4rem !important; font-size: 0.78rem; }
</style>

<div class="container-fluid px-2">
    <!-- KOTAK 1: UPLOAD EXCEL -->
    <div class="card shadow-sm border-0 mb-3 compact-card">
        <div class="card-header bg-dark text-white py-2">
            <h6 class="mb-0 fw-bold fs-6"><i class="fas fa-file-excel me-1"></i> Upload File Excel (2 Kolom)</h6>
        </div>
        <div class="card-body">
            <?php if (!empty($upload_error)): ?>
                <div class="alert alert-danger py-1 px-2 small mb-2"><?= htmlspecialchars($upload_error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                <?= csrf_field(); ?>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Tanggal Dokumen:</label>
                    <input type="date" name="tanggal_upload" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>" required>
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-bold">Pilih File (.csv / .xlsx / .xls)</label>
                    <input type="file" name="file_excel" class="form-control form-control-sm" accept=".csv, .xlsx, .xls" required>
                </div>

                <div class="col-md-4 d-flex align-items-end mt-4">
                    <button type="submit" name="upload_excel_btn" class="btn btn-primary btn-sm w-100 fw-bold py-1">
                        <i class="fas fa-upload me-1"></i> Upload & Proses
                    </button>
                </div>

                <div class="col-12">
                    <span class="text-muted" style="font-size: 0.75rem;">
                        * Format Excel: <strong>Kolom A</strong> (Nomer DO), <strong>Kolom B</strong> (Material). Tanggal dokumen otomatis menggunakan tanggal yang dipilih di atas.
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- KOTAK 2: ANALISIS NOMOR BOLONG -->
    <div class="card shadow-sm border-0 compact-card">
        <div class="card-header bg-secondary text-white py-2">
            <h6 class="mb-0 fw-bold fs-6"><i class="fas fa-search me-1"></i> Analisis Celah Nomerator (Nomor Lompat)</h6>
        </div>
        
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-end bg-light p-2 rounded mb-2 border">
                <input type="hidden" name="page" value="analisa">
                <input type="hidden" name="aksi_cek" value="1">
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">Dari Tanggal:</label>
                    <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal_mulai); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Sampai Tanggal:</label>
                    <input type="date" name="tanggal_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal_selesai); ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold py-1"><i class="fas fa-search me-1"></i> Cek Analisis Bolong</button>
                </div>
            </form>

            <?php if ($aksi_cek): ?>
                <div class="mb-1">
                    <div class="small fw-bold text-dark mb-2">
                        Hasil Analisis: <span class="text-primary"><?= format_indo($tanggal_mulai); ?></span> s.d. <span class="text-primary"><?= format_indo($tanggal_selesai); ?></span>
                    </div>

                    <?php if (empty($nomor_bolong_analisis)): ?>
                        <div class="alert alert-success py-2 px-3 small mb-0">
                            <i class="fas fa-check-circle me-1"></i> Sempurna! Tidak ada celah nomerator yang bolong pada rentang tanggal tersebut.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped align-middle mb-0">
                                <thead class="table-dark text-center">
                                    <tr>
                                        <th width="10%">No</th>
                                        <th width="30%">Nomor Lompat</th>
                                        <th width="60%">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no=1; foreach ($nomor_bolong_analisis as $b): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td class="text-center fw-bold text-danger"><?= $b['nomor_urut']; ?></td>
                                        <td>Kosong di antara tgl <strong><?= format_indo($b['tanggal_sebelumnya']); ?></strong> & <strong><?= format_indo($b['tanggal_berikutnya']); ?></strong>.</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-2" style="font-size: 0.8rem;">
                    Silakan tentukan rentang tanggal di atas, lalu klik <strong>Cek Analisis Bolong</strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>