<?php
// settings.php - Halaman Pengaturan Kos & Rekening Pembayaran
require_once __DIR__ . '/includes/header.php';

$message = '';
$alertType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fields = ['kos_name', 'landlord_name', 'bank_info', 'default_rent_price'];
    $stmtUp = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $stmtUp->execute([$f, $val]);
    }
    $message = "Pengaturan berhasil disimpan.";
    $alertType = "success";
}

$kosName = getSetting($pdo, 'kos_name', 'Kontrakan Srini');
$landlordName = getSetting($pdo, 'landlord_name', 'Ibu Srini');
$bankInfo = getSetting($pdo, 'bank_info', 'BCA 1234567890 a/n Srini');
$defaultRentPrice = getSetting($pdo, 'default_rent_price', '1200000');
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">
                    <i class="fa-solid fa-gear me-2"></i>Pengaturan Informasi Kos
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="settings.php" method="POST">
                    <?= csrfInput() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Kos / Kontrakan</label>
                        <input type="text" name="kos_name" class="form-control" value="<?= htmlspecialchars($kosName) ?>" required>
                        <small class="text-muted">Ditampilkan di header aplikasi dan kuitansi pembayaran.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Pemilik / Pengelola Default</label>
                        <input type="text" name="landlord_name" class="form-control" value="<?= htmlspecialchars($landlordName) ?>" required>
                        <small class="text-muted">Nama yang tercantum pada kuitansi tanda terima.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Rekening Pembayaran / Bank Info</label>
                        <input type="text" name="bank_info" class="form-control" value="<?= htmlspecialchars($bankInfo) ?>" required>
                        <small class="text-muted">Contoh: <em>BCA 8720192837 a/n Siti Aminah</em>. Info ini otomatis disisipkan ke pesan WhatsApp pengingat tagihan.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tarif Sewa Default (Rp)</label>
                        <input type="number" name="default_rent_price" class="form-control" value="<?= (int)$defaultRentPrice ?>" required>
                        <small class="text-muted">Nilai awal tarif sewa saat menambah penghuni baru atau membaca file Excel.</small>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
