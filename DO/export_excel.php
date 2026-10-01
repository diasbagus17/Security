<?php
require_once __DIR__ . '/../config/guard.php';
require_login([2, 5, 99]); // Diperbarui: Mengizinkan Role 2, 5 dan Administrator (99)

$cari          = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$jenis_kriteria = isset($_GET['jenis_kriteria']) ? $_GET['jenis_kriteria'] : 'semua';
$tgl_mulai      = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tgl_selesai    = isset($_GET['tanggal_selesai']) ? trim($_GET['tanggal_selesai']) : '';
$nomor_awal     = isset($_GET['nomor_awal']) ? (int) $_GET['nomor_awal'] : 0;
$nomor_akhir    = isset($_GET['nomor_akhir']) ? (int) $_GET['nomor_akhir'] : 0;

// Validasi sederhana format tanggal (YYYY-MM-DD dari input type=date) supaya query tidak error
$isValidDate = static function ($d) {
    return $d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
};

$conditions = [];
$types = '';
$params = [];

if ($cari !== '') {
    $conditions[] = "(nomor_do LIKE ? OR nomor_urut LIKE ? OR plat_nomor LIKE ? OR nama_sopir LIKE ? OR no_timbang LIKE ? OR no_segel LIKE ? OR tanggal_dokumen LIKE ?)";
    $likeParam = "%$cari%";
    $types .= 'sssssss';
    array_push($params, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam, $likeParam);
}

if ($jenis_kriteria === 'tanggal') {
    if ($isValidDate($tgl_mulai) && $isValidDate($tgl_selesai)) {
        $conditions[] = "STR_TO_DATE(tanggal_dokumen, '%d/%m/%Y') BETWEEN ? AND ?";
        $types .= 'ss';
        array_push($params, $tgl_mulai, $tgl_selesai);
    }
} elseif ($jenis_kriteria === 'nomor') {
    if ($nomor_awal > 0 && $nomor_akhir > 0) {
        $conditions[] = "CAST(nomor_urut AS UNSIGNED) BETWEEN ? AND ?";
        $types .= 'ii';
        array_push($params, $nomor_awal, $nomor_akhir);
    }
}

$where = '';
if (count($conditions) > 0) {
    $where = "WHERE " . implode(" AND ", $conditions);
}

$sql = "SELECT * FROM dokumen_keluar $where ORDER BY CAST(nomor_urut AS UNSIGNED) ASC";
if ($types !== '') {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $query = mysqli_stmt_get_result($stmt);
} else {
    $query = mysqli_query($conn, $sql);
}

// --- HITUNG NOMOR BOLONG SECARA GLOBAL (DARI SELURUH DATA) ---
$q_all_global = mysqli_query($conn, "SELECT CAST(nomor_urut AS UNSIGNED) as num FROM dokumen_keluar WHERE nomor_urut REGEXP '^[0-9]+$' ORDER BY num ASC");
$existing_nums_global = [];
while ($row_g = mysqli_fetch_assoc($q_all_global)) {
    $existing_nums_global[] = (int) $row_g['num'];
}

$bolong_global_list = [];
if (!empty($existing_nums_global)) {
    $min_g = min($existing_nums_global);
    $max_g = max($existing_nums_global);
    for ($i = $min_g; $i <= $max_g; $i++) {
        if (!in_array($i, $existing_nums_global, true)) {
            $bolong_global_list[] = $i;
        }
    }
}
$teks_bolong_global = !empty($bolong_global_list) ? implode(', ', $bolong_global_list) : 'Tidak ada';
// -----------------------------------------------------------

$filename = "Log_Dokumen_Keluar_" . date('Y-m-d_H-i-s') . ".xls";

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export Log Dokumen Keluar</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 11pt; }
        th, td { border: 1px solid #000000; padding: 5px 8px; text-align: left; }
        th { background-color: #d9d9d9; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h3 style="text-align: center;">LOG DOKUMEN KELUAR & ANALISIS NOMOR URUT TIDAK ADA</h3>

    <?php if ($cari !== ''): ?>
        <p>Pencarian: <strong><?= h($cari); ?></strong></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal Catat</th>
                <th>Tanggal Dokumen</th>
                <th>Nomor Urut</th>
                <th>Nomor DO</th>
                <th>Plat Nomor</th>
                <th>Nama Sopir</th>
                <th>No Timbang</th>
                <th>No Segel</th>
                <th>User Input</th>
                <th style="background-color: #ffeb3b;">Nomor Urut Tidak Ada (Global)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if (mysqli_num_rows($query) > 0) {
                while ($data = mysqli_fetch_assoc($query)) {
                    $tgl_catat = (!empty($data['tanggal_catat']) && $data['tanggal_catat'] != '0000-00-00 00:00:00')
                                 ? date('d/m/Y', strtotime($data['tanggal_catat']))
                                 : '-';
            ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td class="text-center"><?= h($tgl_catat); ?></td>
                    <td class="text-center"><?= h($data['tanggal_dokumen']); ?></td>
                    <td class="text-center"><?= h($data['nomor_urut']); ?></td>
                    <td><?= !empty($data['nomor_do']) ? h($data['nomor_do']) : '-'; ?></td>
                    <td class="text-center"><?= !empty($data['plat_nomor']) ? h($data['plat_nomor']) : '-'; ?></td>
                    <td><?= !empty($data['nama_sopir']) ? h($data['nama_sopir']) : '-'; ?></td>
                    <td class="text-center"><?= !empty($data['no_timbang']) ? h($data['no_timbang']) : '-'; ?></td>
                    <td class="text-center"><?= !empty($data['no_segel']) ? h($data['no_segel']) : '-'; ?></td>
                    <td><?= h($data['user_input']); ?></td>
                    <!-- Menampilkan hasil nomor bolong global yang sama di setiap baris -->
                    <td><?= h($teks_bolong_global); ?></td>
                </tr>
            <?php
                }
            } else {
            ?>
                <tr>
                    <td colspan="11" class="text-center">Tidak ada data ditemukan untuk kriteria tersebut.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>