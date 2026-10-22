<?php
require_once __DIR__.'/_auth.php';
require_once __DIR__.'/_layout.php';
require_once __DIR__.'/_financeiro_filtros.php';
require_once __DIR__.'/_financeiro_recorrencias.php';
$db=db();$ready=recorrenciasDisponiveis($db);$error='';$preview=null;$form=null;
$month=substr(financeiroHoje(),0,7);
try{$month=recorrenciaMes($_POST['mes']??$_GET['mes']??$month);}catch(InvalidArgumentException $e){$error=$e->getMessage();}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfVerify();
    try{
        if(!$ready)throw new InvalidArgumentException('A estrutura das recorrências ainda não está disponível.');
        $month=recorrenciaMes($_POST['mes']??'');
        $action=$_POST['action']??'';
        if($action==='salvar'){
            $id=(int)($_POST['id']??0);$data=recorrenciaValidar($_POST);
            $db->beginTransaction();$old=null;
            if($id){$s=$db->prepare('SELECT * FROM centro_recorrencias WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();if(!$old)throw new InvalidArgumentException('Despesa não encontrada.');}
            $values=array_values($data);
            if($id){$db->prepare('UPDATE centro_recorrencias SET descricao=?,fornecedor=?,os=?,centro=?,valor=?,dia=?,inicio=?,fim=?,ativo=? WHERE id=?')->execute([...$values,$id]);}
            else{$db->prepare('INSERT INTO centro_recorrencias(descricao,fornecedor,os,centro,valor,dia,inicio,fim,ativo) VALUES(?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)$db->lastInsertId();}
            auditLog($old?'update':'create','centro_recorrencias',$id,['antes'=>$old,'depois'=>$data]);$db->commit();
            redirect('/admin/financeiro_recorrentes.php?mes='.urlencode($month).'&saved=1');
        }elseif($action==='preview'){
            $preview=recorrenciasPrevia($db,$month);
        }elseif($action==='gerar'){
            if(!isset($_POST['confirmar']))throw new InvalidArgumentException('Confirme a conferência dos lançamentos.');
            $count=recorrenciasGerar($db,$month,is_string($_POST['assinatura']??null)?$_POST['assinatura']:'',financeiroHoje());
            redirect('/admin/financeiro_recorrentes.php?mes='.urlencode($month).'&generated='.$count);
        }else{throw new InvalidArgumentException('Ação inválida.');}
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível concluir. Nenhum lançamento novo foi gravado. Tente novamente.';
        if(!($e instanceof InvalidArgumentException))error_log('[Axion recorrências] '.$e->getMessage());
        if(($_POST['action']??'')==='salvar')$form=$_POST;
    }
}
if($ready&&isset($_GET['edit'])&&!$form){$s=$db->prepare('SELECT * FROM centro_recorrencias WHERE id=?');$s->execute([(int)$_GET['edit']]);$form=$s->fetch()?:null;if(!$form)$error='Despesa não encontrada.';}
$rules=$ready?$db->query('SELECT * FROM centro_recorrencias ORDER BY ativo DESC,descricao,id')->fetchAll():[];
$generated=[];
if($ready){$s=$db->prepare('SELECT g.*,l.descricao,l.valor_saida,l.vencimento FROM centro_recorrencias_geradas g LEFT JOIN centro_lancamentos l ON l.id=g.lancamento_id WHERE g.competencia=? ORDER BY l.vencimento,g.id');$s->execute([$month]);$generated=$s->fetchAll();}
layout_start('Despesas recorrentes','financeiro');$finTab='recorrentes';require __DIR__.'/_financeiro_nav.php';
?>
<link rel="stylesheet" href="/css/financeiro-rotina.css?v=20261022">
<?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success">Despesa recorrente salva. Lançamentos já gerados não foram alterados.</div><?php endif ?>
<?php if(isset($_GET['generated'])): ?><div class="alert alert-success"><?= (int)$_GET['generated'] ?> lançamentos criados como pendentes. Confira em Contas a pagar.</div><?php endif ?>
<div class="card routine-header"><div><h2>Despesas recorrentes</h2><p class="text-muted">Cadastre uma vez, confira a prévia e gere as despesas mensais. Nenhum pagamento é marcado automaticamente.</p></div><a class="btn btn-ghost" href="/admin/financeiro.php">← Visão geral</a></div>
<?php if(!$ready): ?><div class="card">A estrutura do banco precisa ser atualizada antes de usar as recorrências.</div><?php else: ?>
<div class="card"><h3>Gerar despesas do mês</h3><form method="POST" class="routine-toolbar"><?= csrfField() ?><input type="hidden" name="action" value="preview"><div class="fg"><label for="rec-month">Competência</label><input id="rec-month" type="month" name="mes" value="<?= e($month) ?>" min="2000-01" max="2099-12" required></div><button class="btn btn-primary">Conferir antes de gerar</button></form><p class="text-muted">Cada despesa é gerada uma única vez por mês. Se o dia não existir naquele mês, o vencimento será no último dia. Custos fixos antigos não são convertidos automaticamente, para evitar duplicidades.</p></div>
<?php if($preview!==null):$total=0;foreach($preview as $r)$total+=folhaCentavos($r['valor']); ?>
<section class="card routine-preview" aria-label="Prévia de despesas"><h3>Conferência · <?= e($month) ?></h3><p><?= count($preview) ?> novos lançamentos · <strong><?= e(brl($total/100)) ?></strong></p>
<?php if(!$preview): ?><p>Nenhuma despesa nova para este mês. Confira o início, término e situação do cadastro, ou os lançamentos já gerados abaixo.</p><?php else: ?>
<div class="routine-scroll"><table><thead><tr><th>Despesa / fornecedor</th><th>Vencimento</th><th>Obra / centro</th><th>Valor</th></tr></thead><tbody><?php foreach($preview as $r): ?><tr><td><?= e($r['descricao']) ?><div class="td-sub"><?= e($r['fornecedor']) ?></div></td><td><?= e(date('d/m/Y',strtotime($r['vencimento']))) ?></td><td><?= e(($r['os']?:'Todas').' / '.$r['centro']) ?></td><td class="mono"><?= e(brl($r['valor'])) ?></td></tr><?php endforeach ?></tbody></table></div>
<form method="POST" class="routine-confirm"><?= csrfField() ?><input type="hidden" name="action" value="gerar"><input type="hidden" name="mes" value="<?= e($month) ?>"><input type="hidden" name="assinatura" value="<?= e(recorrenciasAssinatura($preview,$month)) ?>"><label><input type="checkbox" name="confirmar" required> Conferi valores e vencimentos e verifiquei se estas contas já foram lançadas manualmente.</label><button class="btn btn-primary">Gerar <?= count($preview) ?> despesas pendentes</button></form>
<?php endif ?></section><?php endif ?>
<details class="card" <?= $form||isset($_GET['novo'])?'open':'' ?>><summary class="routine-summary"><?= $form?'Editar despesa recorrente':'Cadastrar despesa recorrente' ?></summary><p class="text-muted">As alterações valem para os próximos lançamentos. Para encerrar uma despesa, desmarque “Ativa” ou informe o mês final.</p><form method="POST" class="routine-form"><?= csrfField() ?><input type="hidden" name="action" value="salvar"><input type="hidden" name="id" value="<?= (int)($form['id']??0) ?>"><input type="hidden" name="mes" value="<?= e($month) ?>">
<?php foreach(['descricao'=>['Descrição',255],'fornecedor'=>['Fornecedor',255],'os'=>['Obra / OS',20],'centro'=>['Centro de custo',100]] as $key=>[$label,$max]): ?><div class="fg"><label for="rec-<?= $key ?>"><?= $label ?></label><input id="rec-<?= $key ?>" name="<?= $key ?>" value="<?= e($form[$key]??($key==='centro'?'GERAL':'')) ?>" maxlength="<?= $max ?>" <?= $key==='descricao'?'required':'' ?>></div><?php endforeach ?>
<div class="fg"><label for="rec-valor">Valor mensal (R$)</label><input id="rec-valor" type="number" name="valor" step="0.01" min="0.01" max="9999999999.99" value="<?= e($form['valor']??'') ?>" required></div><div class="fg"><label for="rec-dia">Dia do vencimento</label><input id="rec-dia" type="number" name="dia" min="1" max="31" value="<?= e($form['dia']??'10') ?>" required></div><div class="fg"><label for="rec-inicio">Primeiro mês</label><input id="rec-inicio" type="month" name="inicio" min="2000-01" max="2099-12" value="<?= e($form['inicio']??$month) ?>" required></div><div class="fg"><label for="rec-fim">Último mês (opcional)</label><input id="rec-fim" type="month" name="fim" min="2000-01" max="2099-12" value="<?= e($form['fim']??'') ?>"></div><label class="routine-check"><input type="checkbox" name="ativo" <?= !$form||isset($form['ativo'])&&$form['ativo']?'checked':'' ?>> Ativa</label><div class="routine-actions"><button class="btn btn-primary">Salvar despesa</button><?php if($form): ?><a class="btn btn-ghost" href="?mes=<?= e($month) ?>">Cancelar edição</a><?php endif ?></div></form></details>
<div class="card"><h3>Despesas cadastradas</h3><?php if(!$rules): ?><p class="text-muted">Nenhuma despesa recorrente cadastrada. Comece pelo cadastro acima.</p><?php else: ?><div class="routine-scroll"><table><thead><tr><th>Despesa</th><th>Valor mensal</th><th>Vencimento</th><th>Vigência</th><th>Situação</th><th></th></tr></thead><tbody><?php foreach($rules as $r): ?><tr><td><strong><?= e($r['descricao']) ?></strong><div class="td-sub"><?= e($r['fornecedor']) ?></div></td><td class="mono"><?= e(brl($r['valor'])) ?></td><td>Dia <?= (int)$r['dia'] ?></td><td><?= e($r['inicio']) ?> até <?= e($r['fim']??'sem término') ?></td><td><?= $r['ativo']?'Ativa':'Pausada' ?></td><td><a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$r['id'] ?>&amp;mes=<?= e($month) ?>">Editar / pausar</a></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></div>
<div class="card"><h3>Já geradas · <?= e($month) ?></h3><p class="text-muted">Pagamentos e correções são feitos no lançamento. Excluir uma conta não libera sua geração novamente neste mês.</p><?php if(!$generated): ?><p>Nenhuma despesa gerada nesta competência.</p><?php else: ?><div class="routine-scroll"><table><thead><tr><th>Despesa</th><th>Vencimento</th><th>Valor</th><th></th></tr></thead><tbody><?php foreach($generated as $g): ?><tr><td><?= e($g['descricao']??'Lançamento excluído') ?></td><td><?= $g['vencimento']?e(date('d/m/Y',strtotime($g['vencimento']))):'—' ?></td><td><?= $g['valor_saida']!==null?e(brl($g['valor_saida'])):'—' ?></td><td><?php if($g['lancamento_id']): ?><a class="btn btn-secondary btn-sm" href="/admin/financeiro_fluxo.php?edit=<?= (int)$g['lancamento_id'] ?>">Abrir lançamento</a><?php else: ?>Histórico preservado<?php endif ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></div>
<?php endif;layout_end(); ?>
