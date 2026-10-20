<?php
const PROD_ETAPAS = ['prep' => ['Preparação',15], 'mont' => ['Montagem',25], 'solda' => ['Solda',25], 'acab' => ['Acabamento',10], 'pint' => ['Pintura',15], 'exd' => ['Expedição',10]];
const PROD_STATUS = ['pendente'=>'Pendente','preparacao'=>'Preparação','pintura'=>'Pintura','romaneio'=>'Romaneio','expedido'=>'Expedido'];
function prodEtapas(array $input): array {
    $out=[];
    foreach (PROD_ETAPAS as $key=>$spec) {
        $value = filter_var($input[$key] ?? 0, FILTER_VALIDATE_INT);
        if (!in_array($value,[0,25,50,75,100],true)) throw new InvalidArgumentException('Use 0%, 25%, 50%, 75% ou 100% nas etapas.');
        $out[$key]=$value;
    }
    return $out;
}
function prodEstado(string $status, array $etapas, string $anterior): array {
    if (!isset(PROD_STATUS[$status])) throw new InvalidArgumentException('Status inválido.');
    $e=prodEtapas($etapas);
    if ($status !== $anterior) {
        if ($status==='pendente') $e=array_fill_keys(array_keys(PROD_ETAPAS),0);
        if ($status==='preparacao') { $e['pint']=0; $e['exd']=0; }
        if ($status==='pintura') { foreach(['prep','mont','solda','acab'] as $k) $e[$k]=100; $e['exd']=0; }
        if ($status==='romaneio') { foreach(['prep','mont','solda','acab','pint'] as $k) $e[$k]=100; }
    }
    if ($status==='expedido' || $e['exd']===100) { $status='expedido'; $e=array_fill_keys(array_keys(PROD_ETAPAS),100); }
    if ($status==='pendente' && array_sum($e)>0) $status='preparacao';
    if ($status!=='expedido' && $status!=='romaneio' && $e['exd']>0) $status='romaneio';
    if ($status==='preparacao' && $e['pint']>0) $status='pintura';
    return [$status,$e];
}
function prodAvanco(array $etapas): float {
    $total=0; foreach(PROD_ETAPAS as $key=>$spec) $total+=($etapas[$key]??0)*$spec[1]/100;
    return $total;
}
function prodPesoGramas(string $input): int {
    $value=str_replace(',','.',trim($input));
    if (!preg_match('/^\d{1,7}(\.\d{1,3})?$/',$value)) throw new InvalidArgumentException('Informe peso unitário em kg, com até três casas decimais.');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    return (int)$whole*1000+(int)str_pad($fraction,3,'0');
}
function prodData(string $value, bool $required=false): ?string {
    if($value===''&&!$required) return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$date||$date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Data inválida.');
    return $value;
}
function prodTexto(array $input,string $key,int $max,bool $required=false): string {
    $v=trim(is_string($input[$key]??null)?$input[$key]:'');
    if(($required&&$v==='')||!preg_match('/^.{0,'.$max.'}$/us',$v)) throw new InvalidArgumentException('Confira o campo '.$key.'.');
    return $v;
}
