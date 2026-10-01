<?php
// Endpoint AJAX untuk modal "Catat Dokumen" di view_dokumen.php.
// Membalas JSON (tanpa alert/redirect) supaya modal tetap terbuka setelah simpan.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/guard.php';
if (!isset($conn)) {
    require_once __DIR__ . '/../config/koneksi.php';
}
// Diubah posisinya ke folder yang sama (DO/)
require_once __DIR__ . '/log_ringkas.php';
require_login([1, 2, 5, 99]); // sama dengan yang boleh melihat tombol "+ Input"

function json_out($ok, $message, $extra = [])
{
    $out = ['ok' => $ok, 'message' => $message] + $extra;
    // Kirim token CSRF terbaru (jika helper-nya ada) supaya simpan berikutnya tetap valid
    if (function_exists('csrf_token')) {
        $out['csrf'] = csrf_token();
    }
    echo json_encode($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(false, 'Metode request tidak valid.');
}

// Pastikan fungsi csrf_verify tersedia sebelum dipanggil
if (function_exists('csrf_verify')) {
    csrf_verify();
}

$role = (int) ($_SESSION['role'] ?? 0);

$jenis_input = $_POST['jenis_input'] ?? 'satuan';
// Rentang HANYA untuk role 5 dan 99. Jika role lain memilih rentang, paksa ubah jadi 'satuan'.
if ($jenis_input === 'rentang' && !in_array($role, [5, 99], true)) {
    $jenis_input = 'satuan';
}

$nama_user     = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Admin');
$role_user     = $_SESSION['bagian'] ?? ($_SESSION['role'] ?? 'Staff');
$user_input    = "$nama_user ($role_user)";
$tanggal_catat = date('Y-m-d H:i:s');

try {
    // ===================== MODE RENTANG (nomor cadangan) =====================
    if ($jenis_input === 'rentang') {
        $awal  = (int) ($_POST['nomor_awal'] ?? 0);
        $akhir = (int) ($_POST['nomor_akhir'] ?? 0);

        if ($awal <= 0 || $akhir <= 0) {
            json_out(false, 'Nomor awal dan akhir rentang harus berupa angka yang valid!');
        }
        if ($awal > $akhir) {
            json_out(false, 'Nomor awal tidak boleh lebih besar dari nomor akhir!');
        }
        if (($akhir - $awal + 1) > 1000) {
            json_out(false, 'Rentang terlalu besar (maksimal 1000 nomor sekaligus).');
        }

        // Pass 1: cek bentrok dengan nomor yang sudah Aktif + catat mana yang sudah ada
        $stCek = mysqli_prepare($conn, "SELECT status FROM dokumen_keluar WHERE nomor_urut = ? LIMIT 1");
        $bentrok = [];
        $sudahAda = [];
        
        for ($i = $awal; $i <= $akhir; $i++) {
            $no_cek = (string) $i;
            mysqli_stmt_bind_param($stCek, 's', $no_cek);
            mysqli_stmt_execute($stCek);
            $res = mysqli_stmt_get_result($stCek);
            
            if ($row = mysqli_fetch_assoc($res)) {
                $sudahAda[$i] = true;
                if (strtolower(trim($row['status'])) === 'aktif') {
                    $bentrok[] = $i;
                }
            }
        }
        mysqli_stmt_close($stCek);

        if (!empty($bentrok)) {
            json_out(false, 'Nomor urut (' . implode(', ', $bentrok) . ') sudah aktif/terpakai dan tidak bisa ditimpa!');
        }

        // Pass 2: buat / ubah menjadi 'Belum Dipakai' (satu transaksi agar tidak setengah jadi)
        mysqli_begin_transaction($conn);
        $stUpd = mysqli_prepare($conn, "UPDATE dokumen_keluar SET status = 'Belum Dipakai' WHERE nomor_urut = ?");
        $stIns = mysqli_prepare($conn, "INSERT INTO dokumen_keluar (tanggal_catat, tanggal_dokumen, nomor_urut, nomor_do, plat_nomor, nama_sopir, no_timbang, no_segel, status, user_input)
                                         VALUES (?, '', ?, '', '', '', '', '', 'Belum Dipakai', ?)");
        
        for ($i = $awal; $i <= $akhir; $i++) {
            $no_aksi = (string) $i;
            if (isset($sudahAda[$i])) {
                mysqli_stmt_bind_param($stUpd, 's', $no_aksi);
                $okRow = mysqli_stmt_execute($stUpd);
            } else {
                mysqli_stmt_bind_param($stIns, 'sss', $tanggal_catat, $no_aksi, $user_input);
                $okRow = mysqli_stmt_execute($stIns);
            }
            if (!$okRow) {
                mysqli_rollback($conn);
                mysqli_stmt_close($stUpd);
                mysqli_stmt_close($stIns);
                json_out(false, "Gagal menyimpan nomor $i. Tidak ada data rentang yang disimpan.");
            }
        }
        mysqli_commit($conn);
        mysqli_stmt_close($stUpd);
        mysqli_stmt_close($stIns);

        // Menggunakan log_tulis karena ini kejadian tunggal (buat rentang nomor) sesuai dokumentasi helper
        if (function_exists('log_tulis')) {
            log_tulis($conn, 'Delivery Order', 'TAMBAH', "Rentang cadangan $awal s.d $akhir");
        }

        json_out(true, "Rentang cadangan $awal s.d $akhir berhasil dibuat.");
    }

    // ===================== MODE SATUAN =====================
    $tanggal_dokumen = trim($_POST['tanggal_dokumen'] ?? '');
    $nomor_urut      = trim($_POST['nomor_urut'] ?? '');
    $nomor_do        = trim($_POST['nomor_do'] ?? '');
    $plat_nomor      = trim($_POST['plat_nomor'] ?? ''); 
    $nama_sopir      = trim($_POST['nama_sopir'] ?? '');
    $no_timbang      = trim($_POST['no_timbang'] ?? '');
    $no_segel        = trim($_POST['no_segel'] ?? '');
    $status_input    = trim($_POST['status'] ?? 'Aktif');

    // Hanya role 2, 5 & 99 yang boleh memilih status; role lain dipaksa 'Aktif'
    if (!in_array($role, [2, 5, 99], true) || !in_array($status_input, ['Aktif', 'Belum Dipakai', 'Batal'], true)) {
        $status_input = 'Aktif';
    }
    $is_batal = (strtolower($status_input) === 'batal');

    // Validasi ini HANYA dijalankan jika jenis inputnya BUKAN rentang
    if ($jenis_input !== 'rentang') {
        if ($tanggal_dokumen === '') {
            json_out(false, 'Tanggal dokumen wajib diisi!');
        }
        if ($nomor_urut === '' || ($nomor_do === '' && !$is_batal)) {
            json_out(false, 'Nomerator wajib diisi, dan Nomor DO harus diisi (kecuali status Batal)!');
        }
    }

    $stCek = mysqli_prepare($conn, "SELECT id, status FROM dokumen_keluar WHERE nomor_urut = ? LIMIT 1");
    mysqli_stmt_bind_param($stCek, 's', $nomor_urut);
    mysqli_stmt_execute($stCek);
    $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stCek));
    mysqli_stmt_close($stCek);

    if ($lama) {
        if (strtolower(trim($lama['status'])) === 'aktif') {
            json_out(false, "Nomor urut $nomor_urut sudah aktif/terpakai di database!");
        }
        // Isi nomor cadangan / update dokumen yang belum aktif
        $id_lama = (int) $lama['id'];
        $st = mysqli_prepare($conn, "UPDATE dokumen_keluar SET tanggal_dokumen = ?, nomor_do = ?, plat_nomor = ?, nama_sopir = ?, no_timbang = ?, no_segel = ?, status = ?, user_input = ? WHERE id = ?");
        mysqli_stmt_bind_param($st, 'ssssssssi', $tanggal_dokumen, $nomor_do, $plat_nomor, $nama_sopir, $no_timbang, $no_segel, $status_input, $user_input, $id_lama);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        if (!$ok) {
            json_out(false, 'Gagal meng-update data. Silakan coba lagi.');
        }
        if (function_exists('log_ringkas')) {
            log_ringkas($conn, 'Delivery Order', 'EDIT', 'DO (isi cadangan)', $nomor_urut);
        }
        json_out(true, "No. $nomor_urut (cadangan) berhasil diisi.", ['nomor' => $nomor_urut]);
    }

    // Nomor baru
    $st = mysqli_prepare($conn, "INSERT INTO dokumen_keluar (tanggal_catat, tanggal_dokumen, nomor_urut, nomor_do, plat_nomor, nama_sopir, no_timbang, no_segel, status, user_input)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, 'ssssssssss', $tanggal_catat, $tanggal_dokumen, $nomor_urut, $nomor_do, $plat_nomor, $nama_sopir, $no_timbang, $no_segel, $status_input, $user_input);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    if (!$ok) {
        json_out(false, 'Gagal menyimpan data. Silakan coba lagi.');
    }
    if (function_exists('log_ringkas')) {
        log_ringkas($conn, 'Delivery Order', 'TAMBAH', 'DO', $nomor_urut);
    }
    json_out(true, "No. $nomor_urut tersimpan.", ['nomor' => $nomor_urut]);

} catch (Throwable $e) {
    error_log('DO/simpan_ajax.php: ' . $e->getMessage());
    json_out(false, 'Terjadi kesalahan pada server. Silakan coba lagi.');
}