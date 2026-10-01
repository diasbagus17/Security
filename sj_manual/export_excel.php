<?php
require_once __DIR__ . '/../config/guard.php';
require_once __DIR__ . '/../config/koneksi.php';

if (function_exists('require_login')) {
    require_login();
}

$role = (int) ($_SESSION['role'] ?? 0);

// Validasi hak akses ketat (Hanya Role 2 & 99 & 5 yang boleh mengakses)
if ($role !== 2 && $role !== 99 && $role !== 5) {
    die("Akses ditolak!");
}

// Ubah input menjadi integer jika nomor_sj berupa angka di database
$start_sj = trim($_GET['start_sj'] ?? '');
$end_sj = trim($_GET['end_sj'] ?? '');

if ($start_sj === '' || $end_sj === '') {
    die("Nomor SJ Awal dan Akhir wajib diisi!");
}

// Header agar browser mendeteksi file sebagai Excel (.xls)
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_SJ_Manual_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// PERBAIKAN: Jika nomor_sj berupa angka, gunakan tipe "ii" (integer) pada bind_param
// Jika nomor_sj berupa teks/varchar (misal: "SJ-001"), biarkan tetap "ss"
$stmt = mysqli_prepare($conn, "SELECT * FROM sj_manual WHERE nomor_sj BETWEEN ? AND ? ORDER BY id ASC");
mysqli_stmt_bind_param($stmt, "ii", $start_sj, $end_sj); // Ubah "ss" ke "ii" jika kolom database integer
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<center>
    <h3>Laporan Surat Jalan Manual</h3>
    <p>Range No. SJ: <?= htmlspecialchars($start_sj); ?> s/d <?= htmlspecialchars($end_sj); ?></p>
</center>

<table border="1">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>No</th>
            <th>No. Surat Jalan</th>
            <th>Departemen</th>
            <th>Keterangan</th>
            <th>Plat Nomor</th>
            <th>Status</th>
            <th>Tanggal Pakai</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        while ($row = mysqli_fetch_assoc($result)): 
        ?>
        <tr>
            <td><?= $no++; ?></td>
            <td><?= htmlspecialchars($row['nomor_sj']); ?></td>
            <td><?= htmlspecialchars($row['departemen'] ?? '-'); ?></td>
            <td><?= htmlspecialchars($row['keterangan']); ?></td>
            <td><?= htmlspecialchars($row['plat_nomor']); ?></td>
            <td><?= htmlspecialchars($row['status']); ?></td>
            <td><?= htmlspecialchars($row['updated_at'] ?? '-'); ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>