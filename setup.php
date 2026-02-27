<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$resetFile = DATA_DIR . '/.reset';
$hasPassword = PASSWORD_HASH !== '';
$isReset = file_exists($resetFile);

$error = '';
$success = '';

// Если пароль уже задан и нет файла сброса — только показываем ссылку на логин
if ($hasPassword && !$isReset) {
    // не редиректим, а показываем страницу со ссылкой
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $pw  = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (strlen($pw) < 8) {
        $error = 'Пароль должен быть не менее 8 символов.';
    } elseif ($pw !== $pw2) {
        $error = 'Пароли не совпадают.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        $configFile = __DIR__ . '/config.php';
        $content = file_get_contents($configFile);

        // Используем str_replace — preg_replace ломает $2y$10$ (бэкрефы)
        $content = str_replace(
            "define('PASSWORD_HASH', '" . PASSWORD_HASH . "')",
            "define('PASSWORD_HASH', '" . $hash . "')",
            $content
        );

        if (file_put_contents($configFile, $content) !== false) {
            // Удаляем файл сброса если был
            if ($isReset) @unlink($resetFile);
            $success = true;
        } else {
            $error = 'Не удалось записать config.php. Проверьте права на файл.';
        }
    }
}

sendSecurityHeaders();
$title = $isReset ? 'Сброс пароля' : 'Первоначальная настройка';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> — XML Редактор</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:420px;margin-top:80px">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h4 class="mb-3"><?= $isReset ? '🔓 Сброс пароля' : '🔧 Первоначальная настройка' ?></h4>

      <?php if ($isReset): ?>
        <div class="alert alert-warning py-2 small">
          Файл <code>data/.reset</code> найден — введите новый пароль.
          После сохранения файл будет удалён автоматически.
        </div>
      <?php else: ?>
        <p class="text-muted small">Установите пароль для входа в редактор.</p>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success mb-3">Пароль установлен.</div>
        <a href="/login.php" class="btn btn-primary w-100">Войти</a>
      <?php elseif ($hasPassword && !$isReset): ?>
        <div class="alert alert-info mb-3">Пароль уже задан.</div>
        <a href="/login.php" class="btn btn-primary w-100">Войти</a>
        <p class="text-muted small mt-3 mb-0">
          Для смены пароля — войдите и используйте кнопку «Пароль» в шапке.<br>
          Если забыли пароль — создайте файл <code>data/.reset</code> и откройте эту страницу снова.
        </p>
      <?php else: ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Новый пароль</label>
          <input type="password" name="password" class="form-control" required minlength="8" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Повторите пароль</label>
          <input type="password" name="password2" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <?= $isReset ? 'Сбросить пароль' : 'Установить пароль' ?>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$hasPassword && !$isReset && !$success): ?>
  <p class="text-center text-muted small mt-3">
    Если вы забыли пароль — создайте файл <code>data/.reset</code><br>
    и снова откройте эту страницу.
  </p>
  <?php endif; ?>
</div>
</body>
</html>
