<?php
require_once __DIR__ . '/../config/guard.php';
require_login([5, 99]); // Administrator (bebas) dan role 5 (maksimal 7 hari dari tanggal catat)

$role_hapus = (int) ($_SESSION['role'] ?? 0);
$batas_hari = 7;

$redirect = "../index.php?page=view_dokumen";

// Hanya terima POST dari tombol "Hapus Data Terpilih"
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['hapus_pilihan'])) {
    header("Location: $redirect");
    exit;
}

// Verifikasi token CSRF (hanya untuk request POST)
csrf_verify();

// Pastikan ada ID yang dikirim dalam bentuk array dari checkbox
if (!empty($_POST['id_dokumen']) && is_array($_POST['id_dokumen'])) {

    // Sanitasi ke integer, buang nilai 0/negatif & duplikat
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $_POST['id_dokumen']),
        function ($v) { return $v > 0; }
    )));

    if (count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql    = "DELETE FROM dokumen_keluar WHERE id IN ($placeholders)";
        $params = $ids;
        if ($role_hapus !== 99) {
            // Role 5: hanya data dengan tanggal catat dalam 7 hari terakhir (dicek di server)
            $sql     .= " AND tanggal_catat IS NOT NULL AND tanggal_catat >= ?";
            $types   .= 's';
            $params[] = date('Y-m-d 00:00:00', strtotime("-$batas_hari days"));
        }
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);

            if (mysqli_stmt_execute($stmt)) {
                $total_dihapus = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if (function_exists('catat_log')) {
                    catat_log($conn, 'Delivery Order', 'HAPUS', "Menghapus $total_dihapus data Delivery Order secara massal.");
                }

                $dilewati = count($ids) - $total_dihapus;
                $_SESSION['success'] = "$total_dihapus data terpilih berhasil dihapus."
                    . ($dilewati > 0 ? " $dilewati data dilewati (lebih dari $batas_hari hari sejak tanggal catat atau sudah tidak ada)." : '');
            } else {
                $_SESSION['error'] = "Gagal mengeksekusi penghapusan: " . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $_SESSION['error'] = "Gagal menyiapkan database: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['warning'] = "ID data yang dipilih tidak valid.";
    }
} else {
    $_SESSION['warning'] = "Tidak ada data yang dipilih untuk dihapus.";
}

header("Location: $redirect");
exit;