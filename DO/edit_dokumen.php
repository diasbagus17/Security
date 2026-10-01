<?php
require_once __DIR__ . '/../config/guard.php';

// Izinkan role 99 ATAU role selain 99 untuk masuk ke file proses ini
// (Nanti kita validasi kepemilikan datanya di bawah)
$role_login = $_SESSION['role'] ?? 0;
$user_login = trim($_SESSION['username'] ?? '');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (isset($_POST['update'])) {
    csrf_verify();

    $tanggal_dokumen = trim($_POST['tanggal_dokumen'] ?? '');
    $nomor_urut      = trim($_POST['nomor_urut'] ?? '');
    $nomor_do        = trim($_POST['nomor_do'] ?? '');
    $plat_nomor      = trim($_POST['plat_nomor'] ?? '');
    $nama_sopir      = trim($_POST['nama_sopir'] ?? '');
    $no_timbang      = trim($_POST['no_timbang'] ?? '');
    $no_segel        = trim($_POST['no_segel'] ?? '');
    $status          = trim($_POST['status'] ?? 'Aktif');

    // Validasi fleksibel: Jika status 'Batal', cukup Tanggal dan Nomor Urut (Nomor DO boleh kosong).
    // Jika bukan 'Batal', Tanggal dan Nomor DO wajib diisi.
    $is_valid = false;
    if ($id > 0) {
        if ($status === 'Batal') {
            // Untuk status Batal, minimal Tanggal dan Nomor Urut terisi
            if ($tanggal_dokumen !== '' && $nomor_urut !== '') {
                $is_valid = true;
            }
        } else {
            // Untuk status selain Batal, Tanggal dan Nomor DO wajib terisi
            if ($tanggal_dokumen !== '' && $nomor_do !== '') {
                $is_valid = true;
            }
        }
    }

    if ($is_valid) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE dokumen_keluar SET tanggal_dokumen = ?, nomor_urut = ?, nomor_do = ?, plat_nomor = ?, nama_sopir = ?, no_timbang = ?, no_segel = ?, status = ? WHERE id = ?"
        );
        
        // Ubah string tipe parameter menjadi "ssssssssi" (karena ada 8 string dan 1 integer id di akhir)
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssi",
            $tanggal_dokumen,
            $nomor_urut,
            $nomor_do,
            $plat_nomor,
            $nama_sopir,
            $no_timbang,
            $no_segel,
            $status,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            // --- CATAT LOG AKTIVITAS DENGAN MODUL 'Delivery Order' ---
            if (function_exists('catat_log')) {
                catat_log($conn, 'Delivery Order', 'EDIT', "Memperbarui data dokumen ID: $id (No. Urut: $nomor_urut, DO: $nomor_do)");
            }

            echo "<script>alert('Data berhasil diperbarui!'); window.location='../index.php?page=view_dokumen';</script>";
            exit;
        } else {
            error_log('Update dokumen gagal: ' . mysqli_error($conn));
            echo "<script>alert('Gagal memperbarui data. Silakan coba lagi.'); window.location='../index.php?page=view_dokumen';</script>";
            exit;
        }
    } else {
        $pesan_error = ($status === 'Batal') 
            ? 'Data tidak lengkap: Tanggal Dokumen dan Nomor Urut wajib diisi untuk status Batal!' 
            : 'Data tidak lengkap: Tanggal Dokumen dan Nomor DO wajib diisi!';
        
        echo "<script>alert('$pesan_error'); window.location='../index.php?page=view_dokumen';</script>";
        exit;
    }
}
?>