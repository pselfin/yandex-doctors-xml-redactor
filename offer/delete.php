<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
requireAuth();
csrfVerifyGet();

$p = trim($_GET['p'] ?? '');
$id = trim($_GET['id'] ?? '');
if (!$p || !$id || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$pdo->prepare("DELETE FROM offers WHERE id=?")->execute([$id]);
header('Location: /offer/list.php?p=' . urlencode($p));
exit;
