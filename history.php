<?php
// history.php - Log Riwayat Perubahan Status & Pembayaran
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$month = $_GET['month'] ?? '';

$sql = "SELECT * FROM payment_logs WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $sql .= " AND (tenant_name LIKE ? OR room_number LIKE ? OR payment_method LIKE ? OR notes LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($month)) {
    $sql .= " AND strftime('%Y-%m', changed_at) = ?";
    $params[] = $month;
}

$sql .= " ORDER BY changed_at DESC LIMIT 150";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$userNames = [];
foreach ($logs as $log) {
    if (!empty($log['user_id'])) {
        $userNames[(int)$log['user_id']] = '';
    }
}
if ($userNames) {
    $marks = implode(',', array_fill(0, count($userNames), '?'));
    $userStmt = $pdo->prepare("SELECT id, display_name FROM user_accounts WHERE id IN ($marks)");
    $userStmt->execute(array_keys($userNames));
    foreach ($userStmt as $user) $userNames[(int)$user['id']] = $user['display_name'];
}

// Total nominal riwayat pada pencarian saat ini
$totalNominal = 0;
foreach ($logs as $l) {
    if ($l['new_status'] === 'sudah_bayar' || $l['new_status'] === 'bayar_sebagian') {
        $totalNominal += (float)$l['amount'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Log Riwayat Perubahan Status</h4>
        <p class="text-muted small mb-0">Catatan audit otomatis setiap kali admin mengubah status pembayaran penghuni kos.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_logs.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export Log (CSV)
        </a>
    </div>
</div>

<!-- Filter dan Pencarian Log -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="history.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama penghuni, kamar, atau catatan..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($month) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1">Filter</button>
                <?php if (!empty($search) || !empty($month)): ?>
                    <a href="history.php" class="btn btn-outline-secondary btn-sm" title="Reset Filter">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Log -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card p-3 stat-card primary">
            <div class="text-muted small">Total Catatan Log</div>
            <h3 class="fw-bold mb-0 text-primary"><?= count($logs) ?> <span class="fs-6 fw-normal text-muted">transaksi/perubahan</span></h3>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 stat-card success">
            <div class="text-muted small">Total Pembayaran Masuk (Pada Riwayat Tampil)</div>
            <h3 class="fw-bold mb-0 text-success">Rp <?= number_format($totalNominal, 0, ',', '.') ?></h3>
        </div>
    </div>
</div>

<!-- Tabel Log Riwayat -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 170px;">Waktu Perubahan</th>
                    <th style="width: 90px;">Kamar</th>
                    <th>Nama Penghuni</th>
                    <th>Diubah Oleh</th>
                    <th>Perubahan Status</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th>Catatan / Keterangan</th>
                    <th class="text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-receipt fa-3x mb-2 text-secondary"></i>
                        <p class="mb-0">Belum ada catatan log riwayat pembayaran.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($l['changed_at'])) ?></div>
                        <small class="text-muted"><?= date('H:i:s', strtotime($l['changed_at'])) ?> WIB</small>
                    </td>
                    <td>
                        <span class="badge bg-dark"><?= htmlspecialchars($l['room_number']) ?></span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($l['tenant_name']) ?></strong>
                    </td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($userNames[(int)($l['user_id'] ?? 0)] ?? 'Sistem lama') ?></span></td>
                    <td>
                        <?php if ($l['old_status'] === 'sudah_bayar'): ?>
                            <span class="badge bg-success">Sudah Bayar</span>
                        <?php elseif ($l['old_status'] === 'bayar_sebagian'): ?>
                            <span class="badge bg-warning text-dark">Sebagian</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Belum Bayar</span>
                        <?php endif; ?>

                        <i class="fa-solid fa-arrow-right mx-1 text-muted small"></i>

                        <?php if ($l['new_status'] === 'sudah_bayar'): ?>
                            <span class="badge bg-success">Sudah Bayar</span>
                        <?php elseif ($l['new_status'] === 'bayar_sebagian'): ?>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock-rotate-left me-1"></i>Membayar Sebagian</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="text-dark">Rp <?= number_format($l['amount'], 0, ',', '.') ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark"><?= htmlspecialchars($l['payment_method'] ?: 'Tunai') ?></span>
                    </td>
                    <td>
                        <small class="text-secondary"><?= htmlspecialchars($l['notes'] ?: '-') ?></small>
                    </td>
                    <td class="text-center">
                        <?php if (in_array($l['new_status'], ['sudah_bayar', 'bayar_sebagian']) && $l['amount'] > 0): ?>
                            <a href="receipt.php?log_id=<?= $l['id'] ?>" target="_blank" class="btn btn-outline-success btn-sm" title="Cetak Kuitansi Transaksi Ini">
                                <i class="fa-solid fa-print me-1"></i> Kuitansi
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
