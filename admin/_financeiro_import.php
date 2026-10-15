<?php
/** Carga inicial transacional. Nunca substitui um financeiro já preenchido. */
function financeiroImportarInicial(PDO $db): array {
    $locked = (int)$db->query("SELECT GET_LOCK('axion_financeiro_inicial', 10)")->fetchColumn();
    if (!$locked) throw new RuntimeException('Outra importação está em andamento. Tente novamente.');
    try {
        $db->beginTransaction();
        foreach (['centro_lancamentos', 'centro_obras', 'centro_custos_obra', 'centro_funcionarios', 'centro_folha_periodos', 'centro_folha_pagamentos', 'centro_jato_pintura', 'centro_custos_fixos'] as $table) {
            if ((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() > 0) {
                throw new RuntimeException('O financeiro já possui dados. A carga inicial foi bloqueada para preservar os registros existentes.');
            }
        }
        if (financeiroCargaConcluida($db)) throw new RuntimeException('Esta carga inicial já foi executada.');
        $data = require __DIR__ . '/_financeiro_dados.php';
        $groups = [
            ['centro_lancamentos', 'tipo,descricao,os,nfe,cliente_fornecedor,parcela,data_lancamento,vencimento,valor_entrada,valor_saida,situacao,centro,observacao', $data['lancamentos']],
            ['centro_jato_pintura', 'cliente,produto,data_saida,kg,vr_kg,vr_total', $data['jato']],
            ['centro_custos_fixos', 'descricao,valor', $data['fixos']],
            ['centro_obras', 'os,nome', $data['obras']],
        ];
        $counts = [];
        foreach ($groups as [$table, $columns, $rows]) {
            $stmt = $db->prepare("INSERT INTO $table ($columns) VALUES (" . implode(',', array_fill(0, count(explode(',', $columns)), '?')) . ')');
            foreach ($rows as $row) $stmt->execute($row);
            $counts[$table] = count($rows);
        }
        $ids = [];
        $stmt = $db->prepare('INSERT INTO centro_funcionarios (nome,pix_chave,banco_info) VALUES (?,?,?)');
        foreach ($data['funcionarios'] as $row) {
            $stmt->execute($row);
            $ids[$row[0]] = (int)$db->lastInsertId();
        }
        $period = $db->prepare('INSERT INTO centro_folha_periodos (data_periodo,tipo) VALUES (?,?)');
        $payment = $db->prepare('INSERT INTO centro_folha_pagamentos (periodo_id,funcionario_id,valor,vale_transporte,observacao) VALUES (?,?,?,?,?)');
        $payments = 0;
        foreach ($data['periodos'] as [$date, $type, $rows]) {
            $period->execute([$date, $type]);
            $id = (int)$db->lastInsertId();
            foreach ($rows as [$name, $value, $transport, $note]) {
                $payment->execute([$id, $ids[$name], $value, $transport, $note]);
                $payments++;
            }
        }
        $counts['funcionarios'] = count($ids);
        $counts['periodos'] = count($data['periodos']);
        $counts['pagamentos'] = $payments;
        auditLog('create', 'centro_lancamentos', 0, ['carga_inicial_excel' => $data['sha256'], 'origem' => $data['source'], 'contagens' => $counts]);
        $db->commit();
        return $counts;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    } finally {
        $db->query("SELECT RELEASE_LOCK('axion_financeiro_inicial')");
    }
}

function financeiroCargaConcluida(PDO $db): bool {
    return (bool)$db->query("SELECT 1 FROM centro_audit_log WHERE tabela='centro_lancamentos' AND detalhes LIKE '%\"carga_inicial_excel\"%' LIMIT 1")->fetchColumn();
}
