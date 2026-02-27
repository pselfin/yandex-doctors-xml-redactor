<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) {
    header('Location: /');
    exit;
}

$pdo = getDb($p);
$error = '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xmlfile'])) {
    csrfVerify();
    $file = $_FILES['xmlfile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла (код ' . $file['error'] . ').';
    } else {
        $content = file_get_contents($file['tmp_name']);
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET запрещает сетевые запросы из XML (XXE защита)
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERR);
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            $error = 'Не удалось разобрать XML. Убедитесь что файл корректен и соответствует формату Яндекс.Врачи.';
        } else {
            $pdo->beginTransaction();
            try {
                // --- META ---
                if (isset($_POST['import_meta'])) {
                    $metaFields = ['name', 'company', 'url', 'email', 'picture'];
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)");
                    foreach ($metaFields as $key) {
                        if (isset($xml->$key)) {
                            $stmt->execute([$key, (string)$xml->$key]);
                        }
                    }
                    // version и date из атрибутов
                    if (isset($xml['version'])) $stmt->execute(['version', (string)$xml['version']]);
                    if (isset($xml['date'])) $stmt->execute(['date', (string)$xml['date']]);
                    $log[] = 'Мета: импортированы поля name/company/url/email/picture/version.';
                }

                // --- DOCTORS ---
                if (isset($xml->doctors->doctor)) {
                    $dCount = 0;
                    $stmtD = $pdo->prepare("INSERT OR REPLACE INTO doctors
                        (id, name, first_name, surname, patronymic, url, description,
                         experience_years, career_start_date, picture, degree, rank,
                         category, reviews_total_count, sort_order)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmtDel = $pdo->prepare("DELETE FROM doctor_education WHERE doctor_id=?");
                    $stmtDelJ = $pdo->prepare("DELETE FROM doctor_jobs WHERE doctor_id=?");
                    $stmtDelC = $pdo->prepare("DELETE FROM doctor_certificates WHERE doctor_id=?");
                    $stmtEdu = $pdo->prepare("INSERT INTO doctor_education (doctor_id, organization, finish_year, type, specialization) VALUES (?,?,?,?,?)");
                    $stmtJob = $pdo->prepare("INSERT INTO doctor_jobs (doctor_id, organization, period_years, position) VALUES (?,?,?,?)");
                    $stmtCert = $pdo->prepare("INSERT INTO doctor_certificates (doctor_id, organization, finish_year, name) VALUES (?,?,?,?)");

                    foreach ($xml->doctors->doctor as $doc) {
                        $id = (string)$doc['id'];
                        if (!$id) continue;
                        $stmtD->execute([
                            $id,
                            (string)($doc->name ?? ''),
                            (string)($doc->first_name ?? ''),
                            (string)($doc->surname ?? ''),
                            (string)($doc->patronymic ?? ''),
                            (string)($doc->url ?? ''),
                            (string)($doc->description ?? ''),
                            (int)($doc->experience_years ?? 0) ?: null,
                            (string)($doc->career_start_date ?? ''),
                            (string)($doc->picture ?? ''),
                            (string)($doc->degree ?? ''),
                            (string)($doc->rank ?? ''),
                            (string)($doc->category ?? ''),
                            (int)($doc->reviews_total_count ?? 0) ?: null,
                            0
                        ]);

                        $stmtDel->execute([$id]);
                        $stmtDelJ->execute([$id]);
                        $stmtDelC->execute([$id]);

                        foreach ($doc->education as $edu) {
                            $stmtEdu->execute([
                                $id,
                                (string)($edu->organization ?? ''),
                                (int)($edu->finish_year ?? 0) ?: null,
                                (string)($edu->type ?? ''),
                                (string)($edu->specialization ?? ''),
                            ]);
                        }
                        foreach ($doc->job as $job) {
                            $stmtJob->execute([
                                $id,
                                (string)($job->organization ?? ''),
                                (string)($job->period_years ?? ''),
                                (string)($job->position ?? ''),
                            ]);
                        }
                        foreach ($doc->certificate as $cert) {
                            $stmtCert->execute([
                                $id,
                                (string)($cert->organization ?? ''),
                                (int)($cert->finish_year ?? 0) ?: null,
                                (string)($cert->name ?? ''),
                            ]);
                        }
                        $dCount++;
                    }
                    $log[] = "Врачи: импортировано $dCount записей.";
                }

                // --- CLINICS ---
                if (isset($xml->clinics->clinic)) {
                    $cCount = 0;
                    $stmtC = $pdo->prepare("INSERT OR REPLACE INTO clinics
                        (id, name, city, address, url, picture, email, phone, internal_id, company_id)
                        VALUES (?,?,?,?,?,?,?,?,?,?)");
                    foreach ($xml->clinics->clinic as $cli) {
                        $id = (string)$cli['id'];
                        if (!$id) continue;
                        $stmtC->execute([
                            $id,
                            (string)($cli->name ?? ''),
                            (string)($cli->city ?? ''),
                            (string)($cli->address ?? ''),
                            (string)($cli->url ?? ''),
                            (string)($cli->picture ?? ''),
                            (string)($cli->email ?? ''),
                            (string)($cli->phone ?? ''),
                            (string)($cli->internal_id ?? ''),
                            (string)($cli->company_id ?? ''),
                        ]);
                        $cCount++;
                    }
                    $log[] = "Клиники: импортировано $cCount записей.";
                }

                // --- SERVICES ---
                if (isset($xml->services->service)) {
                    $sCount = 0;
                    $stmtS = $pdo->prepare("INSERT OR REPLACE INTO services
                        (id, name, gov_id, description, internal_id)
                        VALUES (?,?,?,?,?)");
                    foreach ($xml->services->service as $svc) {
                        $id = (string)$svc['id'];
                        if (!$id) continue;
                        $stmtS->execute([
                            $id,
                            (string)($svc->name ?? ''),
                            (string)($svc->gov_id ?? ''),
                            (string)($svc->description ?? ''),
                            (string)($svc->internal_id ?? ''),
                        ]);
                        $sCount++;
                    }
                    $log[] = "Услуги: импортировано $sCount записей.";
                }

                // --- OFFERS ---
                if (isset($xml->offers->offer) || isset($xml->offer)) {
                    $oCount = 0;
                    $stmtO = $pdo->prepare("INSERT OR REPLACE INTO offers
                        (id, service_id, clinic_id, doctor_id, url,
                         online_schedule, appointment, oms,
                         base_price, currency, discount, free_appointment,
                         speciality, children_appointment, adult_appointment,
                         house_call, telemed, is_base_service)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

                    $offers = isset($xml->offers->offer) ? $xml->offers->offer : $xml->offer;
                    foreach ($offers as $offer) {
                        $id = (string)$offer['id'];
                        if (!$id) continue;

                        // Структура: <offer id="..."><service id="..."/><clinic id="..."><doctor id="...">...</doctor></clinic>
                        $serviceId = isset($offer->service) ? (string)$offer->service['id'] : '';
                        $clinicId = isset($offer->clinic) ? (string)$offer->clinic['id'] : '';
                        $doctorId = '';
                        $speciality = '';
                        $childrenApp = 0;
                        $adultApp = 1;
                        $isBase = 1;

                        if (isset($offer->clinic->doctor)) {
                            $doctorEl = $offer->clinic->doctor;
                            $doctorId = (string)$doctorEl['id'];
                            $speciality = (string)($doctorEl->speciality ?? '');
                            $childrenApp = xmlBool($doctorEl->children_appointment ?? null);
                            $adultApp = xmlBool($doctorEl->adult_appointment ?? null, 1);
                            $isBase = xmlBool($doctorEl->is_base_service ?? null, 1);
                        }

                        $stmtO->execute([
                            $id, $serviceId, $clinicId, $doctorId,
                            (string)($offer->url ?? ''),
                            xmlBool($offer->online_schedule ?? null),
                            xmlBool($offer->appointment ?? null, 1),
                            xmlBool($offer->oms ?? null),
                            (float)($offer->price->base_price ?? 0) ?: null,
                            (string)($offer->price->currency ?? 'RUR'),
                            (float)($offer->price->discount ?? 0) ?: null,
                            (string)($offer->free_appointment ?? ''),
                            $speciality, $childrenApp, $adultApp,
                            xmlBool($offer->house_call ?? null),
                            xmlBool($offer->telemed ?? null),
                            $isBase,
                        ]);
                        $oCount++;
                    }
                    $log[] = "Предложения: импортировано $oCount записей.";
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка импорта. Проверьте формат файла.';
                error_log('Import error: ' . $e->getMessage());
            }
        }
    }
}

function xmlBool(mixed $val, int $default = 0): int {
    if ($val === null) return $default;
    $s = strtolower(trim((string)$val));
    return in_array($s, ['true', '1', 'yes']) ? 1 : 0;
}

layoutHead('Импорт XML', $p);
layoutContent($p);
?>
<h2 class="mb-4">Импорт XML</h2>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if (!empty($log)): ?>
  <div class="alert alert-success">
    <strong>Импорт завершён:</strong>
    <ul class="mb-0 mt-1">
      <?php foreach ($log as $line): ?>
        <li><?= h($line) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width:600px">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">XML-файл (формат Яндекс.Врачи)</label>
        <input type="file" name="xmlfile" class="form-control" accept=".xml,text/xml,application/xml" required>
      </div>
      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="import_meta" id="chkMeta" checked>
          <label class="form-check-label" for="chkMeta">Импортировать метаданные (&lt;name&gt;, &lt;company&gt; и т.д.)</label>
        </div>
      </div>
      <div class="alert alert-warning py-2">
        <strong>Внимание:</strong> существующие записи с совпадающими ID будут заменены (INSERT OR REPLACE).
      </div>
      <button type="submit" class="btn btn-primary">Импортировать</button>
      <a href="/project.php?p=<?= urlencode($p) ?>" class="btn btn-secondary ms-2">Отмена</a>
    </form>
  </div>
</div>
<?php
layoutFoot();
