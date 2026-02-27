<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$clinics = $pdo->query("SELECT * FROM clinics ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

layoutHead('Клиники', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Клиники <span class="badge bg-secondary"><?= count($clinics) ?></span></h2>
  <a href="/clinic/edit.php?p=<?= urlencode($p) ?>" class="btn btn-success">+ Добавить клинику</a>
</div>

<?php if (empty($clinics)): ?>
  <div class="alert alert-info">Клиник нет.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr><th>ID</th><th>Название</th><th>Город</th><th>Адрес</th><th>Телефон</th><th>Действия</th></tr>
  </thead>
  <tbody>
  <?php foreach ($clinics as $c): ?>
  <tr>
    <td><code class="small"><?= h($c['id']) ?></code></td>
    <td><?= h($c['name']) ?></td>
    <td><?= h($c['city']) ?></td>
    <td><?= h($c['address']) ?></td>
    <td><?= h($c['phone']) ?></td>
    <td>
      <a href="/clinic/edit.php?p=<?= urlencode($p) ?>&id=<?= urlencode($c['id']) ?>" class="btn btn-outline-primary btn-sm">Ред.</a>
      <a href="/clinic/delete.php?p=<?= urlencode($p) ?>&id=<?= urlencode($c['id']) ?>&_csrf=<?= csrfToken() ?>"
         class="btn btn-outline-danger btn-sm"
         onclick="return confirm('Удалить клинику «<?= h(addslashes($c['name'])) ?>»?')">Удал.</a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php layoutFoot(); ?>
