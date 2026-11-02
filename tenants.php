<?php
// tenants.php - Manajemen Lengkap Data Penghuni (Tambah Manual, Edit, Hapus)
require_once __DIR__ . '/includes/header.php';

$message = '';
$alertType = 'info';

// Hapus Penghuni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = (int)$_POST['delete_id'];
    $stmtDeletedTenant = $pdo->prepare('SELECT unit_id FROM tenants WHERE id = ?');
    $stmtDeletedTenant->execute([$id]);
    $deletedUnitId = (int)($stmtDeletedTenant->fetchColumn() ?: 0);
    $stmtDel = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
    $stmtDel->execute([$id]);
    if ($deletedUnitId > 0) {
        $pdo->prepare("UPDATE units SET unit_status = 'kosong', updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND unit_status = 'terisi'
            AND NOT EXISTS (SELECT 1 FROM tenants WHERE unit_id = units.id)")->execute([$deletedUnitId]);
    }
    $message = "Data penghuni berhasil dihapus.";
    $alertType = "success";
}

// Tambah / Update Penghuni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $unitId = (int)($_POST['unit_id'] ?? 0);
    $unitStmt = $pdo->prepare('SELECT id, unit_code, address FROM units WHERE id = ?');
    $unitStmt->execute([$unitId]);
    $unit = $unitStmt->fetch();
    $room = $unit ? $unit['unit_code'] : '';
    $name = trim($_POST['name'] ?? '');
    $nikInput = trim($_POST['identity_number'] ?? '');
    $nik = $nikInput !== '' ? $nikInput : null;
    $phone = trim($_POST['phone'] ?? '');
    $ktpAddress = trim($_POST['ktp_address'] ?? '');
    $landlord = trim($_POST['landlord_name'] ?? '');
    $price = (float)($_POST['rent_price'] ?? 1000000);
    $period = trim($_POST['rent_period'] ?? 'bulanan');
    if (!in_array($period, ['bulanan', '6_bulan', 'tahunan'], true)) {
        $period = 'bulanan';
    }
    $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
    $dueDate = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+' . rentPeriodMonths($period) . ' months')));
    $status = trim($_POST['payment_status'] ?? 'belum_bayar');
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    if ($status === 'sudah_bayar' && $paidAmount <= 0) {
        $paidAmount = $price;
    } elseif ($status === 'belum_bayar') {
        $paidAmount = 0;
    }
    $notes = trim($_POST['notes'] ?? '');

    if (!$unit || empty($name)) {
        $message = "Unit dan nama wajib diisi!";
        $alertType = "danger";
    } else {
        $stmtUnitTenant = $pdo->prepare('SELECT id FROM tenants WHERE unit_id = ? AND id != ?');
        $stmtUnitTenant->execute([$unitId, $id]);
        if ($stmtUnitTenant->fetch()) {
            $message = "Unit tersebut sudah memiliki penghuni.";
            $alertType = "danger";
        } elseif ($id > 0) {
            // Edit: cek NIK apakah bentrok dengan tenant lain
            $stmtCek = $pdo->prepare("SELECT id FROM tenants WHERE identity_number = ? AND id != ?");
            $stmtCek->execute([$nik, $id]);
            if ($nik !== null && $stmtCek->fetch()) {
                $message = 'NIK <strong>' . htmlspecialchars($nik, ENT_QUOTES, 'UTF-8') . '</strong> sudah digunakan oleh penghuni lain!';
                $alertType = "danger";
            } else {
                $stmtUp = $pdo->prepare("UPDATE tenants SET 
                    unit_id = ?, room_number = ?, name = ?, identity_number = ?, phone = ?, ktp_address = ?,
                    landlord_name = ?, rent_price = ?, paid_amount = ?, rent_period = ?, start_date = ?, due_date = ?,
                    payment_status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUp->execute([$unitId, $room, $name, $nik, $phone, $ktpAddress, $landlord, $price, $paidAmount, $period, $startDate, $dueDate, $status, $notes, $id]);
                $message = "Data penghuni berhasil diperbarui.";
                $alertType = "success";
            }
        } else {
            // Tambah baru: cek duplikasi NIK
            $stmtCek = $pdo->prepare("SELECT id FROM tenants WHERE identity_number = ?");
            $stmtCek->execute([$nik]);
            if ($nik !== null && $stmtCek->fetch()) {
                $message = 'NIK <strong>' . htmlspecialchars($nik, ENT_QUOTES, 'UTF-8') . '</strong> sudah terdaftar di sistem! Data tidak disimpan untuk menghindari duplikasi.';
                $alertType = "warning";
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO tenants (
                    unit_id, room_number, name, identity_number, phone, ktp_address, landlord_name,
                    rent_price, paid_amount, rent_period, start_date, due_date, payment_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtIns->execute([$unitId, $room, $name, $nik, $phone, $ktpAddress, $landlord, $price, $paidAmount, $period, $startDate, $dueDate, $status, $notes]);
                $message = "Penghuni baru berhasil ditambahkan.";
                $alertType = "success";
            }
        }
        if ($message === "Data penghuni berhasil diperbarui." || $message === "Penghuni baru berhasil ditambahkan.") {
            $pdo->prepare("UPDATE units SET unit_status = 'terisi', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$unitId]);
            $pdo->exec("UPDATE units SET unit_status = 'kosong', updated_at = CURRENT_TIMESTAMP
                WHERE unit_status = 'terisi'
                AND NOT EXISTS (SELECT 1 FROM tenants WHERE unit_id = units.id)");
        }
    }
}

// Data untuk form edit jika action=edit
$editTenant = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmtE = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmtE->execute([(int)$_GET['id']]);
    $editTenant = $stmtE->fetch();
}
$units = $pdo->query('SELECT id, unit_code, address, unit_status FROM units ORDER BY unit_code')->fetchAll();

// Ambil semua daftar penghuni
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM tenants WHERE 1=1 ";
$params = [];
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR room_number LIKE ? OR identity_number LIKE ? OR phone LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}
$sql .= " ORDER BY room_number ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();
?>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $alertType ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-end gap-2 mb-3">
    <a href="tambah_tenant.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Tambah Tenant</a>
</div>
<div class="row g-4">
    <!-- Form Tambah / Edit Penghuni -->
    <?php if ($editTenant): ?>
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">
                    <i class="fa-solid <?= $editTenant ? 'fa-pen-to-square' : 'fa-user-plus' ?> me-2"></i>
                    <?= $editTenant ? 'Edit Data Penghuni' : 'Tambah Penghuni Manual' ?>
                </h5>
            </div>
            <div class="card-body">
                <form action="tenants.php" method="POST">
                    <?= csrfInput() ?>
                    <?php if ($editTenant): ?>
                        <input type="hidden" name="id" value="<?= $editTenant['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Unit Kontrakan *</label>
                        <select name="unit_id" class="form-select form-select-sm" required>
                            <option value="">Pilih unit</option>
                            <?php foreach ($units as $unitOption): ?>
                                <option value="<?= (int)$unitOption['id'] ?>" <?= (int)($editTenant['unit_id'] ?? 0) === (int)$unitOption['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unitOption['unit_code'] . ' · ' . $unitOption['address'] . ' (' . str_replace('_', ' ', $unitOption['unit_status']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$units): ?><small class="text-danger">Tambahkan unit terlebih dahulu melalui menu Unit.</small><?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nama Lengkap *</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama penghuni..." value="<?= htmlspecialchars($editTenant['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">NIK / No. KTP (Opsional)</label>
                        <input type="text" name="identity_number" class="form-control form-control-sm" placeholder="16 digit NIK jika ada..." value="<?= htmlspecialchars($editTenant['identity_number'] ?? '') ?>">
                        <small class="text-muted" style="font-size: 0.7rem;">Jika diisi, digunakan untuk mencegah data ganda.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">No. HP / WhatsApp</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($editTenant['phone'] ?? '') ?>">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <label class="form-label small fw-bold">Tarif Sewa (Rp)</label>
                            <input type="number" name="rent_price" class="form-control form-control-sm" value="<?= (int)($editTenant['rent_price'] ?? 1000000) ?>" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold">Periode</label>
                            <select name="rent_period" class="form-select form-select-sm">
                                <option value="bulanan" <?= ($editTenant['rent_period'] ?? '') === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                                <option value="6_bulan" <?= ($editTenant['rent_period'] ?? '') === '6_bulan' ? 'selected' : '' ?>>Per 6 Bulan</option>
                                <option value="tahunan" <?= ($editTenant['rent_period'] ?? '') === 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Tgl Masuk</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($editTenant['start_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Tenggat Bayar</label>
                            <input type="date" name="due_date" class="form-control form-control-sm" value="<?= htmlspecialchars($editTenant['due_date'] ?? date('Y-m-d', strtotime('+' . rentPeriodMonths($editTenant['rent_period'] ?? 'bulanan') . ' months'))) ?>" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Status Pembayaran</label>
                        <select name="payment_status" class="form-select form-select-sm">
                            <option value="belum_bayar" <?= ($editTenant['payment_status'] ?? '') === 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                            <option value="bayar_sebagian" <?= ($editTenant['payment_status'] ?? '') === 'bayar_sebagian' ? 'selected' : '' ?>>Membayar Sebagian</option>
                            <option value="sudah_bayar" <?= ($editTenant['payment_status'] ?? '') === 'sudah_bayar' ? 'selected' : '' ?>>Sudah Bayar (Lunas)</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nominal Baru Bayar (Rp)</label>
                        <input type="number" name="paid_amount" class="form-control form-control-sm" placeholder="Contoh: 500000" value="<?= (int)($editTenant['paid_amount'] ?? 0) ?>">
                        <small class="text-muted" style="font-size: 0.7rem;">Keterangan nominal jika Membayar Sebagian atau Lunas.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Alamat Asal KTP</label>
                        <textarea name="ktp_address" class="form-control form-control-sm" rows="2" placeholder="Alamat asal sesuai KTP..."><?= htmlspecialchars($editTenant['ktp_address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Pemilik Kontrakan</label>
                        <input type="text" name="landlord_name" class="form-control form-control-sm" placeholder="Nama pemilik..." value="<?= htmlspecialchars($editTenant['landlord_name'] ?? getSetting($pdo, 'landlord_name')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Catatan</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Keterangan tambahan..." value="<?= htmlspecialchars($editTenant['notes'] ?? '') ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> <?= $editTenant ? 'Simpan Perubahan' : 'Tambahkan Penghuni' ?>
                        </button>
                        <?php if ($editTenant): ?>
                            <a href="tenants.php" class="btn btn-secondary btn-sm">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabel Daftar Seluruh Penghuni -->
    <div class="<?= $editTenant ? 'col-lg-8' : 'col-lg-12' ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold">
                    <i class="fa-solid fa-users text-primary me-2"></i>Daftar Penghuni (<?= count($tenants) ?>)
                </h5>
                <form action="tenants.php" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari penghuni..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Cari</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unit / Alamat</th>
                            <th>Nama & Identitas</th>
                            <th>Kontak</th>
                            <th>Tarif & Tenggat</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada data penghuni.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td><span class="badge bg-dark fs-6"><?= htmlspecialchars($t['room_number']) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($t['name']) ?></strong>
                                <div class="text-muted small">NIK: <?= htmlspecialchars($t['identity_number'] ?: '-') ?></div>
                            </td>
                            <td>
                                <?php if (!empty($t['phone'])): ?>
                                    <small><i class="fa-brands fa-whatsapp text-success me-1"></i><?= htmlspecialchars($t['phone']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>Rp <?= number_format($t['rent_price'], 0, ',', '.') ?></div>
                                <small class="text-muted">Tenggat: <?= date('d M Y', strtotime($t['due_date'])) ?></small>
                            </td>
                            <td>
                                <?php if ($t['payment_status'] === 'sudah_bayar'): ?>
                                    <span class="badge bg-success">Lunas</span>
                                <?php elseif ($t['payment_status'] === 'bayar_sebagian'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock-rotate-left me-1"></i>Sebagian</span>
                                    <div class="small fw-semibold text-primary mt-1">Baru Bayar: Rp <?= number_format($t['paid_amount'] ?? 0, 0, ',', '.') ?></div>
                                    <div class="small text-danger" style="font-size: 0.72rem;">Sisa: Rp <?= number_format(max(0, $t['rent_price'] - ($t['paid_amount'] ?? 0)), 0, ',', '.') ?></div>
                                <?php else: ?>
                                    <span class="badge bg-danger">Belum Bayar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="tenants.php?action=edit&id=<?= $t['id'] ?>" class="btn btn-outline-primary" title="Edit Data">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form action="tenants.php" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus penghuni ini?');">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus Data">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
