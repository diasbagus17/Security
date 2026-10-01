<?php
/**
 * Helper log aktivitas RINGKAS.
 *
 * log_tulis()   : tulis 1 baris log langsung (untuk kejadian tunggal, mis. buat rentang nomor).
 * log_ringkas() : untuk aksi yang berulang cepat (input ratusan dokumen per hari).
 *                 Aksi beruntun dari user yang sama dengan jeda <= LOG_GAP_DETIK
 *                 digabung menjadi SATU baris log yang diperbarui, bukan satu baris per data.
 *                 Jika sudah ada jeda lebih lama, otomatis dibuat baris log baru.
 *
 * Contoh hasil di tabel log:
 *   1 data  -> "DO: 101"
 *   25 data -> "25 DO: 101 s.d 125"
 *
 * Catatan: status batch disimpan di $_SESSION['log_batch'], sedangkan barisnya
 * langsung ada di tabel log_aktivitas, jadi tidak ada log yang hilang walau
 * browser ditutup / user berhenti input.
 */

if (!defined('LOG_GAP_DETIK')) {
    define('LOG_GAP_DETIK', 600); // jeda maksimal (detik) antar aksi agar masih digabung. 600 = 10 menit
}

if (!function_exists('log_tulis')) {
    /** Tulis 1 baris log. Mengembalikan id baris log (0 jika gagal). */
    function log_tulis($conn, $modul, $aksi, $deskripsi)
    {
        $username = $_SESSION['username'] ?? 'System';
        $stmt = mysqli_prepare($conn, "INSERT INTO log_aktivitas (username, aksi, modul, deskripsi, waktu) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ssss', $username, $aksi, $modul, $deskripsi);
        $id = mysqli_stmt_execute($stmt) ? (int) mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        return $id;
    }
}

if (!function_exists('log_ringkas')) {
    /**
     * @param string $modul 'Delivery Order' | 'SJ Manual'
     * @param string $aksi  TAMBAH | EDIT | UPDATE | VERIFIKASI ...
     * @param string $kata  kata pendek pengganti "dokumen", mis. 'DO', 'SJ', 'SJ selesai'
     * @param string $ref   nomor yang baru diproses, mis. nomor urut / nomor SJ
     * @param int    $gap   jeda maksimal (detik) untuk menggabung ke baris log sebelumnya
     */
    function log_ringkas($conn, $modul, $aksi, $kata, $ref, $gap = LOG_GAP_DETIK)
    {
        $key = $modul . '|' . $aksi . '|' . $kata;
        $now = time();
        $b   = $_SESSION['log_batch'][$key] ?? null;

        // Masih dalam satu rangkaian (jeda belum lewat) -> perbarui baris log yang sama
        if ($b && ($now - $b['last']) <= $gap) {
            $n    = $b['n'] + 1;
            $desk = "$n $kata: {$b['first']} s.d $ref";
            $stmt = mysqli_prepare($conn, "UPDATE log_aktivitas SET deskripsi = ?, waktu = NOW() WHERE id = ?");
            if ($stmt) {
                $logId = (int) $b['id'];
                mysqli_stmt_bind_param($stmt, 'si', $desk, $logId);
                mysqli_stmt_execute($stmt);
                $terupdate = mysqli_stmt_affected_rows($stmt) > 0;
                mysqli_stmt_close($stmt);
                if ($terupdate) {
                    $_SESSION['log_batch'][$key]['n']    = $n;
                    $_SESSION['log_batch'][$key]['last'] = $now;
                    return;
                }
            }
            // Baris log lama tidak ditemukan (mis. terhapus) -> lanjut buat baris baru
        }

        // Rangkaian baru -> buat baris log baru
        $id = log_tulis($conn, $modul, $aksi, "$kata: $ref");
        if ($id) {
            $_SESSION['log_batch'][$key] = ['id' => $id, 'n' => 1, 'first' => $ref, 'last' => $now];
        }
    }
}