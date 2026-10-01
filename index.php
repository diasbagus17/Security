<?php
require_once __DIR__ . '/config/guard.php';
require_login(); // wajib login untuk mengakses dashboard

$page = isset($_GET['page']) ? $_GET['page'] : 'view_dokumen';
$role = (int) $_SESSION['role'];

// Jika role adalah 4 dan mengakses halaman selain sj_manual, arahkan ke sj_manual
if ($role === 4 && $page === 'view_dokumen') {
    $page = 'sj_manual';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D-OUT | Dashboard</title>

    <!-- Favicon / Ikon Tab Web -->
    <link rel="icon" type="image/png" href="logo.png">

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome untuk Icon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS Kustom untuk Tabel Padat -->
    <link rel="stylesheet" href="css/view_dokumen.css">

    <style>
        body { 
            overflow-x: hidden; 
            background-color: #f4f6f9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* --- SIDEBAR MINIMALIS & STATIS --- */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            min-height: 100vh;
            background-color: #1e293b; /* Warna abu-abu gelap kebiruan modern */
            color: #cbd5e1; 
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        }

        #sidebar .sidebar-heading {
            padding: 1.5rem 1.25rem;
            background-color: #0f172a;
            border-bottom: 1px solid #334155;
        }

        #sidebar .user-profile {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #334155;
            background-color: rgba(15, 23, 42, 0.3);
        }

        /* Tautan Menu */
        #sidebar .list-group-item {
            background-color: transparent;
            color: #cbd5e1; 
            border: none;
            padding: 10px 1.25rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            margin: 4px 10px;
            border-radius: 6px;
        }

        #sidebar .list-group-item i {
            font-size: 1rem;
            width: 24px;
            text-align: center;
            margin-right: 10px;
            color: #94a3b8;
        }

        #sidebar .list-group-item:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.08);
        }

        #sidebar .list-group-item:hover i {
            color: #ffffff;
        }

        #sidebar .list-group-item.active {
            background-color: #2563eb; 
            color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        #sidebar .list-group-item.active i {
            color: #ffffff;
        }

        /* Sub-menu styling */
        #doSubMenu ul {
            list-style: none;
            padding-left: 0;
            margin: 5px 10px 5px 25px;
        }

        #doSubMenu ul li a {
            padding: 8px 12px;
            font-size: 0.9rem;
            color: #cbd5e1; 
            border-radius: 6px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        #doSubMenu ul li a:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.08);
        }

        #doSubMenu ul li a.active-sub {
            color: #ffffff;
            background-color: #2563eb;
            font-weight: 500;
        }

        #content {
            width: 100%;
            padding: 24px;
        }

        .navbar-custom {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <div class="d-flex">
        <!-- SIDEBAR -->
        <div id="sidebar" class="d-flex flex-column flex-shrink-0">
            <div class="sidebar-heading">
                <h5 class="text-white fw-bold mb-0 d-flex align-items-center">
                    D-OUT
                </h5>
                <div class="text-light opacity-75 mt-1" style="font-size: 11px; line-height: 1.3;">
                    Document Out Tracking System<br>PT CPI – Plant Ngoro
                </div>
            </div>

            <div class="user-profile">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-25 rounded-circle p-2 text-primary me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fas fa-user fs-6"></i>
                        </div>
                        <div>
                            <strong class="text-white fs-6 d-block" style="line-height: 1.1; font-size: 0.9rem !important;"><?= h($_SESSION['nama'] ?? 'User'); ?></strong>
                            <span class="badge bg-primary text-white mt-1 px-2 py-1" style="font-size: 9px; letter-spacing: 0.5px;">
                                <?= h($_SESSION['bagian'] ?? '-'); ?>
                            </span>
                        </div>
                    </div>
                    <!-- Menu Ganti Password Minimalis (Icon Kunci) di sebelah kanan profil user -->
                    <?php if (in_array($role, [1, 2, 3, 4, 5, 99], true)): ?>
                        <a href="index?page=ganti_password" class="btn btn-sm btn-link text-light p-1 text-decoration-none <?= ($page == 'ganti_password') ? 'text-primary' : 'opacity-75'; ?>" title="Ganti Password">
                            <i class="fas fa-key fs-6"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MENU NAVIGASI SIDEBAR -->
            <ul class="list-group list-group-flush my-3 px-2">

                <!-- MENU UTAMA: DELIVERY ORDER (Disembunyikan jika role 4) -->
                <?php if (in_array($role, [1, 2, 3, 5, 99], true)): ?>
                    <?php
                    $is_do_active = ($page == 'view_dokumen' || $page == 'analisa' || $page == 'laporan_selisih' || $page == 'tambah' || $page == 'edit_dokumen');
                    ?>
                    <div>
                        <a href="#doSubMenu" data-bs-toggle="collapse" class="list-group-item d-flex justify-content-between align-items-center <?= $is_do_active ? 'active' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Delivery Order</span>
                            </div>
                            <i class="fas fa-chevron-down small"></i>
                        </a>

                        <!-- KONTEN SUB-MENU (Collapse) -->
                        <div class="collapse <?= $is_do_active ? 'show' : ''; ?>" id="doSubMenu">
                            <ul>
                                <li class="my-1">
                                    <a href="index?page=view_dokumen" class="<?= ($page == 'view_dokumen' || $page == 'edit_dokumen') ? 'active-sub' : ''; ?>">
                                        <i class="fas fa-list me-2" style="width: 16px;"></i> Data Dokumen
                                    </a>
                                </li>

                                <!-- SUB-MENU CEK SELISIH DATA -->
                                <?php if ($role === 2 || $role === 5 || $role === 99): ?>
                                <li class="my-1">
                                    <a href="index.php?page=analisa" class="<?= ($page == 'analisa' || $page == 'laporan_selisih') ? 'active-sub' : ''; ?>">
                                        <i class="fas fa-chart-bar me-2" style="width: 16px;"></i> Cek Selisih Data
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- MENU UTAMA: SJ MANUAL -->
                <?php if (in_array($role, [1, 2, 3, 4, 5, 99], true)): ?>
                    <a href="index?page=sj_manual" class="list-group-item <?= ($page == 'sj_manual') ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i> SJ Manual
                    </a>
                <?php endif; ?>

                <!-- MENU UTAMA: KENDARAAN -->
                <?php if (in_array($role, [1, 2, 3, 4, 99], true)): ?>
                    <a href="index?page=kendaraan" class="list-group-item <?= ($page == 'kendaraan') ? 'active' : ''; ?>">
                        <i class="fas fa-truck"></i> Kendaraan
                    </a>
                <?php endif; ?>

                <!-- MENU TAMBAH USER: Khusus Role 99 (Admin) -->
                <?php if ($role === 99): ?>
                    <a href="index?page=tambah_user" class="list-group-item <?= ($page == 'tambah_user') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Tambah User
                    </a>
                <?php endif; ?>

                <!-- MENU LOG AKTIVITAS: Khusus Administrator (Role 99) -->
                <?php if ($role === 99): ?>
                    <a href="index?page=log_aktivitas" class="list-group-item <?= ($page == 'log_aktivitas') ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Log Aktivitas
                    </a>
                <?php endif; ?>
            </ul>

            <div class="mt-auto p-3 border-top border-secondary border-opacity-25">
                <a href="logout.php" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center py-2" style="border-color: #475569; font-size: 0.85rem;">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
        </div>

        <!-- KONTEN UTAMA -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom shadow-sm mb-4 px-4 py-2">
                <div class="container-fluid px-0">
                    <span class="text-secondary small fw-medium"><i class="far fa-calendar-alt me-2"></i><?= h(date('d M Y')); ?></span>
                </div>
            </nav>

            <div class="container-fluid px-0">
                <?php
                switch ($page) {
                    case 'view_dokumen':
                        if (in_array($role, [1, 2, 3, 5, 99], true)) {
                            include 'DO/view_dokumen.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.</div>";
                        }
                        break;
                    case 'sj_manual':
                        if (in_array($role, [1, 2, 3, 4, 5, 99], true)) {
                            include 'sj_manual/sj_manual.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.</div>";
                        }
                        break;
                    case 'kendaraan':
                        if (in_array($role, [1, 2, 3, 4, 99], true)) {
                            include 'kendaraan/kendaraan.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.</div>";
                        }
                        break;
                    case 'tambah':
                        if (in_array($role, [1, 2, 3, 5, 99], true)) {
                            include 'DO/tambah_dokumen.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak!</div>";
                        }
                        break;
                    case 'edit_dokumen':
                        if ($role === 99) {
                            include 'DO/edit_dokumen.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak!</div>";
                        }
                        break;
                    case 'import':
                        if ($role === 1 || $role === 5 || $role === 99) {
                            include 'DO/import.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak!</div>";
                        }
                        break;
                    case 'analisa':
                        if ($role === 2 || $role === 5 || $role === 99) {
                            include 'DO/analisa.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Menu ini khusus Bagian Logistik dan Administrator.</div>";
                        }
                        break;
                    case 'laporan_selisih':
                        if ($role === 2 || $role === 5 || $role === 99) {
                            include 'DO/laporan_selisih.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Menu-menu ini khusus Bagian Logistik dan Administrator.</div>";
                        }
                        break;
                    case 'tambah_user':
                        if ($role === 99) {
                            include 'tambah_user.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Halaman ini khusus Administrator.</div>";
                        }
                        break;
                    case 'log_aktivitas':
                        if ($role === 99) {
                            include 'log_aktivitas.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak! Halaman ini khusus Administrator.</div>";
                        }
                        break;
                    case 'ganti_password':
                        if (in_array($role, [1, 2, 3, 4, 5, 99], true)) {
                            include 'ganti_password.php';
                        } else {
                            echo "<div class='alert alert-danger text-center'>Akses ditolak!</div>";
                        }
                        break;
                    default:
                        echo "<div class='alert alert-warning text-center'>Halaman tidak ditemukan!</div>";
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>