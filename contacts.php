<?php
require_once __DIR__ . '/includes/header.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $pdo->prepare('DELETE FROM tenant_contacts WHERE id=?')->execute([(int)$_POST['delete_id']]);
    $message = 'Kontak dihapus.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['delete_id'])) verifyCsrf();
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    if ($tenantId && $phone !== '') {
        $pdo->prepare('INSERT INTO tenant_contacts (tenant_id, contact_name, phone, relationship) VALUES (?,?,?,?)')
            ->execute([$tenantId, trim($_POST['contact_name'] ?? ''), $phone, trim($_POST['relationship'] ?? '')]);
        $message = 'Kontak tambahan disimpan.';
    }
}
$tenants = $pdo->query('SELECT id, name, room_number FROM tenants ORDER BY name')->fetchAll();
$contacts = $pdo->query('SELECT c.*, t.name, t.room_number FROM tenant_contacts c JOIN tenants t ON t.id=c.tenant_id ORDER BY t.name, c.id DESC')->fetchAll();
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-4"><div class="card"><div class="card-body"><h5 class="fw-bold">Tambah Kontak Penghuni</h5>
<form method="post"><?= csrfInput() ?><label class="form-label">Penghuni</label><select class="form-select mb-2" name="tenant_id" required><option value="">Pilih penghuni</option><?php foreach ($tenants as $tenant): ?><option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name'] . ' · ' . $tenant['room_number']) ?></option><?php endforeach; ?></select>
<label class="form-label">Nama Kontak</label><input class="form-control mb-2" name="contact_name" placeholder="Contoh: Suami / Istri">
<label class="form-label">Nomor WhatsApp</label><input class="form-control mb-2" name="phone" required placeholder="08xxxxxxxxxx">
<label class="form-label">Hubungan</label><input class="form-control mb-3" name="relationship" placeholder="Keluarga / penanggung jawab">
<button class="btn btn-primary w-100">Simpan Kontak</button></form></div></div></div>
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tenant</th><th>Kontak</th><th>Nomor</th><th>Hubungan</th><th></th></tr></thead><tbody><?php foreach ($contacts as $contact): ?><tr><td><?= htmlspecialchars($contact['name']) ?><br><small class="text-muted"><?= htmlspecialchars($contact['room_number']) ?></small></td><td><?= htmlspecialchars($contact['contact_name'] ?: '-') ?></td><td><?= htmlspecialchars($contact['phone']) ?></td><td><?= htmlspecialchars($contact['relationship'] ?: '-') ?></td><td><form method="post" class="d-inline" onsubmit="return confirm('Hapus kontak?')"><?= csrfInput() ?><input type="hidden" name="delete_id" value="<?= $contact['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
