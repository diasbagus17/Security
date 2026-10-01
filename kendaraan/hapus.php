<?php
// Pastikan guard.php dimuat
if (!isset($conn)) {
    require_once __DIR__ . '/../config/guard.php';
}

$role = isset($_SESSION['role']) ? (int) $_SESSION['role'] : 0;
$is_allowed_delete = in_array($role, [1, 99], true);

// Whitelist tab agar tidak bisa disisipi script lewat URL
$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], ['aktif', 'riwayat'], true)) ? $_GET['tab'] : 'aktif';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$notif = 'gagal';

if ($id > 0 && $is_allowed_delete) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // Cek data & status dulu
        $stmt_cek = mysqli_prepare($conn, "SELECT keterangan_keluar FROM kendaraan WHERE id = ?");
        mysqli_stmt_bind_param($stmt_cek, "i", $id);
        mysqli_stmt_execute($stmt_cek);
        $res_cek = mysqli_stmt_get_result($stmt_cek);
        $data = mysqli_fetch_assoc($res_cek);
        mysqli_stmt_close($stmt_cek);

        // Riwayat (sudah Keluar) hanya boleh dihapus Role 99 -- sama seperti tampilan tombolnya
        $boleh = $data && ($data['keterangan_keluar'] !== 'Keluar' || $role === 99);

        if ($boleh) {
            mysqli_begin_transaction($conn);

            // Hapus detail barang terlebih dahulu
            $stmt_del_detail = mysqli_prepare($conn, "DELETE FROM kendaraan_detail_barang WHERE kendaraan_id = ?");
            mysqli_stmt_bind_param($stmt_del_detail, "i", $id);
            mysqli_stmt_execute($stmt_del_detail);
            mysqli_stmt_close($stmt_del_detail);

            // Hapus data kendaraan utama
            $stmt_del_main = mysqli_prepare($conn, "DELETE FROM kendaraan WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del_main, "i", $id);
            mysqli_stmt_execute($stmt_del_main);
            mysqli_stmt_close($stmt_del_main);

            mysqli_commit($conn);
            $notif = 'hapus';

            try {
                if (function_exists('catat_log')) {
                    catat_log($conn, 'Kendaraan', 'Hapus', 'Menghapus data kendaraan ID: ' . $id);
                }
            } catch (Throwable $e) { }
        }
    } catch (Throwable $e) {
        try { mysqli_rollback($conn); } catch (Throwable $e2) { }
        $notif = 'gagal';
    }
}

echo "<script>window.location='../index.php?page=kendaraan&tab=" . $active_tab . "&notif=" . $notif . "';</script>";
exit;