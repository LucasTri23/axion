<?php
// Execute: php tests/financeiro_test.php (não usa credenciais nem banco).
require_once __DIR__ . '/../admin/_financeiro_filtros.php';
require_once __DIR__ . '/../admin/_financeiro_excel.php';
require_once __DIR__ . '/../admin/_financeiro_selecao.php';
if (($argv[1] ?? '') === '--xlsx') {
    financeiroExcel('teste', [['Descrição', 'Valor'], ['=1+1 & ação <teste>', 1234.56], ['00123', 0]]);
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
check(financeiroCentavos('10.10') + financeiroCentavos('0.20') === 1030, 'A soma deve preservar centavos');
check(financeiroCentavos('9999999999.99') === 999999999999, 'Valor monetário máximo incorreto');
check(financeiroCentavos('5') === 500 && financeiroCentavos('0.01') === 1, 'Conversão de centavos incorreta');
$order = financeiroFiltros(['ordem' => 'vencimento_asc']);
check(financeiroOrdem($order) === '(vencimento IS NULL), vencimento ASC, data_lancamento ASC, id ASC', 'Vencimentos devem seguir ordem cronológica com datas ausentes no final');
check(financeiroOrdem(financeiroFiltros([]), 'emissao_asc') === '(data_lancamento IS NULL), data_lancamento ASC, id ASC', 'Emissões devem seguir ordem cronológica');
$order = financeiroFiltros(['ordem' => 'id; DROP TABLE centro_lancamentos']);
check($order['ordem'] === '' && !str_contains(financeiroOrdem($order), 'DROP'), 'Ordenação deve usar lista permitida');
parse_str(financeiroQuery(financeiroFiltros(['ordem' => 'emissao_desc']), ['page' => 2]), $orderQuery);
check($orderQuery['ordem'] === 'emissao_desc', 'Ordenação deve permanecer na paginação');
$month = financeiroFiltros(['mes' => '2026-10', 'de' => '2026-01', 'ate' => '2026-03']);
check($month['de'] === '' && $month['ate'] === '', 'Mês específico deve substituir o intervalo conflitante');
$range = financeiroFiltros(['de' => '2026-12', 'ate' => '2026-10']);
check($range['de'] === '2026-10' && $range['ate'] === '2026-12', 'Intervalo invertido deve ser corrigido');
[$sql, $params] = financeiroWhere($month, 'COALESCE(vencimento, data_lancamento)');
check(count($params) === 2 && str_contains($sql, 'COALESCE(vencimento, data_lancamento)'), 'Pesquisa de vencimento deve usar emissão quando não houver vencimento');
check(str_contains(financeiroOrdem(financeiroFiltros(['ordem' => 'atual', 'base' => 'vencimento'])), 'COALESCE(vencimento, data_lancamento)'), 'Ordem atual deve seguir a data selecionada');
$f = financeiroFiltros(['mes' => '2026-10', 'os' => '2603', 'tipo' => 'SAIDA', 'status' => 'vencido']);
[$sql, $params] = financeiroWhere($f, 'vencimento');
check(substr_count($sql, '?') === count($params), 'Parâmetros do filtro não correspondem à consulta');
check($params === ['SAIDA', '2603', '2026-10-01', '2026-10-01', financeiroHoje()], 'Filtros combinados incorretos');
check(str_contains($sql, 'vencimento < DATE_ADD(?, INTERVAL 1 MONTH)'), 'Limite final do mês deve excluir o mês seguinte');
check(str_contains($sql, "NOT (UPPER(TRIM(COALESCE(situacao,''))) = 'OK')"), 'Contas pagas não podem aparecer como vencidas');
$bad = financeiroFiltros(['mes' => '2026-13', 'de' => '2026-00', 'ate' => ['2026-10'], 'tipo' => "' OR 1=1", 'q' => "' OR 1=1"]);
check($bad['mes'] === '' && $bad['de'] === '' && $bad['ate'] === '' && $bad['tipo'] === '', 'Filtros inválidos aceitos');
[$sql, $params] = financeiroWhere($bad);
check(!str_contains($sql, "' OR 1=1") && count($params) === 5, 'Busca deve usar parâmetros');
parse_str(financeiroQuery(financeiroFiltros(['status' => '']), ['page' => 2]), $query);
check(array_key_exists('status', $query) && $query['status'] === '', 'Todas as situações deve permanecer na paginação');
$today = financeiroHoje();
$yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
$tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
foreach ([['', null, 'Sem vencimento'], ['', $yesterday, 'Vencido'], ['', $today, 'Vence hoje'], ['', $tomorrow, 'A vencer'], [' ok ', $yesterday, 'Pago']] as [$status, $due, $expected]) {
    check(financeiroStatus(['tipo' => 'SAIDA', 'situacao' => $status, 'vencimento' => $due]) === $expected, 'Classificação incorreta: ' . $expected);
}
check(financeiroStatus(['tipo' => 'ENTRADA', 'situacao' => 'OK', 'vencimento' => null]) === 'Recebido', 'Entrada quitada deve ser recebida');
$pipes = [];
$process = proc_open([PHP_BINARY, __FILE__, '--xlsx'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
check(is_resource($process), 'Não foi possível executar teste do Excel');
$binary = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
check(proc_close($process) === 0 && $error === '', 'Falha ao exportar: ' . $error);
$offset = 0; $files = [];
while (substr($binary, $offset, 4) === "PK\x03\x04") {
    $header = unpack('Vsignature/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra', substr($binary, $offset, 30));
    $name = substr($binary, $offset + 30, $header['name']);
    $body = substr($binary, $offset + 30 + $header['name'] + $header['extra'], $header['size']);
    check(sprintf('%u', crc32($body)) === sprintf('%u', $header['crc']), 'CRC inválido: ' . $name);
    $files[$name] = $body;
    $offset += 30 + $header['name'] + $header['extra'] + $header['size'];
}
check(count($files) === 6 && substr($binary, $offset, 4) === "PK\x01\x02", 'Estrutura ZIP inválida');
$end = unpack('Vsignature/vdisk/vstart/vcount/vtotal/Vsize/Voffset/vcomment', substr($binary, -22));
check($end['signature'] === 0x06054b50 && $end['total'] === 6 && $end['offset'] === $offset, 'Diretório ZIP inválido');
$sheet = $files['xl/worksheets/sheet1.xml'];
check(str_contains($sheet, '=1+1 &amp; ação &lt;teste&gt;') && !str_contains($sheet, '<f>'), 'Texto exportado não pode se tornar fórmula');
check(str_contains($sheet, '<v>1234.56</v>') && str_contains($sheet, '>00123</t>'), 'Valores numéricos e zeros iniciais não foram preservados');
echo "PASS: filtros, limites de mês, situações, paginação e pacote XLSX.\n";
