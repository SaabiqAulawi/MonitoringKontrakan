<?php
// includes/header.php
require_once __DIR__ . '/../auth.php';
$currentUser = requireLogin();

$today = date('Y-m-d');
$h2Date = date('Y-m-d', strtotime('+2 days'));
$overdueCutoff = date('Y-m-d', strtotime('-4 days')); // H+4 batas akhir grace period, H+5 ke atas menunggak

// Hitung notifikasi belum lunas (belum bayar & membayar sebagian) yang mendekati jatuh tempo atau lewat tenggat
$stmtUrgent = $pdo->prepare("SELECT COUNT(*) FROM tenants 
    WHERE payment_status IN ('belum_bayar', 'bayar_sebagian') 
    AND due_date <= ?");
$stmtUrgent->execute([$h2Date]);
$urgentCount = (int)$stmtUrgent->fetchColumn();

// Ambil info nama kontrakan
$kosName = getSetting($pdo, 'kos_name', 'Kontrakan Srini');

// Deteksi active page
$currentPage = basename($_SERVER['PHP_SELF']);

function formatTanggalIndonesia(string $date): string {
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($kosName) ?> - Admin Monitoring</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --accent-color: #3b82f6;
            --bg-color: #f1f5f9;
        }
        body {
            background-color: var(--bg-color);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.2);
        }
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.18);
            color: #ffffff !important;
        }
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .badge-overdue {
            background-color: #ef4444;
            color: #fff;
            font-weight: 600;
            padding: 6px 10px;
        }
        .badge-h2 {
            background-color: #f59e0b;
            color: #fff;
            font-weight: 600;
            padding: 6px 10px;
        }
        .badge-partial {
            background-color: #f59e0b;
            color: #1e293b;
            font-weight: 600;
            padding: 6px 10px;
        }
        .badge-grace {
            background-color: #d97706;
            color: #fff;
            font-weight: 600;
            padding: 6px 10px;
        }
        .badge-safe {
            background-color: #64748b;
            color: #fff;
            padding: 6px 10px;
        }
        .badge-paid {
            background-color: #10b981;
            color: #fff;
            font-weight: 600;
            padding: 6px 10px;
        }
        .stat-card {
            border-left: 5px solid transparent;
        }
        .stat-card.primary { border-left-color: #3b82f6; }
        .stat-card.success { border-left-color: #10b981; }
        .stat-card.warning { border-left-color: #f59e0b; }
        .stat-card.danger { border-left-color: #ef4444; }
        .table > :not(caption) > * > * {
            padding: 0.85rem 1rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        .wa-btn {
            background-color: #25D366;
            color: white;
            font-weight: 600;
            border: none;
        }
        .wa-btn:hover {
            background-color: #20bd5a;
            color: white;
        }
        .content-wrapper {
            flex: 1 0 auto;
        }
        footer {
            flex-shrink: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top mb-4">
    <div class="container-fluid px-lg-5">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="fa-solid fa-building-user fs-4 me-2"></i>
            <span><?= htmlspecialchars($kosName) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fa-solid fa-chart-pie me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'upload.php' ? 'active' : '' ?>" href="upload.php">
                        <i class="fa-solid fa-file-excel me-1"></i> Upload .xlsx
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'tenants.php' ? 'active' : '' ?>" href="tenants.php">
                        <i class="fa-solid fa-users me-1"></i> Penghuni
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'tambah_tenant.php' ? 'active' : '' ?>" href="tambah_tenant.php">
                        <i class="fa-solid fa-user-plus me-1"></i> Tambah Tenant
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'units.php' ? 'active' : '' ?>" href="units.php">
                        <i class="fa-solid fa-house me-1"></i> Unit
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'tambah_unit.php' ? 'active' : '' ?>" href="tambah_unit.php">
                        <i class="fa-solid fa-house-circle-plus me-1"></i> Tambah Unit
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'contacts.php' ? 'active' : '' ?>" href="contacts.php">
                        <i class="fa-solid fa-address-book me-1"></i> Kontak
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'history.php' ? 'active' : '' ?>" href="history.php">
                        <i class="fa-solid fa-receipt me-1"></i> Log Riwayat Status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                        <i class="fa-solid fa-gear me-1"></i> Pengaturan
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <span class="text-white small d-none d-lg-inline"><?= htmlspecialchars($currentUser['display_name']) ?></span>
                <form method="post" action="logout.php" class="d-inline">
                    <?= csrfInput() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
                <?php if ($urgentCount > 0): ?>
                    <a href="index.php?filter=urgent" class="btn btn-warning btn-sm rounded-pill fw-bold px-3 text-dark shadow-sm">
                        <i class="fa-solid fa-bell me-1 fa-shake"></i> <?= $urgentCount ?> Jatuh Tempo / H-2
                    </a>
                <?php else: ?>
                    <span class="badge bg-success rounded-pill px-3 py-2">
                        <i class="fa-solid fa-circle-check me-1"></i> Semua Tagihan Terkontrol
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="content-wrapper container-fluid px-lg-5 mb-5">
