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

// Загрузка существующего врача
$doc = [];
$education = [];
$jobs = [];
$certificates = [];

if (!$isNew) {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id=?");
    $stmt->execute([$editId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) { header('Location: /doctor/list.php?p=' . urlencode($p)); exit; }

    $stmtE = $pdo->prepare("SELECT * FROM doctor_education WHERE doctor_id=? ORDER BY id");
    $stmtE->execute([$editId]);
    $education = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    $stmtJ = $pdo->prepare("SELECT * FROM doctor_jobs WHERE doctor_id=? ORDER BY id");
    $stmtJ->execute([$editId]);
    $jobs = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    $stmtC = $pdo->prepare("SELECT * FROM doctor_certificates WHERE doctor_id=? ORDER BY id");
    $stmtC->execute([$editId]);
    $certificates = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // Офферы врача для панели внизу формы
    $stmtO = $pdo->prepare("
        SELECT o.id, o.clinic_id, o.service_id, o.speciality, o.base_price, o.currency,
               o.oms, o.online_schedule, o.telemed, o.house_call,
               c.name AS clinic_name, s.name AS service_name
        FROM offers o
        LEFT JOIN clinics c ON c.id = o.clinic_id
        LEFT JOIN services s ON s.id = o.service_id
        WHERE o.doctor_id = ?
        ORDER BY c.name, s.name
    ");
    $stmtO->execute([$editId]);
    $docOffers = $stmtO->fetchAll(PDO::FETCH_ASSOC);
} else {
    $docOffers = [];
}

// POST: сохранение
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = trim($_POST['id'] ?? '');
    $id = preg_replace('/[^a-z0-9_-]/i', '', $id);

    if ($id === '') {
        $error = 'ID врача обязателен (только латиница, цифры, дефис, подчёркивание).';
    } elseif ($isNew) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE id=?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) $error = 'Врач с таким ID уже существует.';
    }

    if (!$error) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO doctors
                (id, name, first_name, surname, patronymic, url, description,
                 experience_years, career_start_date, picture, degree, rank,
                 category, reviews_total_count, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $id,
                trim($_POST['name'] ?? ''),
                trim($_POST['first_name'] ?? ''),
                trim($_POST['surname'] ?? ''),
                trim($_POST['patronymic'] ?? ''),
                trim($_POST['url'] ?? ''),
                trim($_POST['description'] ?? ''),
                (int)($_POST['experience_years'] ?? 0) ?: null,
                trim($_POST['career_start_date'] ?? ''),
                trim($_POST['picture'] ?? ''),
                trim($_POST['degree'] ?? ''),
                trim($_POST['rank'] ?? ''),
                trim($_POST['category'] ?? ''),
                (int)($_POST['reviews_total_count'] ?? 0) ?: null,
                (int)($_POST['sort_order'] ?? 0),
            ]);

            // Education
            $pdo->prepare("DELETE FROM doctor_education WHERE doctor_id=?")->execute([$id]);
            $stmtE = $pdo->prepare("INSERT INTO doctor_education (doctor_id, organization, finish_year, type, specialization) VALUES (?,?,?,?,?)");
            foreach (($_POST['edu_org'] ?? []) as $i => $org) {
                $org = trim($org);
                if ($org === '' && empty($_POST['edu_year'][$i])) continue;
                $stmtE->execute([
                    $id, $org,
                    (int)($_POST['edu_year'][$i] ?? 0) ?: null,
                    trim($_POST['edu_type'][$i] ?? ''),
                    trim($_POST['edu_spec'][$i] ?? ''),
                ]);
            }

            // Jobs
            $pdo->prepare("DELETE FROM doctor_jobs WHERE doctor_id=?")->execute([$id]);
            $stmtJ = $pdo->prepare("INSERT INTO doctor_jobs (doctor_id, organization, period_years, position) VALUES (?,?,?,?)");
            foreach (($_POST['job_org'] ?? []) as $i => $org) {
                $org = trim($org);
                if ($org === '') continue;
                $stmtJ->execute([
                    $id, $org,
                    trim($_POST['job_period'][$i] ?? ''),
                    trim($_POST['job_pos'][$i] ?? ''),
                ]);
            }

            // Certificates
            $pdo->prepare("DELETE FROM doctor_certificates WHERE doctor_id=?")->execute([$id]);
            $stmtC = $pdo->prepare("INSERT INTO doctor_certificates (doctor_id, organization, finish_year, name) VALUES (?,?,?,?)");
            foreach (($_POST['cert_org'] ?? []) as $i => $org) {
                $org = trim($org);
                if ($org === '' && empty($_POST['cert_name'][$i])) continue;
                $stmtC->execute([
                    $id, $org,
                    (int)($_POST['cert_year'][$i] ?? 0) ?: null,
                    trim($_POST['cert_name'][$i] ?? ''),
                ]);
            }

            $pdo->commit();
            header('Location: /doctor/list.php?p=' . urlencode($p));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
    // При ошибке — восстановить введённые данные
    $doc = $_POST;
    $doc['id'] = $id ?? '';
}

