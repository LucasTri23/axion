<?php
require_once __DIR__.'/_producao_domain.php';
function prodReady(PDO $db): bool {
    return producaoDisponivel($db);
}
function prodAudit(PDO $db,string $acao,?int $os,?int $peca,$antes,$depois,bool $notify=true): void {
    $db->prepare('INSERT INTO producao_auditoria(os_id,peca_id,user_id,login,acao,antes,depois) VALUES(?,?,?,?,?,?,?)')->execute([$os,$peca,$_SESSION['user_id'],$_SESSION['username'],$acao,json_encode($antes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($depois,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $id=(int)$db->lastInsertId();
    if($notify) {
        $s=$db->prepare("INSERT INTO producao_notificacoes(user_id,auditoria_id) SELECT u.id,? FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE COALESCE(p.ativo,1)=1 AND COALESCE(p.perfil,'admin') IN ('admin','pcp')");
        $s->execute([$id]);
    }
}
function prodRequireManager(): void { if(!podeGerenciarProducao()) { http_response_code(403); exit('Ação exclusiva do ADM e PCP.'); } }
function prodPecaDados(PDO $db,array $input): array {
    $qty=filter_var($input['quantidade']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    if($qty===false) throw new InvalidArgumentException('Quantidade deve ser um inteiro positivo de até 1.000.000.');
    $grams=prodPesoGramas((string)($input['peso_unitario']??'0'));
    if($grams*$qty>1000000000000)throw new InvalidArgumentException('Peso total da linha excede o limite. Confira quantidade e peso unitário.');
    $responsavel=(int)($input['responsavel_id']??0);
    if($responsavel) {
        $s=$db->prepare('SELECT u.id FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE u.id=? AND COALESCE(p.ativo,1)=1'); $s->execute([$responsavel]);
        if(!$s->fetchColumn()) throw new InvalidArgumentException('Responsável não encontrado ou inativo.');
    }
    return [prodData($input['data']??''),prodTexto($input,'avulsas',100),prodTexto($input,'descricao',255,true),prodTexto($input,'tag',150),prodTexto($input,'desenho',255),prodTexto($input,'revisao',30),$qty,number_format($grams/1000,3,'.',''),$responsavel?:null,prodTexto($input,'romaneio',100)];
}
function prodInserirPeca(PDO $db,int $os,array $dados,string $status,array $etapas): int {
    $db->prepare('INSERT INTO producao_pecas(os_id,data,avulsas,descricao,tag,desenho,revisao,quantidade,peso_unitario,responsavel_id,romaneio,status,etapas) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge([$os],$dados,[$status,json_encode($etapas)]));
    return (int)$db->lastInsertId();
}
function prodSalvarPeca(PDO $db,array $input,int $os): void {
    $id=(int)($input['id']??0);
    $db->beginTransaction();
    try {
        $s=$db->prepare('SELECT * FROM producao_pecas WHERE id=? AND os_id=? FOR UPDATE'); $s->execute([$id,$os]); $old=$s->fetch();
        if(!$old) throw new InvalidArgumentException('Peça não encontrada nesta OS.');
        if((int)$old['versao']!==(int)($input['versao']??0)) throw new InvalidArgumentException('Esta peça foi atualizada por outra pessoa. Reabra a edição para conferir os dados atuais.');
        [$status,$etapas]=prodEstado($input['status']??'',(array)($input['etapas']??[]),$old['status']);
        if(podeGerenciarProducao()) {
            $dados=prodPecaDados($db,$input);
            $db->prepare('UPDATE producao_pecas SET data=?,avulsas=?,descricao=?,tag=?,desenho=?,revisao=?,quantidade=?,peso_unitario=?,responsavel_id=?,romaneio=?,status=?,etapas=?,versao=versao+1 WHERE id=?')->execute(array_merge($dados,[$status,json_encode($etapas),$id]));
        } else {
            $db->prepare('UPDATE producao_pecas SET status=?,etapas=?,versao=versao+1 WHERE id=?')->execute([$status,json_encode($etapas),$id]);
        }
        $s=$db->prepare('SELECT * FROM producao_pecas WHERE id=?');$s->execute([$id]);
        prodAudit($db,'atualizar_peca',$os,$id,$old,$s->fetch());
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
function prodFiltros(PDO $db,int $os,array $input): array {
    $where=['os_id=?'];$params=[$os];
    $q=trim(is_string($input['q']??null)?$input['q']:'');
    if($q!=='') { $where[]='(descricao LIKE ? OR tag LIKE ? OR desenho LIKE ? OR romaneio LIKE ?)'; array_push($params,...array_fill(0,4,'%'.$q.'%')); }
    $status=$input['status']??''; if(isset(PROD_STATUS[$status])) {$where[]='status=?';$params[]=$status;}
    if((int)($input['responsavel']??0)>0) {$where[]='responsavel_id=?';$params[]=(int)$input['responsavel'];}
    $s=$db->prepare('SELECT p.*,u.username AS responsavel FROM producao_pecas p LEFT JOIN users u ON u.id=p.responsavel_id WHERE '.implode(' AND ',$where).' ORDER BY p.id');$s->execute($params);return $s->fetchAll();
}
