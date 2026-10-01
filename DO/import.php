<?php
require_once __DIR__ . '/../config/guard.php';
require_login([1, 5, 99]); // Security & Administrator (sesuai izin di index.php)

if (isset($_POST['import'])) {
    csrf_verify();
}

// Fungsi konversi tanggal ke format DD/MM/YYYY
function convertExcelDate($dateValue) {
    $dateValue = trim($dateValue);
    if (empty($dateValue)) return '';

    if (is_numeric($dateValue)) {
        $unixTimestamp = ($dateValue - 25569) * 86400;
        return gmdate("d/m/Y", $unixTimestamp);
    }

    $parsed = strtotime($dateValue);
    if ($parsed !== false) {
        return date("d/m/Y", $parsed);
    }

    return $dateValue;
}

if (isset($_POST['import'])) {
    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['file_excel']['tmp_name'];
        $file_name = $_FILES['file_excel']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt', 'xls', 'xlsx'])) {
            
            $sukses = 0;
            $gagal  = 0;
            
            // Format Nama & Bagian User yang melakukan import
            $nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'User';
            $nama_role = isset($_SESSION['bagian']) ? $_SESSION['bagian'] : 'Staff'; 
            $user_input = "$nama_user ($nama_role)";

            $rows = [];

            if ($ext === 'csv' || $ext === 'txt') {
                if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                    $first_line = fgets($handle);
                    rewind($handle);
                    $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';

                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }
            } else {
                if ($ext === 'xlsx') {
                    $zip = new ZipArchive();
                    if ($zip->open($file_tmp) === TRUE) {
                        $xmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
                        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
                        $zip->close();

                        $strings = [];
                        if ($sharedStrings) {
                            $dom = new DOMDocument();
                            @$dom->loadXML($sharedStrings);
                            foreach ($dom->getElementsByTagName('t') as $node) {
                                $strings[] = $node->textContent;
                            }
                        }

                        if ($xmlContent) {
                            $dom = new DOMDocument();
                            @$dom->loadXML($xmlContent);
                            foreach ($dom->getElementsByTagName('row') as $rowNode) {
                                $rowData = [];
                                foreach ($rowNode->getElementsByTagName('c') as $cNode) {
                                    $vNode = $cNode->getElementsByTagName('v');
                                    if ($vNode->length > 0) {
                                        $val = $vNode->item(0)->textContent;
                                        if ($cNode->getAttribute('t') === 's' && isset($strings[(int)$val])) {
                                            $val = $strings[(int)$val];
                                        }
                                        $rowData[] = $val;
                                    } else {
                                        $rowData[] = '';
                                    }
                                }
                                if (!empty($rowData)) {
                                    $rows[] = $rowData;
                                }
                            }
                        }
                    }
                }
                
                if (empty($rows)) {
                    $lines = file($file_tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $rows[] = explode("\t", $line);
                    }
                }
            }

            $row_index = 0;
            foreach ($rows as $data) {
                $row_index++;
                
                if ($row_index == 1 && (stripos(@$data[0], 'tanggal') !== false || stripos(@$data[0], 'do') !== false || stripos(@$data[0], 'nomor') !== false)) {
                    continue;
                }

                $raw_tanggal     = isset($data[0]) ? $data[0] : '';
                $tanggal_dokumen = mysqli_real_escape_string($conn, convertExcelDate($raw_tanggal));
                
                $nomor_urut      = isset($data[1]) ? mysqli_real_escape_string($conn, trim($data[1])) : '';
                $nomor_do        = isset($data[2]) ? mysqli_real_escape_string($conn, trim($data[2])) : '';
                $plat_nomor      = isset($data[3]) ? mysqli_real_escape_string($conn, trim($data[3])) : '';
                
                $nama_sopir      = isset($data[4]) ? mysqli_real_escape_string($conn, trim($data[4])) : '';
                $no_timbang      = isset($data[5]) ? mysqli_real_escape_string($conn, trim($data[5])) : '';
                $no_segel        = isset($data[6]) ? mysqli_real_escape_string($conn, trim($data[6])) : '';

                if (empty($tanggal_dokumen) && empty($nomor_do) && empty($nomor_urut)) {
                    continue;
                }

                if (!empty($nomor_do) && !empty($nomor_urut)) {
                    $check_query = mysqli_query($conn, "SELECT id, status FROM dokumen_keluar WHERE nomor_urut = '$nomor_urut' LIMIT 1");
                    
                    if (mysqli_num_rows($check_query) > 0) {
                        $row_data = mysqli_fetch_assoc($check_query);
                        $current_status = strtolower(trim($row_data['status']));
                        
                        if ($current_status === 'aktif') {
                            $gagal++;
                            continue; 
                        }
                        
                        $query = "UPDATE dokumen_keluar SET 
                                        tanggal_dokumen = '$tanggal_dokumen', 
                                        nomor_do = '$nomor_do', 
                                        plat_nomor = '$plat_nomor', 
                                        nama_sopir = '$nama_sopir', 
                                        no_timbang = '$no_timbang', 
                                        no_segel = '$no_segel', 
                                        status = 'Aktif', 
                                        user_input = '$user_input' 
                                 WHERE nomor_urut = '$nomor_urut'";
                    } else {
                        $query = "INSERT INTO dokumen_keluar (tanggal_dokumen, nomor_urut, nomor_do, plat_nomor, nama_sopir, no_timbang, no_segel, status, user_input) 
                                 VALUES ('$tanggal_dokumen', '$nomor_urut', '$nomor_do', '$plat_nomor', '$nama_sopir', '$no_timbang', '$no_segel', 'Aktif', '$user_input')";
                    }
                    
                    if (mysqli_query($conn, $query)) {
                        $sukses++;
                    } else {
                        $gagal++;
                    }
                } else {
                    $gagal++;
                }
            }

            // --- CATAT LOG AKTIVITAS IMPORT ---
            if ($sukses > 0) {
                catat_log($conn, 'Delivery Order', 'IMPORT', "Mengimport file Excel ($file_name). Berhasil: $sukses data, Gagal/Dilewati: $gagal data.");
            }

            echo "<script>
                    alert('Import selesai! Berhasil diproses: $sukses data, Gagal/Dilewati: $gagal data.');
                    window.location.href = '../index.php?page=view_dokumen';
                  </script>";
            exit;

        } else {
            echo "<script>
                    alert('Format file tidak valid! Harap gunakan file berformat .csv, .xls, atau .xlsx.');
                    window.location.href = '../index.php?page=view_dokumen';
                  </script>";
            exit;
        }
    } else {
        echo "<script>
                alert('Tidak ada file yang dipilih atau terjadi kesalahan saat upload.');
                window.location.href = '../index.php?page=view_dokumen';
              </script>";
        exit;
    }
} else {
    header("Location: ../index.php?page=view_dokumen");
    exit;
}
?>