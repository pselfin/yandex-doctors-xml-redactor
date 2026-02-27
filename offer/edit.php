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
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE id=?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: /offer/list.php?p=' . urlencode($p)); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = preg_replace('/[^a-z0-9_-]/i', '', trim($_POST['id'] ?? ''));
    if ($id === '') {
        $error = 'ID обязателен.';
    } elseif ($isNew) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE id=?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) $error = 'Предложение с таким ID уже существует.';
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO offers
            (id, service_id, clinic_id, doctor_id, url,
             online_schedule, appointment, oms,
             base_price, currency, discount, free_appointment,
             speciality, children_appointment, adult_appointment,
             house_call, telemed, is_base_service)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $bp = $_POST['base_price'] !== '' ? (float)$_POST['base_price'] : null;
        $disc = $_POST['discount'] !== '' ? (float)$_POST['discount'] : null;
        $stmt->execute([
            $id,
            trim($_POST['service_id'] ?? ''),
            trim($_POST['clinic_id'] ?? ''),
            trim($_POST['doctor_id'] ?? ''),
            trim($_POST['url'] ?? ''),
            isset($_POST['online_schedule']) ? 1 : 0,
            isset($_POST['appointment']) ? 1 : 0,
            isset($_POST['oms']) ? 1 : 0,
            $bp,
            trim($_POST['currency'] ?? 'RUR'),
            $disc,
            trim($_POST['free_appointment'] ?? ''),
            trim($_POST['speciality'] ?? ''),
            isset($_POST['children_appointment']) ? 1 : 0,
            isset($_POST['adult_appointment']) ? 1 : 0,
            isset($_POST['house_call']) ? 1 : 0,
            isset($_POST['telemed']) ? 1 : 0,
            isset($_POST['is_base_service']) ? 1 : 0,
        ]);
        header('Location: /offer/list.php?p=' . urlencode($p));
        exit;
    }
    $row = $_POST;
    $row['id'] = $id ?? '';
}

