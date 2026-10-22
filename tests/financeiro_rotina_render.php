<?php
// Renderiza as páginas reais com dados de teste, sem autenticação nem banco remoto.
ob_start();require __DIR__.'/financeiro_rotina_test.php';ob_end_clean();
function db(): PDO {global $db;return $db;}
function csrfVerify(): void {}
function csrfField(): string {return '<input type="hidden" name="csrf" value="fixture">';}
function e($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function brl($value): string {return 'R$ '.number_format((float)$value,2,',','.');}
function layout_start(string $title,string $key): void {
    preg_match('~<style>(.*?)</style>~s',file_get_contents(__DIR__.'/../admin/_layout.php'),$m);
    echo '<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$m[1].file_get_contents(__DIR__.'/../css/financeiro-rotina.css').'html,body{height:auto;overflow-x:clip}</style><body><main style="padding:24px;max-width:1450px;margin:auto">';
}
function layout_end(): void {echo '</main></body></html>';}
$_SERVER['REQUEST_METHOD']='POST';$_POST=['mes'=>'2026-11','action'=>'preview'];$_GET=[];
$root=dirname(__DIR__).'/admin';
$source=file_get_contents($root.'/financeiro_recorrentes.php');
$source=str_replace(["require_once __DIR__.'/_auth.php';","require_once __DIR__.'/_layout.php';"],'',$source);
$source=str_replace('__DIR__',var_export($root,true),$source);
ob_start();eval(substr($source,5));$html=ob_get_clean();
if(str_contains($html,'Não foi possível concluir'))throw new RuntimeException('Falha na renderização da prévia');
file_put_contents(sys_get_temp_dir().'/axion-recorrentes-test.html',$html);
ob_start();layout_start('Rotina','financeiro');require $root.'/_financeiro_rotina_painel.php';layout_end();$html=ob_get_clean();file_put_contents(sys_get_temp_dir().'/axion-rotina-test.html',$html);
echo "PASS: páginas reais renderizadas com dados fictícios.\n";
