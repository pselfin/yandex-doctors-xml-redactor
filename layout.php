<?php
function layoutHead(string $title, string $project = ''): void {
    echo '<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — XML Редактор Яндекс Врачи</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body{padding-top:56px}
.sidebar{min-height:calc(100vh - 56px);background:#f8f9fa;border-right:1px solid #dee2e6}
.nav-section{font-size:.75rem;text-transform:uppercase;color:#6c757d;padding:.5rem 1rem;margin-top:.5rem}
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="/">&#x1F4CB; XML Редактор Яндекс Врачи</a>
    <div class="d-flex align-items-center gap-2">
';
    if ($project) {
        echo '<span class="text-white-50 small">Проект: <strong class="text-white">' . htmlspecialchars($project, ENT_QUOTES, 'UTF-8') . '</strong></span>';
    }
    echo '
      <a href="https://webmaster.yandex.ru/site/tools/xml-validator/" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm">&#x2705; Валидатор</a>
      <a href="/change-password.php" class="btn btn-outline-light btn-sm">Пароль</a>
      <a href="/logout.php" class="btn btn-outline-light btn-sm">Выход</a>
    </div>
  </div>
</nav>
<div class="container-fluid">
<div class="row">
';
    if ($project) {
        echo '<div class="col-md-2 sidebar py-3">
  <div class="nav-section">Проект</div>
  <a class="nav-link" href="/project.php?p=' . urlencode($project) . '">&#x1F3E0; Дашборд</a>
  <a class="nav-link" href="/import.php?p=' . urlencode($project) . '">&#x1F4E5; Импорт XML</a>
  <a class="nav-link" href="/export.php?p=' . urlencode($project) . '">&#x1F4E4; Экспорт XML</a>
  <div class="nav-section">Сущности</div>
  <a class="nav-link" href="/doctor/list.php?p=' . urlencode($project) . '">&#x1F9D1;&#x200D;&#x2695;&#xFE0F; Врачи</a>
  <a class="nav-link" href="/clinic/list.php?p=' . urlencode($project) . '">&#x1F3E5; Клиники</a>
  <a class="nav-link" href="/service/list.php?p=' . urlencode($project) . '">&#x1F4CB; Услуги</a>
  <a class="nav-link" href="/offer/list.php?p=' . urlencode($project) . '">&#x1F4B0; Предложения</a>
  <a class="nav-link" href="/specialty/list.php?p=' . urlencode($project) . '">&#x1F3F7; Специальности</a>
  <hr>
  <a class="nav-link" href="/">&#x2190; Все проекты</a>
</div>
';
    }
}

function layoutContent(string $project = ''): void {
    $cols = $project ? 'col-md-10' : 'col-12';
    echo '<div class="' . $cols . ' py-4 px-4">';
}

function layoutFoot(): void {
    echo '</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body></html>';
}
