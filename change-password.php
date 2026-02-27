<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $old  = $_POST['old_password']  ?? '';
    $new  = $_POST['new_password']  ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    if (!password_verify($old, PASSWORD_HASH)) {
        $error = 'Текущий пароль введён неверно.';
    } elseif (strlen($new) < 8) {
        $error = 'Новый пароль должен быть не менее 8 символов.';
    } elseif ($new !== $new2) {
        $error = 'Новые пароли не совпадают.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $configFile = __DIR__ . '/config.php';
        $content = file_get_contents($configFile);

        // str_replace — безопасно для bcrypt-хешей с $2y$10$
        $content = str_replace(
            "define('PASSWORD_HASH', '" . PASSWORD_HASH . "')",
            "define('PASSWORD_HASH', '" . $hash . "')",
            $content
        );

        if (file_put_contents($configFile, $content) !== false) {
            $success = 'Пароль успешно изменён.';
        } else {
            $error = 'Не удалось записать config.php. Проверьте права на файл.';
        }
    }
}

layoutHead('Смена пароля');
layoutContent();
?>
<div style="max-width:420px">
  <h2 class="mb-4">Смена пароля</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Текущий пароль</label>
          <input type="password" name="old_password" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Новый пароль</label>
          <input type="password" name="new_password" class="form-control" required minlength="8">
        </div>
        <div class="mb-3">
          <label class="form-label">Повторите новый пароль</label>
          <input type="password" name="new_password2" class="form-control" required minlength="8">
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <a href="/" class="btn btn-secondary">Отмена</a>
        </div>
      </form>
    </div>
  </div>

  <div class="mt-3 text-muted small">
    <strong>Если забыли пароль</strong> — создайте файл <code>data/.reset</code>
    через FTP/SSH и откройте <a href="/setup.php">/setup.php</a>.
  </div>
</div>
<?php
layoutFoot();
