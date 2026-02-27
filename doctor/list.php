<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$doctors = $pdo->query("SELECT * FROM doctors ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Быстрое обновление sort_order через POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sort'])) {
    csrfVerify();
    $stmt = $pdo->prepare("UPDATE doctors SET sort_order=? WHERE id=?");
    foreach ($_POST['sort'] as $id => $order) {
        $stmt->execute([(int)$order, $id]);
    }
    header('Location: /doctor/list.php?p=' . urlencode($p));
    exit;
}

layoutHead('Врачи', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Врачи <span class="badge bg-secondary"><?= count($doctors) ?></span></h2>
  <a href="/doctor/edit.php?p=<?= urlencode($p) ?>" class="btn btn-success">+ Добавить врача</a>
</div>

<?php if (empty($doctors)): ?>
  <div class="alert alert-info">Врачей нет. <a href="/import.php?p=<?= urlencode($p) ?>">Импортировать из XML</a> или добавить вручную.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th style="width:60px">Фото</th>
      <th>ФИО / ID</th>
      <th>Образование</th>
      <th>Места работы</th>
      <th style="width:140px">Действия</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($doctors as $doc):
    $eduCnt = $pdo->prepare("SELECT COUNT(*) FROM doctor_education WHERE doctor_id=?");
    $eduCnt->execute([$doc['id']]);
    $eduCount = $eduCnt->fetchColumn();
    $jobCnt = $pdo->prepare("SELECT COUNT(*) FROM doctor_jobs WHERE doctor_id=?");
    $jobCnt->execute([$doc['id']]);
    $jobCount = $jobCnt->fetchColumn();
  ?>
  <tr>
    <td>
      <?php if ($doc['picture']): ?>
        <img src="<?= h($doc['picture']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:50%">
      <?php else: ?>
        <div style="width:48px;height:48px;background:#e9ecef;border-radius:50%;display:flex;align-items:center;justify-content:center">&#x1F9D1;</div>
      <?php endif; ?>
    </td>
    <td>
      <strong><?= h($doc['name']) ?></strong><br>
      <code class="small text-muted"><?= h($doc['id']) ?></code>
      <?php if ($doc['experience_years']): ?>
        <span class="badge bg-light text-dark ms-1"><?= $doc['experience_years'] ?> лет</span>
      <?php endif; ?>
    </td>
    <td><span class="badge bg-info text-dark"><?= $eduCount ?></span></td>
    <td><span class="badge bg-secondary"><?= $jobCount ?></span></td>
    <td>
      <a href="/doctor/edit.php?p=<?= urlencode($p) ?>&id=<?= urlencode($doc['id']) ?>" class="btn btn-outline-primary btn-sm">Ред.</a>
      <a href="/doctor/delete.php?p=<?= urlencode($p) ?>&id=<?= urlencode($doc['id']) ?>&_csrf=<?= csrfToken() ?>"
         class="btn btn-outline-danger btn-sm"
         onclick="return confirm('Удалить врача «<?= h(addslashes($doc['name'])) ?>»?')">Удал.</a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php layoutFoot(); ?>
