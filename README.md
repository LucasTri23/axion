# Axion Industrial — Site institucional + Painel administrativo

> Documento técnico preparado para análise jurídica (LGPD / necessidade de política de privacidade e aviso de cookies).

## 1. O que é o projeto

Site institucional da empresa **Axion** (estruturas metálicas, pintura industrial e calderaria), com:

- **Site público** (`index.html` + `css/style.css` + `js/main.js`): páginas de apresentação da empresa, portfólio de fotos, e um formulário de **"Solicitar Orçamento"**.
- **Painel administrativo** (`/admin`): área restrita, protegida por login, usada pela equipe da Axion para gerenciar os pedidos de orçamento recebidos e editar textos/imagens do site.
- **Backend em PHP + MySQL** hospedado na Locaweb (não é mais um site puramente estático — antes usava `localStorage` do navegador, hoje os dados ficam em banco de dados no servidor).

Não há loja virtual, pagamento, cadastro de usuário público ou área logada para clientes/visitantes — apenas o formulário de contato.

## 2. Dados pessoais coletados

### 2.1 Visitantes do site (formulário de orçamento — `api/submit-quote.php`)

Quando alguém preenche e envia o formulário "Solicitar Orçamento", os seguintes dados são enviados ao servidor e **gravados no banco de dados MySQL**:

| Campo | Obrigatório? |
|---|---|
| Nome completo | Sim |
| E-mail | Sim |
| Empresa | Não |
| Telefone | Não |
| Serviço de interesse | Não |
| Descrição do projeto | Não |

Além disso, o sistema grava o **IP do visitante — porém apenas em forma de hash SHA-256** (`ipHash()` em `admin/_config.php`), não o IP em texto puro. Esse hash é usado só para limitar tentativas de envio (máx. 5 por hora) e evitar spam/abuso.

Esses dados ficam armazenados indefinidamente na tabela `quotes` até que a equipe da Axion os exclua manualmente pelo painel.

### 2.2 Acesso ao painel administrativo (`admin/login.php`)

Uso interno da equipe, não de clientes/visitantes. O sistema registra tentativas de login (sucesso/falha) na tabela `login_log`, contendo: hash do IP, hash do User-Agent do navegador, horário e se teve sucesso. Não há dado de terceiros aqui, é log de segurança do próprio sistema.

### 2.3 Visitantes comuns (navegação no site)

Ao simplesmente visitar o site (sem preencher o formulário), **nenhum dado de navegação é coletado ou salvo pela Axion** — não há analytics, pixel de rastreamento ou ferramenta de métricas própria integrada (ver seção 4).

### 2.4 Propostas comerciais (uso interno — `admin/propostas.php`)

Funcionalidade adicionada após a primeira versão deste documento: a equipe da Axion pode gerar, pelo painel, propostas comerciais em PDF para clientes (dados como razão social do cliente, CNPJ, nome do contato/A-C, escopo e valores). Esses dados **não são preenchidos por visitantes do site** — são digitados manualmente pela própria equipe da Axion sobre clientes/negociações já em andamento (B2B), ficam no banco de dados na tabela `propostas` e só são acessíveis por quem tem login no `/admin`. Não há formulário público associado a essa funcionalidade.

## 3. Cookies

Foi feita uma varredura completa do código-fonte. Resultado:

| Cookie | Onde é criado | Finalidade | Observação |
|---|---|---|---|
| `PHPSESSID` | Somente em `admin/login.php`, `admin/logout.php` e nas páginas autenticadas do painel (via `ensureSession()` em `admin/_config.php`, chamada por `admin/_auth.php`) | Mantém a sessão de login do **painel administrativo** (2 horas de validade) | ✅ **Corrigido em 29/07/2026:** a sessão PHP deixou de iniciar automaticamente ao carregar `admin/_config.php`. Antes, `api/get-content.php` e `api/submit-quote.php` (chamadas pelo site público) também carregavam esse arquivo e disparavam `session_start()`, emitindo o cookie para qualquer visitante. Agora `_config.php` só define a função `ensureSession()`, chamada explicitamente nos 3 pontos que realmente precisam de sessão. Visitantes que nunca acessam `/admin` não recebem mais nenhum cookie. |

Configuração do cookie de sessão (`admin/_config.php`): `HttpOnly`, `Secure` (exige HTTPS), `SameSite=Strict`. Não é usado para rastreamento, publicidade ou análise de comportamento — é estritamente funcional/técnico (login).

