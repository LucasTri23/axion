<?php
/**
 * Visualização para impressão/PDF da proposta — layout fiel aos dois
 * padrões AXION (Técnica/Grande e Comercial/Pequena).
 * Uso: botão "Baixar PDF" chama window.print() — o navegador gera o PDF
 * (Salvar como PDF) preservando o layout exato via CSS de impressão.
 */
require_once __DIR__ . '/_auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM propostas WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); exit('Proposta não encontrada.'); }

$d = json_decode($p['dados'] ?: '[]', true) ?: [];
$g = fn($k, $fb = '') => $d[$k] ?? $fb;

$MESES = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$dataFmt = $p['data_proposta'] ? date('d/m/Y', strtotime($p['data_proposta'])) : '';
$dataExtenso = '';
if ($p['data_proposta']) {
    $ts = strtotime($p['data_proposta']);
    $dataExtenso = date('d', $ts) . ' de ' . $MESES[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}

function bullets(string $text): string {
    $out = '';
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $out .= '<li>' . e($line) . "</li>\n";
    }
    return $out;
}
function paragraphs(string $text): string {
    $out = '';
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $out .= '<p>' . e($line) . "</p>\n";
    }
    return $out;
}
function materialLabel(string $m, string $obs): string {
    if ($m === 'cliente') return 'O material será fornecido pelo CONTRATANTE.';
    if ($m === 'outro')   return $obs !== '' ? e($obs) : 'A definir entre as partes.';
    return 'O material será fornecido pela AXION INDUSTRIAL LTDA.';
}

