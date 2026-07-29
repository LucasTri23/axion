<?php
/**
 * MODELO de segredos — copie este arquivo para admin/_secrets.php
 * (fora do controle de versão, ver .gitignore) e preencha com os
 * valores reais do ambiente.
 *
 * admin/_secrets.php NUNCA deve ser commitado, enviado por e-mail,
 * chat ou qualquer canal não criptografado. Se algum valor real já
 * vazou (ex.: alerta do GitGuardian), gere um valor NOVO — nunca
 * reaproveite um segredo que já foi exposto publicamente.
 */

define('DB_HOST', 'SEU_HOST_MYSQL');
define('DB_NAME', 'SEU_BANCO');
define('DB_USER', 'SEU_USUARIO');
define('DB_PASS', 'SUA_SENHA');

// String aleatória longa, usada só para gerar o hash do IP nos logs (LGPD).
define('IP_HASH_SALT', 'GERE_UM_VALOR_ALEATORIO_LONGO_AQUI');

// Token de uso único para acessar setup.php. Gere um valor novo e
// aleatório — nunca reutilize um token que já apareceu em algum commit.
define('SETUP_TOKEN', 'GERE_UM_TOKEN_ALEATORIO_LONGO_AQUI');