**Não há**: cookies de terceiros, cookies de analytics (Google Analytics, Meta Pixel, Hotjar etc.), cookies de publicidade/remarketing, ou qualquer banner de consentimento implementado no site atualmente.

## 4. Integrações e recursos externos (terceiros)

| Serviço | Onde | O que acontece |
|---|---|---|
| **Google Fonts** (`fonts.googleapis.com`, `fonts.gstatic.com`) | `index.html` e `admin/login.php` | O navegador do visitante faz uma requisição direta ao Google para baixar as fontes (Inter, Bebas Neue). Isso envia o IP do visitante ao Google. É um ponto comum de exigência de menção em política de privacidade (e, em alguns entendimentos de LGPD/GDPR, pode requerer aviso). |
| **WhatsApp** (`wa.me/55...`) | Botão flutuante e rodapé | Apenas um link comum (`<a href>`) que abre o WhatsApp — não é um script/widget embutido, não roda nada no navegador do visitante antes do clique. |
| **E-mail (`mailto:`)** | Rodapé e painel admin | Link simples, sem coleta de dado. |

Não há Google Analytics, Meta/Facebook Pixel, Google Tag Manager, chat de terceiros (Tawk, Zendesk etc.), CDN de terceiros para JS/CSS, nem qualquer SDK de marketing.

## 5. Quem tem acesso aos dados coletados

- Os dados do formulário de orçamento (`quotes`) ficam no banco MySQL da Locaweb e só são visíveis para quem tiver login no painel `/admin` (usuário/senha próprios da Axion, com bcrypt + limite de tentativas).
- Da tela de detalhe do pedido (`admin/quotes.php`), a equipe pode responder por e-mail diretamente (`mailto:`) — não há envio automático de e-mail pelo servidor.
- Não há compartilhamento automatizado desses dados com terceiros (nenhuma integração com CRM, planilha externa, webhook, etc.).

## 6. Upload de imagens (painel)

A equipe da Axion pode trocar imagens do site pelo painel (`admin/images.php`). Isso não envolve dados de visitantes — são apenas fotos institucionais/portfólio enviadas pela própria equipe.

## 7. Resumo para o jurídico

Pontos que provavelmente precisam ser avaliados/decididos:

1. **Formulário de orçamento coleta dados pessoais** (nome, e-mail, telefone, empresa) → provavelmente exige **Política de Privacidade** no site, informando finalidade (contato comercial), tempo de retenção, e direitos do titular (LGPD, Lei 13.709/2018).
2. ~~Cookie de sessão (`PHPSESSID`) estava sendo emitido para todo visitante~~ — **corrigido em 29/07/2026** (ver seção 8.4): agora só existe para quem acessa `/admin`.
3. **Google Fonts é carregado direto do Google** (não hospedado localmente) — expõe IP do visitante ao Google; comum aparecer em políticas de privacidade como "compartilhamento de dados com terceiros para fins técnicos/estéticos".
4. Não há cookies de marketing/analytics hoje — então, a depender da decisão do jurídico, pode não ser necessário um banner de consentimento de cookies "completo" (modelo com opt-in de categorias), só um aviso informativo sobre o cookie técnico de sessão e o Google Fonts, mas essa é uma decisão jurídica, não técnica.
5. IP de visitantes é armazenado apenas como hash (não reversível na prática), usado só para limitar spam no formulário — não identifica a pessoa diretamente.

## 8. Devolutiva do jurídico — documentos necessários e pendências

O jurídico confirmou que, com o levantamento das seções 1–7, já é possível elaborar a documentação. Precisam ser produzidos **4 documentos**:

1. **Política de Privacidade** (obrigatória)
2. **Política de Cookies** (curta — o site tem pouquíssimos cookies, ver seção 3)
3. **Termos de Uso do site**
4. **Aviso do formulário de orçamento** (texto curto de ciência/consentimento, exibido no próprio formulário)

### 8.1 Base legal da coleta (formulário de orçamento)

A finalidade do formulário é responder ao contato, elaborar orçamento e eventual negociação comercial. A base legal indicada pelo jurídico é o **art. 7º, V da LGPD** (procedimentos preliminares relacionados à celebração de contrato, a pedido do titular). Caso a Axion queira futuramente enviar novidades/marketing para quem preencheu o formulário, isso exige uma base legal separada (consentimento ou legítimo interesse, a depender do caso) — hoje o sistema **não faz** esse tipo de uso.

