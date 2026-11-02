<?php
// receipt.php - Halaman Cetak Kuitansi / Bukti Pembayaran Resmi
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

function terbilang($angka) {
    $angka = abs($angka);
    $baca = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $terbilang = "";

    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " Belas";
    } elseif ($angka < 100) {
        $terbilang = terbilang((int)($angka / 10)) . " Puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = " Seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang((int)($angka / 100)) . " Ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = " Seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang((int)($angka / 1000)) . " Ribu" . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang((int)($angka / 1000000)) . " Juta" . terbilang($angka % 1000000);
    }
    return trim($terbilang);
}

$tenantId = (int)($_GET['tenant_id'] ?? 0);
$logId = (int)($_GET['log_id'] ?? 0);

$tenant = null;
$amount = 0;
$paymentMethod = 'Transfer';
$paymentDate = date('Y-m-d');
$notes = '';
$receiptNo = 'KWT-' . date('Ymd') . '-';

if ($logId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM payment_logs WHERE id = ?");
    $stmt->execute([$logId]);
    $log = $stmt->fetch();
    if ($log) {
        $tenantId = $log['tenant_id'];
        $amount = (float)$log['amount'];
        $paymentMethod = $log['payment_method'];
        $paymentDate = date('Y-m-d', strtotime($log['changed_at']));
        $notes = $log['notes'];
        $receiptNo .= str_pad($log['id'], 4, '0', STR_PAD_LEFT);
    }
}

if ($tenantId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    if ($amount == 0 && $tenant) {
        $amount = (float)$tenant['rent_price'];
        $receiptNo .= str_pad($tenant['id'], 4, '0', STR_PAD_LEFT);
    }
}

if (!$tenant) {
    die("Data pembayaran tidak ditemukan.");
}

$kosName = getSetting($pdo, 'kos_name', 'Kontrakan Srini');
$landlord = $tenant['landlord_name'] ?: getSetting($pdo, 'landlord_name', 'Ibu Srini');
$rentPrice = (float)$tenant['rent_price'];
$isPartial = (isset($log) && $log['new_status'] === 'bayar_sebagian') 
    || ($tenant['payment_status'] === 'bayar_sebagian') 
    || ($amount < $rentPrice && $tenant['payment_status'] !== 'sudah_bayar');
$sisaTagihan = max(0, $rentPrice - $amount);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kuitansi Pembayaran - <?= htmlspecialchars($receiptNo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .receipt-card {
            max-width: 750px;
            margin: 30px auto;
            background: #fff;
            padding: 35px 45px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            border: 2px dashed #cbd5e1;
        }
        .stamp-lunas {
            position: absolute;
            right: 80px;
            bottom: 120px;
            border: 3px solid #10b981;
            color: #10b981;
            font-size: 28px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 6px 18px;
            border-radius: 8px;
            transform: rotate(-15deg);
            opacity: 0.85;
            letter-spacing: 2px;
        }
        .stamp-sebagian {
            position: absolute;
            right: 80px;
            bottom: 120px;
            border: 3px solid #f59e0b;
            color: #d97706;
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 8px;
            transform: rotate(-15deg);
            opacity: 0.88;
            letter-spacing: 2px;
        }
        @media print {
            body { background: #fff; }
            .receipt-card { box-shadow: none; border: 1px solid #000; margin: 0; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container my-3 no-print text-center">
    <button onclick="window.print()" class="btn btn-primary px-4 fw-bold me-2">
        <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
    </button>
    <button onclick="window.close()" class="btn btn-secondary px-3">Tutup</button>
</div>

<div class="receipt-card">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($kosName) ?></h4>
            <small class="text-muted">Kuitansi Resmi Tanda Terima Pembayaran Sewa</small>
        </div>
        <div class="text-end">
            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($receiptNo) ?></h6>
            <small class="text-muted">Tanggal: <?= formatTanggalIndonesia($paymentDate) ?></small>
        </div>
    </div>

    <table class="table table-borderless align-middle mb-4">
        <tbody>
            <tr>
                <td style="width: 25%;" class="text-muted">Telah Diterima Dari</td>
                <td style="width: 3%;">:</td>
                <td><strong class="fs-5 text-dark"><?= htmlspecialchars($tenant['name']) ?></strong></td>
            </tr>
            <tr>
                <td class="text-muted">NIK / No. Identitas</td>
                <td>:</td>
                <td><code><?= htmlspecialchars($tenant['identity_number'] ?: '-') ?></code></td>
            </tr>
            <tr>
                <td class="text-muted">Untuk Pembayaran</td>
                <td>:</td>
                <td>
                    Sewa Kamar / Unit <strong><?= htmlspecialchars($tenant['room_number']) ?></strong> 
                    (Periode <?= htmlspecialchars(rentPeriodLabel($tenant['rent_period'] ?? 'bulanan')) ?>)
                    <?php if ($isPartial): ?>
                        <div class="small text-warning fw-bold mt-1">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i>Pembayaran Sebagian (Total Sewa: Rp <?= number_format($rentPrice, 0, ',', '.') ?> &bull; Sisa: Rp <?= number_format($sisaTagihan, 0, ',', '.') ?>)
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($notes)): ?>
                        <div class="small text-muted fst-italic">Catatan: <?= htmlspecialchars($notes) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="text-muted">Metode Pembayaran</td>
                <td>:</td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($paymentMethod) ?></span></td>
            </tr>
            <tr>
                <td class="text-muted">Sejumlah Uang</td>
                <td>:</td>
                <td class="bg-light p-2 rounded">
                    <em>"<?= terbilang((int)$amount) ?> Rupiah"</em>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="row align-items-center mb-4">
        <div class="col-6">
            <div class="p-3 bg-primary bg-opacity-10 rounded border border-primary">
                <span class="text-muted small d-block">Jumlah Dibayarkan:</span>
                <span class="fs-3 fw-bold text-primary">Rp <?= number_format($amount, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 text-end">
            <p class="mb-5 small text-muted">Diterima oleh Pengelola:</p>
            <p class="fw-bold mb-0 text-decoration-underline"><?= htmlspecialchars($landlord) ?></p>
            <small class="text-muted">Pengelola / Pemilik Kos</small>
        </div>
    </div>

    <?php if ($isPartial): ?>
        <div class="stamp-sebagian">SEBAGIAN</div>
    <?php else: ?>
        <div class="stamp-lunas">L U N A S</div>
    <?php endif; ?>

    <div class="border-top pt-2 text-center text-muted" style="font-size: 0.75rem;">
        Terima kasih atas pembayaran tepat waktu. Bukti ini sah dan dicetak melalui Sistem Monitoring Kos & Kontrakan.
    </div>
</div>

</body>
</html>
