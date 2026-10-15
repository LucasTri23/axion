<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_financeiro_filtros.php';
require_once __DIR__ . '/_financeiro_excel.php';
$filters = financeiroFiltros($_GET);
$dateColumn = ($_GET['base'] ?? '') === 'vencimento' ? (($_GET['origem'] ?? '') === 'fluxo' ? 'COALESCE(vencimento, data_lancamento)' : 'vencimento') : 'data_lancamento';
[$where, $params] = financeiroWhere($filters, $dateColumn);
$orderSql = financeiroOrdem($filters, ($_GET['origem'] ?? '') === 'fluxo' ? 'atual' : ($dateColumn === 'vencimento' ? 'vencimento_asc' : 'emissao_asc'));
$stmt = db()->prepare("SELECT * FROM centro_lancamentos $where ORDER BY $orderSql");
$stmt->execute($params);
$rows = [['Emissão', 'Vencimento', 'Tipo', 'Descrição', 'Cliente / Fornecedor', 'OS', 'NFe', 'Parcela', 'Entrada (R$)', 'Saída (R$)', 'Situação', 'Centro', 'Observação', 'Filtro de mês', 'Base do filtro']];
$formatDate = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';
$baseLabel = $dateColumn === 'vencimento' ? 'Vencimento' : ($dateColumn === 'data_lancamento' ? 'Emissão' : 'Vencimento (emissão se vazio)');
while ($r = $stmt->fetch()) {
    $rows[] = [$formatDate($r['data_lancamento']), $formatDate($r['vencimento']), $r['tipo'], $r['descricao'], $r['cliente_fornecedor'], $r['os'], $r['nfe'], $r['parcela'], (float)$r['valor_entrada'], (float)$r['valor_saida'], financeiroStatus($r), $r['centro'], $r['observacao'], $filters['mes'], $baseLabel];
}
financeiroExcel('financeiro-' . financeiroHoje(), $rows);
