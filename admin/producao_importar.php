<?php
require_once __DIR__.'/_auth.php';require_once __DIR__.'/_layout.php';require_once __DIR__.'/_producao.php';require_once __DIR__.'/_producao_xlsx.php';
prodRequireManager();$db=db();$os=(int)($_GET['os']??$_POST['os_id']??0);$error='';
if(!prodReady($db)){http_response_code(409);exit('Ative o módulo de produção primeiro.');}
$s=$db->prepare('SELECT * FROM producao_os WHERE id=?');$s->execute([$os]);$order=$s->fetch();if(!$order){http_response_code(404);exit('OS não encontrada.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfVerify();
    try{
        if(($_POST['action']??'')==='preview') {
            $file=$_FILES['planilha']??[];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>20*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new InvalidArgumentException('Envie um XLSX de até 20 MB.');
            $result=prodLerXlsx($file['tmp_name']);$hash=hash_file('sha256',$file['tmp_name']);
            $logins=$db->query('SELECT u.username FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE COALESCE(p.ativo,1)=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach($result['rows'] as $row)if(($row['responsavel']??'')!==''&&!in_array($row['responsavel'],$logins,true))$result['warnings'][]=['linha'=>$row['linha'],'tag'=>$row['tag'],'motivo'=>'Responsável "'.$row['responsavel'].'" sem login correspondente. O nome será exibido como importado e guardado no histórico; o PCP poderá vincular um usuário depois.'];
            $_SESSION['prod_import']=['os'=>$os,'sha'=>$hash,'nome'=>substr(basename($file['name']),0,255),'result'=>$result,'token'=>bin2hex(random_bytes(24)),'expires'=>time()+1800];
        }elseif(($_POST['action']??'')==='confirmar'){
            $pending=$_SESSION['prod_import']??null;
            if(!$pending||$pending['os']!==$os||$pending['expires']<time()||!hash_equals($pending['token'],$_POST['preview_token']??'')||$pending['result']['errors'])throw new InvalidArgumentException('Prévia expirada ou com erros. Envie a planilha novamente.');
            if(!isset($_POST['peso_confirmado']))throw new InvalidArgumentException('Confirme que o peso informado é o peso de cada peça.');
            if(!empty($pending['result']['warnings'])&&!isset($_POST['avisos_confirmados']))throw new InvalidArgumentException('Confirme a leitura dos avisos antes de importar.');
            $db->beginTransaction();$db->prepare('INSERT INTO producao_importacoes(os_id,sha256,nome,linhas,created_by) VALUES(?,?,?,?,?)')->execute([$os,$pending['sha'],$pending['nome'],count($pending['result']['rows']),$_SESSION['user_id']]);
            foreach($pending['result']['rows'] as $row){
                $s=$db->prepare('SELECT u.id FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE u.username=? AND COALESCE(p.ativo,1)=1');$s->execute([$row['responsavel']??'']);$responsavel=$s->fetchColumn();
                $row['responsavel_id']=$responsavel?:null;$dados=prodPecaDados($db,$row);$pecaId=prodInserirPeca($db,$os,$dados,$row['status'],$row['etapas']);
                $avisos=array_values(array_filter($pending['result']['warnings']??[],fn($warning)=>(int)$warning['linha']===(int)$row['linha']));
                prodAudit($db,'importar_peca',$os,$pecaId,null,['arquivo'=>$pending['nome'],'linha'=>$row['linha'],'responsavel_original'=>$row['responsavel']??'','dados'=>$row,'avisos'=>$avisos],false);
            }
            prodAudit($db,'importar_planilha',$os,null,null,['arquivo'=>$pending['nome'],'sha256'=>$pending['sha'],'itens'=>count($pending['result']['rows']),'avisos'=>$pending['result']['warnings']??[]]);$db->commit();unset($_SESSION['prod_import']);redirect('/admin/producao.php?os='.$os.'&saved=1');
        }else throw new InvalidArgumentException('Ação inválida.');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e instanceof InvalidArgumentException?$e->getMessage():(($e instanceof PDOException&&($e->errorInfo[1]??0)===1062)?'Este arquivo já foi importado nesta OS.':'Não foi possível importar. Nenhuma peça foi gravada.');error_log('[Axion importar] '.$e->getMessage());}
}
$pending=$_SESSION['prod_import']??null;$preview=$pending&&$pending['os']===$os&&$pending['expires']>=time()?$pending:null;
layout_start('Importar lista de fabricação','producao');
require __DIR__.'/_producao_import_view.php';
layout_end();
