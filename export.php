<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) {
    header('Location: /');
    exit;
}

$pdo = getDb($p);

// Загрузка мета
$meta = [];
foreach ($pdo->query("SELECT key, value FROM meta") as $row) {
    $meta[$row['key']] = $row['value'];
}

$dom = new DOMDocument('1.0', 'utf-8');
$dom->formatOutput = true;
$dom->standalone = true;

$date = date('Y-m-d H:i');

// <shop>
$shop = $dom->createElement('shop');
$shop->setAttribute('version', $meta['version'] ?? '2.0');
$shop->setAttribute('date', $date);
$dom->appendChild($shop);

function addText(DOMDocument $dom, DOMElement $parent, string $tag, string $text): void {
    if ($text === '') return;
    $el = $dom->createElement($tag);
    $el->appendChild($dom->createTextNode($text));
    $parent->appendChild($el);
}


// Мета
addText($dom, $shop, 'name', $meta['name'] ?? '');
addText($dom, $shop, 'company', $meta['company'] ?? '');
addText($dom, $shop, 'url', $meta['url'] ?? '');
addText($dom, $shop, 'email', $meta['email'] ?? '');
addText($dom, $shop, 'picture', $meta['picture'] ?? '');

// --- DOCTORS ---
$doctors = $pdo->query("SELECT * FROM doctors ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($doctors)) {
    $doctorsEl = $dom->createElement('doctors');
    $shop->appendChild($doctorsEl);

    $stmtEdu = $pdo->prepare("SELECT * FROM doctor_education WHERE doctor_id=? ORDER BY id");
    $stmtJob = $pdo->prepare("SELECT * FROM doctor_jobs WHERE doctor_id=? ORDER BY id");
    $stmtCert = $pdo->prepare("SELECT * FROM doctor_certificates WHERE doctor_id=? ORDER BY id");

    foreach ($doctors as $doc) {
        $docEl = $dom->createElement('doctor');
        $docEl->setAttribute('id', $doc['id']);
        $doctorsEl->appendChild($docEl);

        $strFields = ['name', 'url', 'first_name', 'surname', 'patronymic',
                      'picture', 'description', 'degree', 'rank', 'category'];
        foreach ($strFields as $f) {
            if (!empty($doc[$f])) addText($dom, $docEl, $f, $doc[$f]);
        }
        if (!empty($doc['experience_years'])) {
            addText($dom, $docEl, 'experience_years', (string)$doc['experience_years']);
        }
        if (!empty($doc['career_start_date'])) {
            addText($dom, $docEl, 'career_start_date', $doc['career_start_date']);
        }
        if (!empty($doc['reviews_total_count'])) {
            addText($dom, $docEl, 'reviews_total_count', (string)$doc['reviews_total_count']);
        }

        // Education
        $stmtEdu->execute([$doc['id']]);
        foreach ($stmtEdu->fetchAll(PDO::FETCH_ASSOC) as $edu) {
            $eduEl = $dom->createElement('education');
            $docEl->appendChild($eduEl);
            addText($dom, $eduEl, 'organization', $edu['organization'] ?? '');
            if (!empty($edu['finish_year'])) addText($dom, $eduEl, 'finish_year', (string)$edu['finish_year']);
            if (!empty($edu['type'])) addText($dom, $eduEl, 'type', $edu['type']);
            if (!empty($edu['specialization'])) addText($dom, $eduEl, 'specialization', $edu['specialization']);
        }

        // Jobs
        $stmtJob->execute([$doc['id']]);
        foreach ($stmtJob->fetchAll(PDO::FETCH_ASSOC) as $job) {
            $jobEl = $dom->createElement('job');
            $docEl->appendChild($jobEl);
            addText($dom, $jobEl, 'organization', $job['organization'] ?? '');
            if (!empty($job['period_years'])) addText($dom, $jobEl, 'period_years', $job['period_years']);
            if (!empty($job['position'])) addText($dom, $jobEl, 'position', $job['position']);
        }

        // Certificates
        $stmtCert->execute([$doc['id']]);
        foreach ($stmtCert->fetchAll(PDO::FETCH_ASSOC) as $cert) {
            $certEl = $dom->createElement('certificate');
            $docEl->appendChild($certEl);
            addText($dom, $certEl, 'organization', $cert['organization'] ?? '');
            if (!empty($cert['finish_year'])) addText($dom, $certEl, 'finish_year', (string)$cert['finish_year']);
            if (!empty($cert['name'])) addText($dom, $certEl, 'name', $cert['name']);
        }
    }
}

