<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);

// Быстрое переименование inline
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_id'])) {
    $id   = (int)$_POST['rename_id'];
    $name = trim($_POST['rename_name'] ?? '');
    if ($name !== '') {
        $pdo->prepare("UPDATE specialties SET name=? WHERE id=?")->execute([$name, $id]);
        $success = 'Сохранено.';
    }
}

$specialties = $pdo->query("SELECT * FROM specialties ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Сколько офферов использует каждую специальность
$usageCounts = [];
$rows = $pdo->query("SELECT speciality, COUNT(*) c FROM offers WHERE speciality!='' GROUP BY speciality")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) $usageCounts[$r['speciality']] = $r['c'];

layoutHead('Специальности', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Специальности <span class="badge bg-secondary"><?= count($specialties) ?></span></h2>
  <a href="/specialty/edit.php?p=<?= urlencode($p) ?>" class="btn btn-success">+ Добавить</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success py-2"><?= h($success) ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:40px">#</th>
        <th>Название специальности</th>
        <th style="width:100px" class="text-center">Исп. в офферах</th>
        <th style="width:130px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($specialties as $s): ?>
    <tr>
      <td class="text-muted small"><?= $s['id'] ?></td>
      <td><?= h($s['name']) ?></td>
      <td class="text-center">
        <?php $cnt = $usageCounts[$s['name']] ?? 0; ?>
        <?php if ($cnt): ?>
          <span class="badge bg-primary"><?= $cnt ?></span>
        <?php else: ?>
          <span class="text-muted small">—</span>
        <?php endif; ?>
      </td>
      <td class="text-end">
        <a href="/specialty/edit.php?p=<?= urlencode($p) ?>&id=<?= $s['id'] ?>"
           class="btn btn-outline-primary btn-sm">Ред.</a>
        <?php if (($usageCounts[$s['name']] ?? 0) === 0): ?>
          <a href="/specialty/delete.php?p=<?= urlencode($p) ?>&id=<?= $s['id'] ?>&_csrf=<?= csrfToken() ?>"
             class="btn btn-outline-danger btn-sm"
             onclick="return confirm('Удалить «<?= h(addslashes($s['name'])) ?>»?')">Удал.</a>
        <?php else: ?>
          <button class="btn btn-outline-secondary btn-sm" disabled title="Используется в офферах">Удал.</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="mt-3 text-muted small">
  Специальности используются в форме оффера как подсказки. Можно вводить и нестандартные значения.
</div>
<?php layoutFoot(); ?>