$tipo = $p['tipo'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposta — <?= e($p['cliente_nome']) ?> — Axion</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Calibri','Segoe UI',Arial,sans-serif;color:#1a1a1a;background:#e8e8e8;font-size:14px;line-height:1.55}
.toolbar{position:sticky;top:0;z-index:10;background:#080F1E;color:#fff;padding:12px 20px;display:flex;gap:12px;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.3)}
.toolbar a,.toolbar button{font-family:'Segoe UI',Arial,sans-serif;font-size:13.5px;font-weight:600;padding:9px 18px;border-radius:8px;border:none;cursor:pointer;text-decoration:none}
.toolbar .back{background:rgba(255,255,255,.08);color:#fff}
.toolbar .pdf{background:linear-gradient(135deg,#E8751A,#FF8F2C);color:#fff;margin-left:auto}
.sheet{max-width:850px;margin:24px auto;background:#fff;padding:38px 46px;box-shadow:0 4px 24px rgba(0,0,0,.25)}
.hdr{display:flex;border:1px solid #444;margin-bottom:22px}
.hdr .logo-cell{width:150px;display:flex;align-items:center;justify-content:center;padding:8px;border-right:1px solid #444}
.hdr .logo-cell img{max-width:100%;max-height:60px}
.hdr .mid-cell{flex:1;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;border-right:1px solid #444;padding:8px;text-align:center}
.hdr .info-cell{width:220px;font-size:11.5px}
.hdr .info-cell div{padding:5px 10px;border-bottom:1px solid #444}
.hdr .info-cell div:last-child{border-bottom:none}
.hdr .info-cell b{font-weight:700}
h1.title{font-size:26px;text-align:center;margin:30px 0 4px;letter-spacing:.5px}
p.subtitle{text-align:center;font-size:15px;font-weight:600;margin-bottom:26px}
.company-name{text-align:center;font-size:19px;font-weight:700;margin:18px 0 6px}
.company-cnpj{text-align:center;font-size:12.5px;color:#444;margin-bottom:22px}
.center-logo{display:block;margin:0 auto 6px;max-height:90px}
h2.sec{font-size:15px;font-weight:700;color:#0D1B3E;border-bottom:2px solid #3AAFE4;padding-bottom:4px;margin:26px 0 10px}
h3.sub{font-size:13.5px;font-weight:700;margin:16px 0 6px}
p{margin-bottom:8px;text-align:justify}
ul{margin:6px 0 12px 22px}
li{margin-bottom:4px}
table.info-tbl{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:13px}
table.info-tbl td{border:1px solid #999;padding:7px 10px}
table.info-tbl td.k{background:#0D1B3E;color:#fff;font-weight:700;width:200px}
table.data-tbl{width:100%;border-collapse:collapse;margin:10px 0 16px;font-size:13px}
table.data-tbl th{background:#0D1B3E;color:#fff;text-align:left;padding:8px 10px;font-size:12px}
table.data-tbl td{border:1px solid #ccc;padding:8px 10px}
.valor-total{font-size:15px;font-weight:700;margin:12px 0}
.sign{margin-top:34px}
.sign b{display:block}
.footer-bar{border-top:3px solid #3AAFE4;margin-top:34px;padding-top:8px;text-align:center;font-size:11px;color:#555}
@media print {
  body{background:#fff}
  .toolbar{display:none}
  .sheet{box-shadow:none;margin:0;max-width:100%;padding:14mm 16mm}
  h2.sec{break-after:avoid}
  .escopo-block, table{break-inside:avoid}
  @page{size:A4;margin:12mm}
}
</style>
</head>
<body>

<div class="toolbar">
  <a href="/admin/propostas.php" class="back">← Voltar</a>
  <span style="font-size:13px;color:#B8C4D0">Proposta — <?= e($p['cliente_nome']) ?></span>
  <button class="pdf" onclick="window.print()">🖨️ Baixar PDF</button>
</div>

<div class="sheet">

<?php if ($tipo === 'grande'): ?>

  <div class="hdr">
    <div class="logo-cell"><img src="/img/logo.png" alt="Axion"></div>
    <div class="mid-cell">PROPOSTA<br>TÉCNICA</div>
    <div class="info-cell">
      <div><b>Orçamento:</b> <?= e($p['numero'] ?: '—') ?></div>
      <div><b>Revisão:</b> <?= e($p['revisao'] ?: '00') ?></div>
      <div><b>Data:</b> <?= e($dataFmt ?: '—') ?></div>
    </div>
  </div>

  <h1 class="title">PROPOSTA TÉCNICA<br>COMERCIAL</h1>
  <p class="subtitle" style="font-weight:400;font-size:14px">Axion Industrial Ltda</p>
  <p class="subtitle"><?= e($p['objeto'] ?: '') ?></p>

  <p style="font-size:12px;font-weight:700;margin:24px 0 10px">AXION INDUSTRIAL LTDA – CNPJ 63.637.526/0001-05</p>

  <h2 class="sec">1 – Apresentação</h2>
  <p>À</p>
  <p><b><?= e($p['cliente_nome']) ?></b></p>
  <?php if ($p['cliente_cnpj']): ?><p>CNPJ: <?= e($p['cliente_cnpj']) ?></p><?php endif ?>
  <?php if ($p['cliente_contato']): ?><p>A/C: <?= e($p['cliente_contato']) ?>,</p><?php endif ?>
  <p style="margin-top:10px">Prezado(a)<?= $p['cliente_contato'] ? ' ' . e($p['cliente_contato']) : '' ?>,</p>
  <?= paragraphs($g('apresentacao')) ?>
  <p style="margin-top:14px"><?= e($g('cidade_assinatura','Matozinhos')) ?><?= $dataExtenso ? ', ' . $dataExtenso . '.' : '.' ?></p>
  <p style="margin-top:14px">Atenciosamente,</p>
  <div class="sign">
    <b><?= e($g('resp_nome')) ?></b>
    <span><?= e($g('resp_cargo')) ?></span><br>
    <span>Axion Industrial Ltda</span><br>
    <span><?= e($g('resp_tel')) ?></span>
  </div>

  <h2 class="sec">2 – Introdução</h2>
  <h3 class="sub">2.1 Objetivo</h3>
  <?= paragraphs($g('objetivo')) ?>

  <h3 class="sub">2.2 Endereços para Entrega, Fabricação e Faturamento</h3>
  <p>Unidade fabril (entrega, fabricação, montagem, tratamento de superfície e pintura):</p>
  <p><?= e($g('end_entrega_rua')) ?> — <?= e($g('end_entrega_bairro')) ?> — <?= e($g('end_entrega_cidade')) ?> — CEP <?= e($g('end_entrega_cep')) ?></p>
  <p style="margin-top:10px"><b>Endereço para faturamento</b> (sede administrativa):</p>
  <p><?= e($g('end_fat_rua')) ?> — <?= e($g('end_fat_bairro')) ?> — <?= e($g('end_fat_cidade')) ?> — CEP <?= e($g('end_fat_cep')) ?></p>

  <h2 class="sec">3 – Escopo dos Serviços</h2>
  <?php $n = 1; foreach ($g('escopo', []) as $item): ?>
    <div class="escopo-block">
      <h3 class="sub">3.<?= $n++ ?> <?= e($item['titulo']) ?></h3>
      <ul>
        <li><b>Valor:</b> <?= e($item['valor']) ?></li>
        <?= bullets($item['corpo']) ?>
      </ul>
    </div>
  <?php endforeach ?>

  <div class="escopo-block">
    <h3 class="sub">3.<?= $n ?> Armazenamento e Embalagem</h3>
    <?= paragraphs($g('armazenamento')) ?>
    <ul>
      <li>Área coberta de pintura: <?= e($g('area_pintura')) ?></li>
      <li>Área externa para peças expostas: <?= e($g('area_externa')) ?></li>
    </ul>
  </div>

  <h2 class="sec">4 – Prazo de Execução</h2>
  <?= paragraphs($g('prazo_execucao')) ?>
  <?php if ($g('data_inicio')): ?>
    <p>A AXION declara-se disponível para início de fabricação a partir de <b><?= e($g('data_inicio')) ?></b>.</p>
  <?php endif ?>

  <h2 class="sec">5 – Valores</h2>
  <p>Os valores dos serviços serão apurados conforme abaixo:</p>
  <ul>
    <?php foreach ($g('escopo', []) as $item): ?>
      <li><?= e($item['titulo']) ?>: <?= e($item['valor']) ?></li>
    <?php endforeach ?>
  </ul>
  <p>A medição será realizada com base em ticket de balança ou boletim de medição, conforme o tipo de serviço.</p>

  <h2 class="sec">6 – Condições de Pagamento</h2>
  <ul><?= bullets($g('condicao_pagamento')) ?></ul>
  <p><b>Fornecimento de material:</b> <?= materialLabel($g('material','axion'), $g('material_obs')) ?></p>
  <p style="margin-top:10px"><i>A emissão das Notas Fiscais de faturamento ocorrerá somente após a conclusão da fabricação das peças, devidamente conferidas, pesadas e aprovadas, conforme os respectivos Boletins de Medição.</i></p>

  <h2 class="sec">7 – Exclusões de Fornecimento</h2>
  <p>Não fazem parte do escopo desta proposta, salvo acordo formal entre as partes:</p>
  <ul><?= bullets($g('exclusoes')) ?></ul>

  <h2 class="sec">8 – Responsabilidade da Contratada (Axion Industrial Ltda)</h2>
  <ul><?= bullets($g('resp_contratada')) ?></ul>

  <h2 class="sec">9 – Responsabilidade da Contratante (<?= e($p['cliente_nome']) ?>)</h2>
  <ul><?= bullets($g('resp_contratante')) ?></ul>

  <h2 class="sec">10 – Comentários Gerais</h2>
  <?= paragraphs($g('comentarios')) ?>
  <?php if (trim($g('observacoes'))): ?>
    <h3 class="sub">Observações</h3>
    <?= paragraphs($g('observacoes')) ?>
  <?php endif ?>

  <div class="sign" style="margin-top:26px">
    <b>Axion Industrial Ltda</b>
    <span><?= e($g('cidade_assinatura','Matozinhos')) ?> – MG</span><br>
    <span>Telefone: <?= e($g('resp_tel')) ?></span>
  </div>

  <div class="footer-bar">AXION INDUSTRIAL LTDA &nbsp;·&nbsp; Contatos: (31) 97191-9658 &nbsp;|&nbsp; (31) 97159-4583</div>

<?php else: /* ── PADRÃO PEQUENA ─────────────────────────────────── */ ?>

  <img src="/img/logo.png" class="center-logo" alt="Axion">
  <p class="company-name">AXION INDUSTRIAL LTDA</p>
  <p class="company-cnpj">CNPJ: 63.637.526/0001-05</p>

  <h1 class="title" style="font-size:22px">PROPOSTA COMERCIAL</h1>
  <p class="subtitle"><?= e(strtoupper($p['objeto'] ?: '')) ?></p>

  <table class="info-tbl">
    <tr><td class="k">Cliente</td><td><?= e($p['cliente_nome']) ?></td></tr>
    <?php if ($p['cliente_contato']): ?><tr><td class="k">A/C</td><td><?= e($p['cliente_contato']) ?></td></tr><?php endif ?>
    <?php if ($p['cliente_cnpj']): ?><tr><td class="k">CNPJ do Cliente</td><td><?= e($p['cliente_cnpj']) ?></td></tr><?php endif ?>
    <tr><td class="k">Data</td><td><?= e($dataFmt ?: '—') ?></td></tr>
    <tr><td class="k">Proponente</td><td>AXION INDUSTRIAL LTDA</td></tr>
    <tr><td class="k">CNPJ</td><td>63.637.526/0001-05</td></tr>
  </table>

  <h2 class="sec">1. Apresentação</h2>
  <p>Prezados,</p>
  <?= paragraphs($g('apresentacao')) ?>

  <h2 class="sec">2. Escopo de Fornecimento</h2>
  <p>A presente proposta contempla a fabricação dos seguintes componentes:</p>
  <table class="data-tbl">
    <tr><th>Descrição</th><th>Desenho</th><th>Posição</th></tr>
    <?php foreach ($g('itens', []) as $item): ?>
      <tr><td><?= e($item['descricao']) ?></td><td><?= e($item['desenho']) ?></td><td><?= e($item['posicao']) ?></td></tr>
    <?php endforeach ?>
  </table>
  <p><b>O fornecimento contempla:</b></p>
  <ul><?= bullets($g('fornecimento')) ?></ul>

  <h2 class="sec">3. Valor</h2>
  <p class="valor-total">VALOR TOTAL DA PROPOSTA: R$ <?= e($g('valor_total') ?: '—') ?></p>
  <p><b>Observação fiscal:</b> <?= e($g('obs_fiscal')) ?></p>

  <h3 class="sub">3.1. Dados Fiscais para Emissão de Nota</h3>
  <table class="data-tbl">
    <tr><th>PIS</th><td><?= e($g('pis')) ?></td></tr>
    <tr><th>COFINS</th><td><?= e($g('cofins')) ?></td></tr>
    <tr><th>ISS</th><td><?= e($g('iss')) ?></td></tr>
    <tr><th>Retenção de INSS na nota de serviço de obra</th><td><?= e($g('inss')) ?></td></tr>
  </table>

  <h2 class="sec">4. Condições Comerciais</h2>
  <table class="data-tbl">
    <tr><th style="width:220px">Condição de pagamento</th><td><?= e($g('condicao_pagamento')) ?></td></tr>
    <tr><th>Prazo de entrega</th><td><?= nl2br(e($g('prazo_entrega'))) ?></td></tr>
    <tr><th>Frete</th><td><?= e($g('frete')) ?></td></tr>
    <tr><th>Validade da proposta</th><td><?= e($g('validade')) ?></td></tr>
    <tr><th>Fornecimento de material</th><td><?= materialLabel($g('material','axion'), $g('material_obs')) ?></td></tr>
  </table>

  <h2 class="sec">5. Responsabilidades da AXION</h2>
  <ul><?= bullets($g('resp_contratada')) ?></ul>

  <h2 class="sec">6. Responsabilidades da Contratante – <?= e($p['cliente_nome']) ?></h2>
  <ul><?= bullets($g('resp_contratante')) ?></ul>

  <h2 class="sec">7. Exclusões</h2>
  <ul><?= bullets($g('exclusoes')) ?></ul>

  <?php if (trim($g('observacoes'))): ?>
    <h2 class="sec">Observações</h2>
    <?= paragraphs($g('observacoes')) ?>
  <?php endif ?>

  <div class="sign" style="text-align:center;margin-top:34px">
    <p>Atenciosamente,</p>
    <b>AXION INDUSTRIAL LTDA</b>
    <span>CNPJ: 63.637.526/0001-05</span>
  </div>

  <div class="footer-bar">AXION INDUSTRIAL LTDA &nbsp;·&nbsp; Contatos: (31) 97191-9658 &nbsp;|&nbsp; (31) 97159-4583</div>

<?php endif ?>

</div>
</body>
</html>
