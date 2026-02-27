<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$editId = (int)($_GET['id'] ?? 0);
$isNew  = ($editId === 0);
$error  = '';
$row    = ['name' => '', 'sort_order' => 0];

if (!$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM specialties WHERE id=?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: /specialty/list.php?p=' . urlencode($p)); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $name  = trim($_POST['name'] ?? '');
    $order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Название обязательно.';
    } else {
        // Проверка уникальности
        $check = $pdo->prepare("SELECT id FROM specialties WHERE name=? AND id!=?");
        $check->execute([$name, $editId]);
        if ($check->fetch()) {
            $error = 'Специальность с таким названием уже существует.';
        }
    }

    if (!$error) {
        if ($isNew) {
            $pdo->prepare("INSERT INTO specialties (name, sort_order) VALUES (?,?)")->execute([$name, $order]);
        } else {
            $pdo->prepare("UPDATE specialties SET name=?, sort_order=? WHERE id=?")->execute([$name, $order, $editId]);
        }
        header('Location: /specialty/list.php?p=' . urlencode($p));
        exit;
    }
    $row = ['name' => $name, 'sort_order' => $order];
}

layoutHead($isNew ? 'Добавить специальность' : 'Редактировать специальность', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= $isNew ? 'Добавить специальность' : 'Редактировать специальность' ?></h2>
  <a href="/specialty/list.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">← Список</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" style="max-width:480px">
  <?= csrfField() ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Название <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= h($row['name']) ?>" required autofocus
               placeholder="стоматолог-ортопед">
        <div class="form-text">Строчными буквами, как принято в Яндексе.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Порядок сортировки</label>
        <input type="number" name="sort_order" class="form-control" style="width:120px"
               value="<?= h($row['sort_order']) ?>">
      </div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="/specialty/list.php?p=<?= urlencode($p) ?>" class="btn btn-secondary">Отмена</a>
  </div>
</form>
<?php layoutFoot(); ?>
