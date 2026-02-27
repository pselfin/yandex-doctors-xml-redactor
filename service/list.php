<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

layoutHead('Услуги', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Услуги <span class="badge bg-secondary"><?= count($services) ?></span></h2>
  <a href="/service/edit.php?p=<?= urlencode($p) ?>" class="btn btn-success">+ Добавить услугу</a>
</div>

<?php if (empty($services)): ?>
  <div class="alert alert-info">Услуг нет.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr><th>ID</th><th>Название</th><th>Код (gov_id)</th><th>Описание</th><th>Действия</th></tr>
  </thead>
  <tbody>
  <?php foreach ($services as $s): ?>
  <tr>
    <td><code class="small"><?= h($s['id']) ?></code></td>
    <td><?= h($s['name']) ?></td>
    <td><?= h($s['gov_id']) ?></td>
    <td class="text-muted small"><?= h(mb_strimwidth($s['description'] ?? '', 0, 80, '…')) ?></td>
    <td>
      <a href="/service/edit.php?p=<?= urlencode($p) ?>&id=<?= urlencode($s['id']) ?>" class="btn btn-outline-primary btn-sm">Ред.</a>
      <a href="/service/delete.php?p=<?= urlencode($p) ?>&id=<?= urlencode($s['id']) ?>&_csrf=<?= csrfToken() ?>"
         class="btn btn-outline-danger btn-sm"
         onclick="return confirm('Удалить услугу «<?= h(addslashes($s['name'])) ?>»?')">Удал.</a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php layoutFoot(); ?>
