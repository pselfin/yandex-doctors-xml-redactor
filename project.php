<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) {
    header('Location: /');
    exit;
}

$pdo = getDb($p);
$error = '';
$success = '';

// Сохранение мета
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $fields = ['name', 'company', 'url', 'email', 'picture', 'version', 'date'];
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)");
    foreach ($fields as $key) {
        $stmt->execute([$key, trim($_POST[$key] ?? '')]);
    }
    $success = 'Метаданные сохранены.';
}

// Загрузка мета
$meta = [];
foreach ($pdo->query("SELECT key, value FROM meta") as $row) {
    $meta[$row['key']] = $row['value'];
}

// Счётчики
$counts = [
    'doctors' => $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn(),
    'clinics' => $pdo->query('SELECT COUNT(*) FROM clinics')->fetchColumn(),
    'services' => $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn(),
    'offers' => $pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn(),
];

layoutHead('Проект: ' . $p, $p);
layoutContent($p);
?>
<h2 class="mb-1">Проект: <?= h($p) ?></h2>
<p class="text-muted mb-4"><?= h($meta['name'] ?? '') ?></p>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<!-- Счётчики -->
<div class="row g-3 mb-4">
  <?php
  $tiles = [
    ['Врачи', $counts['doctors'], 'primary', '/doctor/list.php?p=' . urlencode($p)],
    ['Клиники', $counts['clinics'], 'success', '/clinic/list.php?p=' . urlencode($p)],
    ['Услуги', $counts['services'], 'info', '/service/list.php?p=' . urlencode($p)],
    ['Предложения', $counts['offers'], 'warning', '/offer/list.php?p=' . urlencode($p)],
  ];
  foreach ($tiles as [$label, $count, $color, $link]):
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $link ?>" class="text-decoration-none">
      <div class="card text-center border-<?= $color ?>">
        <div class="card-body py-3">
          <div class="display-5 fw-bold text-<?= $color ?>"><?= $count ?></div>
          <div class="small text-muted"><?= $label ?></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Быстрые действия -->
<div class="mb-4 d-flex gap-2 flex-wrap">
  <a href="/import.php?p=<?= urlencode($p) ?>" class="btn btn-outline-primary">&#x1F4E5; Импортировать XML</a>
  <a href="/export.php?p=<?= urlencode($p) ?>" class="btn btn-outline-success">&#x1F4E4; Скачать XML</a>
  <a href="/doctor/edit.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">+ Добавить врача</a>
</div>

<!-- Форма метаданных -->
<div class="card">
  <div class="card-header"><strong>Метаданные фида (&lt;shop&gt;)</strong></div>
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Название (<code>name</code>)</label>
          <input type="text" name="name" class="form-control" value="<?= h($meta['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Компания (<code>company</code>)</label>
          <input type="text" name="company" class="form-control" value="<?= h($meta['company'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">URL сайта (<code>url</code>)</label>
          <input type="url" name="url" class="form-control" value="<?= h($meta['url'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email (<code>email</code>)</label>
          <input type="email" name="email" class="form-control" value="<?= h($meta['email'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Логотип URL (<code>picture</code>)</label>
          <input type="url" name="picture" class="form-control" value="<?= h($meta['picture'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Версия (<code>version</code>)</label>
          <input type="text" name="version" class="form-control" value="<?= h($meta['version'] ?? '2.0') ?>">
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary">Сохранить метаданные</button>
      </div>
    </form>
  </div>
</div>
<?php
layoutFoot();