// --- CLINICS ---
$clinics = $pdo->query("SELECT * FROM clinics ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($clinics)) {
    $clinicsEl = $dom->createElement('clinics');
    $shop->appendChild($clinicsEl);
    foreach ($clinics as $cli) {
        $cliEl = $dom->createElement('clinic');
        $cliEl->setAttribute('id', $cli['id']);
        $clinicsEl->appendChild($cliEl);
        foreach (['name', 'city', 'address', 'url', 'picture', 'email', 'phone', 'internal_id', 'company_id'] as $f) {
            if (!empty($cli[$f])) addText($dom, $cliEl, $f, $cli[$f]);
        }
    }
}

// --- SERVICES ---
$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($services)) {
    $servicesEl = $dom->createElement('services');
    $shop->appendChild($servicesEl);
    foreach ($services as $svc) {
        $svcEl = $dom->createElement('service');
        $svcEl->setAttribute('id', $svc['id']);
        $servicesEl->appendChild($svcEl);
        foreach (['name', 'gov_id', 'description', 'internal_id'] as $f) {
            if (!empty($svc[$f])) addText($dom, $svcEl, $f, $svc[$f]);
        }
    }
}

// --- OFFERS ---
$offers = $pdo->query("SELECT * FROM offers ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($offers)) {
    $offersEl = $dom->createElement('offers');
    $shop->appendChild($offersEl);
}
foreach ($offers as $off) {
    $offEl = $dom->createElement('offer');
    $offEl->setAttribute('id', $off['id']);
    $offersEl->appendChild($offEl);

    if (!empty($off['url'])) addText($dom, $offEl, 'url', $off['url']);
    addText($dom, $offEl, 'appointment', boolStr($off['appointment']));
    addText($dom, $offEl, 'online_schedule', boolStr($off['online_schedule']));
    if ($off['oms']) addText($dom, $offEl, 'oms', 'true');

    // Price (free_appointment — внутри <price>, как требует Яндекс)
    if (!empty($off['base_price'])) {
        $priceEl = $dom->createElement('price');
        $offEl->appendChild($priceEl);
        addText($dom, $priceEl, 'base_price', (string)$off['base_price']);
        addText($dom, $priceEl, 'currency', $off['currency'] ?: 'RUR');
        if (!empty($off['discount'])) addText($dom, $priceEl, 'discount', (string)$off['discount']);
        if (!empty($off['free_appointment'])) addText($dom, $priceEl, 'free_appointment', $off['free_appointment']);
    }

    // Service ref
    if (!empty($off['service_id'])) {
        $svcRef = $dom->createElement('service');
        $svcRef->setAttribute('id', $off['service_id']);
        $offEl->appendChild($svcRef);
    }

    // Clinic > Doctor
    // house_call и telemed — внутри <doctor>, как требует Яндекс
    if (!empty($off['clinic_id'])) {
        $cliRef = $dom->createElement('clinic');
        $cliRef->setAttribute('id', $off['clinic_id']);
        $offEl->appendChild($cliRef);

        if (!empty($off['doctor_id'])) {
            $docRef = $dom->createElement('doctor');
            $docRef->setAttribute('id', $off['doctor_id']);
            $cliRef->appendChild($docRef);

            if (!empty($off['speciality'])) addText($dom, $docRef, 'speciality', $off['speciality']);
            addText($dom, $docRef, 'adult_appointment', boolStr($off['adult_appointment']));
            addText($dom, $docRef, 'children_appointment', boolStr($off['children_appointment']));
            if ($off['house_call']) addText($dom, $docRef, 'house_call', 'true');
            if ($off['telemed'])    addText($dom, $docRef, 'telemed', 'true');
            addText($dom, $docRef, 'is_base_service', boolStr($off['is_base_service']));
        }
    }
}

$xml = $dom->saveXML();

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $p . '-' . date('Y-m-d') . '.xml"');
header('Content-Length: ' . strlen($xml));
echo $xml;
