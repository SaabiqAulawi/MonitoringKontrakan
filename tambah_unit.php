<?php
require_once __DIR__ . '/includes/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $code = trim($_POST['unit_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $unitStatus = trim($_POST['unit_status'] ?? 'kosong');
    $condition = trim($_POST['condition_status'] ?? 'baik');
    $notes = trim($_POST['repair_notes'] ?? '');
    $bedrooms = max(0, (int)($_POST['bedrooms'] ?? 0));
    $bathrooms = max(0, (int)($_POST['bathrooms'] ?? 0));
    $area = max(0, (float)($_POST['area_sqm'] ?? 0));
    $floors = max(1, (int)($_POST['floors'] ?? 1));
    $allowedStatuses = ['kosong', 'terisi', 'dalam_perbaikan', 'renovasi'];
    $allowedConditions = ['baik', 'perlu_perbaikan', 'dalam_perbaikan'];
    if ($code === '' || $address === '' || !in_array($unitStatus, $allowedStatuses, true) || !in_array($condition, $allowedConditions, true)) {
        $error = 'Kode, alamat, dan status unit wajib valid.';
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $photo = 'data/unit-' . bin2hex(random_bytes(8)) . '.' . $extension;
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/' . $photo);
            }
        }
        $stmt = $pdo->prepare('INSERT INTO units (unit_code,address,unit_status,condition_status,repair_notes,photo_path,bedrooms,bathrooms,area_sqm,floors) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$code, $address, $unitStatus, $condition, $notes, $photo, $bedrooms, $bathrooms, $area, $floors]);
        header('Location: units.php');
        exit;
    }
}
?>
<div class="row justify-content-center"><div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-center mb-3"><h4 class="fw-bold mb-0"><i class="fa-solid fa-house text-primary me-2"></i>Tambah Unit</h4><a href="units.php" class="btn btn-outline-secondary btn-sm">Kembali</a></div>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card shadow-sm"><div class="card-body"><form method="post" enctype="multipart/form-data"><?= csrfInput() ?>
        <label class="form-label fw-bold">Kode Unit *</label><input class="form-control mb-3" name="unit_code" required placeholder="U-01">
        <label class="form-label fw-bold">Alamat Kontrakan *</label><textarea class="form-control mb-3" name="address" required></textarea>
        <label class="form-label fw-bold">Status Unit</label><select class="form-select mb-3" name="unit_status"><?php foreach (['kosong'=>'Kosong','terisi'=>'Terisi','dalam_perbaikan'=>'Dalam perbaikan','renovasi'=>'Renovasi'] as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select>
        <label class="form-label fw-bold">Kondisi Unit</label><select class="form-select mb-3" name="condition_status"><?php foreach (['baik'=>'Baik','perlu_perbaikan'=>'Perlu perbaikan','dalam_perbaikan'=>'Dalam perbaikan'] as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select>
        <div class="row g-2 mb-3"><div class="col-4"><label class="form-label">Kamar</label><input type="number" min="0" class="form-control" name="bedrooms" value="0"></div><div class="col-4"><label class="form-label">Toilet</label><input type="number" min="0" class="form-control" name="bathrooms" value="0"></div><div class="col-4"><label class="form-label">Lantai</label><input type="number" min="1" class="form-control" name="floors" value="1"></div></div>
        <label class="form-label fw-bold">Luas (m²)</label><input type="number" min="0" step="0.01" class="form-control mb-3" name="area_sqm" value="0">
        <label class="form-label fw-bold">Catatan Perbaikan</label><textarea class="form-control mb-3" name="repair_notes"></textarea>
        <label class="form-label fw-bold">Foto Unit</label><input type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control mb-4" name="photo">
        <button class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Unit</button>
    </form></div></div>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
