<?php
// index.php - Dashboard Admin, Tenggat Terdekat, Notifikasi H-2, Quick Action Status
require_once __DIR__ . '/includes/header.php';

// Helper WhatsApp Direct Link
function generateWaLink(array $t, string $statusDesc, PDO $pdo): string {
    $phone = $t['phone'] ?? '';
    if (empty($phone)) return '#';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    }
    
    $kosName = getSetting($pdo, 'kos_name', 'Kontrakan Srini');
    $bankInfo = getSetting($pdo, 'bank_info', 'BCA 1234567890');
    $rentPrice = (float)$t['rent_price'];
    $paidAmount = (float)($t['paid_amount'] ?? 0);
    $sisa = max(0, $rentPrice - $paidAmount);

    if ($t['payment_status'] === 'bayar_sebagian') {
        $pesan = "Halo Bpk/Ibu {$t['name']} (Unit/Kamar {$t['room_number']}),\n\n"
               . "Salam dari pengelola {$kosName}.\n"
               . "Kami menginformasikan rincian pembayaran sewa Anda:\n"
               . "• Total Tarif Sewa: Rp " . number_format($rentPrice, 0, ',', '.') . "\n"
               . "• Sudah Dibayarkan: Rp " . number_format($paidAmount, 0, ',', '.') . "\n"
               . "• Sisa Tagihan: Rp " . number_format($sisa, 0, ',', '.') . "\n"
               . "• Tenggat Pembayaran: " . formatTanggalIndonesia($t['due_date']) . " ({$statusDesc})\n\n"
               . "Pelunasan sisa tagihan dapat ditransfer ke rekening:\n"
               . "{$bankInfo}\n\n"
               . "Mohon konfirmasi dan kirimkan bukti transfer setelah melakukan pembayaran. Terima kasih banyak!";
    } else {
        $pesan = "Halo Bpk/Ibu {$t['name']} (Unit/Kamar {$t['room_number']}),\n\n"
               . "Salam dari pengelola {$kosName}.\n"
               . "Kami menginformasikan tagihan sewa dengan rincian berikut:\n"
               . "• Tarif Sewa: Rp " . number_format($rentPrice, 0, ',', '.') . "\n"
               . "• Tenggat Pembayaran: " . formatTanggalIndonesia($t['due_date']) . " ({$statusDesc})\n\n"
               . "Pembayaran dapat ditransfer ke rekening:\n"
               . "{$bankInfo}\n\n"
               . "Mohon konfirmasi dan kirimkan bukti transfer jika pembayaran telah dilakukan. Terima kasih banyak!";
    }

    return "https://wa.me/{$clean}?text=" . urlencode($pesan);
}

// Filter dan Search
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$today = date('Y-m-d');
$h2Date = date('Y-m-d', strtotime('+2 days'));

// 1. Statistik Ringkasan
$totalPenghuni = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$sudahBayar    = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE payment_status = 'sudah_bayar'")->fetchColumn();
$bayarSebagian = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE payment_status = 'bayar_sebagian'")->fetchColumn();
$belumBayar    = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE payment_status = 'belum_bayar'")->fetchColumn();

