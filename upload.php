<?php
// upload.php - Handler Upload Excel .xlsx dengan Pencegahan Duplikasi Data
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/SimpleXLSX.php';

$message = '';
$alertType = 'info';
$skippedDetails = [];
$insertedCount = 0;
$skippedCount = 0;

function parseDynamicColumns(array $row, array $headerMap, array $defaults): array {
    $data = $defaults;
    if (isset($headerMap['room'])) $data['room_number'] = trim($row[$headerMap['room']] ?? '');
    if (isset($headerMap['name'])) $data['name'] = trim($row[$headerMap['name']] ?? '');
    if (isset($headerMap['nik'])) $data['identity_number'] = trim($row[$headerMap['nik']] ?? '');
    if (isset($headerMap['phone'])) $data['phone'] = trim($row[$headerMap['phone']] ?? '');
    if (isset($headerMap['ktp_address'])) $data['ktp_address'] = trim($row[$headerMap['ktp_address']] ?? '');
    if (isset($headerMap['landlord'])) $data['landlord_name'] = trim($row[$headerMap['landlord']] ?? $defaults['landlord_name']);
    if (isset($headerMap['price']) && !empty($row[$headerMap['price']])) {
        $data['rent_price'] = (float)preg_replace('/[^0-9.]/', '', $row[$headerMap['price']]);
    }
    if (isset($headerMap['start_date']) && !empty($row[$headerMap['start_date']])) {
        $data['start_date'] = parseExcelDate($row[$headerMap['start_date']]);
    }
    if (isset($headerMap['due_date']) && !empty($row[$headerMap['due_date']])) {
        $data['due_date'] = parseExcelDate($row[$headerMap['due_date']]);
    }
    if (isset($headerMap['status'])) {
        $st = strtolower(trim($row[$headerMap['status']] ?? ''));
        if (strpos($st, 'sudah') !== false || strpos($st, 'lunas') !== false) {
            $data['payment_status'] = 'sudah_bayar';
        } elseif (strpos($st, 'sebagian') !== false || strpos($st, 'cicil') !== false || strpos($st, 'dp') !== false) {
            $data['payment_status'] = 'bayar_sebagian';
        } else {
            $data['payment_status'] = 'belum_bayar';
        }
    }
    if (isset($headerMap['notes'])) $data['notes'] = trim($row[$headerMap['notes']] ?? '');
    return $data;
}

function parseLegacyFormat(array $row, array $defaults): array {
    $data = $defaults;
    $namaNikRaw = trim($row[1] ?? '');
    $data['ktp_address'] = trim($row[2] ?? '');
    $data['landlord_name'] = !empty($row[3]) ? trim($row[3]) : $defaults['landlord_name'];
    $alamatKost = trim($row[4] ?? '');
    $ketRaw = trim($row[5] ?? '');

    if (preg_match('/\b\d{10,18}\b/', $namaNikRaw, $mNik)) {
        $data['identity_number'] = $mNik[0];
        $clean = trim(str_replace([$mNik[0], '/', '(', ')', '-'], ' ', $namaNikRaw));
        $data['name'] = preg_replace('/\s+/', ' ', $clean) ?: 'Penghuni ' . $mNik[0];
    } elseif (strpos($namaNikRaw, '/') !== false) {
        $parts = explode('/', $namaNikRaw, 2);
        $data['name'] = trim($parts[0]);
        $data['identity_number'] = trim($parts[1]);
    } else {
        $data['name'] = $namaNikRaw;
        $data['identity_number'] = null;
    }

    $data['room_number'] = $alamatKost ?: ('Kamar ' . ($row[0] ?? '1'));
    $data['notes'] = $ketRaw;

    if (preg_match('/(08\d{8,13})/', $ketRaw, $mPhone)) {
        $data['phone'] = $mPhone[1];
    }
    if (preg_match('/(?:Rp\.?|tarif|sewa)?\s*(\d{1,3}(?:\.\d{3})+)/i', $ketRaw, $mPrice)) {
        $data['rent_price'] = (float)str_replace('.', '', $mPrice[1]);
    }
    if (stripos($ketRaw, 'lunas') !== false || stripos($ketRaw, 'sudah bayar') !== false) {
        $data['payment_status'] = 'sudah_bayar';
    } elseif (stripos($ketRaw, 'sebagian') !== false || stripos($ketRaw, 'cicil') !== false || stripos($ketRaw, 'dp') !== false) {
        $data['payment_status'] = 'bayar_sebagian';
    }
    return $data;
}

