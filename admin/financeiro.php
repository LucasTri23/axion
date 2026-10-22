<?php
/**
 * Financeiro — Visão geral — OWASP A01, A03, A04, A09
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_financeiro_filtros.php';

$db = db();

// ── Ações POST (custos fixos) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_fixo') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor     = toFloat($_POST['valor'] ?? '0');
        if ($descricao !== '') {
            $db->prepare('INSERT INTO centro_custos_fixos (descricao, valor) VALUES (?,?)')
               ->execute([$descricao, $valor]);
            auditLog('create', 'centro_custos_fixos', (int)$db->lastInsertId(), ['descricao' => $descricao, 'valor' => $valor]);
        }
        redirect('/admin/financeiro.php?saved=1');
    }

    if ($action === 'edit_fixo') {
        $id        = (int)($_POST['id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $valor     = toFloat($_POST['valor'] ?? '0');
        if ($id > 0 && $descricao !== '') {
            $db->prepare('UPDATE centro_custos_fixos SET descricao=?, valor=? WHERE id=?')
               ->execute([$descricao, $valor, $id]);
            auditLog('update', 'centro_custos_fixos', $id, ['descricao' => $descricao, 'valor' => $valor]);
        }
        redirect('/admin/financeiro.php?saved=1');
    }

    if ($action === 'delete_fixo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $db->prepare('SELECT * FROM centro_custos_fixos WHERE id=?');
            $row->execute([$id]);
            $data = $row->fetch();
            $db->prepare('DELETE FROM centro_custos_fixos WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_custos_fixos', $id, $data ?: []);
        }
        redirect('/admin/financeiro.php?deleted=1');
    }
}

// ── KPIs gerais ──────────────────────────────────────────────────
$filters = financeiroFiltros(['mes' => $_GET['mes'] ?? '', 'os' => $_GET['os'] ?? '']);
[$whereSql, $params] = financeiroWhere($filters);
$obrasFiltro = financeiroObras($db);
$stmt = $db->prepare("SELECT COALESCE(SUM(valor_entrada),0) AS entrada, COALESCE(SUM(valor_saida),0) AS saida FROM centro_lancamentos $whereSql");
$stmt->execute($params);
$tot = $stmt->fetch();
$totalEntrada = (float)$tot['entrada'];
$totalSaida = (float)$tot['saida'];
$folhaWhere = $filters['mes'] !== '' ? 'WHERE p.data_periodo >= ? AND p.data_periodo < DATE_ADD(?, INTERVAL 1 MONTH)' : '';
$stmt = $db->prepare("SELECT COALESCE(SUM(pg.valor + pg.vale_transporte),0) FROM centro_folha_pagamentos pg JOIN centro_folha_periodos p ON p.id=pg.periodo_id $folhaWhere");
$stmt->execute($folhaWhere ? [$filters['mes'] . '-01', $filters['mes'] . '-01'] : []);
$totalFolha = (float)$stmt->fetchColumn();
$resultado    = $totalEntrada - $totalSaida;

// ── Maiores categorias de saída ─────────────────────────────────
$expenseWhere = $whereSql ? $whereSql . ' AND' : 'WHERE';
$stmt = $db->prepare(
    "SELECT descricao AS lbl, SUM(valor_saida) AS total
     FROM centro_lancamentos $expenseWhere tipo='SAIDA' AND valor_saida > 0
     GROUP BY descricao ORDER BY total DESC LIMIT 8"
);
$stmt->execute($params);
$categorias = $stmt->fetchAll();

// ── Maiores fornecedores ─────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT cliente_fornecedor AS lbl, SUM(valor_saida) AS total
     FROM centro_lancamentos $expenseWhere tipo='SAIDA' AND valor_saida > 0 AND cliente_fornecedor <> ''
     GROUP BY cliente_fornecedor ORDER BY total DESC LIMIT 8"
);
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

// ── Custos fixos ───────────────────────────────────────────────────
$custosFixos = $db->query("SELECT * FROM centro_custos_fixos ORDER BY id")->fetchAll();

// ── Auditoria recente ────────────────────────────────────────────
$auditoria = $db->query(
    "SELECT * FROM centro_audit_log ORDER BY created_at DESC LIMIT 20"
)->fetchAll();

$acaoLabel = ['create' => '➕ criou', 'update' => '✏️ editou', 'delete' => '🗑 excluiu'];

function hbar($items, $color) {
    if (!$items) { echo '<div class="text-muted">Sem dados ainda.</div>'; return; }
    $max = max(array_map(fn($i) => (float)$i['total'], $items)) ?: 1;
    foreach ($items as $it) {
        $pct = max(((float)$it['total'] / $max) * 100, 3);
        echo '<div class="hbar-row">';
        echo '<div class="lbl" title="' . e($it['lbl']) . '">' . e($it['lbl']) . '</div>';
        echo '<div class="hbar-track"><div class="hbar-fill" style="width:' . $pct . '%;background:' . $color . '"></div></div>';
        echo '<div class="val mono">' . e(brl($it['total'])) . '</div>';
        echo '</div>';
    }
}

layout_start('Financeiro — Visão geral', 'financeiro');
$finTab = 'geral';
require __DIR__ . '/_financeiro_nav.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Excluído com sucesso.</div><?php endif ?>
<?php if (isset($_GET['imported'])): ?><div class="alert alert-success">Dados iniciais da planilha importados com sucesso.</div><?php endif ?>

<?php require __DIR__ . '/_financeiro_rotina_painel.php'; ?>

<div class="card">
  <form method="GET" class="frow">
    <div class="fg"><label>Mês de emissão</label><input type="month" name="mes" value="<?= e($filters['mes']) ?>"></div>
    <div class="fg"><label>Obra / OS</label><select name="os"><option value="">Todas as obras</option><?php foreach ($obrasFiltro as $obra): ?><option value="<?= e($obra['os']) ?>" <?= $filters['os'] === $obra['os'] ? 'selected' : '' ?>><?= e($obra['os'] . ' — ' . ($obra['nome'] ?: 'Sem nome')) ?></option><?php endforeach ?></select></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-ghost" href="/admin/financeiro.php">Limpar</a><a class="btn btn-secondary" href="/admin/financeiro_exportar.php?<?= e(financeiroQuery($filters)) ?>">Exportar Excel (.xlsx)</a></div>
  </form>
  <p class="text-muted" style="margin-top:12px">Entradas, saídas e gráficos seguem o filtro. A folha considera o mês e todas as obras; custos fixos mostram o cadastro mensal completo.</p>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-label">Total de Entradas</div><div class="kpi-val g mono"><?= e(brl($totalEntrada)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Total de Saídas</div><div class="kpi-val" style="color:var(--red)"><?= e(brl($totalSaida)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Resultado</div><div class="kpi-val" style="color:<?= $resultado >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= e(brl($resultado)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Folha (todas as obras<?= $filters['mes'] ? ' / mês selecionado' : '' ?>)</div><div class="kpi-val o mono"><?= e(brl($totalFolha)) ?></div></div>
</div>

<div class="frow">
  <div class="card">
    <div class="card-title">📉 Maiores categorias de saída</div>
    <?php hbar($categorias, 'var(--red)') ?>
  </div>
  <div class="card">
    <div class="card-title">🏭 Maiores fornecedores</div>
    <?php hbar($fornecedores, 'var(--sky)') ?>
  </div>
</div>

<div class="card">
  <div class="card-title">🏢 Custos fixos mensais</div>

  <details class="add-form">
    <summary><span class="btn btn-secondary btn-sm">+ Novo custo fixo</span></summary>
    <form method="POST" class="frow" style="margin-top:12px;align-items:flex-end">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_fixo">
      <div class="fg"><label>Descrição</label><input type="text" name="descricao" maxlength="150" required></div>
      <div class="fg"><label>Valor (R$)</label><input type="number" name="valor" step="0.01" min="0" value="0"></div>
      <div class="fg"><button type="submit" class="btn btn-primary">Adicionar</button></div>
    </form>
  </details>

  <?php if (!$custosFixos): ?>
    <div class="empty"><div class="empty-icon">🏢</div><p>Nenhum custo fixo cadastrado.</p></div>
  <?php else: ?>
    <table>
      <thead><tr><th>Descrição</th><th class="num">Valor</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($custosFixos as $c):
          $fid = 'fixo-edit-' . (int)$c['id'];
        ?>
          <tr>
            <td>
              <input form="<?= $fid ?>" type="text" name="descricao" value="<?= e($c['descricao']) ?>" maxlength="150"
                     style="background:transparent;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:6px 8px;color:#fff;width:100%">
            </td>
            <td style="text-align:right">
              <input form="<?= $fid ?>" type="number" name="valor" step="0.01" min="0" value="<?= e((string)$c['valor']) ?>"
                     style="background:transparent;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:6px 8px;color:#fff;width:120px;text-align:right">
            </td>
            <td style="white-space:nowrap">
              <form id="<?= $fid ?>" method="POST" style="display:none">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_fixo">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              </form>
              <button type="submit" form="<?= $fid ?>" class="btn btn-secondary btn-sm">💾</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este custo fixo?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_fixo">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<div class="card">
  <div class="card-title">🕵️ Últimas alterações (auditoria)</div>
  <?php if (!$auditoria): ?>
    <div class="text-muted">Nenhuma alteração registrada ainda.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Quando</th><th>Usuário</th><th>Ação</th><th>Tabela</th><th>Registro</th></tr></thead>
      <tbody>
        <?php foreach ($auditoria as $a): ?>
          <tr>
            <td style="font-size:12px;color:var(--gray)"><?= e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
            <td><?= e($a['username'] ?: '—') ?></td>
            <td><?= e($acaoLabel[$a['acao']] ?? $a['acao']) ?></td>
            <td style="font-size:12.5px;color:var(--gray2)"><?= e($a['tabela']) ?></td>
            <td class="mono">#<?= (int)$a['registro_id'] ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<?php layout_end(); ?>