// DP menunggak setelah batas pelunasan 2 bulan; penghuni tanpa pembayaran menunggak setelah tenggat.
$stmtOverdue = $pdo->prepare("SELECT COUNT(*) FROM tenants
    WHERE (payment_status = 'bayar_sebagian' AND due_date < ?)
       OR (payment_status = 'belum_bayar' AND due_date < ?)");
$stmtOverdue->execute([$today, $today]);
$overdueCount = (int)$stmtOverdue->fetchColumn();

// H-1 & H-2 mendekati tenggat
$stmtH2 = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE payment_status IN ('belum_bayar', 'bayar_sebagian') AND due_date > ? AND due_date <= ?");
$stmtH2->execute([$today, $h2Date]);
$h2Count = (int)$stmtH2->fetchColumn();

// Total yang perlu ditindaklanjuti: sudah jatuh tempo atau mendekati tenggat.
$stmtUrgent = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE payment_status IN ('belum_bayar', 'bayar_sebagian') AND due_date <= ?");
$stmtUrgent->execute([$h2Date]);
$urgentCount = (int)$stmtUrgent->fetchColumn();

$totalPemasukan = (float)$pdo->query("SELECT SUM(amount) FROM payment_logs WHERE strftime('%Y-%m', changed_at) = strftime('%Y-%m', 'now')")->fetchColumn() ?: 0;

// 2. Query Data Penghuni Diurutkan dari Tenggat Terdekat
$sql = "SELECT * FROM tenants WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR room_number LIKE ? OR identity_number LIKE ? OR phone LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($filter === 'unpaid') {
    $sql .= " AND payment_status = 'belum_bayar'";
} elseif ($filter === 'partial') {
    $sql .= " AND payment_status = 'bayar_sebagian'";
} elseif ($filter === 'paid') {
    $sql .= " AND payment_status = 'sudah_bayar'";
} elseif ($filter === 'urgent') {
    $sql .= " AND payment_status IN ('belum_bayar', 'bayar_sebagian') AND due_date <= ?";
    $params[] = $h2Date;
} elseif ($filter === 'overdue') {
    $sql .= " AND ((payment_status = 'bayar_sebagian' AND due_date < ?) OR (payment_status = 'belum_bayar' AND due_date < ?))";
    $params[] = $today;
    $params[] = $today;
}

// URUTKAN DARI TENGGAT TERDEKAT (Prioritas Belum Bayar & Membayar Sebagian terlebih dahulu)
$sql .= " ORDER BY CASE 
    WHEN payment_status = 'belum_bayar' THEN 0 
    WHEN payment_status = 'bayar_sebagian' THEN 1 
    ELSE 2 
END, due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

