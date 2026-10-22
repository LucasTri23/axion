<?php
require_once __DIR__.'/../admin/_folha_pagamentos.php';
function expect(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
expect(folhaCentavos('1234,56')===123456,'Conversão de moeda');
expect(folhaDecimal(123456)==='1234.56','Valor decimal');
foreach(['-1','10.001','1e3',''] as $bad){
    try{folhaCentavos($bad);throw new RuntimeException('Valor inválido aceito');}catch(InvalidArgumentException $expected){}
}
expect(folhaSituacao(10000,10000,1,2)==='a_confirmar','Não quitar mês com lançamento desconhecido');
expect(folhaSituacao(10000,10000,0,2)==='pago','Quitação integral');
expect(folhaSituacao(10000,5000,0,2)==='parcial','Pagamento parcial');
expect(folhaSituacao(10000,0,0,2)==='pendente','Pagamento pendente');
expect(folhaSituacao(0,0,0,0)==='sem_lancamentos','Sem lançamentos não significa pago');
expect(folhaSituacao(0,0,0,1)==='pendente','Lançamento sem valor não significa pago');
expect(folhaData('2026-10-22')==='2026-10-22','Data válida');
try{folhaData('2026-02-30');throw new RuntimeException('Data inválida aceita');}catch(InvalidArgumentException $expected){}
echo "PASS: centavos, pagamento parcial, competência sem quitação presumida e datas.\n";
