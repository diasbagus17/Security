document.addEventListener("DOMContentLoaded", function () {

    // Tampilkan modal Bootstrap (pakai satu instance saja, tidak dobel)
    function showModal(id) {
        const el = document.getElementById(id);
        if (el) bootstrap.Modal.getOrCreateInstance(el).show();
    }

    // ---------------------------------------------------------------
    // Tombol Proses Masuk / Keluar (event delegation)
    // ---------------------------------------------------------------
    document.addEventListener("click", function (e) {
        const btnMasuk = e.target.closest(".btn-proses-masuk");
        if (btnMasuk) {
            const inputId = document.getElementById("masuk_kendaraan_id");
            const inputPlat = document.getElementById("masuk_plat_no");
            if (inputId) inputId.value = btnMasuk.getAttribute("data-id");
            if (inputPlat) inputPlat.value = btnMasuk.getAttribute("data-plat");
            showModal("modalProsesMasuk");
            return;
        }

        const btnKeluar = e.target.closest(".btn-proses-keluar");
        if (btnKeluar) {
            const inputId = document.getElementById("keluar_kendaraan_id");
            const inputPlat = document.getElementById("keluar_plat_no");
            if (inputId) inputId.value = btnKeluar.getAttribute("data-id");
            if (inputPlat) inputPlat.value = btnKeluar.getAttribute("data-plat");
            showModal("modalProsesKeluar");
            return;
        }

        // Popup detail barang (tabel)
        const btnDetail = e.target.closest(".btn-detail-barang");
        if (btnDetail) {
            let listBarang = [];
            try {
                listBarang = JSON.parse(btnDetail.getAttribute("data-barang") || "[]");
            } catch (err) {
                listBarang = [];
            }

            const platEl = document.getElementById("detail_plat_no");
            if (platEl) platEl.textContent = btnDetail.getAttribute("data-plat") || "";

            const tbody = document.getElementById("body-table-detail-barang");
            if (tbody) {
                tbody.innerHTML = "";
                if (listBarang.length > 0) {
                    listBarang.forEach(function (item, index) {
                        const tr = document.createElement("tr");
                        [index + 1, item.no_surat_jalan, item.jenis_barang, item.jumlah].forEach(function (val) {
                            const td = document.createElement("td");
                            // textContent = aman dari XSS
                            td.textContent = (val === null || val === undefined || val === "") ? "-" : val;
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement("tr");
                    const td = document.createElement("td");
                    td.colSpan = 4;
                    td.className = "text-center text-muted";
                    td.textContent = "Tidak ada detail barang.";
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
            }
            showModal("modalDetailBarang");
        }
    });

    // ---------------------------------------------------------------
    // Form input kendaraan: tampilkan rincian barang hanya jika Bongkar
    // ---------------------------------------------------------------
    const selectStatus = document.getElementById("status_kendaraan");
    const wrapperDetail = document.getElementById("wrapper-detail-barang");

    function toggleDetailBarang() {
        if (!selectStatus || !wrapperDetail) return;
        const isBongkar = selectStatus.value === "Bongkar";
        wrapperDetail.classList.toggle("d-none", !isBongkar);
        // Input yang disembunyikan tidak ikut terkirim
        wrapperDetail.querySelectorAll("input").forEach(function (inp) {
            inp.disabled = !isBongkar;
        });
    }
    if (selectStatus && wrapperDetail) {
        selectStatus.addEventListener("change", toggleDetailBarang);
        toggleDetailBarang();
    }

    // ---------------------------------------------------------------
    // Tambah / hapus baris barang dinamis
    // ---------------------------------------------------------------
    const btnTambahBaris = document.getElementById("tambahBarisBarang");
    const containerBaris = document.getElementById("container-baris-barang");
    if (btnTambahBaris && containerBaris) {
        btnTambahBaris.addEventListener("click", function () {
            containerBaris.insertAdjacentHTML("beforeend", `
                <div class="row input-barang-row mb-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="no_surat_jalan[]" placeholder="No Surat Jalan">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="jenis_barang[]" placeholder="Nama Jenis Barang">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="jumlah_barang[]" placeholder="Jumlah (cth: 10 kg)">
                    </div>
                    <div class="col-md-1 text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-baris"><i class="fas fa-times"></i></button>
                    </div>
                </div>`);
        });

        containerBaris.addEventListener("click", function (e) {
            const btn = e.target.closest(".btn-hapus-baris");
            if (btn) btn.closest(".input-barang-row").remove();
        });
    }

    // ---------------------------------------------------------------
    // Buka ulang modal input jika simpan gagal (data form diisi ulang oleh PHP)
    // ---------------------------------------------------------------
    const modalTambah = document.getElementById("modalTambahKendaraan");
    if (modalTambah && modalTambah.getAttribute("data-auto-open") === "1") {
        showModal("modalTambahKendaraan");
    }
});