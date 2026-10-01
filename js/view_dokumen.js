/**
 * view_dokumen.js - Skrip manajemen dokumen keluar
 */

/**
 * Mengatur tampilan form input berdasarkan jenis (Satuan atau Rentang)
 */
function toggleInputMode() {
    let jenisElem = document.getElementById('jenis_input');
    if (!jenisElem) return;
    
    let jenis = jenisElem.value;
    let formSatuan = document.getElementById('form-satuan');
    let formRentang = document.getElementById('form-rentang');
    let groupTanggal = document.getElementById('group-tanggal');
    
    let inputTanggal = document.getElementById('tanggal_dokumen');
    let inputNomorUrut = document.getElementById('nomor_urut');
    let inputNomorAwal = document.getElementById('nomor_awal');
    let inputNomorAkhir = document.getElementById('nomor_akhir');
    
    if (jenis === 'rentang') {
        if (formSatuan) formSatuan.style.display = 'none';
        if (formRentang) formRentang.style.display = 'block';
        if (groupTanggal) groupTanggal.style.display = 'none';
        
        if (inputTanggal) inputTanggal.removeAttribute('required');
        if (inputNomorUrut) inputNomorUrut.removeAttribute('required');
        
        if (inputNomorAwal) inputNomorAwal.setAttribute('required', 'true');
        if (inputNomorAkhir) inputNomorAkhir.setAttribute('required', 'true');
    } else {
        if (formSatuan) formSatuan.style.display = 'block';
        if (formRentang) formRentang.style.display = 'none';
        if (groupTanggal) groupTanggal.style.display = 'block';
        
        if (inputTanggal) inputTanggal.setAttribute('required', 'true');
        if (inputNomorUrut) inputNomorUrut.setAttribute('required', 'true');
        
        if (inputNomorAwal) inputNomorAwal.removeAttribute('required');
        if (inputNomorAkhir) inputNomorAkhir.removeAttribute('required');
    }
}

/**
 * Mengatur tampilan filter berdasarkan kriteria export
 */