// JSON для Alpine.js — JSON_HEX_TAG защищает от </script> в данных
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
$eduJson  = json_encode(array_values(array_map(fn($e) => [
    'org'  => $e['organization'] ?? '',
    'year' => (string)($e['finish_year'] ?? ''),
    'type' => $e['type'] ?? '',
    'spec' => $e['specialization'] ?? '',
], $education)), $flags);
$jobJson  = json_encode(array_values(array_map(fn($j) => [
    'org'    => $j['organization'] ?? '',
    'period' => $j['period_years'] ?? '',
    'pos'    => $j['position'] ?? '',
], $jobs)), $flags);
$certJson = json_encode(array_values(array_map(fn($c) => [
    'org'  => $c['organization'] ?? '',
    'year' => (string)($c['finish_year'] ?? ''),
    'name' => $c['name'] ?? '',
], $certificates)), $flags);

layoutHead(($isNew ? 'Добавить врача' : 'Редактировать: ' . ($doc['name'] ?? '')), $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><?= $isNew ? 'Добавить врача' : ('Врач: ' . h($doc['name'] ?? '')) ?></h2>
  <a href="/doctor/list.php?p=<?= urlencode($p) ?>" class="btn btn-outline-secondary">← Список</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<script>
function doctorFormData() {
    return {
        education:    <?= $eduJson ?>,
        jobs:         <?= $jobJson ?>,
        certificates: <?= $certJson ?>,
        name:     <?= json_encode($doc['name']    ?? '', $flags) ?>,
        surname:  <?= json_encode($doc['surname'] ?? '', $flags) ?>,
        idValue:  <?= json_encode($doc['id']      ?? '', $flags) ?>,
        idManual: <?= (!$isNew || ($doc['id'] ?? '') !== '') ? 'true' : 'false' ?>,
        openEdu:   <?= !empty($education)    ? 'true' : 'false' ?>,
        openJobs:  <?= !empty($jobs)         ? 'true' : 'false' ?>,
        openCerts: <?= !empty($certificates) ? 'true' : 'false' ?>,
        translit(str) {
            const map = {
                'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh',
                'з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o',
                'п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts',
                'ч':'ch','ш':'sh','щ':'shch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
            };
            return str.toLowerCase().split('').map(c => map[c] ?? c).join('');
        },
        suggestedId() {
            const base = this.surname || (this.name ? this.name.split(' ')[0] : '');
            return this.translit(base).replace(/[^a-z0-9_-]/g, '');
        },
        syncId() {
            if (!this.idManual) this.idValue = this.suggestedId();
        },
        addEdu()      { this.openEdu   = true; this.education.push({org:'',year:'',type:'',spec:''}) },
        removeEdu(i)  { this.education.splice(i,1) },
        addJob()      { this.openJobs  = true; this.jobs.push({org:'',period:'',pos:''}) },
        removeJob(i)  { this.jobs.splice(i,1) },
        addCert()     { this.openCerts = true; this.certificates.push({org:'',year:'',name:''}) },
        removeCert(i) { this.certificates.splice(i,1) }
    };
}
</script>

<form method="post" x-data="doctorFormData()" x-init="syncId()">
<?= csrfField() ?>

  <!-- Основные поля -->
  <div class="card mb-3">
    <div class="card-header"><strong>Основные данные</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">ID <span class="text-danger">*</span></label>
          <input type="text" name="id" class="form-control font-monospace"
                 x-model="idValue"
                 @input="idManual = true"
                 <?= $isNew ? '' : 'readonly' ?>
                 pattern="[a-zA-Z0-9_-]+" required
                 placeholder="timchenko">
          <?php if ($isNew): ?>
            <div class="form-text">Генерируется из фамилии. Можно изменить вручную — только латиница, цифры, дефис, подчёркивание.</div>
          <?php else: ?>
            <div class="form-text text-warning">ID нельзя изменить после создания.</div>
          <?php endif; ?>
        </div>
        <div class="col-md-8">
          <label class="form-label">Полное ФИО <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" x-model="name" @input="syncId()" required placeholder="Иванов Иван Иванович">
        </div>
        <div class="col-md-4">
          <label class="form-label">Имя (<code>first_name</code>)</label>
          <input type="text" name="first_name" class="form-control" value="<?= h($doc['first_name'] ?? '') ?>" placeholder="Иван">
          <div class="form-text">Только имя, без фамилии и отчества.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Фамилия (<code>surname</code>)</label>
          <input type="text" name="surname" class="form-control" x-model="surname" @input="syncId()" placeholder="Иванов">
        </div>
        <div class="col-md-4">
          <label class="form-label">Отчество (<code>patronymic</code>)</label>
          <input type="text" name="patronymic" class="form-control" value="<?= h($doc['patronymic'] ?? '') ?>" placeholder="Иванович">
          <div class="form-text">Заполните все три поля — Яндекс использует их для фильтрации.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">URL страницы врача</label>
          <input type="url" name="url" class="form-control" value="<?= h($doc['url'] ?? '') ?>"
                 placeholder="https://example.ru/doctors/ivanov">
          <div class="form-text">Полная ссылка на страницу врача, начиная с <code>https://</code>.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">URL фото (<code>picture</code>)</label>
          <input type="url" name="picture" class="form-control" value="<?= h($doc['picture'] ?? '') ?>"
                 placeholder="https://example.ru/img/doctor.jpg">
          <div class="form-text">Прямая ссылка на фото. Рекомендуемый размер — от 300×300 пикселей.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Стаж (лет)</label>
          <input type="number" name="experience_years" class="form-control"
                 value="<?= h($doc['experience_years'] ?? '') ?>" min="0" max="80" placeholder="10">
          <div class="form-text">Полных лет практики.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Начало карьеры</label>
          <input type="text" name="career_start_date" class="form-control"
                 value="<?= h($doc['career_start_date'] ?? '') ?>"
                 placeholder="1996-01-01"
                 pattern="\d{4}-\d{2}-\d{2}"
                 title="Формат: ГГГГ-ММ-ДД">
          <div class="form-text">Формат ISO 8601: <strong>ГГГГ-ММ-ДД</strong>.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Учёная степень</label>
          <input type="text" name="degree" class="form-control"
                 value="<?= h($doc['degree'] ?? '') ?>"
                 list="degree-list" placeholder="доктор наук">
          <datalist id="degree-list">
            <option value="кандидат наук">
            <option value="доктор наук">
          </datalist>
          <div class="form-text">Кандидат или доктор наук.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Звание</label>
          <input type="text" name="rank" class="form-control"
                 value="<?= h($doc['rank'] ?? '') ?>"
                 list="rank-list" placeholder="профессор">
          <datalist id="rank-list">
            <option value="доцент">
            <option value="профессор">
          </datalist>
          <div class="form-text">Профессор или доцент.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Категория</label>
          <input type="text" name="category" class="form-control"
                 value="<?= h($doc['category'] ?? '') ?>"
                 list="category-list" placeholder="высшая">
          <datalist id="category-list">
            <option value="первая">
            <option value="вторая">
            <option value="высшая">
          </datalist>
          <div class="form-text">Первая, вторая или высшая.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Отзывы (кол-во)</label>
          <input type="number" name="reviews_total_count" class="form-control"
                 value="<?= h($doc['reviews_total_count'] ?? '') ?>" min="0">
          <div class="form-text">Общее число отзывов, доступных по URL врача.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Порядок сортировки</label>
          <input type="number" name="sort_order" class="form-control" value="<?= h($doc['sort_order'] ?? 0) ?>">
          <div class="form-text">Меньшее число — выше в списке. По умолчанию 0.</div>
        </div>
        <div class="col-12">
          <label class="form-label">Описание</label>
          <textarea name="description" class="form-control" rows="3"><?= h($doc['description'] ?? '') ?></textarea>
          <div class="form-text">Краткое описание специализации и опыта врача. Отображается в карточке Яндекса.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Образование -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none text-dark"
              @click="openEdu = !openEdu">
        Образование
        <span class="text-muted fw-normal small ms-1" x-show="education.length" x-text="'(' + education.length + ')'"></span>
        <span class="ms-1 small" x-text="openEdu ? '▲' : '▼'"></span>
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" @click="addEdu()">+ Добавить</button>
    </div>
    <div class="card-body p-0" x-show="openEdu">
      <template x-for="(edu, i) in education" :key="i">
        <div class="border-bottom p-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-6">
              <label class="form-label small mb-1">Организация</label>
              <input type="text" :name="'edu_org['+i+']'" class="form-control form-control-sm"
                     x-model="edu.org" placeholder="Кубанская медицинская академия">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Год окончания</label>
              <input type="number" :name="'edu_year['+i+']'" class="form-control form-control-sm"
                     x-model="edu.year" placeholder="1996" min="1900" max="2099">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Тип <span class="text-muted fw-normal">(необяз.)</span></label>
              <input type="text" :name="'edu_type['+i+']'" class="form-control form-control-sm"
                     x-model="edu.type" placeholder="Специалитет"
                     title="Например: Специалитет, Ординатура, Интернатура, Аспирантура">
            </div>
            <div class="col-md-1">
              <label class="form-label small mb-1">Специализация <span class="text-muted fw-normal">(необяз.)</span></label>
              <input type="text" :name="'edu_spec['+i+']'" class="form-control form-control-sm"
                     x-model="edu.spec" placeholder="Стоматология"
                     title="Специальность по диплому, например: Лечебное дело, Стоматология">
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-outline-danger btn-sm" @click="removeEdu(i)">✕</button>
            </div>
          </div>
        </div>
      </template>
      <template x-if="education.length === 0">
        <div class="p-3 text-muted small">Нет записей. Нажмите «+ Добавить».</div>
      </template>
    </div>
  </div>

  <!-- Места работы -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none text-dark"
              @click="openJobs = !openJobs">
        Места работы
        <span class="text-muted fw-normal small ms-1" x-show="jobs.length" x-text="'(' + jobs.length + ')'"></span>
        <span class="ms-1 small" x-text="openJobs ? '▲' : '▼'"></span>
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" @click="addJob()">+ Добавить</button>
    </div>
    <div class="card-body p-0" x-show="openJobs">
      <template x-for="(job, i) in jobs" :key="i">
        <div class="border-bottom p-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-7">
              <label class="form-label small mb-1">Организация</label>
              <input type="text" :name="'job_org['+i+']'" class="form-control form-control-sm"
                     x-model="job.org" placeholder="Стоматологический центр">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Период</label>
              <input type="text" :name="'job_period['+i+']'" class="form-control form-control-sm"
                     x-model="job.period" placeholder="2000-2010"
                     title="Формат: 2000-2010 или 2015-н.в.">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Должность <span class="text-muted fw-normal">(необяз.)</span></label>
              <input type="text" :name="'job_pos['+i+']'" class="form-control form-control-sm"
                     x-model="job.pos" placeholder="врач-стоматолог"
                     title="Например: врач-стоматолог, заведующий отделением">
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-outline-danger btn-sm" @click="removeJob(i)">✕</button>
            </div>
          </div>
        </div>
      </template>
      <template x-if="jobs.length === 0">
        <div class="p-3 text-muted small">Нет записей. Нажмите «+ Добавить».</div>
      </template>
    </div>
  </div>

  <!-- Сертификаты -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none text-dark"
              @click="openCerts = !openCerts">
        Сертификаты
        <span class="text-muted fw-normal small ms-1" x-show="certificates.length" x-text="'(' + certificates.length + ')'"></span>
        <span class="ms-1 small" x-text="openCerts ? '▲' : '▼'"></span>
      </button>
      <button type="button" class="btn btn-outline-success btn-sm" @click="addCert()">+ Добавить</button>
    </div>
    <div class="card-body p-0" x-show="openCerts">
      <template x-for="(cert, i) in certificates" :key="i">
        <div class="border-bottom p-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label small mb-1">Название сертификата</label>
              <input type="text" :name="'cert_name['+i+']'" class="form-control form-control-sm"
                     x-model="cert.name"
                     placeholder="Имплантология и хирургия"
                     title="Название курса повышения квалификации или сертификата">
            </div>
            <div class="col-md-5">
              <label class="form-label small mb-1">Организация</label>
              <input type="text" :name="'cert_org['+i+']'" class="form-control form-control-sm"
                     x-model="cert.org"
                     placeholder="Московский медицинский университет"
                     title="Организация, выдавшая сертификат">
            </div>
            <div class="col-md-1">
              <label class="form-label small mb-1">Год</label>
              <input type="number" :name="'cert_year['+i+']'" class="form-control form-control-sm"
                     x-model="cert.year" min="1900" max="2099"
                     title="Год получения сертификата">
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-outline-danger btn-sm" @click="removeCert(i)">✕</button>
            </div>
          </div>
        </div>
      </template>
      <template x-if="certificates.length === 0">
        <div class="p-3 text-muted small">Нет записей. Нажмите «+ Добавить».</div>
      </template>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="/doctor/list.php?p=<?= urlencode($p) ?>" class="btn btn-secondary">Отмена</a>
  </div>
</form>

<?php if (!$isNew): ?>
<div class="card mt-4" style="max-width:900px">
  <div class="card-header d-flex justify-content-between align-items-center">
    <button class="btn btn-link p-0 fw-semibold text-decoration-none text-dark <?= empty($docOffers) ? 'collapsed' : '' ?>"
            type="button" data-bs-toggle="collapse" data-bs-target="#offersPanel">
      Офферы врача
      <span class="text-muted fw-normal small ms-1">(<?= count($docOffers) ?>)</span>
    </button>
    <a href="/offer/edit.php?p=<?= urlencode($p) ?>&doctor_id=<?= urlencode($editId) ?>"
       class="btn btn-outline-success btn-sm">+ Добавить оффер</a>
  </div>
  <div class="collapse <?= !empty($docOffers) ? 'show' : '' ?>" id="offersPanel">
  <?php if (empty($docOffers)): ?>
    <div class="card-body text-muted small">
      Нет офферов. Оффер связывает врача с клиникой и услугой — добавьте первый.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Клиника</th>
            <th>Услуга</th>
            <th>Специальность</th>
            <th>Цена</th>
            <th>Флаги</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docOffers as $off): ?>
          <tr>
            <td class="font-monospace small"><?= h($off['id']) ?></td>
            <td><?= h($off['clinic_name'] ?: $off['clinic_id']) ?></td>
            <td><?= h($off['service_name'] ?: $off['service_id']) ?></td>
            <td><?= h($off['speciality']) ?></td>
            <td class="text-nowrap">
              <?= $off['base_price'] !== null ? h($off['base_price']) . ' ' . h($off['currency']) : '—' ?>
            </td>
            <td>
              <?php if ($off['oms']): ?><span class="badge bg-info text-dark">ОМС</span> <?php endif; ?>
              <?php if ($off['online_schedule']): ?><span class="badge bg-success">онлайн</span> <?php endif; ?>
              <?php if ($off['telemed']): ?><span class="badge bg-secondary">телемед</span> <?php endif; ?>
              <?php if ($off['house_call']): ?><span class="badge bg-warning text-dark">дом</span> <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a href="/offer/edit.php?p=<?= urlencode($p) ?>&id=<?= urlencode($off['id']) ?>"
                 class="btn btn-outline-primary btn-sm py-0">✎</a>
              <a href="/offer/delete.php?p=<?= urlencode($p) ?>&id=<?= urlencode($off['id']) ?>&_csrf=<?= csrfToken() ?>"
                 class="btn btn-outline-danger btn-sm py-0"
                 onclick="return confirm('Удалить оффер «<?= h(addslashes($off['id'])) ?>»?')">✕</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div><!-- /collapse -->
</div>
<?php endif; ?>

<?php
layoutFoot();
