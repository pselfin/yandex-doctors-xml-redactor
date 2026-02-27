<?php
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
sendSecurityHeaders();

if (PASSWORD_HASH === '') {
    header('Location: /setup.php');
    exit;
}

if (!empty($_SESSION['auth'])) {
    header('Location: /');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    checkLoginRateLimit();
    if (login($_POST['password'] ?? '')) {
        header('Location: /');
        exit;
    }
    recordFailedLogin();
    $error = 'Неверный пароль.';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — XML Редактор</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:380px;margin-top:100px">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h4 class="mb-4 text-center">&#x1F512; XML Редактор</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Пароль</label>
          <input type="password" name="password" class="form-control" autofocus required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Войти</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