function toggleExportMode() {
    let jenisElem = document.getElementById('jenis_kriteria_export');
    if (!jenisElem) return;
    
    let jenis = jenisElem.value;
    let groupTanggal = document.getElementById('group-filter-tanggal');
    let groupNomor = document.getElementById('group-filter-nomor');

    if (groupTanggal) groupTanggal.style.display = 'none';
    if (groupNomor) groupNomor.style.display = 'none';

    if (jenis === 'tanggal' && groupTanggal) {
        groupTanggal.style.display = 'block';
    } else if (jenis === 'nomor' && groupNomor) {
        groupNomor.style.display = 'block';
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Event listener untuk perubahan select mode input & export
    const jenisInputElem = document.getElementById('jenis_input');
    if (jenisInputElem) {
        jenisInputElem.addEventListener('change', toggleInputMode);
    }

    const jenisExportElem = document.getElementById('jenis_kriteria_export');
    if (jenisExportElem) {
        jenisExportElem.addEventListener('change', toggleExportMode);
    }

    const tambahModal = document.getElementById('tambahModal');
    const formTambah = document.getElementById('formTambah');
    
    if (tambahModal) {
        tambahModal.addEventListener('show.bs.modal', function () {
            let jenisElem = document.getElementById('jenis_input');
            if (jenisElem) {
                jenisElem.value = 'satuan';
                toggleInputMode();
            }
        });
    }

    // ===== SIMPAN VIA AJAX =====
    if (formTambah && tambahModal) {
        const KOSONGKAN_SATUAN = ['nomor_urut', 'nomor_do'];
        const KOSONGKAN_RENTANG = ['nomor_awal', 'nomor_akhir'];

        const btnSimpan = document.getElementById('btnSimpanTambah');
        const alertBox = document.getElementById('tambahAlert');
        let jumlahSimpan = 0;

        function tampilPesan(ok, teks) {
            if (!alertBox) return;
            alertBox.className = 'alert py-1 px-2 small mb-2 ' + (ok ? 'alert-success' : 'alert-danger');
            alertBox.textContent = teks;
        }

        formTambah.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!btnSimpan || btnSimpan.disabled) return;
            if (!formTambah.checkValidity()) { 
                formTambah.reportValidity(); 
                return; 
            }

            btnSimpan.disabled = true;
            const fd = new FormData(formTambah);
            const mode = fd.get('jenis_input') || 'satuan';

            fetch('DO/simpan_ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.text())
            .then(txt => {
                try { 
                    return JSON.parse(txt); 
                } catch (_) { 
                    throw new Error('Respon server tidak valid (sesi habis / error PHP). Cek console.'); 
                }
            })
            .then(j => {
                if (j.csrf) {
                    document.querySelectorAll('input[type="hidden"][name*="csrf"]').forEach(function (c) {
                        c.value = j.csrf;
                    });
                }
                if (!j.ok) { 
                    tampilPesan(false, j.message || 'Terjadi kesalahan.'); 
                    return; 
                }

                jumlahSimpan++;
                tampilPesan(true, (j.message || 'Berhasil disimpan') + ' (' + jumlahSimpan + 'x simpan di sesi ini)');
                
                const targetKosong = (mode === 'rentang' ? KOSONGKAN_RENTANG : KOSONGKAN_SATUAN);
                targetKosong.forEach(function (n) {
                    const el = formTambah.elements[n];
                    if (el) el.value = '';
                });
                
                // Setelah simpan sukses, arahkan kembali fokus ke no_timbang (atau tetap di nominator jika diinginkan)
                const fokus = formTambah.elements['no_timbang'];
                if (fokus) fokus.focus();
            })
            .catch(err => {
                tampilPesan(false, err.message || 'Gagal terhubung ke server.');
            })
            .finally(() => { 
                if (btnSimpan) btnSimpan.disabled = false; 
            });
        });

        tambahModal.addEventListener('show.bs.modal', function () {
            jumlahSimpan = 0;
            if (alertBox) {
                alertBox.className = 'd-none';
                alertBox.textContent = '';
            }
        });

        tambahModal.addEventListener('shown.bs.modal', function () {
            // Diubah agar kursor otomatis fokus ke field 'no_timbang' saat modal pertama kali muncul
            const f = formTambah.elements['no_timbang'];
            if (f && f.offsetParent !== null) f.focus();
        });

        tambahModal.addEventListener('hidden.bs.modal', function () {
            if (jumlahSimpan > 0) window.location.reload();
        });
    }

    // ===== POPULASI MODAL EDIT =====
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const id = button.getAttribute('data-id');
            const formEdit = document.getElementById('formEditModal');
            if (formEdit) {
                formEdit.action = 'DO/edit_dokumen.php?id=' + id;
            }
            
            const setVal = (elId, attrName) => {
                let el = document.getElementById(elId);
                if (el) el.value = button.getAttribute(attrName) || '';
            };

            setVal('edit-tanggal', 'data-tanggal');
            setVal('edit-urut', 'data-urut');
            setVal('edit-do', 'data-do');
            setVal('edit-plat', 'data-plat');
            setVal('edit-sopir', 'data-sopir');
            setVal('edit-timbang', 'data-timbang');
            setVal('edit-segel', 'data-segel');
            
            let statusEl = document.getElementById('edit-status');
            if (statusEl) {
                statusEl.value = button.getAttribute('data-status') || 'Aktif';
            }
        });
    }

    // ===== CHECKBOX & HAPUS MASSAL =====
    const formHapus = document.getElementById('formHapusBanyak');
    const selectAll = document.getElementById('selectAll');
    const btnHapusPilih = document.getElementById('btnHapusPilih');
    const infoPilih = document.getElementById('infoPilih');

    function updateHapusButtonState() {
        if (!btnHapusPilih) return;
        const checkItems = document.querySelectorAll('.checkItem');
        const checkedCount = document.querySelectorAll('.checkItem:checked').length;

        if (checkedCount > 0) {
            btnHapusPilih.disabled = false;
            if (infoPilih) infoPilih.textContent = checkedCount + ' data dipilih';
        } else {
            btnHapusPilih.disabled = true;
            if (infoPilih) infoPilih.textContent = 'Belum ada data yang dipilih';
        }

        if (selectAll) {
            selectAll.checked = (checkItems.length > 0 && checkedCount === checkItems.length);
            selectAll.indeterminate = (checkedCount > 0 && checkedCount < checkItems.length);
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.checkItem').forEach(function (item) {
                item.checked = selectAll.checked;
            });
            updateHapusButtonState();
        });
    }

    // Event delegation untuk checkbox baris
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('checkItem')) {
            updateHapusButtonState();
        }
    });

    // Konfirmasi sebelum submit hapus massal
    if (formHapus) {
        formHapus.addEventListener('submit', function (e) {
            const checkedCount = document.querySelectorAll('.checkItem:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                return;
            }
            if (!confirm('Yakin ingin menghapus ' + checkedCount + ' data yang dicentang? Tindakan ini tidak dapat dibatalkan.')) {
                e.preventDefault();
            }
        });
    }

    updateHapusButtonState();
});