### 8.2 Prazo de retenção — ajustar redação

A seção 2.1 dizia que os dados do formulário "ficam armazenados indefinidamente". O jurídico recomendou não formalizar isso dessa forma na política. Redação sugerida:

> "Os dados são mantidos enquanto necessário ao atendimento da solicitação, ao relacionamento comercial ou ao cumprimento de obrigações legais."

Fica em aberto se a Axion quer adotar uma política interna de exclusão automática (ex.: excluir pedidos sem retorno após X meses) — hoje o painel só permite exclusão manual (`admin/quotes.php`).

### 8.3 Google Fonts — decisão pendente

Duas opções, a escolher:

- **Hospedar as fontes localmente** (baixar os arquivos woff2 de Inter/Bebas Neue e servir via `css/style.css`) — elimina o compartilhamento de IP com o Google e some com a necessidade de mencionar isso na política.
- **Manter como está** e apenas declarar esse compartilhamento com terceiro na Política de Privacidade.

*(Decisão de produto/dev, não jurídica — mas afeta o conteúdo da política.)*

### 8.4 Cookie `PHPSESSID` — ✅ corrigido

O jurídico reforçou o ponto já levantado na seção 3: a sessão PHP não deveria iniciar para visitantes comuns, só no fluxo de login do admin. **Corrigido em 29/07/2026** — ver detalhe na seção 3. A Política de Cookies já pode descrever o comportamento definitivo: o site público não emite mais nenhum cookie de sessão; `PHPSESSID` só existe para quem acessa `/admin`.

### 8.5 Texto de consentimento no formulário

Sugestão do jurídico para exibir abaixo do botão "Enviar Mensagem" em `index.html`:

> "Ao enviar este formulário, você declara estar ciente da Política de Privacidade e autoriza o tratamento dos seus dados para resposta à sua solicitação de orçamento."

Com link para a Política de Privacidade. *(Ainda não implementado — depende da política estar publicada primeiro.)*

### 8.6 Canal de atendimento ao titular

A política precisa indicar um e-mail para exercício de direitos do titular (acesso, correção, exclusão etc.). Falta confirmar se será usado o e-mail já existente (`adm@axionindustrial.com.br`) ou um dedicado (ex.: `privacidade@axionindustrial.com.br`).

### 8.7 Banner de cookies — modelo sugerido

Como o site praticamente não usa cookies relevantes para visitantes (e não usará, depois de corrigido o item 8.4), o jurídico sugere um banner simples, sem categorias/opt-in:

> "Este site utiliza apenas cookies técnicos indispensáveis ao seu funcionamento. Para saber mais, consulte nossa Política de Cookies." — botão único **"Entendi"**.

*(Ainda não implementado no site — hoje não há nenhum banner.)*

### 8.8 `setup.php` — risco de segurança reforçado

O jurídico também apontou o `setup.php` como algo a resolver antes de ir para produção — script de instalação acessível publicamente é um risco. Isso já constava na seção 7 deste documento e no próprio comentário do arquivo (`setup.php` e `.htaccess`): depois de rodar o setup uma vez, é preciso **descomentar o bloqueio no `.htaccess`** (linha `# Require all denied` dentro de `<Files "setup.php">`).

### 8.9 Dados cadastrais confirmados para redigir os documentos

Confirmado com a Axion em 29/07/2026:

| Campo | Valor definido |
|---|---|
| Razão social | AXION INDUSTRIAL LTDA |
| CNPJ | 63.637.526/0001-05 |
| Endereço oficial (usar nos documentos) | Rua Itamar Raul Mendes, nº 30, Bairro São Paulo, Matozinhos – MG, CEP 35720-000 (unidade fabril — grafia "Itamar" confirmada como a correta) |
| Endereço de faturamento (secundário, não usar como endereço oficial) | Rua Gercino Pereira da Costa, nº 156, Bairro Maracanã, Prudente de Morais – MG, CEP 35738-000 |
| Telefone oficial | (12) 99651-3704 — os números com DDD 31 usados nas propostas comerciais **não** devem ser usados nos documentos legais |
| E-mail de contato geral e canal de privacidade/LGPD | adm@axionindustrial.com.br (mesmo e-mail para as duas finalidades) |
| Cidade/Estado do foro contratual | Matozinhos – MG (mesma comarca do endereço oficial) |

