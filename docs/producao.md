# Produção / PCP

## Ativação em instalação existente

1. Publicar os arquivos novos e alterados, incluindo `admin/_producao_files/.htaccess`.
2. Entrar com o administrador atual e acessar **Produção / PCP → Criar estrutura de produção**. É uma operação POST autenticada com CSRF; cria tabelas adicionais sem apagar o financeiro ou outros registros. Em instalações novas, `setup.php` também inclui essas tabelas.
3. Em **Usuários**, criar logins individuais com perfil PCP ou ajudante. Usuários anteriores sem perfil explícito continuam administradores. Ajudantes podem consultar produção e desenhos e alterar status/etapas; não podem importar, editar os dados técnicos, administrar usuários ou abrir as áreas antigas. PCP administra a produção sem acesso às áreas antigas.
4. Abrir a OS com confirmação e data da aprovação do cliente. O vínculo com uma proposta existente é opcional. Anexar desenhos e importar uma lista com prévia, ou cadastrar peças manualmente.

## Lista padronizada

Cabeçalhos: DATA, AVULSAS, DESCRIÇÃO DO MATERIAL, TAG, Nº DESENHO, QUANTIDADE, PESO, RESPONSÁVEL, Nº ROMANEIO, STATUS FABRICAÇÃO. GRD é aceito como alternativa a DATA. Revisão e etapas PREP, MONT, SOLDA, ACAB, PINT e EXD são opcionais. Responsável usa o login cadastrado; vazio significa não atribuído. Quantidade ausente é 1. Peso é **unitário em kg**, com até três casas decimais.

Modelos anteriores podem conter peso total, datas textuais inválidas ou totais com referências quebradas. A prévia mostra os dados interpretados, bloqueia erros de dados e exige confirmação de peso unitário antes de inserir. Divergências de embarque com fabricação incompleta geram avisos: o indicador de expedição não é aplicado, sem concluir etapas por suposição; etapas anteriores são preservadas. Responsável sem login correspondente fica sem vínculo e seu texto original permanece no histórico da peça. Esses avisos precisam de confirmação, não bloqueiam a importação e são guardados na auditoria. Não executa fórmulas; lê seus valores armazenados e recalcula os totais no sistema. O mesmo SHA-256 não pode ser importado duas vezes na mesma OS. Um arquivo alterado tem outro hash: para revisões, editar as peças existentes em vez de reimportar a lista inteira.

Importação requer extensões PHP ZIP, XMLReader e SimpleXML. Documentos requerem Fileinfo. Uploads limitados a 20 MB, sujeitos aos limites menores de `upload_max_filesize` e `post_max_size` da hospedagem. Pasta `admin/_producao_files` precisa ser gravável pelo PHP e bloqueada por seu `.htaccess`. Os downloads passam pela autenticação e são servidos como anexos; não expor essa pasta por outra rota.

## Avanço e pesos

Cada etapa aceita 0, 25, 50, 75 ou 100%. O avanço da peça pondera preparação 15%, montagem 25%, solda 25%, acabamento 10%, pintura 15% e expedição 10%. Alterar para pintura conclui as quatro primeiras etapas; romaneio conclui também pintura; expedido conclui todas; voltar a pendente zera o avanço. Recuar para preparação zera pintura e expedição. Cada salvamento é auditado.

Peso total da linha = quantidade × peso unitário. Peso da OS e peso filtrado consideram as linhas correspondentes. A seleção soma as linhas marcadas do resultado atual; mudar o filtro limpa a seleção. Avanço da OS = soma(peso total da linha × avanço da linha) / peso total da OS. OS com peso total zero apresenta avanço zero até que os pesos sejam definidos.

## Auditoria e notificações

Alterações de peças usam transação, bloqueio de linha e número de versão para impedir sobreposição silenciosa de edições simultâneas. Auditoria guarda ID do usuário, login no momento da ação, antes, depois e horário. Desenhos e importações também geram eventos. Notificações internas são criadas para cada administrador/PCP ativo, com leitura individual e contador no menu, consultado a cada 30 segundos enquanto a página está visível. Novas alterações mostram um aviso com link para conferência. Não há envio por e-mail ou WhatsApp. O ajudante não altera permissões, histórico ou destinatários pelo formulário.

Histórico da OS e notificações têm paginação, mantendo as alterações antigas acessíveis. Não há exclusão de peças/OS ou edição da auditoria pela interface nesta versão. Usuários são desativados para preservar autoria.

## Validação

Executar com PHP disponível:

```
php tests/producao_test.php
php -l admin/producao.php
php -l admin/producao_importar.php
php -l admin/_producao_xlsx.php
```

Antes de disponibilizar para a equipe, validar em banco de teste: ativação, perfis, acesso direto às URLs antigas, importação com prévia e repetição, dois editores da mesma peça, notificação para ADM e PCP, leitura individual, download protegido e recálculo de pesos. O ambiente de desenvolvimento desta entrega não possui PHP nem banco MySQL de teste; testes de interface não substituem esses testes de integração.
