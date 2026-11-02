<?php
require_once __DIR__ . '/includes/header.php';
$message = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare('DELETE FROM units WHERE id = ?');
        $stmt->execute([(int)$_POST['delete_id']]);
        $message = 'Unit berhasil dihapus.';
    } else {
    $id = (int)($_POST['id'] ?? 0);
    $code = trim($_POST['unit_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $unitStatus = trim($_POST['unit_status'] ?? 'kosong');
    $allowedUnitStatuses = ['terisi', 'kosong', 'dalam_perbaikan', 'renovasi'];
    if (!in_array($unitStatus, $allowedUnitStatuses, true)) {
        $unitStatus = 'kosong';
    }
    $condition = trim($_POST['condition_status'] ?? 'baik');
    $notes = trim($_POST['repair_notes'] ?? '');
    $bedrooms = max(0, (int)($_POST['bedrooms'] ?? 0));
    $bathrooms = max(0, (int)($_POST['bathrooms'] ?? 0));
    $area = max(0, (float)($_POST['area_sqm'] ?? 0));
    $floors = max(1, (int)($_POST['floors'] ?? 1));
    if ($code === '' || $address === '') {
        $message = 'Kode unit dan alamat wajib diisi.';
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $photo = 'data/unit-' . bin2hex(random_bytes(8)) . '.' . $extension;
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/' . $photo);
            }
        }
        if ($id) {
            $sql = 'UPDATE units SET unit_code=?, address=?, unit_status=?, condition_status=?, repair_notes=?, bedrooms=?, bathrooms=?, area_sqm=?, floors=?, updated_at=CURRENT_TIMESTAMP';
            $params = [$code, $address, $unitStatus, $condition, $notes, $bedrooms, $bathrooms, $area, $floors];
            if ($photo) { $sql .= ', photo_path=?'; $params[] = $photo; }
            $sql .= ' WHERE id=?'; $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            $message = 'Data unit diperbarui.';
        } else {
            $pdo->prepare('INSERT INTO units (unit_code,address,unit_status,condition_status,repair_notes,photo_path,bedrooms,bathrooms,area_sqm,floors) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$code, $address, $unitStatus, $condition, $notes, $photo, $bedrooms, $bathrooms, $area, $floors]);
            $message = 'Unit berhasil ditambahkan.';
        }
    }
    }
}
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM units WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
$units = $pdo->query('SELECT * FROM units ORDER BY unit_code')->fetchAll();
?>
<?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="d-flex justify-content-end gap-2 mb-3">
    <a href="tambah_unit.php" class="btn btn-primary"><i class="fa-solid fa-house me-1"></i>Tambah Unit</a>
</div>
<div class="row g-4">
<div class="col-lg-4"><div class="card"><div class="card-body">
<?php if ($edit): ?>
<h5 class="fw-bold"><?= $edit ? 'Edit Unit' : 'Tambah Unit' ?></h5>
<form method="post" enctype="multipart/form-data">
<?= csrfInput() ?>
<?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
<label class="form-label">Kode Unit</label><input class="form-control mb-2" name="unit_code" required value="<?= htmlspecialchars($edit['unit_code'] ?? '') ?>" placeholder="U-01">
<label class="form-label">Alamat Kontrakan</label><textarea class="form-control mb-2" name="address" required><?= htmlspecialchars($edit['address'] ?? '') ?></textarea>
<label class="form-label">Status Unit</label><select class="form-select mb-2" name="unit_status">
<?php foreach (['terisi'=>'Terisi','kosong'=>'Kosong','dalam_perbaikan'=>'Dalam perbaikan','renovasi'=>'Renovasi'] as $value => $label): ?><option value="<?= $value ?>" <?= ($edit['unit_status'] ?? 'kosong') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select>
<label class="form-label">Kondisi Unit</label><select class="form-select mb-2" name="condition_status">
<?php foreach (['baik'=>'Baik','perlu_perbaikan'=>'Perlu perbaikan','dalam_perbaikan'=>'Dalam perbaikan'] as $value => $label): ?><option value="<?= $value ?>" <?= ($edit['condition_status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
</select>
<div class="row g-2 mb-2"><div class="col-4"><label class="form-label">Kamar</label><input type="number" min="0" class="form-control" name="bedrooms" value="<?= (int)($edit['bedrooms'] ?? 0) ?>"></div>
<div class="col-4"><label class="form-label">Toilet</label><input type="number" min="0" class="form-control" name="bathrooms" value="<?= (int)($edit['bathrooms'] ?? 0) ?>"></div>
<div class="col-4"><label class="form-label">Lantai</label><input type="number" min="1" class="form-control" name="floors" value="<?= (int)($edit['floors'] ?? 1) ?>"></div></div>
<label class="form-label">Luas (m²)</label><input type="number" min="0" step="0.01" class="form-control mb-2" name="area_sqm" value="<?= htmlspecialchars($edit['area_sqm'] ?? 0) ?>">
<label class="form-label">Catatan Perbaikan</label><textarea class="form-control mb-2" name="repair_notes"><?= htmlspecialchars($edit['repair_notes'] ?? '') ?></textarea>
<label class="form-label">Foto Unit</label><input type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control mb-3" name="photo">
<button class="btn btn-primary w-100">Simpan Unit</button>
</form>
<?php endif; ?>
</div></div></div>
<div class="<?= $edit ? 'col-lg-8' : 'col-lg-12' ?>"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Unit</th><th>Alamat</th><th>Spesifikasi</th><th>Status</th><th>Kondisi</th><th></th></tr></thead><tbody>
<?php foreach ($units as $unit): ?><tr><td class="fw-bold"><?= htmlspecialchars($unit['unit_code']) ?></td><td><?= nl2br(htmlspecialchars($unit['address'])) ?></td><td><?= (int)$unit['bedrooms'] ?> kamar · <?= (int)$unit['bathrooms'] ?> toilet · <?= (int)$unit['floors'] ?> lantai<br><small><?= htmlspecialchars($unit['area_sqm']) ?> m²</small></td><td><span class="badge <?= $unit['unit_status'] === 'terisi' ? 'bg-primary' : ($unit['unit_status'] === 'kosong' ? 'bg-secondary' : 'bg-warning text-dark') ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($unit['unit_status']))) ?></span></td><td><span class="badge <?= $unit['condition_status'] === 'baik' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($unit['condition_status']))) ?></span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $unit['id'] ?>">Edit</a><form method="post" class="d-inline" onsubmit="return confirm('Hapus unit ini?')"><?= csrfInput() ?><input type="hidden" name="delete_id" value="<?= $unit['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
