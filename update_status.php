<?php
// update_status.php - Handler Perubahan Status Pembayaran & Pencatatan Log Riwayat
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$currentUser = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? 'belum_bayar');
    if (!in_array($newStatus, ['sudah_bayar', 'bayar_sebagian', 'belum_bayar'], true)) {
        http_response_code(422);
        exit('Status pembayaran tidak valid.');
    }
    $paymentMethod = trim($_POST['payment_method'] ?? 'Transfer');
    $amount = (float)($_POST['amount'] ?? 0);
    $advanceDue = (int)($_POST['advance_due'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if ($amount < 0 || !in_array($paymentMethod, ['Transfer BCA', 'Transfer Mandiri', 'Transfer BRI', 'Transfer BNI', 'Tunai / Cash', 'QRIS', 'Transfer'], true)) {
        http_response_code(422);
        exit('Data pembayaran tidak valid.');
    }

    // Cari data penghuni saat ini
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();

    if ($tenant) {
        $oldStatus = $tenant['payment_status'];
        $dueDate = $tenant['due_date'];
        $paidAt = $tenant['last_paid_at'];
        $rentPrice = (float)$tenant['rent_price'];

        // Pengaturan nominal dan status
        if ($newStatus === 'sudah_bayar') {
            $paidAt = date('Y-m-d H:i:s');
            $paidAmount = $amount > 0 ? $amount : $rentPrice;
            $amount = $paidAmount;
            // Jika opsi perpanjang tenggat dicentang
            if ($advanceDue === 1) {
                $dueDate = date('Y-m-d', strtotime($dueDate . ' +1 month'));
            }
            if (empty($notes)) {
                $notes = "Pembayaran lunas sebesar Rp " . number_format($paidAmount, 0, ',', '.');
            }
        } elseif ($newStatus === 'bayar_sebagian') {
            $paidAt = date('Y-m-d H:i:s');
            $paidAmount = $amount;
            if ($advanceDue === 1) {
                $dueDate = date('Y-m-d', strtotime($dueDate . ' +1 month'));
            }
            $sisa = max(0, $rentPrice - $paidAmount);
            if (empty($notes)) {
                $notes = "Membayar sebagian: Baru bayar Rp " . number_format($paidAmount, 0, ',', '.') . " (Sisa: Rp " . number_format($sisa, 0, ',', '.') . ")";
            }
        } else {
            // Belum bayar
            $paidAmount = 0;
            if (empty($notes)) {
                $notes = "Perubahan status ke Belum Bayar";
            }
        }

        // 1. Update tabel tenants
        $stmtUpdate = $pdo->prepare("UPDATE tenants SET 
            payment_status = :new_status,
            paid_amount = :paid_amount,
            due_date = :due_date,
            last_paid_at = :last_paid_at,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $stmtUpdate->execute([
            ':new_status' => $newStatus,
            ':paid_amount' => $paidAmount,
            ':due_date' => $dueDate,
            ':last_paid_at' => $paidAt,
            ':id' => $tenantId
        ]);

        // 2. Simpan catatan ke tabel payment_logs
        $stmtLog = $pdo->prepare("INSERT INTO payment_logs (
            tenant_id, room_number, tenant_name, old_status, new_status,
            amount, payment_method, payment_date, notes, user_id
        ) VALUES (
            :tenant_id, :room_number, :tenant_name, :old_status, :new_status,
            :amount, :payment_method, :payment_date, :notes, :user_id
        )");
        $stmtLog->execute([
            ':tenant_id' => $tenantId,
            ':room_number' => $tenant['room_number'],
            ':tenant_name' => $tenant['name'],
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':amount' => $amount,
            ':payment_method' => $paymentMethod,
            ':payment_date' => date('Y-m-d'),
            ':notes' => $notes
            ,':user_id' => $currentUser['id']
        ]);
    }
}

// Redirect kembali ke halaman asal
header('Location: index.php');
exit;
