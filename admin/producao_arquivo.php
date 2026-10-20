<?php
require_once __DIR__.'/_auth.php';require_once __DIR__.'/_producao.php';
$db=db();if(!prodReady($db)){http_response_code(409);exit('Módulo ainda não ativado.');}
$dir=__DIR__.'/_producao_files/';
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrfVerify();prodRequireManager();$os=(int)($_POST['os_id']??0);$error='';$target=null;
    try {
        $s=$db->prepare('SELECT id FROM producao_os WHERE id=?');$s->execute([$os]);if(!$s->fetch())throw new InvalidArgumentException('OS não encontrada.');
        $file=$_FILES['arquivo']??[];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>20*1024*1024)throw new InvalidArgumentException('Envie um arquivo de até 20 MB.');
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=['pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'dwg'=>['image/vnd.dwg','application/acad','application/octet-stream'],'dxf'=>['text/plain','application/dxf','image/vnd.dxf','application/octet-stream']];
        if(!isset($allowed[$ext])||!in_array($mime,$allowed[$ext],true))throw new InvalidArgumentException('Formatos aceitos: PDF, PNG, JPG, DWG e DXF.');
        if($ext==='dwg'&&!str_starts_with(file_get_contents($file['tmp_name'],false,null,0,6),'AC10'))throw new InvalidArgumentException('Desenho DWG inválido.');
        $nome=basename($file['name']);if(strlen($nome)>255)throw new InvalidArgumentException('Nome do arquivo muito longo.');
        $stored=bin2hex(random_bytes(24)).'.'.$ext;$target=$dir.$stored;
        if(!is_dir($dir)||!is_file($dir.'.htaccess'))throw new RuntimeException('Pasta privada indisponível. Publique admin/_producao_files/.htaccess.');
        if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Falha ao guardar arquivo.');
        $db->beginTransaction();$db->prepare('INSERT INTO producao_arquivos(os_id,nome,arquivo,mime,created_by) VALUES(?,?,?,?,?)')->execute([$os,$nome,$stored,$mime,$_SESSION['user_id']]);prodAudit($db,'anexar_documento',$os,null,null,['nome'=>$nome]);$db->commit();
        redirect('/admin/producao.php?os='.$os.'&saved=1');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($target&&is_file($target))unlink($target);error_log('[Axion documento] '.$e->getMessage());http_response_code(400);exit($e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível anexar o documento. Confira a pasta privada e o limite de upload da hospedagem.');}
}
$s=$db->prepare('SELECT * FROM producao_arquivos WHERE id=?');$s->execute([(int)($_GET['id']??0)]);$row=$s->fetch();
if(!$row||basename($row['arquivo'])!==$row['arquivo']||!is_file($dir.$row['arquivo'])){http_response_code(404);exit('Documento não encontrado.');}
header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header("Content-Disposition: attachment; filename=\"documento\"; filename*=UTF-8''".rawurlencode($row['nome']));readfile($dir.$row['arquivo']);