$timelineTenants = $pdo->query("SELECT room_number, name, start_date, due_date, payment_status
    FROM tenants ORDER BY start_date, room_number, name")->fetchAll();
$timelineStart = new DateTime('first day of this month');
$timelineEnd = clone $timelineStart;
$timelineEnd->modify('+12 months');
if ($timelineTenants) {
    $timelineStart = new DateTime($timelineTenants[0]['start_date']);
    $timelineStart->modify('first day of this month');
    $timelineEnd = new DateTime($timelineTenants[0]['due_date']);
    foreach ($timelineTenants as $timelineTenant) {
        $contractStart = new DateTime($timelineTenant['start_date']);
        $contractEnd = new DateTime($timelineTenant['due_date']);
        if ($contractStart < $timelineStart) {
            $timelineStart = clone $contractStart;
            $timelineStart->modify('first day of this month');
        }
        if ($contractEnd > $timelineEnd) {
            $timelineEnd = clone $contractEnd;
        }
    }
    $timelineEnd->modify('first day of next month');
}
$timelineTotalDays = max(1, (int)$timelineStart->diff($timelineEnd)->days);
$timelineMonths = [];
$timelineCursor = clone $timelineStart;
$timelineMonthCount = max(1, ((int)$timelineStart->diff($timelineEnd)->y * 12) + (int)$timelineStart->diff($timelineEnd)->m);
for ($monthIndex = 0; $monthIndex < $timelineMonthCount; $monthIndex++) {
    $timelineMonths[] = clone $timelineCursor;
    $timelineCursor->modify('+1 month');
}
?>

<!-- Alert Notifikasi H-2 & Menunggak -->
<?php if ($urgentCount > 0): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4 py-3">
    <div class="me-3">
        <span class="fa-stack fa-lg">
            <i class="fa-solid fa-circle fa-stack-2x text-warning"></i>
            <i class="fa-solid fa-bell fa-stack-1x text-white fa-shake"></i>
        </span>
    </div>
    <div class="flex-grow-1">
        <h6 class="mb-1 fw-bold text-dark">Pemberitahuan Tenggat Pembayaran Kos!</h6>
        <div class="small text-secondary">
            Terdapat <strong><?= $overdueCount ?></strong> penghuni yang menunggak
            (DP melewati 2 bulan atau belum membayar setelah tenggat),
            dan <strong><?= $bayarSebagian ?></strong> penghuni berstatus <strong>Membayar Sebagian</strong>.
            Anda dapat langsung mengirimkan pengingat ramah melalui tombol WhatsApp di bawah.
        </div>
    </div>
    <div>
        <a href="index.php?filter=urgent" class="btn btn-warning btn-sm fw-bold shadow-sm px-3">
            Lihat <?= $urgentCount ?> Tagihan
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 4 Kotak Ringkasan Statistik -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Penghuni</span>
                    <h3 class="fw-bold my-1"><?= $totalPenghuni ?></h3>
                    <small class="text-primary"><i class="fa-solid fa-house me-1"></i>Unit aktif terdata</small>
                </div>
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary fs-3">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Sudah Lunas</span>
                    <h3 class="fw-bold my-1 text-success"><?= $sudahBayar ?></h3>
                    <small class="text-success"><i class="fa-solid fa-check-double me-1"></i>Pembayaran penuh sesuai tarif</small>
                </div>
                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success fs-3">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Belum Bayar & Sebagian</span>
                    <h3 class="fw-bold my-1 text-warning"><?= ($belumBayar + $bayarSebagian) ?></h3>
                    <small class="text-secondary"><i class="fa-solid fa-clock-rotate-left me-1"></i><?= $belumBayar ?> belum bayar &bull; <?= $bayarSebagian ?> DP valid, belum lunas</small>
                </div>
                <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning fs-3">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3 stat-card danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Menunggak</span>
                    <h3 class="fw-bold my-1 text-danger"><?= $overdueCount ?></h3>
                    <small class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>DP lewat 2 bulan atau belum bayar setelah tenggat</small>
                </div>
                <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger fs-3">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timeline Masa Kontrak -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fa-solid fa-chart-gantt text-primary me-2"></i>Timeline Masa Kontrak
            </h5>
            <small class="text-muted">Rentang kontrak berdasarkan tanggal mulai dan tenggat pembayaran setiap penghuni.</small>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$timelineTenants): ?>
            <div class="text-center text-muted py-4">Belum ada data penghuni untuk ditampilkan.</div>
        <?php else: ?>
            <div class="timeline-scroll">
                <div class="timeline-grid" style="--timeline-columns: <?= $timelineMonthCount ?>;">
                    <div class="timeline-label timeline-header">Unit / Penghuni</div>
                    <div class="timeline-months">
                        <?php foreach ($timelineMonths as $month): ?>
                            <div class="timeline-month"><?= htmlspecialchars(formatTanggalIndonesia($month->format('Y-m-01'))) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($timelineTenants as $timelineTenant): ?>
                        <?php
                        $contractStart = new DateTime($timelineTenant['start_date']);
                        $contractEnd = new DateTime($timelineTenant['due_date']);
                        $barStart = max(0, min($timelineTotalDays, $timelineStart->diff($contractStart)->invert
                            ? 0
                            : (int)$timelineStart->diff($contractStart)->days));
                        if ($contractStart < $timelineStart) {
                            $barStart = 0;
                        }
                        $barEnd = $contractEnd <= $timelineStart
                            ? 0
                            : ($contractEnd >= $timelineEnd ? $timelineTotalDays : (int)$timelineStart->diff($contractEnd)->days);
                        $barWidth = max(1, $barEnd - $barStart);
                        $barClass = $timelineTenant['payment_status'] === 'sudah_bayar'
                            ? 'timeline-paid'
                            : ($timelineTenant['payment_status'] === 'bayar_sebagian' ? 'timeline-partial' : 'timeline-unpaid');
                        ?>
                        <div class="timeline-label">
                            <strong><?= htmlspecialchars($timelineTenant['room_number']) ?></strong>
                            <small><?= htmlspecialchars($timelineTenant['name']) ?></small>
                        </div>
                        <div class="timeline-track">
                            <?php foreach ($timelineMonths as $month): ?>
                                <span class="timeline-column"></span>
                            <?php endforeach; ?>
                            <?php if ($barEnd > 0 && $barStart < $timelineTotalDays): ?>
                                <span class="timeline-bar <?= $barClass ?>" style="left: <?= ($barStart / $timelineTotalDays) * 100 ?>%; width: <?= ($barWidth / $timelineTotalDays) * 100 ?>%;" title="<?= htmlspecialchars(formatTanggalIndonesia($timelineTenant['start_date']) . ' - ' . formatTanggalIndonesia($timelineTenant['due_date'])) ?>"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 px-3 py-3 border-top small text-muted">
                <span><i class="fa-solid fa-square text-success me-1"></i>Sudah Lunas</span>
                <span><i class="fa-solid fa-square text-warning me-1"></i>Membayar Sebagian</span>
                <span><i class="fa-solid fa-square text-secondary me-1"></i>Belum Bayar</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Header Tabel, Filter & Pencarian -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <!-- Filter Tabs -->
            <div class="col-md-7">
                <div class="btn-group btn-group-sm flex-wrap gap-1" role="group">
                    <a href="index.php" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Semua (<?= $totalPenghuni ?>)
                    </a>
                    <a href="index.php?filter=urgent" class="btn <?= $filter === 'urgent' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark' ?>">
                        <i class="fa-solid fa-bell me-1"></i> Perlu Ditagih (<?= $urgentCount ?>)
                    </a>
                    <a href="index.php?filter=overdue" class="btn <?= $filter === 'overdue' ? 'btn-danger' : 'btn-outline-danger' ?>">
                        Menunggak (<?= $overdueCount ?>)
                    </a>
                    <a href="index.php?filter=partial" class="btn <?= $filter === 'partial' ? 'btn-info text-dark fw-bold' : 'btn-outline-info text-dark' ?>">
                        Membayar Sebagian (<?= $bayarSebagian ?>)
                    </a>
                    <a href="index.php?filter=unpaid" class="btn <?= $filter === 'unpaid' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                        Belum Bayar (<?= $belumBayar ?>)
                    </a>
                    <a href="index.php?filter=paid" class="btn <?= $filter === 'paid' ? 'btn-success' : 'btn-outline-success' ?>">
                        Sudah Lunas (<?= $sudahBayar ?>)
                    </a>
                </div>
            </div>

            <!-- Form Pencarian -->
            <div class="col-md-5">
                <form action="index.php" method="GET" class="d-flex gap-2">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <?php endif; ?>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama, kamar, NIK, atau no HP..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit">Cari</button>
                        <?php if (!empty($search)): ?>
                            <a href="index.php<?= $filter !== 'all' ? '?filter=' . urlencode($filter) : '' ?>" class="btn btn-outline-danger" title="Reset Pencarian">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Monitoring Tenggat Pembayaran -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fa-solid fa-calendar-check text-primary me-2"></i>Daftar Tenggat Pembayaran
            </h5>
            <small class="text-muted">Diurutkan otomatis dari hari tenggat terdekat (menunggak, H-2, hingga terbayar)</small>
        </div>
        <div class="d-flex gap-2">
            <a href="upload.php" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-file-excel me-1"></i> Upload .xlsx
            </a>
            <a href="tambah_tenant.php" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-user-plus me-1"></i> Tambah Penghuni
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 100px;">Unit</th>
                    <th>Nama Penghuni & NIK</th>
                    <th>Kontak WhatsApp</th>
                    <th>Tarif Sewa</th>
                    <th>Tenggat Pembayaran</th>
                    <th>Status</th>
                    <th>Hitungan Hari</th>
                    <th class="text-center" style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary"></i>
                            <h6>Tidak ada data penghuni yang cocok.</h6>
                            <p class="small mb-3">Silakan upload file Excel atau tambahkan data penghuni baru.</p>
                            <a href="upload.php" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-file-excel me-1"></i> Upload File .xlsx Sekarang
                            </a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tenants as $t): 
                    $due = new DateTime($t['due_date']);
                    $now = new DateTime($today);
                    $diff = (int)$now->diff($due)->format('%r%a');

                    $isPaid = ($t['payment_status'] === 'sudah_bayar');
                    $isPartial = ($t['payment_status'] === 'bayar_sebagian');
                    $paidAmount = (float)($t['paid_amount'] ?? 0);
                    $rentPrice = (float)$t['rent_price'];
                    $sisa = max(0, $rentPrice - $paidAmount);

                    // Badge dan Label Status
                    if ($isPaid) {
                        $badgeClass = 'badge-paid';
                        $statusLabel = '<i class="fa-solid fa-check me-1"></i> Sudah Lunas';
                        $countdownText = '<span class="text-success small fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Terbayar</span>';
                        $waStatus = "Lunas";
                        $rowClass = '';
                    } elseif ($isPartial) {
                        $keteranganNominal = '<div class="small fw-semibold text-primary mt-1">Baru bayar: Rp ' . number_format($paidAmount, 0, ',', '.') . '</div>'
                                           . '<div class="small text-danger" style="font-size: 0.72rem;">Sisa: Rp ' . number_format($sisa, 0, ',', '.') . '</div>';

                        if ($diff < 0) {
                            $badgeClass = 'badge-overdue';
                            $statusLabel = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Membayar Sebagian (Menunggak)' . $keteranganNominal;
                            $countdownText = '<span class="badge bg-danger">Batas DP terlewati ' . abs($diff) . ' Hari</span>';
                            $waStatus = "Batas pelunasan DP terlewati " . abs($diff) . " Hari (Sisa Rp " . number_format($sisa, 0, ',', '.') . ")";
                            $rowClass = 'table-danger bg-opacity-25';
                        } elseif ($diff === 0) {
                            $badgeClass = 'badge-partial';
                            $statusLabel = '<i class="fa-solid fa-clock-rotate-left me-1"></i> Membayar Sebagian (Hari H)' . $keteranganNominal;
                            $countdownText = '<span class="badge bg-warning text-dark animate__animated animate__pulse">Hari H</span>';
                            $waStatus = "Jatuh Tempo Hari Ini (Sisa Rp " . number_format($sisa, 0, ',', '.') . ")";
                            $rowClass = 'table-warning bg-opacity-25';
                        } elseif ($diff <= 2) {
                            $badgeClass = 'badge-partial';
                            $statusLabel = '<i class="fa-solid fa-clock-rotate-left me-1"></i> Membayar Sebagian (H-' . $diff . ')' . $keteranganNominal;
                            $countdownText = '<span class="badge bg-warning text-dark fw-bold">' . $diff . ' Hari Lagi</span>';
                            $waStatus = $diff . " Hari Lagi (Sisa Rp " . number_format($sisa, 0, ',', '.') . ")";
                            $rowClass = 'table-warning bg-opacity-10';
                        } else {
                            $badgeClass = 'badge-partial';
                            $statusLabel = '<i class="fa-solid fa-clock-rotate-left me-1"></i> Membayar Sebagian' . $keteranganNominal;
                            $countdownText = '<span class="text-secondary small">' . $diff . ' Hari Lagi</span>';
                            $waStatus = $diff . " Hari Lagi (Sisa Rp " . number_format($sisa, 0, ',', '.') . ")";
                            $rowClass = '';
                        }
                    } else {
                        // Belum bayar sama sekali
                        if ($diff < 0) {
                            $badgeClass = 'badge-overdue';
                            $statusLabel = '<i class="fa-solid fa-circle-exclamation me-1"></i> Menunggak';
                            $countdownText = '<span class="badge bg-danger">Terlambat ' . abs($diff) . ' Hari</span>';
                            $waStatus = "Menunggak " . abs($diff) . " Hari";
                            $rowClass = 'table-danger bg-opacity-25';
                        } elseif ($diff === 0) {
                            $badgeClass = 'badge-h2';
                            $statusLabel = '<i class="fa-solid fa-bell me-1"></i> Belum Bayar (Hari H)';
                            $countdownText = '<span class="badge bg-warning text-dark animate__animated animate__pulse">Jatuh Tempo Hari Ini</span>';
                            $waStatus = "Jatuh Tempo Hari Ini";
                            $rowClass = 'table-warning bg-opacity-25';
                        } elseif ($diff <= 2) {
                            // H-1 atau H-2
                            $badgeClass = 'badge-h2';
                            $statusLabel = '<i class="fa-solid fa-triangle-exclamation me-1"></i> H-' . $diff . ' Jatuh Tempo';
                            $countdownText = '<span class="badge bg-warning text-dark fw-bold">' . $diff . ' Hari Lagi</span>';
                            $waStatus = $diff . " Hari Lagi";
                            $rowClass = 'table-warning bg-opacity-10';
                        } else {
                            // Belum bayar (aman)
                            $badgeClass = 'badge-safe';
                            $statusLabel = 'Belum Bayar';
                            $countdownText = '<span class="text-secondary small">' . $diff . ' Hari Lagi</span>';
                            $waStatus = $diff . " Hari Lagi";
                            $rowClass = '';
                        }
                    }

                    $waLink = generateWaLink($t, $waStatus, $pdo);
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <span class="badge bg-dark fs-6 px-2 py-1"><?= htmlspecialchars($t['room_number']) ?></span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($t['name']) ?></div>
                        <small class="text-muted"><i class="fa-solid fa-id-card me-1"></i>NIK: <?= htmlspecialchars($t['identity_number'] ?: '-') ?></small>
                        <?php if (!empty($t['ktp_address'])): ?>
                            <div class="text-muted small" style="font-size: 0.75rem;"><i class="fa-solid fa-map-pin me-1"></i><?= htmlspecialchars(substr($t['ktp_address'], 0, 35)) ?><?= strlen($t['ktp_address']) > 35 ? '...' : '' ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($t['phone'])): ?>
                            <a href="<?= $waLink ?>" target="_blank" class="btn wa-btn btn-sm rounded-pill px-2 py-1" title="Kirim Pengingat Tagihan via WhatsApp">
                                <i class="fa-brands fa-whatsapp me-1"></i> <?= htmlspecialchars($t['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small"><i class="fa-solid fa-phone-slash me-1"></i>Tidak ada no HP</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="text-dark">Rp <?= number_format($t['rent_price'], 0, ',', '.') ?></strong>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars(rentPeriodLabel($t['rent_period'] ?? 'bulanan')) ?></div>
                        <?php if ($isPartial): ?>
                            <div class="small text-success fw-semibold" style="font-size: 0.75rem;"><i class="fa-solid fa-check me-1"></i>Masuk: Rp <?= number_format($paidAmount, 0, ',', '.') ?></div>
                            <div class="small text-danger fw-semibold" style="font-size: 0.75rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Sisa: Rp <?= number_format($sisa, 0, ',', '.') ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-bold"><?= formatTanggalIndonesia($t['due_date']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                    </td>
                    <td>
                        <?= $countdownText ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <!-- Tombol Ubah Status -->
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalStatus<?= $t['id'] ?>" title="Ubah Status Pembayaran">
                                <i class="fa-solid fa-arrow-right-arrow-left"></i> Status
                            </button>

                            <?php if ($isPaid || $isPartial): ?>
                                <!-- Cetak Kuitansi -->
                                <a href="receipt.php?tenant_id=<?= $t['id'] ?>" target="_blank" class="btn btn-outline-success" title="Cetak Kuitansi Tanda Terima">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Edit Penghuni -->
                            <a href="tenants.php?action=edit&id=<?= $t['id'] ?>" class="btn btn-outline-secondary" title="Edit Data">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </div>

                            <?php include __DIR__ . '/includes/payment_modal.php'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
