<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/layout.php';
requireAuth();

$p = trim($_GET['p'] ?? '');
if (!$p || !projectExists($p)) { header('Location: /'); exit; }

$pdo = getDb($p);
$offers = $pdo->query("
    SELECT o.*,
           d.name AS doctor_name,
           c.name AS clinic_name,
           s.name AS service_name
    FROM offers o
    LEFT JOIN doctors d ON d.id = o.doctor_id
    LEFT JOIN clinics c ON c.id = o.clinic_id
    LEFT JOIN services s ON s.id = o.service_id
    ORDER BY o.id
")->fetchAll(PDO::FETCH_ASSOC);

layoutHead('Предложения', $p);
layoutContent($p);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Предложения <span class="badge bg-secondary"><?= count($offers) ?></span></h2>
  <a href="/offer/edit.php?p=<?= urlencode($p) ?>" class="btn btn-success">+ Добавить</a>
</div>

<?php if (empty($offers)): ?>
  <div class="alert alert-info">Предложений нет.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle small">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Услуга</th>
      <th>Врач</th>
      <th>Клиника</th>
      <th>Специальность</th>
      <th>Цена</th>
      <th>Флаги</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($offers as $o): ?>
  <tr>
    <td><code><?= h($o['id']) ?></code></td>
    <td>
      <?= h($o['service_name'] ?: $o['service_id']) ?>
      <?php if ($o['service_name'] && $o['service_id']): ?>
        <br><code class="text-muted" style="font-size:.7em"><?= h($o['service_id']) ?></code>
      <?php endif; ?>
    </td>
    <td><?= h($o['doctor_name'] ?: $o['doctor_id']) ?></td>
    <td><?= h($o['clinic_name'] ?: $o['clinic_id']) ?></td>
    <td><?= h($o['speciality']) ?></td>
    <td><?= $o['base_price'] ? h($o['base_price']) . ' ' . h($o['currency']) : '—' ?></td>
    <td>
      <?php if ($o['appointment']): ?><span class="badge bg-success" title="Запись">З</span><?php endif; ?>
      <?php if ($o['online_schedule']): ?><span class="badge bg-info text-dark" title="Онлайн-расписание">О</span><?php endif; ?>
      <?php if ($o['oms']): ?><span class="badge bg-primary" title="ОМС">ОМС</span><?php endif; ?>
      <?php if ($o['telemed']): ?><span class="badge bg-warning text-dark" title="Телемедицина">Тел</span><?php endif; ?>
      <?php if ($o['children_appointment']): ?><span class="badge bg-secondary" title="Детские">Д</span><?php endif; ?>
    </td>
    <td>
      <a href="/offer/edit.php?p=<?= urlencode($p) ?>&id=<?= urlencode($o['id']) ?>" class="btn btn-outline-primary btn-sm">Ред.</a>
      <a href="/offer/delete.php?p=<?= urlencode($p) ?>&id=<?= urlencode($o['id']) ?>&_csrf=<?= csrfToken() ?>"
         class="btn btn-outline-danger btn-sm"
         onclick="return confirm('Удалить предложение «<?= h(addslashes($o['id'])) ?>»?')">Удал.</a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php layoutFoot(); ?>
