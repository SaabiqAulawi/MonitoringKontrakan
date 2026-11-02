<?php
// export_logs.php - Export Riwayat Log Pembayaran ke CSV
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

function csvCell($value): string {
    $value = (string)($value ?? '');
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=log_pembayaran_kos_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['ID Log', 'Waktu Perubahan', 'Unit / Alamat', 'Nama Penghuni', 'Status Lama', 'Status Baru', 'Nominal (Rp)', 'Metode Bayar', 'Catatan', 'Diubah Oleh']);

$stmt = $pdo->query("SELECT * FROM payment_logs ORDER BY changed_at DESC");
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['changed_at'],
        csvCell($row['room_number']),
        csvCell($row['tenant_name']),
        csvCell($row['old_status']),
        csvCell($row['new_status']),
        csvCell($row['amount']),
        csvCell($row['payment_method']),
        csvCell($row['notes']),
        csvCell($row['user_id'] ?? '')
    ]);
}

fclose($output);
exit;
