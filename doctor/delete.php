<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
requireAuth();
csrfVerifyGet();

$p = trim($_GET['p'] ?? '');
$id = trim($_GET['id'] ?? '');
if (!$p || !$id || !projectExists($p)) {
    header('Location: /');
    exit;
}

$pdo = getDb($p);
$pdo->beginTransaction();
$pdo->prepare("DELETE FROM doctor_education WHERE doctor_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM doctor_jobs WHERE doctor_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM doctor_certificates WHERE doctor_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM doctors WHERE id=?")->execute([$id]);
$pdo->commit();

header('Location: /doctor/list.php?p=' . urlencode($p));
exit;
