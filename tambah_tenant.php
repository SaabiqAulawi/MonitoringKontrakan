<?php
require_once __DIR__ . '/includes/header.php';
$units = $pdo->query("SELECT id, unit_code, address, unit_status FROM units WHERE unit_status = 'kosong' ORDER BY unit_code")->fetchAll();
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-user-plus text-primary me-2"></i>Tambah Penghuni</h4>
            <a href="tenants.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </div>
        <?php if (!$units): ?>
            <div class="alert alert-warning">Belum ada unit kosong. Tambahkan unit terlebih dahulu melalui halaman <a href="tambah_unit.php">Tambah Unit</a>.</div>
        <?php else: ?>
        <div class="card shadow-sm"><div class="card-body">
            <form action="tenants.php" method="POST">
                <?= csrfInput() ?>
                <div class="mb-3"><label class="form-label fw-bold">Unit Kontrakan *</label>
                    <select name="unit_id" class="form-select" required><option value="">Pilih unit</option>
                    <?php foreach ($units as $unit): ?><option value="<?= (int)$unit['id'] ?>"><?= htmlspecialchars($unit['unit_code'] . ' · ' . $unit['address']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label fw-bold">Nama Lengkap *</label><input name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-bold">NIK / No. KTP (Opsional)</label><input name="identity_number" class="form-control" placeholder="16 digit NIK jika ada"></div>
                <div class="mb-3"><label class="form-label fw-bold">No. HP / WhatsApp</label><input name="phone" class="form-control" placeholder="08xxxxxxxxxx"></div>
                <div class="row g-2 mb-3">
                    <div class="col-md-7"><label class="form-label fw-bold">Tarif Sewa (Rp)</label><input type="number" min="0" name="rent_price" class="form-control" value="<?= (int)getSetting($pdo, 'default_rent_price', '1000000') ?>" required></div>
                    <div class="col-md-5"><label class="form-label fw-bold">Periode</label><select name="rent_period" class="form-select"><option value="bulanan">Perbulan</option><option value="6_bulan">Per 6 Bulan</option><option value="tahunan">Pertahun</option></select></div>
                </div>
                <div class="row g-2 mb-3"><div class="col-md-6"><label class="form-label fw-bold">Tgl Masuk</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div><div class="col-md-6"><label class="form-label fw-bold">Tenggat Bayar</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required></div></div>
                <div class="mb-3"><label class="form-label fw-bold">Status Pembayaran</label><select name="payment_status" class="form-select"><option value="belum_bayar">Belum Bayar</option><option value="bayar_sebagian">Membayar Sebagian</option><option value="sudah_bayar">Sudah Bayar (Lunas)</option></select></div>
                <div class="mb-3"><label class="form-label fw-bold">Nominal Dibayar (Rp)</label><input type="number" min="0" name="paid_amount" class="form-control" value="0"><small class="text-muted">Jika memilih Membayar Sebagian, DP minimal 2/3 dari tarif.</small></div>
                <div class="mb-3"><label class="form-label fw-bold">Alamat Asal KTP</label><textarea name="ktp_address" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label fw-bold">Pemilik Kontrakan</label><input name="landlord_name" class="form-control" value="<?= htmlspecialchars(getSetting($pdo, 'landlord_name')) ?>"></div>
                <div class="mb-3"><label class="form-label fw-bold">Catatan</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <button class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Penghuni</button>
            </form>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
