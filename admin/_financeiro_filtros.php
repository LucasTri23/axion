<?php
/** Filtros compartilhados entre consulta, indicadores e exportação. */
function financeiroFiltros(array $input): array {
    $f = [];
    foreach (['tipo', 'centro', 'de', 'ate', 'mes', 'os', 'q', 'status', 'ordem', 'base', 'limite'] as $key) {
        $f[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }
    foreach (['mes', 'de', 'ate'] as $key) {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $f[$key])) $f[$key] = '';
    }
    // Um mês específico substitui o intervalo, evitando condições incompatíveis.
    if ($f['mes'] !== '') { $f['de'] = ''; $f['ate'] = ''; }
    elseif ($f['de'] !== '' && $f['ate'] !== '' && $f['de'] > $f['ate']) {
        [$f['de'], $f['ate']] = [$f['ate'], $f['de']];
    }
    if (!in_array($f['base'], ['emissao', 'vencimento'], true)) $f['base'] = '';
    if ($f['limite'] !== '7' || $f['status'] !== 'proximos') $f['limite'] = '';
    if (!in_array($f['tipo'], ['ENTRADA', 'SAIDA'], true)) $f['tipo'] = '';
    if (!in_array($f['status'], ['pendente', 'vencido', 'hoje', 'proximos', 'sem_vencimento', 'quitado'], true)) $f['status'] = '';
    if (!in_array($f['ordem'], ['atual', 'vencimento_asc', 'vencimento_desc', 'emissao_asc', 'emissao_desc'], true)) $f['ordem'] = '';
    return $f;
}

function financeiroOrdem(array $filters, string $default = 'vencimento_asc'): string {
    if (($filters['ordem'] ?: $default) === 'atual') {
        $column = ($filters['base'] ?? '') === 'emissao' ? 'data_lancamento' : 'COALESCE(vencimento, data_lancamento)';
        $start = substr(financeiroHoje(), 0, 7) . '-01';
        $next = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
        return "CASE WHEN $column IS NULL THEN 3 WHEN $column >= '$start' AND $column < '$next' THEN 0 WHEN $column >= '$next' THEN 1 ELSE 2 END,
            CASE WHEN $column >= '$start' THEN $column END ASC,
            CASE WHEN $column < '$start' THEN $column END DESC, id ASC";
    }
    $options = [
        'vencimento_asc' => '(vencimento IS NULL), vencimento ASC, data_lancamento ASC, id ASC',
        'vencimento_desc' => '(vencimento IS NULL), vencimento DESC, data_lancamento DESC, id DESC',
        'emissao_asc' => '(data_lancamento IS NULL), data_lancamento ASC, id ASC',
        'emissao_desc' => '(data_lancamento IS NULL), data_lancamento DESC, id DESC',
    ];
    return $options[$filters['ordem'] ?? ''] ?? $options[$default];
}

function financeiroHoje(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
}

function financeiroWhere(array $f, string $dateColumn = 'data_lancamento'): array {
    if (!in_array($dateColumn, ['data_lancamento', 'vencimento', 'COALESCE(vencimento, data_lancamento)'], true)) throw new InvalidArgumentException('Campo de data inválido.');
    $where = []; $params = [];
    foreach (['tipo', 'centro', 'os'] as $key) {
        if ($f[$key] !== '') { $where[] = "$key = ?"; $params[] = $f[$key]; }
    }
    if ($f['mes'] !== '') {
        $where[] = "$dateColumn >= ? AND $dateColumn < DATE_ADD(?, INTERVAL 1 MONTH)";
        array_push($params, $f['mes'] . '-01', $f['mes'] . '-01');
    }
    if ($f['de'] !== '') { $where[] = "$dateColumn >= ?"; $params[] = $f['de'] . '-01'; }
    if ($f['ate'] !== '') { $where[] = "$dateColumn < DATE_ADD(?, INTERVAL 1 MONTH)"; $params[] = $f['ate'] . '-01'; }
    if ($f['q'] !== '') {
        $where[] = '(descricao LIKE ? OR cliente_fornecedor LIKE ? OR nfe LIKE ? OR os LIKE ? OR observacao LIKE ?)';
        array_push($params, ...array_fill(0, 5, '%' . $f['q'] . '%'));
    }
    $paid = "UPPER(TRIM(COALESCE(situacao,''))) = 'OK'";
    if ($f['status'] === 'quitado') $where[] = $paid;
    elseif ($f['status'] !== '') {
        $where[] = "NOT ($paid)";
        $today = financeiroHoje();
        if ($f['status'] === 'vencido') { $where[] = 'vencimento < ?'; $params[] = $today; }
        if ($f['status'] === 'hoje') { $where[] = 'vencimento = ?'; $params[] = $today; }
        if ($f['status'] === 'proximos') {
            $where[] = 'vencimento > ?'; $params[] = $today;
            if (($f['limite'] ?? '') === '7') {
                $where[] = 'vencimento <= ?';
                $params[] = (new DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
            }
        }
        if ($f['status'] === 'sem_vencimento') $where[] = 'vencimento IS NULL';
    }
    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function financeiroQuery(array $filters, array $overrides = []): string {
    return http_build_query(array_filter(array_merge($filters, $overrides), fn($v, $key) => $v !== null && ($v !== '' || $key === 'status'), ARRAY_FILTER_USE_BOTH));
}

function financeiroObras(PDO $db): array {
    return $db->query("SELECT os, MAX(nome) AS nome FROM (
        SELECT os, nome FROM centro_obras WHERE os <> ''
        UNION ALL SELECT DISTINCT os, '' AS nome FROM centro_lancamentos WHERE os <> ''
    ) obras GROUP BY os ORDER BY os")->fetchAll();
}

function financeiroStatus(array $row): string {
    if (strtoupper(trim($row['situacao'] ?? '')) === 'OK') return $row['tipo'] === 'ENTRADA' ? 'Recebido' : 'Pago';
    if (!$row['vencimento']) return 'Sem vencimento';
    if ($row['vencimento'] < financeiroHoje()) return 'Vencido';
    if ($row['vencimento'] === financeiroHoje()) return 'Vence hoje';
    return 'A vencer';
}