// Загрузка списков для select / datalist
$doctors     = $pdo->query("SELECT id, name FROM doctors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$clinics     = $pdo->query("SELECT id, name FROM clinics ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$services    = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$specialties = $pdo->query("SELECT name FROM specialties ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);

// Пресет врача при переходе с карточки врача
$presetDoctorId  = $isNew ? trim($_GET['doctor_id'] ?? '') : '';
// Авто-клиника: если в проекте только одна — подставляем автоматом
$defaultClinicId = (count($clinics) === 1) ? $clinics[0]['id'] : '';

// Начальные значения для Alpine.js (учитывают и редактирование, и создание)
$initDoctorId = $row['doctor_id'] ?? $presetDoctorId;
$initClinicId = $row['clinic_id'] ?? $defaultClinicId;
$jFlags       = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

layoutHead(($isNew ? 'Добавить предложение' : 'Предложение: ' . ($row['id'] ?? '')), $p);
layoutContent($p);

function selOpt(array $items, string $selected, string $emptyLabel = '— не выбрано —'): void {
    echo '<option value="">' . h($emptyLabel) . '</option>';
    foreach ($items as $item) {
        $sel = ($item['id'] === $selected) ? ' selected' : '';
        echo '<option value="' . h($item['id']) . '"' . $sel . '>' . h($item['name']) . ' (' . h($item['id']) . ')</option>';
    }
}
function chk(array $row, string $key, bool $default = false): string {
    if (isset($row[$key])) return $row[$key] ? ' checked' : '';
    return $default ? ' checked' : '';
}
?>
<script>
function offerFormData() {
    return {
        doctorId: <?= json_encode($initDoctorId, $jFlags) ?>,
        clinicId: <?= json_encode($initClinicId, $jFlags) ?>,
        idValue:  <?= json_encode($row['id'] ?? '', $jFlags) ?>,
        // idManual=true означает что пользователь сам ввёл ID — не перезаписываем
        idManual: <?= (!$isNew || ($row['id'] ?? '') !== '') ? 'true' : 'false' ?>,
        suggestedId() {
            if (this.doctorId && this.clinicId) return this.doctorId + '_' + this.clinicId;
            return this.doctorId || '';
        },
        syncId() {
            if (!this.idManual) this.idValue = this.suggestedId();
        }
    };
}
</script>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= $isNew ? 'Добавить предложение' : ('Предложение: ' . h($row['id'] ?? '')) ?></h2>
  <a href="/offer/list.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">← Список</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" style="max-width:800px" x-data="offerFormData()" x-init="syncId()">
  <?= csrfField() ?>
  <div class="card mb-3">
    <div class="card-header"><strong>Идентификация</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">ID оффера <span class="text-danger">*</span></label>
          <input type="text" name="id" class="form-control font-monospace"
                 x-model="idValue"
                 @input="idManual = true"
                 <?= $isNew ? '' : 'readonly' ?>
                 pattern="[a-zA-Z0-9_-]+" required placeholder="offer_timchenko">
          <div class="form-text">
            <?php if ($isNew): ?>
              Заполняется автоматически при выборе врача и клиники. Можно изменить вручную.
            <?php else: ?>
              ID нельзя изменить после создания.
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Врач</label>
          <select name="doctor_id" class="form-select" x-model="doctorId" @change="syncId()">
            <?php selOpt($doctors, $initDoctorId) ?>
          </select>
          <div class="form-text">Врач, к которому относится это предложение.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Клиника</label>
          <select name="clinic_id" class="form-select" x-model="clinicId" @change="syncId()">
            <?php selOpt($clinics, $initClinicId) ?>
          </select>
          <div class="form-text">Клиника, в которой работает врач по этому предложению.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Услуга</label>
          <select name="service_id" class="form-select">
            <?php selOpt($services, $row['service_id'] ?? '') ?>
          </select>
          <div class="form-text">Услуга, которую оказывает врач в этой клинике. Один врач может вести несколько услуг.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Специальность</label>
          <input type="text" name="speciality" class="form-control"
                 value="<?= h($row['speciality'] ?? '') ?>"
                 list="specialties-list"
                 placeholder="стоматолог-ортопед"
                 autocomplete="off">
          <datalist id="specialties-list">
            <?php foreach ($specialties as $sp): ?>
              <option value="<?= h($sp) ?>">
            <?php endforeach; ?>
          </datalist>
          <div class="form-text">Специальность врача для этого предложения. Выберите из списка или введите вручную.</div>
        </div>
        <div class="col-12">
          <label class="form-label">URL страницы врача</label>
          <input type="url" name="url" class="form-control" value="<?= h($row['url'] ?? '') ?>" placeholder="https://clinic.ru/doctors/timchenko/">
          <div class="form-text">Ссылка на страницу врача в данной клинике (используется в оффере, может отличаться от основного URL врача).</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Цена</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Базовая цена</label>
          <input type="number" name="base_price" class="form-control" value="<?= h($row['base_price'] ?? '') ?>" step="0.01" min="0">
          <div class="form-text">Цена приёма в рублях. Оставьте пустым, если цена не указывается.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Валюта</label>
          <select name="currency" class="form-select">
            <option value="RUR" <?= ($row['currency'] ?? 'RUR') === 'RUR' ? 'selected' : '' ?>>RUR</option>
            <option value="USD" <?= ($row['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
            <option value="EUR" <?= ($row['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
          </select>
          <div class="form-text">Для России используйте RUR.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Скидка</label>
          <input type="number" name="discount" class="form-control" value="<?= h($row['discount'] ?? '') ?>" step="0.01" min="0">
          <div class="form-text">Сумма скидки в той же валюте.</div>
        </div>
        <div class="col-12">
          <label class="form-label">Бесплатный приём (<code>free_appointment</code>)</label>
          <input type="text" name="free_appointment" class="form-control" value="<?= h($row['free_appointment'] ?? '') ?>" placeholder="первичный приём бесплатно">
          <div class="form-text">Текстовое описание условий бесплатного приёма, если он предусмотрен. Оставьте пустым, если платный.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Флаги</strong></div>
    <div class="card-body">
      <div class="row g-2">
        <?php
        $flags = [
          ['appointment',          'Запись (appointment)',  true,  'Врач принимает запись на приём. Снимите, если запись временно недоступна.'],
          ['online_schedule',      'Онлайн-расписание',    false, 'Доступно онлайн-расписание — пользователь может видеть свободные слоты.'],
          ['oms',                  'ОМС',                  false, 'Врач принимает по полису ОМС (обязательное медицинское страхование).'],
          ['adult_appointment',    'Взрослые',             true,  'Врач ведёт приём взрослых пациентов (от 18 лет).'],
          ['children_appointment', 'Дети',                 false, 'Врач ведёт приём детей (до 18 лет).'],
          ['house_call',           'Вызов на дом',         false, 'Врач выезжает на дом к пациенту.'],
          ['telemed',              'Телемедицина',         false, 'Врач проводит дистанционные онлайн-консультации.'],
          ['is_base_service',      'Базовая услуга',       true,  'Услуга является основной для данного врача (влияет на ранжирование в поиске Яндекса).'],
        ];
        foreach ($flags as [$key, $label, $default, $hint]):
        ?>
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="chk_<?= $key ?>"<?= chk($row, $key, $default) ?>>
            <label class="form-check-label" for="chk_<?= $key ?>"><?= $label ?></label>
          </div>
          <div class="form-text ms-4"><?= $hint ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="/offer/list.php?p=<?= urlencode($p) ?>" class="btn btn-secondary">Отмена</a>
  </div>
</form>
<?php layoutFoot(); ?>
