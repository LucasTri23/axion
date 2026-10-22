<?php
function folhaMes($value): string {
    $s=is_string($value)?$value:'';
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$s)?$s:(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
}
function folhaCentavos($value): int {
    $value=str_replace(',','.',trim((string)$value));
    if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$value))throw new InvalidArgumentException('Informe um valor válido, com até duas casas decimais.');
    [$a,$b]=array_pad(explode('.',$value,2),2,'');return (int)$a*100+(int)str_pad($b,2,'0');
}
function folhaDecimal(int $cents): string {return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
function folhaSituacao(int $previsto,int $pago,int $desconhecidos,int $lancamentos): string {
    if(!$lancamentos)return 'sem_lancamentos';
    if($desconhecidos)return 'a_confirmar';
    if($previsto>0&&$pago>=$previsto)return 'pago';
    return $pago>0?'parcial':'pendente';
}
const FOLHA_SITUACOES=['sem_lancamentos'=>'Sem lançamentos','a_confirmar'=>'A confirmar','pendente'=>'Pendente','parcial'=>'Parcial','pago'=>'Pago'];
function folhaData(string $value): string {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('Informe uma data de pagamento válida.');return $value;
}
