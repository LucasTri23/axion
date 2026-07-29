<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();

$total    = (int)$db->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
$novos    = (int)$db->query("SELECT COUNT(*) FROM quotes WHERE status='novo'")->fetchColumn();
$andam    = (int)$db->query("SELECT COUNT(*) FROM quotes WHERE status='andamento'")->fetchColumn();
$conc     = (int)$db->query("SELECT COUNT(*) FROM quotes WHERE status='concluido'")->fetchColumn();

$recentes = $db->query(
    "SELECT id, name, company, service, status, created_at
     FROM quotes ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

$statusLabel = [
    'novo'      => ['b-novo',      'Novo'],
    'andamento' => ['b-andamento', 'Em Andamento'],
    'concluido' => ['b-concluido', 'Concluído'],
    'cancelado' => ['b-cancelado', 'Cancelado'],
];

layout_start('Dashboard', 'index');
?>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-label">Total de Orçamentos</div><div class="kpi-val o"><?= $total ?></div></div>
  <div class="kpi"><div class="kpi-label">Novos (sem resposta)</div><div class="kpi-val s"><?= $novos ?></div></div>
  <div class="kpi"><div class="kpi-label">Em Andamento</div><div class="kpi-val y"><?= $andam ?></div></div>
  <div class="kpi"><div class="kpi-label">Concluídos</div><div class="kpi-val g"><?= $conc ?></div></div>
</div>

<div class="card">
  <div class="card-title">📋 Últimos Pedidos</div>
  <?php if (!$recentes): ?>
    <div class="empty"><div class="empty-icon">📭</div><p>Nenhum pedido ainda.</p></div>
  <?php else: ?>
    <table>
      <thead><tr>
        <th>Nome / Empresa</th>
        <th>Serviço</th>
        <th>Data</th>
        <th>Status</th>
      </tr></thead>
      <tbody>
        <?php foreach ($recentes as $q):
          [$cls, $lbl] = $statusLabel[$q['status']] ?? ['b-novo','Novo'];
        ?>
          <tr style="cursor:pointer" onclick="location.href='/admin/quotes.php?id=<?= (int)$q['id'] ?>'">
            <td>
              <div class="td-name"><?= e($q['name']) ?></div>
              <div class="td-sub"><?= e($q['company']) ?></div>
            </td>
            <td><?= e($q['service'] ?: '—') ?></td>
            <td style="font-size:12px;color:var(--gray)">
              <?= e(date('d/m/Y H:i', strtotime($q['created_at']))) ?>
            </td>
            <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <div style="margin-top:16px">
      <a href="/admin/quotes.php" class="btn btn-ghost btn-sm">Ver todos os orçamentos →</a>
    </div>
  <?php endif ?>
</div>

<?php layout_end(); ?>
