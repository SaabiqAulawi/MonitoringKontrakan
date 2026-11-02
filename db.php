<?php
// db.php - Database connection and automatic schema initialization using SQLite
$configuredDataDir = getenv('APP_DATA_DIR');
$dataDir = is_string($configuredDataDir) && $configuredDataDir !== ''
    ? $configuredDataDir
    : __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$dbPath = $dataDir . '/kos.db';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Data lama dipertahankan agar migrasi tidak menghapus riwayat pembayaran.
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_number VARCHAR(100) NOT NULL,
        name VARCHAR(150) NOT NULL,
        identity_number VARCHAR(50) UNIQUE NULL,
        phone VARCHAR(30) NULL,
        ktp_address TEXT NULL,
        landlord_name VARCHAR(150) NULL,
        rent_price REAL NOT NULL DEFAULT 1000000,
        paid_amount REAL DEFAULT 0,
        rent_period VARCHAR(20) DEFAULT 'bulanan',
        start_date DATE NOT NULL,
        due_date DATE NOT NULL,
        payment_status VARCHAR(20) DEFAULT 'belum_bayar',
        last_paid_at DATETIME NULL,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrasi kolom paid_amount jika tabel tenants sudah ada sebelumnya
    $cols = $pdo->query("PRAGMA table_info(tenants)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('paid_amount', $colNames)) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN paid_amount REAL DEFAULT 0");
    }
    if (!in_array('unit_id', $colNames)) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN unit_id INTEGER NULL");
    }
    $identityColumn = null;
    foreach ($cols as $column) {
        if ($column['name'] === 'identity_number') {
            $identityColumn = $column;
            break;
        }
    }
    if ($identityColumn && (int)$identityColumn['notnull'] === 1) {
        $pdo->beginTransaction();
        try {
            $pdo->exec("CREATE TABLE tenants_migrating (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_number VARCHAR(100) NOT NULL,
                name VARCHAR(150) NOT NULL,
                identity_number VARCHAR(50) UNIQUE NULL,
                phone VARCHAR(30) NULL,
                ktp_address TEXT NULL,
                landlord_name VARCHAR(150) NULL,
                rent_price REAL NOT NULL DEFAULT 1000000,
                paid_amount REAL DEFAULT 0,
                rent_period VARCHAR(20) DEFAULT 'bulanan',
                start_date DATE NOT NULL,
                due_date DATE NOT NULL,
                payment_status VARCHAR(20) DEFAULT 'belum_bayar',
                last_paid_at DATETIME NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                unit_id INTEGER NULL
            )");
            $pdo->exec("INSERT INTO tenants_migrating
                (id, room_number, name, identity_number, phone, ktp_address, landlord_name,
                 rent_price, paid_amount, rent_period, start_date, due_date, payment_status,
                 last_paid_at, notes, created_at, updated_at, unit_id)
                SELECT id, room_number, name, NULLIF(identity_number, ''), phone, ktp_address,
                 landlord_name, rent_price, paid_amount, rent_period, start_date, due_date,
                 payment_status, last_paid_at, notes, created_at, updated_at, unit_id
                FROM tenants");
            $pdo->exec("DROP TABLE tenants");
            $pdo->exec("ALTER TABLE tenants_migrating RENAME TO tenants");
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS units (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        unit_code VARCHAR(100) UNIQUE NOT NULL,
        address TEXT NOT NULL,
        unit_status VARCHAR(30) NOT NULL DEFAULT 'kosong',
        condition_status VARCHAR(30) NOT NULL DEFAULT 'baik',
        repair_notes TEXT NULL,
        photo_path VARCHAR(255) NULL,
        bedrooms INTEGER NOT NULL DEFAULT 0,
        bathrooms INTEGER NOT NULL DEFAULT 0,
        area_sqm REAL NOT NULL DEFAULT 0,
        floors INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $unitCols = $pdo->query("PRAGMA table_info(units)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('unit_status', array_column($unitCols, 'name'))) {
        $pdo->exec("ALTER TABLE units ADD COLUMN unit_status VARCHAR(30) NOT NULL DEFAULT 'kosong'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenant_id INTEGER NOT NULL,
        contact_name VARCHAR(150) NULL,
        phone VARCHAR(30) NOT NULL,
        relationship VARCHAR(80) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(80) UNIQUE NOT NULL,
        display_name VARCHAR(150) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'owner',
        active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_token VARCHAR(128) UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Tabel Riwayat Log Perubahan Status (payment_logs)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenant_id INTEGER NOT NULL,
        room_number VARCHAR(100) NOT NULL,
        tenant_name VARCHAR(150) NOT NULL,
        old_status VARCHAR(20) NOT NULL,
        new_status VARCHAR(20) NOT NULL,
        amount REAL NOT NULL,
        payment_method VARCHAR(50) NULL,
        payment_date DATE NOT NULL,
        notes TEXT NULL,
        user_id INTEGER NULL,
        changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $logCols = $pdo->query("PRAGMA table_info(payment_logs)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('user_id', array_column($logCols, 'name'))) {
        $pdo->exec("ALTER TABLE payment_logs ADD COLUMN user_id INTEGER NULL");
    }

    // 3. Tabel Pengaturan / Profil Kos (settings)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");

    // Inisialisasi default settings jika belum ada
    $initSettings = [
        'kos_name' => 'Kontrakan Srini',
        'landlord_name' => 'Ibu Srini',
        'bank_info' => 'BCA 1234567890 a/n Srini',
        'default_rent_price' => '1200000',
    ];
    $stmtSet = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($initSettings as $k => $v) {
        $stmtSet->execute([$k, $v]);
    }

    $accountCount = (int)$pdo->query("SELECT COUNT(*) FROM user_accounts")->fetchColumn();
    if ($accountCount === 0) {
        $adminPassword = getenv('APP_ADMIN_PASSWORD');
        $ownerPassword = getenv('APP_OWNER_PASSWORD');
        if (!is_string($adminPassword) || strlen($adminPassword) < 12 || !is_string($ownerPassword) || strlen($ownerPassword) < 12) {
            die('Konfigurasi APP_ADMIN_PASSWORD dan APP_OWNER_PASSWORD wajib diatur dan masing-masing minimal 12 karakter sebelum database pertama kali dibuat.');
        }
        $stmtUser = $pdo->prepare("INSERT INTO user_accounts (username, display_name, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmtUser->execute(['administrator', 'Administrator', password_hash($adminPassword, PASSWORD_DEFAULT), 'administrator']);
        $stmtUser->execute(['owner', 'Owner', password_hash($ownerPassword, PASSWORD_DEFAULT), 'owner']);
    }

    // Setiap data lama mendapat unit dasar sehingga bisa dikembangkan tanpa kehilangan relasi.
    $pdo->exec("INSERT OR IGNORE INTO units (unit_code, address)
        SELECT DISTINCT room_number, room_number FROM tenants
        WHERE room_number IS NOT NULL AND room_number <> ''");
    $pdo->exec("UPDATE tenants SET unit_id = (
        SELECT id FROM units WHERE units.unit_code = tenants.room_number
    ) WHERE unit_id IS NULL");
    $pdo->exec("UPDATE units SET unit_status = 'terisi'
        WHERE EXISTS (SELECT 1 FROM tenants WHERE tenants.unit_id = units.id)");

} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// Helper fungsi untuk mengambil nilai setting
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetchColumn();
    return $res !== false ? $res : $default;
}

function rentPeriodMonths(string $period): int {
    return match ($period) {
        '6_bulan' => 6,
        'tahunan' => 12,
        default => 1,
    };
}

function rentPeriodLabel(string $period): string {
    return match ($period) {
        '6_bulan' => 'Per 6 Bulan',
        'tahunan' => 'Pertahun',
        default => 'Perbulan',
    };
}
