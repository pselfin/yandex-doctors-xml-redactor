<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $name = trim($_POST['project_name'] ?? '');
    $slug = preg_replace('/[^a-z0-9_-]/i', '', strtolower($name));
    if ($slug === '') {
        $error = 'Недопустимое имя проекта.';
    } elseif (projectExists($slug)) {
        $error = 'Проект с таким именем уже существует.';
    } else {
        getDb($slug); // создаст файл и схему
        $success = 'Проект «' . htmlspecialchars($slug) . '» создан.';
    }
}

$projects = getProjects();

layoutHead('Проекты');
layoutContent();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Проекты</h2>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newProjectModal">+ Новый проект</button>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if (empty($projects)): ?>
  <div class="alert alert-info">Нет проектов. Создайте первый проект или импортируйте XML.</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($projects as $p): ?>
    <?php
      $pdo = getDb($p);
      $docCount = $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
      $cliCount = $pdo->query('SELECT COUNT(*) FROM clinics')->fetchColumn();
      $svcCount = $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
      $offCount = $pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn();
      $projName = $pdo->query("SELECT value FROM meta WHERE key='name'")->fetchColumn() ?: $p;
    ?>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><?= h($p) ?></h5>
          <p class="text-muted small mb-2"><?= h($projName) ?></p>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <span class="badge bg-primary"><?= $docCount ?> врачей</span>
            <span class="badge bg-success"><?= $cliCount ?> клиник</span>
            <span class="badge bg-info"><?= $svcCount ?> услуг</span>
            <span class="badge bg-warning text-dark"><?= $offCount ?> предложений</span>
          </div>
          <a href="/project.php?p=<?= urlencode($p) ?>" class="btn btn-outline-primary btn-sm">Открыть</a>
          <a href="/export.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary btn-sm">Скачать XML</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: новый проект -->
<div class="modal fade" id="newProjectModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrfField() ?>
      <div class="modal-header">
        <h5 class="modal-title">Новый проект</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Имя проекта (slug, только латиница, цифры, - _)</label>
        <input type="text" name="project_name" class="form-control" placeholder="eurodent" required pattern="[a-zA-Z0-9_-]+">
        <div class="form-text">Будет создан файл <code>data/имя.sqlite</code></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-success">Создать</button>
      </div>
    </form>
  </div>
</div>
<?php
layoutFoot();
