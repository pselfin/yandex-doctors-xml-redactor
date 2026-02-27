<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
requireAuth();
csrfVerifyGet();

$p  = trim($_GET['p'] ?? '');
$id = (int)($_GET['id'] ?? 0);
if (!$p || !$id || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);

// Не удалять если используется в офферах
$name = $pdo->prepare("SELECT name FROM specialties WHERE id=?");
$name->execute([$id]);
$row = $name->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $used = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE speciality=?");
    $used->execute([$row['name']]);
    if ($used->fetchColumn() == 0) {
        $pdo->prepare("DELETE FROM specialties WHERE id=?")->execute([$id]);
    }
}

header('Location: /specialty/list.php?p=' . urlencode($p));
exit;
