<?php
// download_template.php - Unduh Template Excel Bawaan Format_Penghuni_Kost_Kontrakan.xlsx
$filePath = __DIR__ . '/Format_Penghuni_Kost_Kontrakan.xlsx';

if (!file_exists($filePath)) {
    http_response_code(404);
    die("File template tidak ditemukan.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Format_Penghuni_Kost_Kontrakan.xlsx"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

if (ob_get_level()) {
    ob_end_clean();
}
readfile($filePath);
exit;
