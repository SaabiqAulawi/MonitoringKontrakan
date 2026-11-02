<?php
// includes/payment_modal.php
?>
<div class="modal fade" id="modalStatus<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered text-start">
        <div class="modal-content">
            <form action="update_status.php" method="POST">
                <?= csrfInput() ?>
                <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                
                <div class="modal-header bg-light">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-money-bill-wave text-success me-2"></i>
                        Update Pembayaran &bull; <?= htmlspecialchars($t['room_number']) ?>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Nama Penghuni:</small>
                                <strong><?= htmlspecialchars($t['name']) ?></strong>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block">Tarif Sewa:</small>
                                <strong class="text-primary">Rp <?= number_format($t['rent_price'], 0, ',', '.') ?></strong>
                                <?php if (($t['paid_amount'] ?? 0) > 0): ?>
                                    <div class="small text-success fw-semibold">Baru Bayar: Rp <?= number_format($t['paid_amount'], 0, ',', '.') ?></div>
                                    <div class="small text-danger">Sisa: Rp <?= number_format(max(0, $t['rent_price'] - $t['paid_amount']), 0, ',', '.') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status Pembayaran Baru</label>
                        <select name="new_status" id="statusSelect<?= $t['id'] ?>" class="form-select" onchange="handleStatusChange<?= $t['id'] ?>(this.value)" required>
                            <option value="sudah_bayar" <?= $t['payment_status'] === 'sudah_bayar' ? 'selected' : '' ?>>
                                Sudah Bayar (Lunas)
                            </option>
                            <option value="bayar_sebagian" <?= $t['payment_status'] === 'bayar_sebagian' ? 'selected' : '' ?>>
                                Membayar Sebagian (Baru Bayar Sebagian)
                            </option>
                            <option value="belum_bayar" <?= $t['payment_status'] === 'belum_bayar' ? 'selected' : '' ?>>
                                Belum Bayar
                            </option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Metode Pembayaran</label>
                            <select name="payment_method" class="form-select form-select-sm">
                                <option value="Transfer BCA">Transfer BCA</option>
                                <option value="Transfer Mandiri">Transfer Mandiri</option>
                                <option value="Transfer BRI">Transfer BRI</option>
                                <option value="Transfer BNI">Transfer BNI</option>
                                <option value="Tunai / Cash">Tunai (Cash)</option>
                                <option value="QRIS">QRIS</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Nominal Pembayaran (Rp)</label>
                            <input type="number" name="amount" id="amountInput<?= $t['id'] ?>" class="form-control form-control-sm" 
                                value="<?= $t['payment_status'] === 'bayar_sebagian' ? (int)($t['paid_amount'] ?: ($t['rent_price'] / 2)) : (int)$t['rent_price'] ?>" required>
                            <small class="text-muted" id="amountHint<?= $t['id'] ?>" style="font-size: 0.7rem;">
                                <?= $t['payment_status'] === 'bayar_sebagian' ? 'Nominal DP (minimal Rp ' . number_format(minimumDownPayment((float)$t['rent_price']), 0, ',', '.') . ')' : 'Nominal tagihan' ?>
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="advance_due" id="advanceDue<?= $t['id'] ?>" value="1" <?= $t['payment_status'] === 'sudah_bayar' ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="advanceDue<?= $t['id'] ?>">
                                Otomatis perpanjang tenggat sesuai periode sewa
                            </label>
                        </div>
                        <small class="text-muted d-block ps-4">
                            Periode: <?= htmlspecialchars(rentPeriodLabel($t['rent_period'] ?? 'bulanan')) ?> ·
                            Minimal DP 2/3 tarif ·
                            Tenggat saat ini: <?= date('d M Y', strtotime($t['due_date'])) ?> &rarr; Akan menjadi: <strong><?= date('d M Y', strtotime($t['due_date'] . ' +' . rentPeriodMonths($t['rent_period'] ?? 'bulanan') . ' months')) ?></strong>
                        </small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Catatan Perubahan (Tercatat di Log)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Bukti transfer diterima via WhatsApp">
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Status & Catat Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function handleStatusChange<?= $t['id'] ?>(status) {
    var amt = document.getElementById('amountInput<?= $t['id'] ?>');
    var chk = document.getElementById('advanceDue<?= $t['id'] ?>');
    var hint = document.getElementById('amountHint<?= $t['id'] ?>');
    var rent = <?= (int)$t['rent_price'] ?>;
    var currentPaid = <?= (int)($t['paid_amount'] ?? 0) ?>;

    if (status === 'sudah_bayar') {
        amt.value = rent;
        chk.checked = true;
        if (hint) hint.innerText = 'Nominal lunas sewa';
    } else if (status === 'bayar_sebagian') {
        amt.value = currentPaid > 0 ? currentPaid : Math.ceil(rent * 2 / 3);
        chk.checked = false;
        if (hint) hint.innerText = 'Nominal yang baru dibayar';
    } else {
        amt.value = 0;
        chk.checked = false;
        if (hint) hint.innerText = 'Status diubah ke Belum Bayar';
    }
}
</script>