function parseTenantRow(array $row, array $headerMap, PDO $pdo): array {
    $defaults = [
        'room_number' => '',
        'name' => '',
        'identity_number' => '',
        'phone' => '',
        'ktp_address' => '',
        'landlord_name' => getSetting($pdo, 'landlord_name', 'Pemilik Kos'),
        'rent_price' => (float)getSetting($pdo, 'default_rent_price', '1000000'),
        'paid_amount' => 0,
        'rent_period' => 'bulanan',
        'start_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'payment_status' => 'belum_bayar',
        'notes' => ''
    ];

    $data = !empty($headerMap) 
        ? parseDynamicColumns($row, $headerMap, $defaults) 
        : parseLegacyFormat($row, $defaults);

    if (empty($data['name'])) $data['name'] = 'Tanpa Nama';
    if (empty($data['room_number'])) $data['room_number'] = 'Kamar 01';
    if (empty($data['identity_number'])) {
        $data['identity_number'] = null;
    }
    if ($data['payment_status'] === 'bayar_sebagian') {
        $data['down_payment_date'] = $data['start_date'];
        $data['due_date'] = downPaymentDeadline($data['start_date']);
    } else {
        $data['down_payment_date'] = null;
    }
    return $data;
}

// Helper parsing tanggal excel
function parseExcelDate($val): string {
    if (is_numeric($val) && $val > 25000 && $val < 60000) {
        $timestamp = ($val - 25569) * 86400;
        return gmdate('Y-m-d', (int)$timestamp);
    }
    $val = str_replace('/', '-', trim((string)$val));
    $time = strtotime($val);
    if ($time !== false) {
        return date('Y-m-d', $time);
    }
    return date('Y-m-d', strtotime('+30 days'));
}

