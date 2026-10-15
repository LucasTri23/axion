<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_financeiro_filtros.php';
require_once __DIR__ . '/_financeiro_selecao.php';
$db = db();
$filters = financeiroFiltros($_GET);
if (!isset($_GET['status'])) $filters['status'] = 'pendente';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['quitar', 'reabrir'], true)) {
        $stmt = $db->prepare('SELECT * FROM centro_lancamentos WHERE id=?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            $status = $action === 'quitar' ? 'OK' : '';
            $db->prepare('UPDATE centro_lancamentos SET situacao=? WHERE id=?')->execute([$status, $id]);
            auditLog('update', 'centro_lancamentos', $id, ['situacao_anterior' => $old['situacao'], 'situacao' => $status]);
        }
    }
    redirect('/admin/financeiro_contas.php?' . financeiroQuery($filters, ['saved' => 1]));
}
[$where, $params] = financeiroWhere($filters, 'vencimento');
$today = financeiroHoje();
$stmt = $db->prepare("SELECT COUNT(*) AS qtd,
    COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(situacao,''))) <> 'OK' THEN valor_saida ELSE 0 END),0) AS pagar,
    COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(situacao,''))) <> 'OK' THEN valor_entrada ELSE 0 END),0) AS receber,
    COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(situacao,''))) <> 'OK' AND vencimento < ? THEN valor_saida ELSE 0 END),0) AS pagar_vencido,
    COALESCE(SUM(CASE WHEN UPPER(TRIM(COALESCE(situacao,''))) <> 'OK' AND vencimento < ? THEN valor_entrada ELSE 0 END),0) AS receber_vencido
    FROM centro_lancamentos $where");
