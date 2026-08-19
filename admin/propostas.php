<?php
/**
 * Gerador de Propostas Comerciais — dois padrões (grande/pequena)
 * OWASP A01/A03/A04: auth + prepared statements + CSRF + validação
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();

// ── Padrões de conteúdo (texto base editável por proposta) ────────
function defaultsGrande(): array {
    return [
        'apresentacao' => "A AXION INDUSTRIAL LTDA apresenta a presente Proposta Técnica-Comercial, visando à fabricação de estruturas metálicas, montagem de chapas de desgaste, fabricação de estruturas leves, médias e pesadas, bem como serviços correlatos de preparação de superfície, pintura industrial e montagem final.\nA proposta foi elaborada considerando os padrões industriais, requisitos técnicos e operacionais do cliente, prezando pela qualidade, rastreabilidade, conformidade técnica e cumprimento de prazos acordados entre as partes.",
        'objetivo' => 'O objetivo desta proposta é estabelecer as condições técnicas e comerciais para a fabricação e montagem de estruturas metálicas, garantindo qualidade dimensional, acabamento adequado, proteção anticorrosiva e atendimento às necessidades produtivas do cliente.',
        'end_entrega_rua'    => 'Rua Isamar Raul Mendes, nº 30',
        'end_entrega_bairro' => 'São Paulo',
        'end_entrega_cidade' => 'Matozinhos – MG',
        'end_entrega_cep'    => '35720-000',
        'end_fat_rua'    => 'Rua Gercino Pereira da Costa, nº 156',
        'end_fat_bairro' => 'Maracanã',
        'end_fat_cidade' => 'Prudente de Morais – MG',
        'end_fat_cep'    => '35738-000',
        'escopo' => [
            ['titulo' => 'Montagem de Chapas de Desgaste', 'valor' => 'R$ 3,00 (três reais) por quilo',
             'corpo' => "As chapas de desgaste serão enviadas à AXION já cortadas e escamadas\nApós recebimento, as chapas passarão por jateamento abrasivo, aplicação de tinta de fundo com espessura mínima de 60 microns e montagem nos equipamentos designados pelo cliente"],
            ['titulo' => 'Fabricação de Estruturas Metálicas – Médio e Pesado Porte', 'valor' => 'R$ 8,50 (oito reais e cinquenta centavos) por quilo, mediante ticket de balança',
             'corpo' => "Fabricação conforme desenhos, projetos e especificações fornecidas pelo cliente\nAplicável a estruturas de médio e grande porte, guarda-corpos, proteções coletivas e similares"],
            ['titulo' => 'Tratamento de Superfície e Pintura', 'valor' => 'Incluso conforme escopo acordado',
             'corpo' => "Pré-montagem em 2 estágios: montagem inicial e montagem definitiva após a 1ª demão de tinta\nAplicação da 2ª demão de tinta após a montagem final\nEspessura total do sistema de pintura: 200 micras"],
        ],
        'armazenamento' => 'Após pintura e inspeção, as estruturas permanecerão em área coberta. Posteriormente, serão embaladas com madeira e cintadas, garantindo integridade e qualidade até o destino final.',
        'area_pintura' => '1.200 m²',
        'area_externa' => '5.500 m²',
        'prazo_execucao' => 'O prazo de entrega será sempre alinhado previamente com o PCP e a área responsável, em função da necessidade produtiva do cliente.',
        'data_inicio' => '',
        'condicao_pagamento' => "Medição realizada no dia 15, com pagamento no dia 20\nMedição realizada no dia 30, com pagamento no dia 04 do mês subsequente\nO pagamento será efetuado via depósito em conta",
        'material' => 'axion',
        'material_obs' => '',
        'exclusoes' => "Fornecimento de projetos, desenhos ou memoriais de cálculo não fornecidos pelo cliente\nFornecimento de matérias-primas ou insumos não especificados no escopo\nTransporte das estruturas até o destino final, salvo contratação específica\nMontagens em campo, salvo quando expressamente acordadas\nServiços não descritos explicitamente nesta proposta",
        'resp_contratada' => "Executar os serviços conforme especificações técnicas, normas aplicáveis e orientações do cliente\nGarantir qualidade de fabricação, soldagem, pintura e acabamento\nDisponibilizar mão de obra qualificada, equipamentos e infraestrutura fabril\nRealizar inspeções e controles internos antes da liberação das peças\nZelar pela integridade dos materiais enquanto sob sua responsabilidade",
        'resp_contratante' => "Fornecer projetos, desenhos e especificações técnicas necessárias\nValidar medições e autorizar faturamentos\nDefinir prioridades e prazos junto ao PCP\nEfetuar os pagamentos conforme condições acordadas",
        'comentarios' => 'A AXION INDUSTRIAL LTDA reafirma seu compromisso com qualidade, confiabilidade, cumprimento de prazos e parceria industrial, colocando-se à disposição para quaisquer esclarecimentos adicionais ou ajustes necessários.',
        'observacoes' => '',
        'resp_nome'  => 'Rômulo Anjos',
        'resp_cargo' => 'Gerente Comercial',
        'resp_tel'   => '(31) 97157-1412 | (31) 97191-9658 | (31) 97159-4583',
        'cidade_assinatura' => 'Matozinhos',
    ];
}

function defaultsPequena(): array {
    return [
        'apresentacao' => "Em atendimento à solicitação de orçamento, a AXION INDUSTRIAL LTDA apresenta sua proposta comercial para fabricação dos componentes abaixo, conforme desenhos/especificações fornecidas pelo cliente.\nA fabricação será realizada em estrita conformidade com os desenhos técnicos fornecidos pela contratante, utilizando procedimentos compatíveis com as especificações de fabricação e controle dimensional.",
        'itens' => [
            ['descricao' => '', 'desenho' => '', 'posicao' => '01'],
        ],
        'valores' => [
            ['descricao' => '', 'valor' => ''],
        ],
        'fornecimento' => "Fabricação dos componentes metálicos conforme desenhos fornecidos\nFornecimento de mão de obra especializada\nFabricação e montagem em oficina\nControle dimensional durante o processo de fabricação\nInspeção visual dos componentes antes da liberação para entrega\nDisponibilização do material para retirada na modalidade FOB",
        'valor_total' => '',
        'obs_fiscal'  => 'Os impostos incidentes deverão ser destacados conforme emissão da nota fiscal e enquadramento fiscal aplicável.',
        'pis' => '0,65%', 'cofins' => '3%', 'iss' => '3%', 'inss' => '11%',
        'condicao_pagamento' => '30 dias após a entrega.',
        'prazo_entrega' => '60 dias contados a partir da emissão da ordem de compra e disponibilização de informações técnicas.',
        'frete' => 'FOB – retirada nas instalações da AXION INDUSTRIAL',
        'validade' => '10 dias corridos, salvo negociação entre as partes.',
        'material' => 'axion',
        'material_obs' => '',
        'resp_contratada' => "Fabricação conforme desenhos fornecidos pelo cliente\nFornecimento de mão de obra especializada\nDisponibilização dos equipamentos e ferramentas necessários à fabricação\nControle dimensional durante o processo produtivo\nInspeção visual final dos componentes\nDisponibilização do material para retirada em suas instalações",
        'resp_contratante' => "Fornecer os desenhos executivos e especificações técnicas definitivas\nEsclarecer eventuais dúvidas técnicas durante a fabricação\nProvidenciar a retirada dos materiais nas instalações da AXION (FOB)\nEfetuar os pagamentos conforme as condições comerciais estabelecidas",
        'exclusoes' => "Ensaios por Partículas Magnéticas (PM)\nEnsaios por Ultrassom (UT)\nDemais ensaios não especificados no escopo\nMontagem em campo\nFrete e transporte dos componentes\nPintura ou tratamentos superficiais, quando não especificados nos desenhos\nInstalação, comissionamento ou assistência técnica em campo\nQualquer fornecimento ou serviço não descrito expressamente nesta proposta",
        'observacoes' => '',
    ];
}

function loadProposta(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM propostas WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['dados'] = json_decode($row['dados'] ?: '[]', true) ?: [];
    return $row;
}

// ── Processar POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $db->prepare('DELETE FROM propostas WHERE id = ?')->execute([$id]);
        redirect('/admin/propostas.php?deleted=1');
    }

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $saveMode = $_POST['save_mode'] ?? 'overwrite';
        if (!in_array($saveMode, ['overwrite', 'copy', 'print'], true)) $saveMode = 'overwrite';
        if ($saveMode === 'copy') $id = 0;
        $tipo = ($_POST['tipo'] ?? '') === 'pequena' ? 'pequena' : 'grande';

        $numero   = substr(trim($_POST['numero'] ?? ''), 0, 50);
        $revisao  = substr(trim($_POST['revisao'] ?? '00'), 0, 10);
        $dataProp = trim($_POST['data_proposta'] ?? '');
        $dataProp = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataProp) ? $dataProp : null;

        $clienteNome    = substr(trim(strip_tags($_POST['cliente_nome'] ?? '')), 0, 255);
        $clienteContato = substr(trim(strip_tags($_POST['cliente_contato'] ?? '')), 0, 255);
        $clienteCnpj    = substr(trim(strip_tags($_POST['cliente_cnpj'] ?? '')), 0, 30);
        $objeto         = substr(trim(strip_tags($_POST['objeto'] ?? '')), 0, 255);

        if ($clienteNome === '') {
            redirect('/admin/propostas.php?' . ($id ? "id=$id" : "novo=$tipo") . '&erro=cliente');
        }

        // Campos comuns
        $dados = [
            'apresentacao'       => trim($_POST['apresentacao'] ?? ''),
            'condicao_pagamento' => trim($_POST['condicao_pagamento'] ?? ''),
            'material'           => in_array($_POST['material'] ?? '', ['axion','cliente','outro'], true) ? $_POST['material'] : 'axion',
            'material_obs'       => trim($_POST['material_obs'] ?? ''),
            'exclusoes'          => trim($_POST['exclusoes'] ?? ''),
            'resp_contratada'    => trim($_POST['resp_contratada'] ?? ''),
            'resp_contratante'   => trim($_POST['resp_contratante'] ?? ''),
            'observacoes'        => trim($_POST['observacoes'] ?? ''),
        ];

        if ($tipo === 'grande') {
            $titulos = $_POST['escopo_titulo'] ?? [];
            $valores = $_POST['escopo_valor']  ?? [];
            $corpos  = $_POST['escopo_corpo']  ?? [];
            $escopo  = [];
            foreach ($titulos as $i => $t) {
                $t = trim($t);
                if ($t === '') continue;
                $escopo[] = [
                    'titulo' => substr($t, 0, 255),
                    'valor'  => substr(trim($valores[$i] ?? ''), 0, 255),
                    'corpo'  => trim($corpos[$i] ?? ''),
                ];
            }
            $dados += [
                'objetivo'           => trim($_POST['objetivo'] ?? ''),
                'end_entrega_rua'    => substr(trim($_POST['end_entrega_rua'] ?? ''), 0, 255),
                'end_entrega_bairro' => substr(trim($_POST['end_entrega_bairro'] ?? ''), 0, 120),
                'end_entrega_cidade' => substr(trim($_POST['end_entrega_cidade'] ?? ''), 0, 120),
                'end_entrega_cep'    => substr(trim($_POST['end_entrega_cep'] ?? ''), 0, 20),
                'end_fat_rua'        => substr(trim($_POST['end_fat_rua'] ?? ''), 0, 255),
                'end_fat_bairro'     => substr(trim($_POST['end_fat_bairro'] ?? ''), 0, 120),
                'end_fat_cidade'     => substr(trim($_POST['end_fat_cidade'] ?? ''), 0, 120),
                'end_fat_cep'        => substr(trim($_POST['end_fat_cep'] ?? ''), 0, 20),
                'escopo'             => $escopo,
                'armazenamento'      => trim($_POST['armazenamento'] ?? ''),
                'area_pintura'       => substr(trim($_POST['area_pintura'] ?? ''), 0, 40),
                'area_externa'       => substr(trim($_POST['area_externa'] ?? ''), 0, 40),
                'prazo_execucao'     => trim($_POST['prazo_execucao'] ?? ''),
                'data_inicio'        => substr(trim($_POST['data_inicio'] ?? ''), 0, 40),
                'comentarios'        => trim($_POST['comentarios'] ?? ''),
                'resp_nome'          => substr(trim($_POST['resp_nome'] ?? ''), 0, 120),
                'resp_cargo'         => substr(trim($_POST['resp_cargo'] ?? ''), 0, 120),
                'resp_tel'           => substr(trim($_POST['resp_tel'] ?? ''), 0, 120),
                'cidade_assinatura'  => substr(trim($_POST['cidade_assinatura'] ?? ''), 0, 80),
            ];
        } else {
            $descs = $_POST['item_desc']  ?? [];
            $desen = $_POST['item_desenho'] ?? [];
            $pos   = $_POST['item_pos']   ?? [];
            $itens = [];
            foreach ($descs as $i => $d) {
                $d = trim($d);
                if ($d === '') continue;
                $itens[] = [
                    'descricao' => substr($d, 0, 255),
                    'desenho'   => substr(trim($desen[$i] ?? ''), 0, 255),
                    'posicao'   => substr(trim($pos[$i] ?? ''), 0, 20),
                ];
            }
            $valorDescs = $_POST['valor_descricao'] ?? [];
            $valorNums  = $_POST['valor_item'] ?? [];
            $valores = [];
            foreach ($valorDescs as $i => $descricaoValor) {
                $descricaoValor = trim(strip_tags($descricaoValor));
                $valorItem = trim(strip_tags($valorNums[$i] ?? ''));
                if ($descricaoValor === '' && $valorItem === '') continue;
                $valores[] = [
                    'descricao' => substr($descricaoValor, 0, 255),
                    'valor' => substr($valorItem, 0, 40),
                ];
            }
            $dados += [
                'itens'         => $itens,
                'valores'       => $valores,
                'fornecimento'  => trim($_POST['fornecimento'] ?? ''),
                'valor_total'   => substr(trim($_POST['valor_total'] ?? ''), 0, 40),
                'obs_fiscal'    => trim($_POST['obs_fiscal'] ?? ''),
                'pis'           => substr(trim($_POST['pis'] ?? ''), 0, 20),
                'cofins'        => substr(trim($_POST['cofins'] ?? ''), 0, 20),
                'iss'           => substr(trim($_POST['iss'] ?? ''), 0, 20),
                'inss'          => substr(trim($_POST['inss'] ?? ''), 0, 20),
                'prazo_entrega' => trim($_POST['prazo_entrega'] ?? ''),
                'frete'         => substr(trim($_POST['frete'] ?? ''), 0, 255),
                'validade'      => substr(trim($_POST['validade'] ?? ''), 0, 255),
            ];
        }

        $json = json_encode($dados, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            $db->prepare(
                "UPDATE propostas SET tipo=?, numero=?, revisao=?, data_proposta=?, cliente_nome=?,
                 cliente_contato=?, cliente_cnpj=?, objeto=?, dados=? WHERE id=?"
            )->execute([$tipo, $numero, $revisao, $dataProp, $clienteNome, $clienteContato, $clienteCnpj, $objeto, $json, $id]);
        } else {
            $db->prepare(
                "INSERT INTO propostas (tipo, numero, revisao, data_proposta, cliente_nome, cliente_contato, cliente_cnpj, objeto, dados)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$tipo, $numero, $revisao, $dataProp, $clienteNome, $clienteContato, $clienteCnpj, $objeto, $json]);
            $id = (int)$db->lastInsertId();
        }

        if ($saveMode === 'print') {
            redirect('/admin/proposta-imprimir.php?id=' . $id . '&print=1');
        }
        redirect('/admin/propostas.php?id=' . $id . '&saved=1');
    }
}

// ── Determinar modo de exibição ────────────────────────────────────
$modo = 'lista';
$proposta = null;
$tipo = 'grande';

if (isset($_GET['id'])) {
    $proposta = loadProposta((int)$_GET['id']);
    if ($proposta) { $modo = 'form'; $tipo = $proposta['tipo']; }
} elseif (isset($_GET['novo'])) {
    $tipo = $_GET['novo'] === 'pequena' ? 'pequena' : 'grande';
    $modo = 'form';
}

layout_start('Propostas Comerciais', 'propostas');
?>

<style>
@media (max-width: 760px) {
  .valor-row { grid-template-columns: 1fr !important; align-items: stretch !important; }
  .valor-row .remove-row { margin-bottom: 14px !important; justify-self: start; }
}
</style>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success">✔ Proposta salva com sucesso.</div>
<?php endif ?>
<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success">🗑 Proposta excluída.</div>
<?php endif ?>
<?php if (isset($_GET['erro']) && $_GET['erro'] === 'cliente'): ?>
  <div class="alert alert-error">⚠️ Informe o nome do cliente antes de salvar.</div>
<?php endif ?>

<?php if ($modo === 'lista'):
    $empresaFiltro = substr(trim($_GET['empresa'] ?? ''), 0, 255);
    $valorFiltro = substr(trim($_GET['valor'] ?? ''), 0, 80);
    $servicoFiltro = substr(trim($_GET['servico'] ?? ''), 0, 255);
    $sqlLista = "SELECT id, tipo, numero, cliente_nome, objeto, data_proposta, updated_at FROM propostas";
    $whereLista = [];
    $paramsLista = [];
    if ($empresaFiltro !== '') { $whereLista[] = 'cliente_nome LIKE ?'; $paramsLista[] = '%' . $empresaFiltro . '%'; }
    if ($valorFiltro !== '') { $whereLista[] = 'dados LIKE ?'; $paramsLista[] = '%' . $valorFiltro . '%'; }
    if ($servicoFiltro !== '') {
        $whereLista[] = '(objeto LIKE ? OR dados LIKE ?)';
        $paramsLista[] = '%' . $servicoFiltro . '%';
        $paramsLista[] = '%' . $servicoFiltro . '%';
    }
    if ($whereLista) $sqlLista .= ' WHERE ' . implode(' AND ', $whereLista);
    $sqlLista .= ' ORDER BY updated_at DESC';
    $stmtLista = $db->prepare($sqlLista);
    $stmtLista->execute($paramsLista);
    $lista = $stmtLista->fetchAll();
?>

<div class="alert alert-info">
  📄 Gere propostas comerciais nos dois padrões da AXION (Técnica/Grande ou Comercial/Pequena).
  Preencha os dados do cliente e o escopo, depois abra a proposta para <strong>imprimir/baixar em PDF</strong>.
</div>

<div style="display:flex;gap:10px;margin-bottom:22px">
  <a href="/admin/propostas.php?novo=grande" class="btn btn-primary">📄 Nova Proposta — Padrão Técnica (Grande)</a>
  <a href="/admin/propostas.php?novo=pequena" class="btn btn-secondary">📄 Nova Proposta — Padrão Comercial (Pequena)</a>
</div>

<div class="card">
  <div class="card-title">📑 Propostas Salvas</div>
  <form method="GET" class="frow" style="align-items:end;margin-bottom:18px">
    <div class="fg"><label>Empresa</label><input type="text" name="empresa" value="<?= e($empresaFiltro) ?>" placeholder="Nome do cliente"></div>
    <div class="fg"><label>Valor</label><input type="text" name="valor" value="<?= e($valorFiltro) ?>" placeholder="Ex: 18.500,00"></div>
    <div class="fg"><label>Serviço</label><input type="text" name="servico" value="<?= e($servicoFiltro) ?>" placeholder="Ex: Pintura"></div>
    <div style="display:flex;gap:7px;margin-bottom:14px">
      <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      <a class="btn btn-ghost btn-sm" href="/admin/propostas.php">Limpar</a>
    </div>
  </form>
  <?php if (!$lista): ?>
    <div class="empty"><div class="empty-icon">📭</div><p>Nenhuma proposta criada ainda.</p></div>
  <?php else: ?>
    <table>
      <thead><tr>
        <th>Cliente</th><th>Objeto</th><th>Padrão</th><th>Número</th><th>Data</th><th>Atualizada em</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($lista as $p): ?>
        <tr>
          <td class="td-name"><?= e($p['cliente_nome']) ?></td>
          <td style="font-size:13px"><?= e($p['objeto'] ?: '—') ?></td>
          <td><span class="badge <?= $p['tipo'] === 'grande' ? 'b-andamento' : 'b-novo' ?>"><?= $p['tipo'] === 'grande' ? 'Técnica' : 'Comercial' ?></span></td>
          <td style="font-size:13px"><?= e($p['numero'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--gray)"><?= $p['data_proposta'] ? e(date('d/m/Y', strtotime($p['data_proposta']))) : '—' ?></td>
          <td style="font-size:12px;color:var(--gray)"><?= e(date('d/m/Y H:i', strtotime($p['updated_at']))) ?></td>
          <td style="display:flex;gap:6px">
            <a href="/admin/propostas.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">✏️ Editar</a>
            <a href="/admin/proposta-imprimir.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ PDF</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir esta proposta permanentemente?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<?php else:
    // ── Formulário (criação/edição) ─────────────────────────────────
    $defaults = $tipo === 'grande' ? defaultsGrande() : defaultsPequena();
    $d = $proposta ? array_merge($defaults, $proposta['dados']) : $defaults;
    if ($tipo === 'pequena' && $proposta && empty($proposta['dados']['valores']) && !empty($d['valor_total'])) {
        $d['valores'] = [['descricao' => 'Valor total da proposta', 'valor' => $d['valor_total']]];
    }
    $id = $proposta['id'] ?? 0;
    $numero  = $proposta['numero']  ?? '';
    $revisao = $proposta['revisao'] ?? '00';
    $dataProposta = $proposta['data_proposta'] ?? date('Y-m-d');
    $clienteNome    = $proposta['cliente_nome']    ?? '';
    $clienteContato = $proposta['cliente_contato'] ?? '';
    $clienteCnpj    = $proposta['cliente_cnpj']    ?? '';
    $objeto         = $proposta['objeto']          ?? '';
    $v = fn($k, $fb = '') => e((string)($d[$k] ?? $fb));
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
  <a href="/admin/propostas.php" class="btn btn-ghost btn-sm">← Voltar</a>
  <span class="badge <?= $tipo === 'grande' ? 'b-andamento' : 'b-novo' ?>"><?= $tipo === 'grande' ? 'Padrão Técnica (Grande)' : 'Padrão Comercial (Pequena)' ?></span>
  <?php if ($id): ?>
    <a href="/admin/proposta-imprimir.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-primary btn-sm" style="margin-left:auto">🖨️ Abrir / Baixar PDF</a>
  <?php endif ?>
</div>

<form method="POST">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="card">
    <div class="card-title">🏢 Dados do Cliente / Proposta</div>
    <div class="frow">
      <div class="fg"><label>Cliente (razão social)</label>
        <input type="text" name="cliente_nome" required maxlength="255" value="<?= e($clienteNome) ?>" placeholder="Ex: IMIC – Indústria Mecânica Irmãos Corgozinho">
      </div>
      <div class="fg"><label>A/C (contato)</label>
        <input type="text" name="cliente_contato" maxlength="255" value="<?= e($clienteContato) ?>" placeholder="Ex: Josiane">
      </div>
    </div>
    <div class="frow">
      <div class="fg"><label>CNPJ do cliente</label>
        <input type="text" name="cliente_cnpj" maxlength="30" value="<?= e($clienteCnpj) ?>">
      </div>
      <div class="fg"><label>Objeto / Título do serviço</label>
        <input type="text" name="objeto" maxlength="255" value="<?= e($objeto) ?>" placeholder="Ex: Fabricação de Estruturas Metálicas">
      </div>
    </div>
    <div class="frow">
      <div class="fg"><label>Número do orçamento</label>
        <input type="text" name="numero" maxlength="50" value="<?= e($numero) ?>" placeholder="Ex: AX-PTC-270126">
      </div>
      <div class="fg"><label>Revisão</label>
        <input type="text" name="revisao" maxlength="10" value="<?= e($revisao) ?>">
      </div>
    </div>
    <div class="fg" style="max-width:220px"><label>Data da proposta</label>
      <input type="date" name="data_proposta" value="<?= e($dataProposta) ?>">
    </div>
  </div>

  <?php if ($tipo === 'grande'): ?>

  <div class="card">
    <div class="card-title">📝 Apresentação e Objetivo</div>
    <div class="fg"><label>Texto de apresentação</label>
      <textarea name="apresentacao" style="min-height:110px"><?= $v('apresentacao') ?></textarea>
    </div>
    <div class="fg"><label>Objetivo</label>
      <textarea name="objetivo"><?= $v('objetivo') ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📍 Endereços</div>
    <p style="font-size:12px;color:var(--gray);margin-bottom:10px">Entrega / fabricação:</p>
    <div class="frow">
      <div class="fg"><label>Rua</label><input type="text" name="end_entrega_rua" value="<?= $v('end_entrega_rua') ?>"></div>
      <div class="fg"><label>Bairro</label><input type="text" name="end_entrega_bairro" value="<?= $v('end_entrega_bairro') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>Cidade</label><input type="text" name="end_entrega_cidade" value="<?= $v('end_entrega_cidade') ?>"></div>
      <div class="fg"><label>CEP</label><input type="text" name="end_entrega_cep" value="<?= $v('end_entrega_cep') ?>"></div>
    </div>
    <p style="font-size:12px;color:var(--gray);margin:14px 0 10px">Faturamento (sede administrativa):</p>
    <div class="frow">
      <div class="fg"><label>Rua</label><input type="text" name="end_fat_rua" value="<?= $v('end_fat_rua') ?>"></div>
      <div class="fg"><label>Bairro</label><input type="text" name="end_fat_bairro" value="<?= $v('end_fat_bairro') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>Cidade</label><input type="text" name="end_fat_cidade" value="<?= $v('end_fat_cidade') ?>"></div>
      <div class="fg"><label>CEP</label><input type="text" name="end_fat_cep" value="<?= $v('end_fat_cep') ?>"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">⚙️ Escopo dos Serviços</div>
    <div id="escopo-rows">
      <?php foreach ($d['escopo'] as $item): ?>
      <div class="escopo-row" style="border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:14px;margin-bottom:12px">
        <div class="fg"><label>Título da etapa</label>
          <input type="text" name="escopo_titulo[]" value="<?= e($item['titulo']) ?>" placeholder="Ex: Montagem de Chapas de Desgaste">
        </div>
        <div class="fg"><label>Valor</label>
          <input type="text" name="escopo_valor[]" value="<?= e($item['valor']) ?>" placeholder="Ex: R$ 8,50 por quilo">
        </div>
        <div class="fg"><label>Descrição (uma linha por item)</label>
          <textarea name="escopo_corpo[]"><?= e($item['corpo']) ?></textarea>
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-row">🗑 Remover etapa</button>
      </div>
      <?php endforeach ?>
    </div>
    <button type="button" id="add-escopo" class="btn btn-ghost btn-sm">➕ Adicionar etapa do escopo</button>
  </div>

  <div class="card">
    <div class="card-title">📦 Armazenamento e Embalagem</div>
    <div class="fg"><label>Texto</label>
      <textarea name="armazenamento"><?= $v('armazenamento') ?></textarea>
    </div>
    <div class="frow">
      <div class="fg"><label>Área coberta de pintura</label><input type="text" name="area_pintura" value="<?= $v('area_pintura') ?>"></div>
      <div class="fg"><label>Área externa</label><input type="text" name="area_externa" value="<?= $v('area_externa') ?>"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">⏱️ Prazo de Execução</div>
    <div class="fg"><label>Texto</label>
      <textarea name="prazo_execucao"><?= $v('prazo_execucao') ?></textarea>
    </div>
    <div class="fg" style="max-width:260px"><label>Disponível para início em</label>
      <input type="text" name="data_inicio" value="<?= $v('data_inicio') ?>" placeholder="Ex: 03/02/2026">
    </div>
  </div>

  <?php endif ?>

  <?php if ($tipo === 'pequena'): ?>

  <div class="card">
    <div class="card-title">📝 Apresentação</div>
    <div class="fg"><label>Texto de apresentação</label>
      <textarea name="apresentacao" style="min-height:110px"><?= $v('apresentacao') ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📦 Escopo de Fornecimento — Itens</div>
    <div id="itens-rows">
      <?php foreach ($d['itens'] as $item): ?>
      <div class="item-row frow" style="align-items:end;grid-template-columns:2fr 2fr 1fr auto">
        <div class="fg"><label>Descrição</label>
          <input type="text" name="item_desc[]" value="<?= e($item['descricao']) ?>" placeholder="Ex: Detalhe do Cone de Descarga">
        </div>
        <div class="fg"><label>Desenho / Referência</label>
          <input type="text" name="item_desenho[]" value="<?= e($item['desenho']) ?>">
        </div>
        <div class="fg" style="max-width:110px"><label>Posição</label>
          <input type="text" name="item_pos[]" value="<?= e($item['posicao']) ?>">
        </div>
        <button type="button" class="btn btn-danger btn-sm remove-row" style="margin-bottom:14px">🗑</button>
      </div>
      <?php endforeach ?>
    </div>
    <button type="button" id="add-item" class="btn btn-ghost btn-sm">➕ Adicionar item</button>
    <div class="fg" style="margin-top:16px"><label>O fornecimento contempla (uma linha por item)</label>
      <textarea name="fornecimento"><?= $v('fornecimento') ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-title">💰 Valor e Dados Fiscais</div>
    <p style="font-size:13px;color:var(--gray);margin-bottom:14px">Informe cada valor separadamente e descreva claramente a que ele se refere.</p>
    <div id="valor-rows">
      <?php foreach (($d['valores'] ?? []) as $valor): ?>
      <div class="valor-row frow" style="align-items:end;grid-template-columns:minmax(0,2fr) minmax(160px,1fr) auto;margin-bottom:10px">
        <div class="fg"><label>Descrição do valor</label><input type="text" name="valor_descricao[]" value="<?= e($valor['descricao'] ?? '') ?>" placeholder="Ex: Fabricação das estruturas"></div>
        <div class="fg"><label>Valor (R$)</label><input type="text" class="valor-money" name="valor_item[]" value="<?= e($valor['valor'] ?? '') ?>" placeholder="Ex: 18.500,00" inputmode="decimal"></div>
        <button type="button" class="btn btn-danger btn-sm remove-row" style="margin-bottom:14px">Remover</button>
      </div>
      <?php endforeach ?>
    </div>
    <button type="button" id="add-valor" class="btn btn-ghost btn-sm">+ Adicionar valor</button>
    <div style="display:flex;justify-content:flex-end;align-items:center;gap:14px;margin:16px 0 22px;padding:14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:8px">
      <span style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:.7px">Total calculado</span>
      <strong id="valor-total-preview" style="font-size:18px;color:var(--orange)">R$ 0,00</strong>
    </div>
    <input type="hidden" name="valor_total" id="valor_total" value="<?= $v('valor_total') ?>">
    <div class="fg"><label>Observação fiscal</label>
      <textarea name="obs_fiscal"><?= $v('obs_fiscal') ?></textarea>
    </div>
    <div class="frow">
      <div class="fg"><label>PIS</label><input type="text" name="pis" value="<?= $v('pis') ?>"></div>
      <div class="fg"><label>COFINS</label><input type="text" name="cofins" value="<?= $v('cofins') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>ISS</label><input type="text" name="iss" value="<?= $v('iss') ?>"></div>
      <div class="fg"><label>Retenção INSS</label><input type="text" name="inss" value="<?= $v('inss') ?>"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📋 Condições Comerciais</div>
    <div class="frow">
      <div class="fg"><label>Prazo de entrega</label>
        <textarea name="prazo_entrega"><?= $v('prazo_entrega') ?></textarea>
      </div>
      <div class="fg"><label>Frete</label>
        <input type="text" name="frete" value="<?= $v('frete') ?>">
      </div>
    </div>
    <div class="fg" style="max-width:320px"><label>Validade da proposta</label>
      <input type="text" name="validade" value="<?= $v('validade') ?>">
    </div>
  </div>

  <?php endif ?>

  <div class="card">
    <div class="card-title">💳 Condição de Pagamento e Material</div>
    <div class="fg"><label>Condição de pagamento (uma linha por item)</label>
      <textarea name="condicao_pagamento"><?= $v('condicao_pagamento') ?></textarea>
    </div>
    <div class="frow">
      <div class="fg"><label>Fornecimento de material</label>
        <select name="material">
          <option value="axion"   <?= ($d['material'] ?? '') === 'axion'   ? 'selected' : '' ?>>Material fornecido pela AXION</option>
          <option value="cliente" <?= ($d['material'] ?? '') === 'cliente' ? 'selected' : '' ?>>Material fornecido pelo CONTRATANTE</option>
          <option value="outro"   <?= ($d['material'] ?? '') === 'outro'   ? 'selected' : '' ?>>Outro (especificar)</option>
        </select>
      </div>
      <div class="fg"><label>Observação sobre o material (se "Outro")</label>
        <input type="text" name="material_obs" value="<?= $v('material_obs') ?>">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">🚫 Exclusões de Fornecimento</div>
    <div class="fg"><label>Uma linha por item</label>
      <textarea name="exclusoes" style="min-height:110px"><?= $v('exclusoes') ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-title">🤝 Responsabilidades</div>
    <div class="frow">
      <div class="fg"><label>Responsabilidades da AXION (uma linha por item)</label>
        <textarea name="resp_contratada" style="min-height:110px"><?= $v('resp_contratada') ?></textarea>
      </div>
      <div class="fg"><label>Responsabilidades do Cliente (uma linha por item)</label>
        <textarea name="resp_contratante" style="min-height:110px"><?= $v('resp_contratante') ?></textarea>
      </div>
    </div>
  </div>

  <?php if ($tipo === 'grande'): ?>
  <div class="card">
    <div class="card-title">✅ Comentários Gerais e Assinatura</div>
    <div class="fg"><label>Comentários finais</label>
      <textarea name="comentarios"><?= $v('comentarios') ?></textarea>
    </div>
    <div class="frow">
      <div class="fg"><label>Responsável (nome)</label><input type="text" name="resp_nome" value="<?= $v('resp_nome') ?>"></div>
      <div class="fg"><label>Cargo</label><input type="text" name="resp_cargo" value="<?= $v('resp_cargo') ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>Telefone(s)</label><input type="text" name="resp_tel" value="<?= $v('resp_tel') ?>"></div>
      <div class="fg"><label>Cidade (assinatura)</label><input type="text" name="cidade_assinatura" value="<?= $v('cidade_assinatura') ?>"></div>
    </div>
  </div>
  <?php endif ?>

  <div class="card">
    <div class="card-title">🗒️ Observações</div>
    <div class="fg"><label>Observações adicionais (aparecem no fim da proposta)</label>
      <textarea name="observacoes" placeholder="Observações internas ou complementares para esta proposta"><?= $v('observacoes') ?></textarea>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-bottom:40px">
    <button type="submit" name="save_mode" value="overwrite" class="btn btn-primary">💾 <?= $id ? 'Sobrescrever proposta' : 'Salvar proposta' ?></button>
    <?php if ($id): ?>
      <button type="submit" name="save_mode" value="copy" class="btn btn-secondary">Salvar como nova</button>
    <?php endif ?>
    <button type="submit" name="save_mode" value="print" class="btn btn-secondary">Salvar e gerar PDF</button>
    <a href="/admin/propostas.php" class="btn btn-ghost">Cancelar</a>
  </div>
</form>

<template id="tpl-escopo">
  <div class="escopo-row" style="border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:14px;margin-bottom:12px">
    <div class="fg"><label>Título da etapa</label><input type="text" name="escopo_titulo[]" placeholder="Ex: Montagem de Chapas de Desgaste"></div>
    <div class="fg"><label>Valor</label><input type="text" name="escopo_valor[]" placeholder="Ex: R$ 8,50 por quilo"></div>
    <div class="fg"><label>Descrição (uma linha por item)</label><textarea name="escopo_corpo[]"></textarea></div>
    <button type="button" class="btn btn-danger btn-sm remove-row">🗑 Remover etapa</button>
  </div>
</template>

<template id="tpl-item">
  <div class="item-row frow" style="align-items:end;grid-template-columns:2fr 2fr 1fr auto">
    <div class="fg"><label>Descrição</label><input type="text" name="item_desc[]" placeholder="Ex: Detalhe do Cone de Descarga"></div>
    <div class="fg"><label>Desenho / Referência</label><input type="text" name="item_desenho[]"></div>
    <div class="fg" style="max-width:110px"><label>Posição</label><input type="text" name="item_pos[]" value="01"></div>
    <button type="button" class="btn btn-danger btn-sm remove-row" style="margin-bottom:14px">🗑</button>
  </div>
</template>

<template id="tpl-valor">
  <div class="valor-row frow" style="align-items:end;grid-template-columns:minmax(0,2fr) minmax(160px,1fr) auto;margin-bottom:10px">
    <div class="fg"><label>Descrição do valor</label><input type="text" name="valor_descricao[]" placeholder="Ex: Montagem em campo"></div>
    <div class="fg"><label>Valor (R$)</label><input type="text" class="valor-money" name="valor_item[]" placeholder="Ex: 8.750,00" inputmode="decimal"></div>
    <button type="button" class="btn btn-danger btn-sm remove-row" style="margin-bottom:14px">Remover</button>
  </div>
</template>

<script>
if (!Element.prototype.matches) Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
if (!Element.prototype.closest) {
  Element.prototype.closest = function (selector) {
    var element = this;
    while (element && element.nodeType === 1) {
      if (element.matches(selector)) return element;
      element = element.parentElement;
    }
    return null;
  };
}
var addEscopo = document.getElementById('add-escopo');
if (addEscopo) addEscopo.onclick = function () {
  var tpl = document.getElementById('tpl-escopo').content.cloneNode(true);
  document.getElementById('escopo-rows').appendChild(tpl);
};
var addValor = document.getElementById('add-valor');
if (addValor) addValor.onclick = function () {
  var tpl = document.getElementById('tpl-valor').content.cloneNode(true);
  document.getElementById('valor-rows').appendChild(tpl);
  updateValorTotal();
};

function parseValorBR(value) {
  var clean = String(value || '').replace(/[^\d,.-]/g, '').replace(/\.(?=.*\.)/g, '');
  var normalized = clean.indexOf(',') !== -1 ? clean.replace(/\./g, '').replace(',', '.') : clean;
  var number = parseFloat(normalized);
  return isFinite(number) ? number : 0;
}
function updateValorTotal() {
  var inputs = document.querySelectorAll('.valor-money');
  var total = 0;
  for (var i = 0; i < inputs.length; i++) total += parseValorBR(inputs[i].value);
  var formatted = total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var preview = document.getElementById('valor-total-preview');
  var hidden = document.getElementById('valor_total');
  if (preview) preview.textContent = 'R$ ' + formatted;
  if (hidden) hidden.value = formatted;
}
var valorRows = document.getElementById('valor-rows');
if (valorRows) valorRows.addEventListener('input', updateValorTotal);
updateValorTotal();
var addItem = document.getElementById('add-item');
if (addItem) addItem.onclick = function () {
  var tpl = document.getElementById('tpl-item').content.cloneNode(true);
  document.getElementById('itens-rows').appendChild(tpl);
};
document.addEventListener('click', function (event) {
  var target = event.target;
  var removeButton = target.closest ? target.closest('.remove-row') : null;
  if (removeButton) {
    var row = removeButton.closest('.escopo-row, .item-row, .valor-row');
    if (row && row.parentNode) row.parentNode.removeChild(row);
    updateValorTotal();
  }
});
</script>

<?php endif ?>

<?php layout_end(); ?>
