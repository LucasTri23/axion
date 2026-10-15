<?php
/** A seleção planeja pagamentos; não altera a situação das contas. */
function financeiroCentavos(string $decimal): int {
    [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
    return (int)$whole * 100 + (int)str_pad(substr($fraction, 0, 2), 2, '0');
}

function financeiroSelecionados(PDO $db): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_SESSION['financeiro_selecao'] ?? [])), fn($id) => $id > 0)));
    $ids = array_slice($ids, 0, 1000);
    $items = []; $pagar = 0; $receber = 0;
    if ($ids) {
        $stmt = $db->prepare('SELECT id, tipo, descricao, cliente_fornecedor, vencimento, valor_entrada, valor_saida FROM centro_lancamentos WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND UPPER(TRIM(COALESCE(situacao,''))) <> 'OK' ORDER BY (vencimento IS NULL), vencimento, id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $cents = financeiroCentavos((string)($row['tipo'] === 'ENTRADA' ? $row['valor_entrada'] : $row['valor_saida']));
            if ($row['tipo'] === 'ENTRADA') $receber += $cents; else $pagar += $cents;
            $items[] = ['id' => (int)$row['id'], 'tipo' => $row['tipo'], 'descricao' => $row['descricao'], 'cliente' => $row['cliente_fornecedor'], 'vencimento' => $row['vencimento'], 'centavos' => $cents];
        }
    }
    $_SESSION['financeiro_selecao'] = array_column($items, 'id');
    return ['items' => $items, 'pagar' => $pagar, 'receber' => $receber, 'quantidade' => count($items)];
}
