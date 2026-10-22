<?php
function financeiroRotina(PDO $db,string $today): array {
    $end=(new DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
    $open="tipo='SAIDA' AND valor_saida>0 AND UPPER(TRIM(COALESCE(situacao,'')))<>'OK'";
    $s=$db->prepare("SELECT
        COALESCE(SUM(CASE WHEN vencimento<? THEN 1 ELSE 0 END),0) vencidas,
        COALESCE(SUM(CASE WHEN vencimento<? THEN valor_saida ELSE 0 END),0) valor_vencidas,
        COALESCE(SUM(CASE WHEN vencimento=? THEN 1 ELSE 0 END),0) hoje,
        COALESCE(SUM(CASE WHEN vencimento=? THEN valor_saida ELSE 0 END),0) valor_hoje,
        COALESCE(SUM(CASE WHEN vencimento>? AND vencimento<=? THEN 1 ELSE 0 END),0) proximas,
        COALESCE(SUM(CASE WHEN vencimento>? AND vencimento<=? THEN valor_saida ELSE 0 END),0) valor_proximas
        FROM centro_lancamentos WHERE $open");
    $s->execute([$today,$today,$today,$today,$today,$end,$today,$end]);$totals=$s->fetch();
    $s=$db->prepare("SELECT id,descricao,cliente_fornecedor,vencimento,valor_saida FROM centro_lancamentos WHERE $open AND vencimento<=? ORDER BY vencimento,id LIMIT 8");$s->execute([$end]);$accounts=$s->fetchAll();
    $month=substr($today,0,7);
    $s=$db->prepare("SELECT f.id,f.nome,COALESCE(p.competencia,DATE_FORMAT(p.data_periodo,'%Y-%m')) competencia,
        SUM(pg.valor+pg.vale_transporte) previsto,SUM(COALESCE(pg.valor_pago,0)) pago,
        SUM(pg.situacao_pagamento='a_confirmar') desconhecidos,COUNT(*) qtd
        FROM centro_folha_pagamentos pg JOIN centro_folha_periodos p ON p.id=pg.periodo_id JOIN centro_funcionarios f ON f.id=pg.funcionario_id
        WHERE p.data_periodo<=? AND COALESCE(p.competencia,DATE_FORMAT(p.data_periodo,'%Y-%m'))<=?
        GROUP BY f.id,f.nome,COALESCE(p.competencia,DATE_FORMAT(p.data_periodo,'%Y-%m'))
        HAVING SUM(pg.valor+pg.vale_transporte)>SUM(COALESCE(pg.valor_pago,0)) OR SUM(pg.situacao_pagamento='a_confirmar')>0
        ORDER BY competencia DESC,f.nome");
    $s->execute([$today,$month]);$staff=$s->fetchAll();
    return ['totals'=>$totals,'accounts'=>$accounts,'staff'=>$staff,'staff_count'=>count(array_unique(array_column($staff,'id'))),'end'=>$end,'month'=>$month];
}
