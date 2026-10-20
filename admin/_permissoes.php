<?php
function perfisDisponiveis(PDO $db): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { $db->query('SELECT user_id FROM user_perfis LIMIT 0'); return $ready = true; }
    catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) === 1146) return $ready = false;
        throw $error;
    }
}
function perfilUsuario(PDO $db, int $id): array {
    if (!perfisDisponiveis($db)) return ['perfil' => 'admin', 'ativo' => 1];
    $s = $db->prepare('SELECT perfil, ativo FROM user_perfis WHERE user_id=?');
    $s->execute([$id]);
    return $s->fetch() ?: ['perfil' => 'admin', 'ativo' => 1];
}
function perfilAtual(): string { return $_SESSION['perfil'] ?? 'admin'; }
function producaoDisponivel(PDO $db): bool {
    try { $db->query('SELECT id FROM producao_importacoes LIMIT 0'); return true; }
    catch(PDOException $error) { if(($error->errorInfo[1]??0)===1146)return false;throw $error; }
}
function podeGerenciarProducao(): bool { return in_array(perfilAtual(), ['admin', 'pcp'], true); }
function paginaPermitida(string $perfil, string $page): bool {
    if ($perfil === 'admin') return true;
    if (!in_array($perfil, ['pcp', 'ajudante'], true)) return false;
    return in_array($page, ['producao.php', 'producao_arquivo.php', 'producao_importar.php', 'producao_alertas.php', 'notificacoes.php', 'logout.php'], true)
        && ($perfil !== 'ajudante' || !in_array($page,['producao_importar.php','producao_alertas.php','notificacoes.php'],true));
}
function inicioUsuario(): string { return perfilAtual() === 'admin' ? '/admin/index.php' : '/admin/producao.php'; }
