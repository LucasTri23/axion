<?php
/**
 * Financeiro — Jato e pintura — OWASP A01, A03, A04, A09
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();

function jatoFromPost(array $p): array {
    $kg    = toFloat($p['kg']    ?? '0');
    $vrKg  = toFloat($p['vr_kg'] ?? '1.5');
    $total = isset($p['vr_total']) && $p['vr_total'] !== '' ? toFloat($p['vr_total']) : round($kg * $vrKg, 2);
    return [
        'cliente'    => trim($p['cliente'] ?? ''),
        'produto'    => trim($p['produto'] ?? ''),
        'data_saida' => $p['data_saida'] ?: null,
        'kg'         => $kg,
        'vr_kg'      => $vrKg,
        'vr_total'   => $total,
    ];
}

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $d = jatoFromPost($_POST);
        if ($d['cliente'] !== '' && $d['data_saida']) {
            $db->prepare('INSERT INTO centro_jato_pintura (cliente, produto, data_saida, kg, vr_kg, vr_total) VALUES (?,?,?,?,?,?)')
               ->execute(array_values($d));
            auditLog('create', 'centro_jato_pintura', (int)$db->lastInsertId(), $d);
        }
        redirect('/admin/financeiro_jato.php?saved=1');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $d  = jatoFromPost($_POST);
        if ($id > 0 && $d['cliente'] !== '' && $d['data_saida']) {
            $db->prepare('UPDATE centro_jato_pintura SET cliente=?, produto=?, data_saida=?, kg=?, vr_kg=?, vr_total=? WHERE id=?')
               ->execute(array_merge(array_values($d), [$id]));
            auditLog('update', 'centro_jato_pintura', $id, $d);
        }
        redirect('/admin/financeiro_jato.php?saved=1');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_jato_pintura WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_jato_pintura WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_jato_pintura', $id, $old ?: []);
        }
        redirect('/admin/financeiro_jato.php?deleted=1');
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM centro_jato_pintura WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

$rows = $db->query('SELECT * FROM centro_jato_pintura ORDER BY data_saida DESC, id DESC')->fetchAll();

$totalGeral = 0;
$porMes = [];
foreach ($rows as $r) {
    $totalGeral += (float)$r['vr_total'];
    if ($r['data_saida']) {
        $mk = substr($r['data_saida'], 0, 7);
        $porMes[$mk] = ($porMes[$mk] ?? 0) + (float)$r['vr_total'];
    }
}
krsort($porMes);

layout_start('Financeiro — Jato e pintura', 'financeiro');
$finTab = 'jato';
require __DIR__ . '/_financeiro_nav.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Registro salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Registro excluído com sucesso.</div><?php endif ?>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-label">Faturamento total</div><div class="kpi-val o mono"><?= e(brl($totalGeral)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Registros</div><div class="kpi-val s"><?= count($rows) ?></div></div>
</div>

<div class="card">
  <div class="card-title"><?= $editRow ? '✏️ Editar registro #' . (int)$editRow['id'] : '➕ Novo registro' ?></div>
  <form method="POST" class="frow" style="flex-wrap:wrap">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif ?>
    <div class="fg"><label>Cliente</label><input type="text" name="cliente" maxlength="150" required value="<?= e($editRow['cliente'] ?? '') ?>"></div>
    <div class="fg"><label>Produto</label><input type="text" name="produto" maxlength="150" value="<?= e($editRow['produto'] ?? '') ?>"></div>
    <div class="fg"><label>Data de saída</label><input type="date" name="data_saida" required value="<?= e($editRow['data_saida'] ?? '') ?>"></div>
    <div class="fg"><label>Kg</label><input type="number" name="kg" step="0.01" min="0" required value="<?= e($editRow['kg'] ?? '') ?>"></div>
    <div class="fg"><label>Vr./Kg (R$)</label><input type="number" name="vr_kg" step="0.01" min="0" value="<?= e($editRow['vr_kg'] ?? '1.5') ?>"></div>
    <div class="fg"><label>Vr. total (R$)</label><input type="number" name="vr_total" step="0.01" min="0" value="<?= e($editRow['vr_total'] ?? '') ?>" placeholder="auto (kg × vr/kg)"></div>
    <div class="fg" style="justify-content:flex-end;display:flex;gap:8px">
      <button type="submit" class="btn btn-primary"><?= $editRow ? '💾 Salvar' : '➕ Adicionar' ?></button>
      <?php if ($editRow): ?><a href="/admin/financeiro_jato.php" class="btn btn-ghost">Cancelar</a><?php endif ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">📊 Faturamento por mês</div>
  <?php if (!$porMes): ?>
    <div class="text-muted">Sem dados ainda.</div>
  <?php else: ?>
    <?php foreach ($porMes as $mk => $val): [$y, $m] = explode('-', $mk); ?>
      <div class="hbar-row" style="grid-template-columns:90px 1fr 120px">
        <div class="lbl"><?= e($m . '/' . $y) ?></div>
        <div class="hbar-track"><div class="hbar-fill" style="width:<?= min(100, ($val / max(array_values($porMes))) * 100) ?>%;background:var(--orange)"></div></div>
        <div class="val mono"><?= e(brl($val)) ?></div>
      </div>
    <?php endforeach ?>
  <?php endif ?>
</div>

<div class="card">
  <div class="card-title">📋 Registros</div>
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon">📭</div><p>Nenhum registro ainda.</p></div>
  <?php else: ?>
    <table>
      <thead><tr><th>Data</th><th>Cliente</th><th>Produto</th><th class="num">Kg</th><th class="num">Vr/Kg</th><th class="num">Vr. Total</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="font-size:12.5px"><?= e($r['data_saida'] ? date('d/m/Y', strtotime($r['data_saida'])) : '—') ?></td>
            <td class="td-name"><?= e($r['cliente']) ?></td>
            <td style="font-size:12.5px"><?= e($r['produto'] ?: '—') ?></td>
            <td class="mono" style="text-align:right"><?= e(number_format((float)$r['kg'], 2, ',', '.')) ?></td>
            <td class="mono" style="text-align:right"><?= e(brl($r['vr_kg'])) ?></td>
            <td class="mono" style="text-align:right"><?= e(brl($r['vr_total'])) ?></td>
            <td style="white-space:nowrap">
              <a href="/admin/financeiro_jato.php?edit=<?= (int)$r['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este registro?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<?php layout_end(); ?>
