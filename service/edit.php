<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$editId = trim($_GET['id'] ?? '');
$isNew = ($editId === '');
$error = '';
$row = [];

if (!$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: /service/list.php?p=' . urlencode($p)); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = preg_replace('/[^a-z0-9_-]/i', '', trim($_POST['id'] ?? ''));
    if ($id === '') {
        $error = 'ID обязателен.';
    } elseif ($isNew) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id=?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) $error = 'Услуга с таким ID уже существует.';
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO services (id, name, gov_id, description, internal_id) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $id,
            trim($_POST['name'] ?? ''),
            trim($_POST['gov_id'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['internal_id'] ?? ''),
        ]);
        header('Location: /service/list.php?p=' . urlencode($p));
        exit;
    }
    $row = $_POST;
    $row['id'] = $id ?? '';
}

layoutHead(($isNew ? 'Добавить услугу' : 'Услуга: ' . ($row['name'] ?? '')), $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= $isNew ? 'Добавить услугу' : ('Услуга: ' . h($row['name'] ?? '')) ?></h2>
  <a href="/service/list.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">← Список</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" style="max-width:600px">
  <?= csrfField() ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">ID <span class="text-danger">*</span></label>
          <input type="text" name="id" class="form-control font-monospace"
                 value="<?= h($row['id'] ?? '') ?>"
                 <?= $isNew ? '' : 'readonly' ?>
                 pattern="[a-zA-Z0-9_-]+" required placeholder="pervichny_priem">
          <div class="form-text">Латиница, цифры, дефис, подчёркивание. Используется как атрибут <code>id</code> в XML.</div>
        </div>
        <div class="col-md-7">
          <label class="form-label">Название <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= h($row['name'] ?? '') ?>" required>
          <div class="form-text">Полное название медицинской услуги.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Код услуги (<code>gov_id</code>)</label>
          <input type="text" name="gov_id" class="form-control font-monospace" value="<?= h($row['gov_id'] ?? '') ?>" placeholder="A01.07.001">
          <div class="form-text">Код из Номенклатуры медицинских услуг Минздрава РФ. Формат: <code>A00.00.000</code> или <code>B00.000.000</code>.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Internal ID</label>
          <input type="text" name="internal_id" class="form-control" value="<?= h($row['internal_id'] ?? '') ?>">
          <div class="form-text">Внутренний идентификатор услуги в системе онлайн-записи.</div>
        </div>
        <div class="col-12">
          <label class="form-label">Описание</label>
          <textarea name="description" class="form-control" rows="3"><?= h($row['description'] ?? '') ?></textarea>
          <div class="form-text">Краткое описание услуги — что включает, для кого предназначена.</div>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="/service/list.php?p=<?= urlencode($p) ?>" class="btn btn-secondary">Отмена</a>
  </div>
</form>
<?php layoutFoot(); ?>
