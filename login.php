<?php
require_once __DIR__ . '/auth.php';
if (currentUser()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM user_accounts WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $account = $stmt->fetch();
    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['id'];
        header('Location: index.php');
        exit;
    }
    $error = 'Username atau kata sandi salah.';
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - Monitoring Kontrakan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center min-vh-100">
<main class="container"><div class="row justify-content-center"><div class="col-sm-10 col-md-6 col-lg-4">
<div class="card shadow-sm border-0"><div class="card-body p-4">
<h4 class="fw-bold text-primary mb-1">Monitoring Kontrakan</h4><p class="text-muted mb-4">Silakan masuk untuk melanjutkan.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post"><?= csrfInput() ?><label class="form-label">Username</label><input class="form-control mb-3" name="username" required autofocus>
<label class="form-label">Kata sandi</label><input class="form-control mb-4" type="password" name="password" required>
<button class="btn btn-primary w-100">Masuk</button></form>
</div></div></div></div></main></body></html>