**Pendência que sobra**: os números de telefone `(31) 97157-1412 / 97191-9658 / 97159-4583` aparecem no `admin/propostas.php` (campo `resp_tel`, usado nas propostas comerciais em PDF) e continuarão sendo impressos nas propostas — isso é esperado, já que propostas comerciais são documentos separados dos documentos legais do site (Política de Privacidade etc.) e não precisam usar o mesmo telefone. Nenhuma ação necessária aqui, só um registro para não gerar confusão.

## 9. Estrutura técnica (referência)

```
index.html              → site público
privacidade.html        → Política de Privacidade (linkada no rodapé do site)
css/style.css           → estilos do site público
js/main.js              → lógica do site público (galeria, formulário, animações)
api/submit-quote.php    → recebe o formulário de orçamento (grava no MySQL)
api/get-content.php     → devolve textos/imagens customizados pelo admin
admin/                  → painel administrativo (login obrigatório)
  login.php / logout.php
  index.php             → dashboard
  quotes.php            → lista/detalhe dos pedidos de orçamento (leads do formulário público)
  propostas.php         → gerador de propostas comerciais (uso interno, não público)
  proposta-imprimir.php → visualização/impressão em PDF das propostas
  content.php           → editor de textos do site
  images.php            → editor de imagens do site
  settings.php          → troca de senha
  _config.php           → configuração central, conexão MySQL, sessão PHP
  _auth.php             → guarda de autenticação
  _csrf.php             → proteção CSRF
  _layout.php           → layout/menu compartilhado do painel
  _secrets.php          → credenciais reais (NÃO versionado — ver .gitignore)
  _secrets.example.php  → modelo de credenciais (versionado, sem valores reais)
uploads/                → imagens enviadas pelo admin
setup.php               → script de criação das tabelas do banco (uso único, deve ser removido/bloqueado após uso)
.htaccess                → regras de segurança (HTTPS forçado, headers, bloqueio de arquivos sensíveis)
.gitignore               → exclui admin/_secrets.php e conteúdo de uploads/ do controle de versão
```

## 10. Incidente de segurança — credenciais expostas no GitHub (29/07/2026)

Em 29/07/2026 o GitGuardian detectou a senha do MySQL exposta publicamente no repositório `LucasTri23/axion` no GitHub, no commit `575fa5e` (a senha estava hardcoded em `admin/_config.php`). Como esse serviço monitora pushes **públicos** no GitHub em tempo real, o alerta confirma que o repositório estava público no momento do push.

**O que foi corrigido no código:**

- Todas as credenciais (senha do MySQL, salt do hash de IP, token do `setup.php`) foram removidas do código-fonte e movidas para `admin/_secrets.php`, um arquivo que fica **fora do controle de versão** (adicionado ao `.gitignore` criado nesta correção).
- `admin/_secrets.example.php` foi criado como modelo versionado, sem valores reais, para quem for configurar o projeto do zero.
- O salt do hash de IP e o token do `setup.php` foram trocados para valores novos, já que os antigos (`axion_2025_salt` e `axion_setup_2025_xK9mP`) também estavam no mesmo commit vazado.
- `setup.php` agora usa `hash_equals()` para comparar o token (comparação resistente a timing attack).

**Ações que só a Axion pode fazer (fora do código) — urgente:**

1. **Trocar a senha do banco MySQL no painel da Locaweb agora**, mesmo com a correção acima — o código só evita que a senha volte a vazar de novo; a senha antiga já é pública e deve ser tratada como comprometida até ser trocada no servidor.
2. Depois de trocar, atualizar o valor de `DB_PASS` em `admin/_secrets.php` (local) **e** no arquivo equivalente no servidor de produção (upload manual via FTP/painel da Locaweb — esse arquivo nunca deve ir por `git push`).
3. Confirmar se o repositório `LucasTri23/axion` deveria realmente ser público; se não, torná-lo privado no GitHub.
4. Opcionalmente, higienizar o histórico do Git para remover o segredo dos commits antigos (ex.: `git filter-repo` ou BFG Repo-Cleaner) — isso exige reescrever o histórico e um `push --force`, então deve ser feito com cuidado e de preferência coordenado (ninguém mais pode ter um clone desatualizado do repositório na hora). Rotacionar a senha (item 1) já neutraliza o risco prático mesmo sem esse passo.
