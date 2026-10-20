<?php
// php tests/producao_test.php — não usa banco nem credenciais.
require_once __DIR__.'/../admin/_producao_domain.php';
require_once __DIR__.'/../admin/_permissoes.php';
function verify(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function rejects(callable $fn): void {try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Entrada inválida foi aceita.');}
foreach(['pcp','ajudante'] as $role){
    foreach(['financeiro.php','financeiro_exportar.php','propostas.php','proposta-imprimir.php','usuarios.php','settings.php','index.php','import_financeiro.php'] as $page)verify(!paginaPermitida($role,$page),'Área antiga exposta: '.$role.' '.$page);
    verify(paginaPermitida($role,'producao.php'),'Produção bloqueada');
    verify(paginaPermitida($role,'producao_arquivo.php'),'Documento bloqueado');
}
verify(!paginaPermitida('ajudante','producao_importar.php'),'Ajudante não pode importar');
verify(!paginaPermitida('ajudante','notificacoes.php')&&!paginaPermitida('ajudante','producao_alertas.php'),'Ajudante não pode consultar notificações de gestão');
verify(paginaPermitida('pcp','producao_importar.php'),'PCP pode importar');
verify(!paginaPermitida('desconhecido','producao.php'),'Perfil desconhecido não pode entrar');
verify(paginaPermitida('admin','usuarios.php'),'Admin gerencia usuários');
$e=prodEtapas([]);verify(prodAvanco($e)===0.0,'Pendente deve ter avanço zero');
$e['prep']=100;$e['mont']=100;verify(prodAvanco($e)===40.0,'Preparação e montagem devem somar 40%');
$e['solda']=50;verify(prodAvanco($e)===52.5,'Solda parcial deve contribuir proporcionalmente');
[$status,$steps]=prodEstado('pintura',$e,'preparacao');verify($status==='pintura'&&prodAvanco($steps)===75.0,'Pintura deve concluir a fabricação anterior');
[$status,$steps]=prodEstado('romaneio',$steps,'pintura');verify(prodAvanco($steps)===90.0,'Romaneio deve concluir pintura, sem expedir');
$steps['exd']=75;[$status,$steps]=prodEstado('romaneio',$steps,'romaneio');verify(prodAvanco($steps)===97.5,'Embarque parcial');
[$status,$steps]=prodEstado('expedido',$steps,'romaneio');verify($status==='expedido'&&prodAvanco($steps)===100.0,'Expedido deve fechar 100%');
[$status,$steps]=prodEstado('pendente',$steps,'expedido');verify(prodAvanco($steps)===0.0,'Reabertura pendente deve zerar as etapas');
rejects(fn()=>prodEtapas(['prep'=>26]));rejects(fn()=>prodEtapas(['solda'=>-25]));rejects(fn()=>prodEstado('invalido',[],'pendente'));
verify(prodPesoGramas('12,345')*3===37035,'Peso unitário vezes quantidade deve ser exato em gramas');
verify(prodPesoGramas('0.001')*1000===1000,'Precisão de gramas');
rejects(fn()=>prodPesoGramas('-1'));rejects(fn()=>prodPesoGramas('NaN'));rejects(fn()=>prodPesoGramas('1.0001'));
verify(prodData('2026-10-19')==='2026-10-19','Data válida');rejects(fn()=>prodData('2026-02-30'));rejects(fn()=>prodData('',true));
[$status,$steps,$warnings]=prodImportAvanco('preparacao',['prep'=>100,'mont'=>100,'solda'=>0,'acab'=>0,'pint'=>0,'exd'=>100],true);
verify($status==='preparacao'&&$steps['exd']===0&&prodAvanco($steps)===40.0&&count($warnings)===1,'Linha 103 do modelo: não expedir fabricação incompleta nem bloquear a importação');
verify($warnings[0]['etapas_originais']['exd']===100,'Indicador original deve ser preservado no aviso de auditoria');
[$status,$steps,$warnings]=prodImportAvanco('romaneio',array_fill_keys(array_keys(PROD_ETAPAS),100),true);
verify($status==='expedido'&&prodAvanco($steps)===100.0&&!$warnings,'Expedição consistente deve ser preservada');
echo "PASS: perfis, bloqueios por página, etapas parciais, avanço por pesos, reabertura, peso e datas.\n";
