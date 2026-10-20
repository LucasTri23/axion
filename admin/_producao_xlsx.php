<?php
require_once __DIR__.'/_producao_domain.php';
function prodCabecalho(string $s): string {
    $s=strtr(strtoupper(trim($s)),['Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C','á'=>'A','à'=>'A','ã'=>'A','â'=>'A','é'=>'E','ê'=>'E','í'=>'I','ó'=>'O','ô'=>'O','õ'=>'O','ú'=>'U','ç'=>'C']);
    return preg_replace('/[^A-Z0-9]/','',$s);
}
function prodXml(string $xml): SimpleXMLElement {
    if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)throw new InvalidArgumentException('XML com entidades externas não é aceito.');
    $old=libxml_use_internal_errors(true);
    try{$result=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOBLANKS);if(!$result)throw new InvalidArgumentException('Arquivo Excel inválido.');return $result;}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
}
function prodXlsxStream(ZipArchive $zip,string $path): Generator {
    $stat=$zip->statName($path);if(!$stat||$stat['size']>120*1024*1024)throw new InvalidArgumentException('Aba muito grande ou inválida; remova formatação de linhas e colunas vazias.');
    $source=$zip->getStream($path);if(!$source)throw new InvalidArgumentException('Não foi possível abrir a aba.');
    $temp=tempnam(sys_get_temp_dir(),'axion_xlsx_');$target=fopen($temp,'wb');$reader=new XMLReader();
    try{
        stream_copy_to_stream($source,$target,120*1024*1024+1);fclose($target);$target=null;
        if(!$reader->open($temp,null,LIBXML_NONET|LIBXML_NOBLANKS))throw new InvalidArgumentException('XML da planilha inválido.');
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES,false);
        while($reader->read()) {
            if($reader->nodeType===XMLReader::DOC_TYPE)throw new InvalidArgumentException('XML com DTD não aceito.');
            if($reader->nodeType===XMLReader::ELEMENT&&in_array($reader->localName,['si','row'],true))yield prodXml($reader->readOuterXml());
        }
    }finally{$reader->close();if(is_resource($source))fclose($source);if(is_resource($target))fclose($target);if(is_file($temp))unlink($temp);}
}
function prodXlsxData(string $s): ?string {
    if($s==='')return null;
    if(is_numeric($s)&&$s>30000&&$s<80000)return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int)$s.' days')->format('Y-m-d');
    if(preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$s,$m))$s=$m[3].'-'.$m[2].'-'.$m[1];
    return prodData($s);
}
function prodLerXlsx(string $file): array {
    foreach(['ZipArchive','XMLReader','SimpleXMLElement'] as $class)if(!class_exists($class))throw new InvalidArgumentException('A hospedagem precisa habilitar ZIP, XMLReader e SimpleXML para importar XLSX.');
    $zip=new ZipArchive();if($zip->open($file)!==true)throw new InvalidArgumentException('Envie um arquivo XLSX válido.');
    try {
        foreach(['xl/workbook.xml','xl/_rels/workbook.xml.rels'] as $path){$info=$zip->statName($path);if(!$info||$info['size']>2*1024*1024)throw new InvalidArgumentException('Estrutura XLSX inválida.');}
        $book=prodXml($zip->getFromName('xl/workbook.xml'));$rels=prodXml($zip->getFromName('xl/_rels/workbook.xml.rels'));$targets=[];
        foreach($rels->xpath('//*[local-name()="Relationship"]') as $rel)if(str_ends_with((string)$rel['Type'],'/worksheet'))$targets[(string)$rel['Id']]=(string)$rel['Target'];
        $sheetPath=null;
        foreach($book->xpath('//*[local-name()="sheet"]') as $sheet){$a=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$id=(string)$a['id'];if(isset($targets[$id])){$candidate=$targets[$id];$candidate=str_starts_with($candidate,'/')?ltrim($candidate,'/'):'xl/'.$candidate;if(str_contains($candidate,'..'))continue;if($sheetPath===null||prodCabecalho((string)$sheet['name'])==='AVANCO')$sheetPath=$candidate;}}
        if(!$sheetPath)throw new InvalidArgumentException('Nenhuma aba de fabricação encontrada.');
        $strings=[];
        if($zip->locateName('xl/sharedStrings.xml')!==false)foreach(prodXlsxStream($zip,'xl/sharedStrings.xml') as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$text.=(string)$t;$strings[]=$text;if(count($strings)>300000)throw new InvalidArgumentException('Planilha com textos demais. Separe por OS.');}
        $aliases=['DATA'=>'data','GRD'=>'data','AVULSAS'=>'avulsas','DESCRICAODOMATERIAL'=>'descricao','DESCRICAOMATERIAL'=>'descricao','TAG'=>'tag','NDESENHO'=>'desenho','NODESENHO'=>'desenho','NUMERODESENHO'=>'desenho','PESO'=>'peso_unitario','PESOUNITARIO'=>'peso_unitario','PESOUNITARIOKG'=>'peso_unitario','RESPONSAVEL'=>'responsavel','RESPOSAVEL'=>'responsavel','NROMANEIO'=>'romaneio','NOROMANEIO'=>'romaneio','STATUSFABRICACAO'=>'status','STATUSDEFABRICACAO'=>'status','QUANTIDADE'=>'quantidade','QTD'=>'quantidade','QTDPECA'=>'quantidade','REVISAO'=>'revisao','PREP'=>'prep','MONT'=>'mont','SOLDA'=>'solda','ACAB'=>'acab','ACABAME'=>'acab','ACABAMENTO'=>'acab','PINT'=>'pint','PINTURA'=>'pint','EXD'=>'exd','EMBARQUE'=>'exd'];
        $map=[];$rows=[];$errors=[];$warnings=[];$header=false;
        foreach(prodXlsxStream($zip,$sheetPath) as $row) {
            $line=(int)$row['r'];$cells=[];
            foreach($row->xpath('./*[local-name()="c"]') as $c){$col=preg_replace('/\d/','',(string)$c['r']);$v=$c->xpath('./*[local-name()="v"]');$value=$v?(string)$v[0]:'';$type=(string)$c['t'];if($type==='s')$value=$strings[(int)$value]??'';if($type==='inlineStr'){$value='';foreach($c->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;}if($value!=='')$cells[$col]=trim($value);}
            if(!$header){foreach($cells as $col=>$value){$norm=prodCabecalho($value);if(isset($aliases[$norm]))$map[$col]=$aliases[$norm];}if(in_array('descricao',$map,true)&&in_array('peso_unitario',$map,true)&&in_array('desenho',$map,true))$header=true;else $map=[];continue;}
            $item=[];foreach($map as $col=>$key)if(isset($cells[$col]))$item[$key]=$cells[$col];
            if(empty($item['descricao'])&&empty($item['tag'])&&empty($item['desenho']))continue;
            try {
                $item['data']=prodXlsxData($item['data']??'')??'';
                $item['quantidade']=$item['quantidade']??'1';
                if(!ctype_digit($item['quantidade'])||(int)$item['quantidade']<1||(int)$item['quantidade']>1000000)throw new InvalidArgumentException('Quantidade inválida.');
                if(!is_numeric(str_replace(',','.',$item['peso_unitario']??'')))throw new InvalidArgumentException('Peso inválido.');
                $item['peso_unitario']=number_format((float)str_replace(',','.',$item['peso_unitario']),3,'.','');prodPesoGramas($item['peso_unitario']);
                if(prodPesoGramas($item['peso_unitario'])*(int)$item['quantidade']>1000000000000)throw new InvalidArgumentException('Peso total excessivo. Confira quantidade e peso unitário.');
                foreach(['descricao'=>255,'tag'=>150,'desenho'=>255,'avulsas'=>100,'revisao'=>30,'romaneio'=>100] as $key=>$limit)$item[$key]=prodTexto($item,$key,$limit,$key==='descricao');
                $e=[];$hasSteps=false;foreach(PROD_ETAPAS as $key=>$spec){$value=$item[$key]??'0';$hasSteps=$hasSteps||isset($item[$key]);if(!is_numeric($value))throw new InvalidArgumentException('Avanço inválido em '.$key);$number=(float)$value;$e[$key]=$number>0&&$number<=1?$number*100:$number;}
                $e=prodEtapas($e);$raw=prodCabecalho($item['status']??'');$status=match($raw){'PINTURA','AGUARDANDOJATO'=>'pintura','ROMANEIO','CONCLUIDO'=>'romaneio','EXPEDIDO','ENTREGUE'=>'expedido','PREPARACAO','EMFABRICACAO'=>'preparacao',default=>'pendente'};
                [$status,$e,$rowWarnings]=prodImportAvanco($status,$e,$hasSteps);
                foreach($rowWarnings as $warning)$warnings[]=array_merge(['linha'=>$line,'tag'=>$item['tag']],$warning);
                $item['etapas']=$e;$item['status']=$status;$item['linha']=$line;$rows[]=$item;
                if(count($rows)>5000)throw new RuntimeException('Importe no máximo 5.000 itens por arquivo.');
            }catch(InvalidArgumentException $e){$errors[]='Linha '.$line.': '.$e->getMessage();if(count($errors)>100)throw new InvalidArgumentException('Mais de 100 erros. Revise o padrão da planilha.');}
        }
        if(!$header)throw new InvalidArgumentException('Cabeçalho não encontrado. Inclua DESCRIÇÃO DO MATERIAL, Nº DESENHO e PESO.');
        if(!$rows&&!$errors)throw new InvalidArgumentException('Nenhuma peça preenchida encontrada.');
        return ['rows'=>$rows,'errors'=>$errors,'warnings'=>$warnings];
    }finally{$zip->close();}
}
