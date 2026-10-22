<?php
require_once __DIR__.'/_folha_pagamentos.php';

function recorrenciaMes($value): string {
    if(!is_string($value)||!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$value)) {
        throw new InvalidArgumentException('Informe uma competência válida entre 2000 e 2099.');
    }
    return $value;
}

function recorrenciaValidar(array $input): array {
    $data=[];
    foreach(['descricao'=>255,'fornecedor'=>255,'os'=>20,'centro'=>100] as $key=>$max){
        $value=is_string($input[$key]??null)?trim($input[$key]):'';
        if(!preg_match('/^.{0,'.$max.'}$/usD',$value)||($key==='descricao'&&$value===''))throw new InvalidArgumentException('Confira a descrição e os dados da despesa.');
        $data[$key]=$value;
    }
    $cents=folhaCentavos(is_string($input['valor']??null)?$input['valor']:'');
    if($cents<=0)throw new InvalidArgumentException('O valor da despesa deve ser maior que zero.');
    $data['valor']=folhaDecimal($cents);
    $day=filter_var($input['dia']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>31]]);
    if($day===false)throw new InvalidArgumentException('Escolha um dia de vencimento entre 1 e 31.');
    $data['dia']=$day;
    $data['inicio']=recorrenciaMes($input['inicio']??'');
    $data['fim']=empty($input['fim'])?null:recorrenciaMes($input['fim']);
    if($data['fim']!==null&&$data['fim']<$data['inicio'])throw new InvalidArgumentException('O mês final deve ser igual ou posterior ao início.');
    $data['centro']=$data['centro']?:'GERAL';
    $data['ativo']=isset($input['ativo'])?1:0;
    return $data;
}

function recorrenciaVencimento(string $month,int $day): string {
    recorrenciaMes($month);
    if($day<1||$day>31)throw new InvalidArgumentException('Dia inválido.');
    $date=new DateTimeImmutable($month.'-01');
    return $month.'-'.str_pad((string)min($day,(int)$date->format('t')),2,'0',STR_PAD_LEFT);
}

function recorrenciasDisponiveis(PDO $db): bool {
    try{$db->query('SELECT id FROM centro_recorrencias_geradas LIMIT 0');return true;}
    catch(PDOException $e){if(($e->errorInfo[1]??0)===1146)return false;throw $e;}
}

function recorrenciasPrevia(PDO $db,string $month,bool $lock=false): array {
    recorrenciaMes($month);
    // Sempre bloqueia as regras na mesma ordem; a chave única protege a competência.
    $s=$db->prepare('SELECT * FROM centro_recorrencias WHERE ativo=1 AND inicio<=? AND (fim IS NULL OR fim>=?) ORDER BY id'.($lock?' FOR UPDATE':''));
    $s->execute([$month,$month]);$rules=$s->fetchAll();$rows=[];
    $generated=$db->prepare('SELECT id FROM centro_recorrencias_geradas WHERE recorrencia_id=? AND competencia=?');
    foreach($rules as $r){
        $generated->execute([$r['id'],$month]);
        if($generated->fetchColumn())continue;
        $r['vencimento']=recorrenciaVencimento($month,(int)$r['dia']);$rows[]=$r;
    }
    return $rows;
}

function recorrenciasAssinatura(array $rows,string $month): string {
    return hash('sha256',json_encode([$month,$rows],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function recorrenciasGerar(PDO $db,string $month,string $signature,string $today): int {
    recorrenciaMes($month);folhaData($today);
    $db->beginTransaction();
    try{
        $rows=recorrenciasPrevia($db,$month,true);
        if(!$rows)throw new InvalidArgumentException('Nenhum lançamento novo para gerar. As despesas deste mês podem já ter sido geradas.');
        if(!hash_equals(recorrenciasAssinatura($rows,$month),$signature))throw new InvalidArgumentException('O cadastro mudou desde a prévia. Confira novamente antes de gerar.');
        $insert=$db->prepare("INSERT INTO centro_lancamentos(tipo,descricao,cliente_fornecedor,os,centro,data_lancamento,vencimento,valor_entrada,valor_saida,situacao,observacao) VALUES('SAIDA',?,?,?,?,?,?,0,?,'',?)");
        $map=$db->prepare('INSERT INTO centro_recorrencias_geradas(recorrencia_id,competencia,lancamento_id) VALUES(?,?,?)');
        foreach($rows as $r){
            $insert->execute([$r['descricao'],$r['fornecedor'],$r['os'],$r['centro'],$today,$r['vencimento'],$r['valor'],'Despesa recorrente #'.$r['id'].' | competência '.$month]);
            $id=(int)$db->lastInsertId();$map->execute([$r['id'],$month,$id]);
            auditLog('create','centro_lancamentos',$id,['recorrencia_id'=>(int)$r['id'],'competencia'=>$month,'valor'=>$r['valor'],'vencimento'=>$r['vencimento']]);
        }
        $db->commit();return count($rows);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
