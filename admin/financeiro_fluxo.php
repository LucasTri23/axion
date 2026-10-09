<?php
/**
 * Financeiro — Fluxo de caixa — OWASP A01, A03, A04, A09
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();
const FLUXO_PAGE_SIZE = 50;

function lancamentoFromPost(array $p): array {
    $tipo  = ($p['tipo'] ?? '') === 'ENTRADA' ? 'ENTRADA' : 'SAIDA';
    $valor = toFloat($p['valor'] ?? '0');
    return [
        'tipo'               => $tipo,
        'descricao'          => trim($p['descricao'] ?? ''),
        'os'                 => trim($p['os'] ?? ''),
        'nfe'                => trim($p['nfe'] ?? ''),
        'cliente_fornecedor' => trim($p['cliente_fornecedor'] ?? ''),
        'parcela'            => trim($p['parcela'] ?? ''),
        'data_lancamento'    => $p['data_lancamento'] ?: null,
        'vencimento'         => $p['vencimento'] ?: null,
        'valor_entrada'      => $tipo === 'ENTRADA' ? $valor : 0,
        'valor_saida'        => $tipo === 'SAIDA' ? $valor : 0,
        'situacao'           => trim($p['situacao'] ?? ''),
        'centro'             => trim($p['centro'] ?? '') ?: 'GERAL',
        'observacao'         => trim($p['observacao'] ?? ''),
    ];
}

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $d = lancamentoFromPost($_POST);
        if ($d['descricao'] !== '' && $d['data_lancamento']) {
            $db->prepare(
                "INSERT INTO centro_lancamentos
                 (tipo, descricao, os, nfe, cliente_fornecedor, parcela, data_lancamento, vencimento,
                  valor_entrada, valor_saida, situacao, centro, observacao)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute(array_values($d));
            auditLog('create', 'centro_lancamentos', (int)$db->lastInsertId(), $d);
        }
        redirect('/admin/financeiro_fluxo.php?saved=1');
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $d  = lancamentoFromPost($_POST);
        if ($id > 0 && $d['descricao'] !== '' && $d['data_lancamento']) {
            $db->prepare(
                "UPDATE centro_lancamentos SET
                 tipo=?, descricao=?, os=?, nfe=?, cliente_fornecedor=?, parcela=?, data_lancamento=?,
                 vencimento=?, valor_entrada=?, valor_saida=?, situacao=?, centro=?, observacao=?
                 WHERE id=?"
            )->execute(array_merge(array_values($d), [$id]));
            auditLog('update', 'centro_lancamentos', $id, $d);
        }
        redirect('/admin/financeiro_fluxo.php?saved=1');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_lancamentos WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_lancamentos WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_lancamentos', $id, $old ?: []);
        }
        redirect('/admin/financeiro_fluxo.php?deleted=1');
    }
}

// ── Filtros ──────────────────────────────────────────────────────
$fTipo   = $_GET['tipo']   ?? '';
$fCentro = $_GET['centro'] ?? '';
$fDe     = $_GET['de']     ?? '';
$fAte    = $_GET['ate']    ?? '';
$fQ      = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if (in_array($fTipo, ['ENTRADA', 'SAIDA'], true)) { $where[] = 'tipo = ?'; $params[] = $fTipo; }
if ($fCentro !== '') { $where[] = 'centro = ?'; $params[] = $fCentro; }
if ($fDe !== '')  { $where[] = 'data_lancamento >= ?'; $params[] = $fDe . '-01'; }
if ($fAte !== '') { $where[] = "data_lancamento <= LAST_DAY(?)"; $params[] = $fAte . '-01'; }
if ($fQ !== '') {
    $where[] = '(descricao LIKE ? OR cliente_fornecedor LIKE ? OR nfe LIKE ? OR os LIKE ? OR observacao LIKE ?)';
    $like = '%' . $fQ . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totals = $db->prepare("SELECT COALESCE(SUM(valor_entrada),0) e, COALESCE(SUM(valor_saida),0) s, COUNT(*) c FROM centro_lancamentos $whereSql");
$totals->execute($params);
$tot = $totals->fetch();

$totalRows = (int)$tot['c'];
$pages     = max(1, (int)ceil($totalRows / FLUXO_PAGE_SIZE));
$page      = min($page, $pages);
$offset    = ($page - 1) * FLUXO_PAGE_SIZE;

$sql = "SELECT * FROM centro_lancamentos $whereSql ORDER BY data_lancamento DESC, id DESC LIMIT " . FLUXO_PAGE_SIZE . " OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$centros = $db->query("SELECT DISTINCT centro FROM centro_lancamentos WHERE centro <> '' ORDER BY centro")->fetchAll(PDO::FETCH_COLUMN);

// ── Registro em edição ───────────────────────────────────────────
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM centro_lancamentos WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch() ?: null;
}

function qs(array $over = []): string {
    $base = ['tipo' => $_GET['tipo'] ?? '', 'centro' => $_GET['centro'] ?? '', 'de' => $_GET['de'] ?? '', 'ate' => $_GET['ate'] ?? '', 'q' => $_GET['q'] ?? ''];
    return http_build_query(array_filter(array_merge($base, $over), fn($v) => $v !== ''));
}

layout_start('Financeiro — Fluxo de caixa', 'financeiro');
$finTab = 'fluxo';
require __DIR__ . '/_financeiro_nav.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Lançamento salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Lançamento excluído com sucesso.</div><?php endif ?>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi-label">Entradas (filtro atual)</div><div class="kpi-val g mono"><?= e(brl($tot['e'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">Saídas (filtro atual)</div><div class="kpi-val" style="color:var(--red)"><?= e(brl($tot['s'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">Resultado (filtro atual)</div><div class="kpi-val" style="color:<?= ($tot['e']-$tot['s'])>=0?'var(--green)':'var(--red)' ?>"><?= e(brl($tot['e'] - $tot['s'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">Lançamentos</div><div class="kpi-val s"><?= (int)$totalRows ?></div></div>
</div>

<div class="card">
  <div class="card-title"><?= $editRow ? '✏️ Editar lançamento #' . (int)$editRow['id'] : '➕ Novo lançamento' ?></div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif ?>
    <div class="frow">
      <div class="fg">
        <label>Tipo</label>
        <select name="tipo" required>
          <option value="SAIDA"   <?= (!$editRow || $editRow['tipo']==='SAIDA')   ? 'selected' : '' ?>>Saída</option>
          <option value="ENTRADA" <?= ($editRow && $editRow['tipo']==='ENTRADA') ? 'selected' : '' ?>>Entrada</option>
        </select>
      </div>
      <div class="fg">
        <label>Valor (R$)</label>
        <input type="number" name="valor" step="0.01" min="0" required
               value="<?= $editRow ? e((string)($editRow['tipo']==='ENTRADA' ? $editRow['valor_entrada'] : $editRow['valor_saida'])) : '' ?>">
      </div>
    </div>
    <div class="frow">
      <div class="fg"><label>Descrição</label><input type="text" name="descricao" maxlength="255" required value="<?= e($editRow['descricao'] ?? '') ?>" placeholder="Ex: MATERIA PRIMA, GASES, LOCAÇÃO…"></div>
      <div class="fg"><label>Cliente / Fornecedor</label><input type="text" name="cliente_fornecedor" maxlength="255" value="<?= e($editRow['cliente_fornecedor'] ?? '') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>Data</label><input type="date" name="data_lancamento" required value="<?= e($editRow['data_lancamento'] ?? '') ?>"></div>
      <div class="fg"><label>Vencimento</label><input type="date" name="vencimento" value="<?= e($editRow['vencimento'] ?? '') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>OS</label><input type="text" name="os" maxlength="20" value="<?= e($editRow['os'] ?? '') ?>"></div>
      <div class="fg"><label>NFe</label><input type="text" name="nfe" maxlength="50" value="<?= e($editRow['nfe'] ?? '') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>Parcela</label><input type="text" name="parcela" maxlength="20" placeholder="1/1" value="<?= e($editRow['parcela'] ?? '') ?>"></div>
      <div class="fg">
        <label>Situação</label>
        <select name="situacao">
          <option value="OK" <?= (($editRow['situacao'] ?? 'OK') === 'OK') ? 'selected' : '' ?>>OK</option>
          <option value=""   <?= (isset($editRow) && $editRow['situacao'] === '') ? 'selected' : '' ?>>Pendente</option>
        </select>
      </div>
    </div>
    <div class="frow">
      <div class="fg"><label>Centro de custo</label><input type="text" name="centro" maxlength="100" list="centroList" placeholder="GERAL" value="<?= e($editRow['centro'] ?? '') ?>"></div>
      <div class="fg"><label>Observação</label><input type="text" name="observacao" maxlength="255" value="<?= e($editRow['observacao'] ?? '') ?>"></div>
    </div>
    <datalist id="centroList"><?php foreach ($centros as $c): ?><option value="<?= e($c) ?>"><?php endforeach ?></datalist>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary"><?= $editRow ? '💾 Salvar alterações' : '➕ Adicionar lançamento' ?></button>
      <?php if ($editRow): ?><a href="/admin/financeiro_fluxo.php" class="btn btn-ghost">Cancelar</a><?php endif ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="tbl-header">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="text" name="q" value="<?= e($fQ) ?>" placeholder="Buscar descrição, cliente, NFe, OS…"
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 12px;color:#fff;font-size:13px;outline:none;width:220px">
      <select name="tipo" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 10px;color:#fff;font-size:13px">
        <option value="">Todos os tipos</option>
        <option value="ENTRADA" <?= $fTipo==='ENTRADA'?'selected':'' ?>>Entrada</option>
        <option value="SAIDA"   <?= $fTipo==='SAIDA'?'selected':'' ?>>Saída</option>
      </select>
      <select name="centro" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 10px;color:#fff;font-size:13px">
        <option value="">Todos os centros</option>
        <?php foreach ($centros as $c): ?><option value="<?= e($c) ?>" <?= $fCentro===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach ?>
      </select>
      <input type="month" name="de" value="<?= e($fDe) ?>" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:6px 10px;color:#fff;font-size:13px">
      <span style="color:var(--gray)">–</span>
      <input type="month" name="ate" value="<?= e($fAte) ?>" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:6px 10px;color:#fff;font-size:13px">
      <button type="submit" class="btn btn-ghost btn-sm">🔍 Filtrar</button>
      <?php if ($fTipo || $fCentro || $fDe || $fAte || $fQ): ?><a href="/admin/financeiro_fluxo.php" class="btn btn-ghost btn-sm">Limpar</a><?php endif ?>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon">🔍</div><p>Nenhum lançamento encontrado para este filtro.</p></div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table>
      <thead><tr>
        <th>Data</th><th>Tipo</th><th>Descrição</th><th>Cliente/Fornecedor</th><th>OS</th>
        <th>Vencimento</th><th class="num">Entrada</th><th class="num">Saída</th><th>Situação</th><th>Centro</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="font-size:12.5px"><?= e($r['data_lancamento'] ? date('d/m/Y', strtotime($r['data_lancamento'])) : '—') ?></td>
            <td><span class="badge <?= $r['tipo']==='ENTRADA'?'b-concluido':'b-cancelado' ?>"><?= $r['tipo']==='ENTRADA'?'Entrada':'Saída' ?></span></td>
            <td class="td-name"><?= e($r['descricao']) ?><?php if($r['observacao']): ?><div class="td-sub"><?= e($r['observacao']) ?></div><?php endif ?></td>
            <td style="font-size:12.5px"><?= e($r['cliente_fornecedor'] ?: '—') ?></td>
            <td style="font-size:12.5px"><?= e($r['os'] ?: '—') ?></td>
            <td style="font-size:12px;color:var(--gray)"><?= e($r['vencimento'] ? date('d/m/Y', strtotime($r['vencimento'])) : '—') ?></td>
            <td class="mono" style="text-align:right"><?= $r['valor_entrada'] > 0 ? '<span class="money-in">'.e(brl($r['valor_entrada'])).'</span>' : '—' ?></td>
            <td class="mono" style="text-align:right"><?= $r['valor_saida'] > 0 ? '<span class="money-out">'.e(brl($r['valor_saida'])).'</span>' : '—' ?></td>
            <td><?= $r['situacao']==='OK' ? '<span class="badge b-concluido">OK</span>' : '<span class="badge b-andamento">Pendente</span>' ?></td>
            <td><span class="badge" style="background:rgba(255,255,255,.06);color:var(--gray2)"><?= e($r['centro']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="/admin/financeiro_fluxo.php?edit=<?= (int)$r['id'] ?>&<?= qs() ?>" class="btn btn-secondary btn-sm">✏️</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este lançamento permanentemente?')">
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
  </div>
  <div class="pager" style="display:flex;justify-content:space-between;align-items:center;padding-top:14px;font-size:12.5px;color:var(--gray)">
    <div>Página <?= $page ?> de <?= $pages ?> — <?= $totalRows ?> lançamento(s)</div>
    <div style="display:flex;gap:8px">
      <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="?<?= qs(['page' => $page-1]) ?>">← Anterior</a><?php endif ?>
      <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="?<?= qs(['page' => $page+1]) ?>">Próxima →</a><?php endif ?>
    </div>
  </div>
  <?php endif ?>
</div>

<?php layout_end(); ?>