$stmt->execute(array_merge([$today, $today], $params));
$tot = $stmt->fetch();
$pages = max(1, (int)ceil((int)$tot['qtd'] / 50));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * 50;
$orderSql = financeiroOrdem($filters);
$stmt = $db->prepare("SELECT * FROM centro_lancamentos $where ORDER BY $orderSql LIMIT 50 OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$obras = financeiroObras($db);
$selection = financeiroSelecionados($db);
$selectedIds = array_column($selection['items'], 'id');
$statuses = ['' => 'Todas as situações', 'pendente' => 'Todas em aberto', 'vencido' => 'Vencidas', 'hoje' => 'Vencem hoje', 'proximos' => 'A vencer', 'sem_vencimento' => 'Sem vencimento', 'quitado' => 'Pagas / recebidas'];
layout_start('Financeiro — Contas a pagar e receber', 'financeiro');
$finTab = 'contas';
require __DIR__ . '/_financeiro_nav.php';
?>
<link rel="stylesheet" href="/css/financeiro.css">
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Situação da conta atualizada.</div><?php endif ?>
<div class="card fin-filters">
  <div class="card-title">Contas a pagar e receber</div>
  <p class="text-muted">Contas dos lançamentos do fluxo de caixa. O filtro de mês usa o vencimento; contas sem vencimento aparecem quando o mês está vazio. “OK” indica pago ou recebido.</p>
  <nav class="fin-segments" aria-label="Pesquisar por tipo de conta">
    <?php foreach (['' => ['Todas em aberto', '↔'], 'SAIDA' => ['A pagar', '↗'], 'ENTRADA' => ['A receber', '↙']] as $type => [$label, $icon]): ?>
      <a class="fin-segment <?= $filters['tipo'] === $type ? 'is-active' : '' ?>" <?= $filters['tipo'] === $type ? 'aria-current="true"' : '' ?> href="?<?= e(financeiroQuery($filters, ['tipo' => $type, 'status' => 'pendente'])) ?>"><span aria-hidden="true"><?= $icon ?></span><?= e($label) ?></a>
    <?php endforeach ?>
  </nav>
  <nav class="fin-chips" aria-label="Pesquisar por vencimento">
    <?php foreach (['pendente' => 'Em aberto', 'vencido' => 'Atrasadas', 'hoje' => 'Vencem hoje', 'proximos' => 'A vencer', 'sem_vencimento' => 'Sem vencimento', 'quitado' => 'Pagas / recebidas', '' => 'Tudo'] as $key => $label): ?>
      <a class="fin-chip <?= $filters['status'] === $key ? 'is-active' : '' ?>" <?= $filters['status'] === $key ? 'aria-current="true"' : '' ?> href="?<?= e(financeiroQuery($filters, ['status' => $key])) ?>"><?= e($label) ?></a>
    <?php endforeach ?>
  </nav>
  <form method="GET" class="frow fin-filter-fields" style="margin-top:16px">
    <div class="fg"><label>Mês de vencimento</label><input type="month" name="mes" value="<?= e($filters['mes']) ?>"></div>
    <div class="fg"><label>Obra / OS</label><select name="os"><option value="">Todas as obras</option><?php foreach ($obras as $obra): ?><option value="<?= e($obra['os']) ?>" <?= $filters['os'] === $obra['os'] ? 'selected' : '' ?>><?= e($obra['os'] . ' — ' . ($obra['nome'] ?: 'Sem nome')) ?></option><?php endforeach ?></select></div>
    <div class="fg"><label>Tipo de conta</label><select name="tipo"><option value="">Pagar e receber</option><option value="SAIDA" <?= $filters['tipo'] === 'SAIDA' ? 'selected' : '' ?>>A pagar</option><option value="ENTRADA" <?= $filters['tipo'] === 'ENTRADA' ? 'selected' : '' ?>>A receber</option></select></div>
    <div class="fg"><label>Situação</label><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
    <div class="fg"><label>Buscar descrição, cliente ou documento</label><input type="text" name="q" value="<?= e($filters['q']) ?>"></div>
    <div class="fg"><label>Ordenar por</label><select name="ordem"><?php foreach (['vencimento_asc' => 'Vencimento: mais antigo primeiro', 'vencimento_desc' => 'Vencimento: mais recente primeiro', 'emissao_asc' => 'Emissão: mais antiga primeiro', 'emissao_desc' => 'Emissão: mais recente primeiro'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($filters['ordem'] ?: 'vencimento_asc') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
    <div class="fg" style="display:flex;gap:8px;justify-content:flex-end"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-ghost" href="/admin/financeiro_contas.php">Limpar</a></div>
  </form>
  <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
    <a class="btn btn-secondary btn-sm" href="/admin/financeiro_fluxo.php">Cadastrar conta no fluxo</a>
    <a class="btn btn-secondary btn-sm" href="/admin/financeiro_exportar.php?<?= e(financeiroQuery($filters, ['base' => 'vencimento'])) ?>">Exportar Excel (.xlsx)</a>
  </div>
</div>
<section class="fin-selection" aria-label="Planejamento de pagamentos" data-fin-selection data-csrf="<?= e(csrfToken()) ?>">
  <div class="fin-selection-heading"><div><span class="fin-eyebrow">SEU PLANEJAMENTO</span><h2>Contas selecionadas <span data-selection-count><?= (int)$selection['quantidade'] ?></span></h2></div><button class="btn btn-ghost btn-sm" type="button" data-selection-clear>Limpar seleção</button></div>
  <div class="fin-selection-totals" aria-live="polite" aria-atomic="true">
    <div><span>Total a pagar</span><strong data-selection-pay><?= e(brl($selection['pagar'] / 100)) ?></strong></div>
    <div><span>Total a receber</span><strong data-selection-receive><?= e(brl($selection['receber'] / 100)) ?></strong></div>
  </div>
  <p class="text-muted">Marque as contas para somar. A seleção continua entre páginas e filtros durante esta sessão e não marca nada como pago.</p>
  <p class="fin-selection-message" data-selection-message role="status"></p>
  <details class="fin-selection-details"><summary>Conferir seleção completa</summary><ul data-selection-list>
    <?php foreach ($selection['items'] as $item): ?><li><span><?= e(($item['vencimento'] ? date('d/m/Y', strtotime($item['vencimento'])) : 'Sem vencimento') . ' · ' . ($item['tipo'] === 'SAIDA' ? 'A pagar' : 'A receber') . ' · ' . ($item['descricao'] ?: $item['cliente']) . ' · ' . brl($item['centavos'] / 100)) ?></span><button type="button" class="btn btn-ghost btn-sm" data-selection-remove="<?= (int)$item['id'] ?>">Remover</button></li><?php endforeach ?>
  </ul></details>
  <noscript><p>Ative o JavaScript para selecionar e somar contas.</p></noscript>
</section>
<div class="kpi-grid">
  <div class="kpi"><div class="kpi-label">A pagar em aberto (filtro)</div><div class="kpi-val mono"><?= e(brl($tot['pagar'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">A receber em aberto (filtro)</div><div class="kpi-val g mono"><?= e(brl($tot['receber'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">A pagar vencido (filtro)</div><div class="kpi-val mono" style="color:var(--red)"><?= e(brl($tot['pagar_vencido'])) ?></div></div>
  <div class="kpi"><div class="kpi-label">A receber vencido (filtro)</div><div class="kpi-val mono" style="color:var(--yellow)"><?= e(brl($tot['receber_vencido'])) ?></div></div>
</div>
<div class="card" style="overflow-x:auto">
  <?php if (!$rows): ?><p class="text-muted">Nenhuma conta encontrada para estes filtros.</p><?php else: ?>
  <div class="fin-table-toolbar"><label><input type="checkbox" data-selection-page> Selecionar contas em aberto desta página</label><span class="text-muted">Datas não informadas ficam no final da lista.</span></div>
  <table class="fin-accounts-table"><thead><tr><th>Selecionar</th><th>Vencimento</th><th>Emissão</th><th>Tipo</th><th>Descrição / Cliente</th><th>OS</th><th>Valor</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $paid = strtoupper(trim($r['situacao'] ?? '')) === 'OK'; ?>
    <tr class="<?= in_array((int)$r['id'], $selectedIds, true) ? 'is-selected' : '' ?>">
      <td><input type="checkbox" class="fin-account-check" data-selection-id="<?= (int)$r['id'] ?>" aria-label="<?= e('Selecionar ' . ($r['descricao'] ?: $r['cliente_fornecedor']) . ' — ' . brl($r['tipo'] === 'ENTRADA' ? $r['valor_entrada'] : $r['valor_saida'])) ?>" <?= $paid ? 'disabled title="Conta já quitada"' : '' ?> <?= in_array((int)$r['id'], $selectedIds, true) ? 'checked' : '' ?>></td>
      <td><?= e($r['vencimento'] ? date('d/m/Y', strtotime($r['vencimento'])) : 'Não informado') ?></td>
      <td><?= e($r['data_lancamento'] ? date('d/m/Y', strtotime($r['data_lancamento'])) : 'Não informada') ?></td>
      <td><?= $r['tipo'] === 'ENTRADA' ? 'A receber' : 'A pagar' ?></td>
      <td><?= e($r['descricao'] ?: 'Sem descrição') ?><div class="td-sub"><?= e($r['cliente_fornecedor']) ?></div></td>
      <td><?= e($r['os'] ?: '—') ?></td>
      <td class="mono"><?= e(brl($r['tipo'] === 'ENTRADA' ? $r['valor_entrada'] : $r['valor_saida'])) ?></td>
      <td><span class="badge <?= $paid ? 'b-concluido' : ($r['vencimento'] && $r['vencimento'] < $today ? 'b-cancelado' : 'b-andamento') ?>"><?= e(financeiroStatus($r)) ?></span></td>
      <td><div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-secondary btn-sm" href="/admin/financeiro_fluxo.php?edit=<?= (int)$r['id'] ?>">Editar</a>
        <form method="POST" action="?<?= e(financeiroQuery($filters)) ?>">
          <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="<?= $paid ? 'reabrir' : 'quitar' ?>">
          <button class="btn btn-primary btn-sm" type="submit"><?= $paid ? 'Reabrir' : ($r['tipo'] === 'ENTRADA' ? 'Marcar recebido' : 'Marcar pago') ?></button>
        </form>
      </div></td>
    </tr>
  <?php endforeach ?>
  </tbody></table>
  <div style="display:flex;gap:12px;margin-top:16px;align-items:center">
    <span>Página <?= $page ?> de <?= $pages ?> — <?= (int)$tot['qtd'] ?> contas</span>
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="?<?= e(financeiroQuery($filters, ['page' => $page - 1])) ?>">Anterior</a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="?<?= e(financeiroQuery($filters, ['page' => $page + 1])) ?>">Próxima</a><?php endif ?>
  </div>
  <?php endif ?>
</div>
<script src="/js/financeiro-contas.js" defer></script>
<?php layout_end(); ?>
