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
    $stmt = $pdo->prepare("SELECT * FROM clinics WHERE id=?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: /clinic/list.php?p=' . urlencode($p)); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = preg_replace('/[^a-z0-9_-]/i', '', trim($_POST['id'] ?? ''));
    if ($id === '') {
        $error = 'ID обязателен.';
    } elseif ($isNew) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM clinics WHERE id=?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) $error = 'Клиника с таким ID уже существует.';
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO clinics
            (id, name, city, address, url, picture, email, phone, internal_id, company_id)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $id,
            trim($_POST['name'] ?? ''),
            trim($_POST['city'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['url'] ?? ''),
            trim($_POST['picture'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['internal_id'] ?? ''),
            trim($_POST['company_id'] ?? ''),
        ]);
        header('Location: /clinic/list.php?p=' . urlencode($p));
        exit;
    }
    $row = $_POST;
    $row['id'] = $id ?? '';
}

layoutHead(($isNew ? 'Добавить клинику' : 'Клиника: ' . ($row['name'] ?? '')), $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= $isNew ? 'Добавить клинику' : ('Клиника: ' . h($row['name'] ?? '')) ?></h2>
  <a href="/clinic/list.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">← Список</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" style="max-width:700px">
  <?= csrfField() ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">ID <span class="text-danger">*</span></label>
          <input type="text" name="id" class="form-control font-monospace"
                 value="<?= h($row['id'] ?? '') ?>"
                 <?= $isNew ? '' : 'readonly' ?>
                 pattern="[a-zA-Z0-9_-]+" required placeholder="eurodent">
          <div class="form-text">Латиница, цифры, дефис, подчёркивание. Используется как атрибут <code>id</code> в XML.</div>
        </div>
        <div class="col-md-8">
          <label class="form-label">Название <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= h($row['name'] ?? '') ?>" required>
          <div class="form-text">Полное официальное название клиники.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Город</label>
          <input type="text" name="city" class="form-control" value="<?= h($row['city'] ?? '') ?>" placeholder="Краснодар">
          <div class="form-text">Только название города, без слова «г.».</div>
        </div>
        <div class="col-md-8">
          <label class="form-label">Адрес</label>
          <input type="text" name="address" class="form-control" value="<?= h($row['address'] ?? '') ?>" placeholder="ул. Красная, 1">
          <div class="form-text">Улица, дом — без города.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">URL</label>
          <input type="url" name="url" class="form-control" value="<?= h($row['url'] ?? '') ?>" placeholder="https://clinic.ru/about/">
          <div class="form-text">Ссылка на страницу клиники на сайте.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Телефон</label>
          <input type="tel" name="phone" class="form-control"
                 value="<?= h($row['phone'] ?? '') ?>"
                 placeholder="+7(861)238-74-20"
                 pattern="[\+\d\s\(\)\-]{7,20}"
                 title="Формат: +7(000)000-00-00">
          <div class="form-text">Формат: <code>+7(861)238-74-20</code>.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($row['email'] ?? '') ?>" placeholder="info@clinic.ru">
        </div>
        <div class="col-md-6">
          <label class="form-label">URL логотипа</label>
          <input type="url" name="picture" class="form-control" value="<?= h($row['picture'] ?? '') ?>" placeholder="https://clinic.ru/logo.png">
          <div class="form-text">Прямая ссылка на изображение логотипа (PNG или JPG).</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Internal ID</label>
          <input type="text" name="internal_id" class="form-control" value="<?= h($row['internal_id'] ?? '') ?>">
          <div class="form-text">Внутренний идентификатор клиники в системе онлайн-записи.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Company ID</label>
          <input type="text" name="company_id" class="form-control" value="<?= h($row['company_id'] ?? '') ?>">
          <div class="form-text">ID организации в Яндекс.Бизнес — найдите в URL: <code>yandex.ru/profile/<strong>XXXXXXX</strong></code>.</div>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="/clinic/list.php?p=<?= urlencode($p) ?>" class="btn btn-secondary">Отмена</a>
  </div>
</form>
<?php layoutFoot(); ?>