// Proses form upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    verifyCsrf();
    $file = $_FILES['excel_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "Terjadi kesalahan saat mengunggah file. Kode error: " . $file['error'];
        $alertType = 'danger';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $message = "Format file tidak didukung! Pastikan Anda mengunggah file berekstensi <strong>.xlsx</strong>.";
            $alertType = 'danger';
        } else {
            $xlsx = SimpleXLSX::parse($file['tmp_name']);
            if (!$xlsx) {
                $message = "File .xlsx tidak dapat dibaca. Pastikan file tidak rusak atau terproteksi kata sandi.";
                $alertType = 'danger';
            } else {
                $rows = $xlsx->rows();
                if (empty($rows)) {
                    $message = "File .xlsx kosong atau tidak berisi data yang dapat dibaca.";
                    $alertType = 'warning';
                } else {
                    // Cari baris header
                    $headerRowIndex = -1;
                    $headerMap = [];

                    foreach ($rows as $idx => $r) {
                        $lineStr = strtolower(implode(' ', $r));
                        if (strpos($lineStr, 'nama') !== false || strpos($lineStr, 'nik') !== false) {
                            $headerRowIndex = $idx;
                            // Petakan index kolom
                            foreach ($r as $colIdx => $colName) {
                                $cn = strtolower(trim((string)$colName));
                                if (strpos($cn, 'nama/nik') !== false) {
                                    // format gabungan
                                } elseif (strpos($cn, 'nik') !== false || strpos($cn, 'ktp') !== false && strpos($cn, 'alamat') === false) {
                                    $headerMap['nik'] = $colIdx;
                                } elseif (strpos($cn, 'nama') !== false) {
                                    $headerMap['name'] = $colIdx;
                                } elseif (strpos($cn, 'kamar') !== false || strpos($cn, 'kost') !== false || strpos($cn, 'kontrakan') !== false && strpos($cn, 'pemilik') === false) {
                                    $headerMap['room'] = $colIdx;
                                } elseif (strpos($cn, 'pemilik') !== false) {
                                    $headerMap['landlord'] = $colIdx;
                                } elseif (strpos($cn, 'alamat ktp') !== false) {
                                    $headerMap['ktp_address'] = $colIdx;
                                } elseif (strpos($cn, 'hp') !== false || strpos($cn, 'telp') !== false || strpos($cn, 'wa') !== false) {
                                    $headerMap['phone'] = $colIdx;
                                } elseif (strpos($cn, 'tarif') !== false || strpos($cn, 'harga') !== false || strpos($cn, 'sewa') !== false || strpos($cn, 'biaya') !== false) {
                                    $headerMap['price'] = $colIdx;
                                } elseif (strpos($cn, 'tenggat') !== false || strpos($cn, 'jatuh tempo') !== false) {
                                    $headerMap['due_date'] = $colIdx;
                                } elseif (strpos($cn, 'masuk') !== false || strpos($cn, 'mulai') !== false) {
                                    $headerMap['start_date'] = $colIdx;
                                } elseif (strpos($cn, 'status') !== false) {
                                    $headerMap['status'] = $colIdx;
                                } elseif (strpos($cn, 'ket') !== false || strpos($cn, 'catatan') !== false) {
                                    $headerMap['notes'] = $colIdx;
                                }
                            }
                            break;
                        }
                    }

                    $startIdx = ($headerRowIndex !== -1) ? $headerRowIndex + 1 : 0;
                    
                    $stmtCheck = $pdo->prepare("SELECT id, name, room_number FROM tenants WHERE identity_number = ?");
                    $stmtInsert = $pdo->prepare("INSERT INTO tenants (
                        room_number, name, identity_number, phone, ktp_address, landlord_name,
                        rent_price, paid_amount, rent_period, start_date, due_date, payment_status, down_payment_date, notes
                    ) VALUES (
                        :room_number, :name, :identity_number, :phone, :ktp_address, :landlord_name,
                        :rent_price, :paid_amount, :rent_period, :start_date, :due_date, :payment_status, :down_payment_date, :notes
                    )");

                    for ($i = $startIdx; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        // Lewati baris kosong
                        if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                            continue;
                        }

                        $data = parseTenantRow($row, $headerMap, $pdo);

                        // 1. CEK IDENTITAS KE DATABASE (ANTI-DUPLIKASI)
                        $existing = false;
                        if (!empty($data['identity_number'])) {
                            $stmtCheck->execute([$data['identity_number']]);
                            $existing = $stmtCheck->fetch();
                        }

                        if ($existing) {
                            $skippedCount++;
                            $skippedDetails[] = [
                                'row' => $i + 1,
                                'nik' => $data['identity_number'],
                                'name' => $data['name'],
                                'room' => $data['room_number'],
                                'reason' => "Sudah terdaftar sebagai penghuni " . htmlspecialchars($existing['room_number']) . " a/n " . htmlspecialchars($existing['name'])
                            ];
                            continue; // SKIP BARIS INI
                        }

                        // 2. INSERT PENGHUNI BARU
                        try {
                            $stmtInsert->execute([
                                ':room_number' => $data['room_number'],
                                ':name' => $data['name'],
                                ':identity_number' => $data['identity_number'],
                                ':phone' => $data['phone'],
                                ':ktp_address' => $data['ktp_address'],
                                ':landlord_name' => $data['landlord_name'],
                                ':rent_price' => $data['rent_price'],
                                ':paid_amount' => $data['paid_amount'] ?? 0,
                                ':rent_period' => $data['rent_period'],
                                ':start_date' => $data['start_date'],
                                ':due_date' => $data['due_date'],
                                ':payment_status' => $data['payment_status'],
                                ':down_payment_date' => $data['down_payment_date'],
                                ':notes' => $data['notes']
                            ]);
                            $insertedCount++;
                        } catch (PDOException $e) {
                            $skippedCount++;
                            $skippedDetails[] = [
                                'row' => $i + 1,
                                'nik' => $data['identity_number'],
                                'name' => $data['name'],
                                'room' => $data['room_number'],
                                'reason' => "Gagal simpan: " . $e->getMessage()
                            ];
                        }
                    }

                    if ($insertedCount > 0 || $skippedCount > 0) {
                        $message = "Proses impor selesai! <strong>{$insertedCount}</strong> data baru berhasil ditambahkan, dan <strong>{$skippedCount}</strong> data dilewati karena sudah terdaftar di sistem.";
                        $alertType = $insertedCount > 0 ? 'success' : 'warning';
                    } else {
                        $message = "Tidak ada data yang diproses. Pastikan file memiliki baris data di bawah judul/header.";
                        $alertType = 'info';
                    }
                }
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Upload Penghuni .xlsx</li>
            </ol>
        </nav>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa-solid <?= $alertType === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> fa-2x me-3"></i>
                    <div><?= $message ?></div>
                </div>

                <?php if (!empty($skippedDetails)): ?>
                    <hr class="my-2">
                    <p class="mb-1 fw-bold small text-dark"><i class="fa-solid fa-filter me-1"></i> Data yang dilewati (mencegah data ganda):</p>
                    <div style="max-height: 180px; overflow-y: auto;" class="bg-white p-2 rounded border small">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Baris</th>
                                    <th>Nama</th>
                                    <th>NIK / Identitas</th>
                                    <th>Kamar</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skippedDetails as $sk): ?>
                                <tr>
                                    <td>Baris <?= $sk['row'] ?></td>
                                    <td><strong><?= htmlspecialchars($sk['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($sk['nik']) ?></code></td>
                                    <td><?= htmlspecialchars($sk['room']) ?></td>
                                    <td class="text-danger"><?= htmlspecialchars($sk['reason']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-primary">
                    <i class="fa-solid fa-file-arrow-up me-2"></i>Upload Data Penghuni (.xlsx)
                </h5>
                <a href="download_template.php" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-file-excel me-1"></i> Download Format_Penghuni_Kost_Kontrakan.xlsx
                </a>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    Sistem ini membaca file format <strong><code>.xlsx</code></strong> (termasuk format <em>Format_Penghuni_Kost_Kontrakan.xlsx</em>).
                    Setiap baris akan diverifikasi berdasarkan nomor identitas (NIK/KTP). 
                    <strong>Jika identitas sudah terdaftar di database, data tersebut otomatis di-skip (tidak diduplikasi).</strong>
                </p>

                <form action="upload.php" method="POST" enctype="multipart/form-data" class="my-4">
                    <?= csrfInput() ?>
                    <div class="border rounded-3 p-4 bg-light text-center">
                        <i class="fa-solid fa-file-excel fa-3x text-success mb-3"></i>
                        <h6 class="fw-bold">Pilih File Spreadsheet Excel</h6>
                        <p class="text-muted small mb-3">Format yang didukung: <strong>.xlsx</strong></p>
                        
                        <div class="col-md-8 mx-auto">
                            <input class="form-control form-control-lg" type="file" name="excel_file" id="excel_file" accept=".xlsx" required>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-cloud-arrow-up me-2"></i> Mulai Unggah & Impor Data
                            </button>
                        </div>
                    </div>
                </form>

                <div class="card bg-body-tertiary border-0 p-3">
                    <h6 class="fw-bold text-dark mb-2">
                        <i class="fa-solid fa-circle-question text-info me-1"></i> Mendukung Format Excel Bawaan:
                    </h6>
                    <p class="small text-muted mb-2">
                        Format bawaan template <code>Format_Penghuni_Kost_Kontrakan.xlsx</code> memiliki susunan kolom:
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm bg-white small">
                            <thead class="table-light">
                                <tr>
                                    <th>Kolom A</th>
                                    <th>Kolom B</th>
                                    <th>Kolom C</th>
                                    <th>Kolom D</th>
                                    <th>Kolom E</th>
                                    <th>Kolom F</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>NO</code></td>
                                    <td><code>NAMA/NIK</code></td>
                                    <td><code>ALAMAT KTP</code></td>
                                    <td><code>PEMILIK KONTRAKAN</code></td>
                                    <td><code>ALAMAT KOST/KONTRAKAN</code></td>
                                    <td><code>KET</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">
                        💡 <em>Tips:</em> Sistem secara cerdas akan mengekstrak Nama dan NIK dari kolom <code>NAMA/NIK</code>. Informasi nomor HP atau nomor kamar pada kolom <code>ALAMAT KOST</code> dan <code>KET</code> juga akan diekstrak secara otomatis.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
