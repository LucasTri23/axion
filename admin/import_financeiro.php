<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_financeiro_import.php';
$data = require __DIR__ . '/_financeiro_dados.php';
$error = '';
$done = financeiroCargaConcluida(db());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    try {
        financeiroImportarInicial(db());
        redirect('/admin/financeiro.php?imported=1');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[Axion Financeiro] Falha na carga inicial: ' . $e->getMessage());
        $error = 'Não foi possível concluir a importação. Nenhum dado foi gravado. Confira a estrutura do banco e tente novamente.';
    }
}
layout_start('Financeiro — Dados iniciais', 'financeiro');
$finTab = 'geral';
require __DIR__ . '/_financeiro_nav.php';
?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif ?>
<div class="card">
  <div class="card-title">Dados iniciais do financeiro</div>
  <p>Origem: <?= e($data['source']) ?>. A carga inclui 893 lançamentos, 14 serviços de jato/pintura, 7 custos fixos, 13 obras, 23 funcionários e 145 pagamentos em 14 períodos.</p>
  <p>As datas de emissão e vencimento são preservadas separadamente. Entradas e saídas seguem o sinal dos valores originais; divergências de tipo ficam registradas na observação. Totais e saldos da planilha são recalculados pelo financeiro.</p>
  <?php if ($done): ?>
    <div class="alert alert-success">Esta carga inicial já foi importada.</div>
  <?php else: ?>
    <p>A importação só é permitida com o financeiro vazio e não substitui registros existentes.</p>
    <form method="POST">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-primary">Importar dados iniciais</button>
    </form>
  <?php endif ?>
</div>
<div class="card">
  <div class="card-title">Pendências para conferência</div>
  <p>Itens abaixo foram preservados para revisão. Pagamentos sem data, chaves PIX divergentes e valores de TCM não conciliados não foram aplicados automaticamente.</p>
  <table><thead><tr><th>Referência</th><th>Detalhes</th></tr></thead><tbody>
  <?php foreach ($data['pendencias'] as $item): ?>
    <tr><td><?= e(($item['aba'] ?? 'Custos Fixos / Fluxo') . (isset($item['linha']) ? ' — linha ' . $item['linha'] : '') . (isset($item['nome']) ? ' — ' . $item['nome'] : '')) ?></td>
    <td><?= e($item['motivo']) ?>
      <?php if (isset($item['valor'])): ?> Valor: <?= e(brl($item['valor'])) ?>; transporte: <?= e(brl($item['vale_transporte'])) ?>.<?php endif ?>
      <?php if (isset($item['chaves'])): ?> <?= e(implode(' / ', $item['chaves'])) ?><?php endif ?>
      <?php if (isset($item['dados'])): ?> <?= e(implode(' | ', array_map(fn($v) => (string)$v, $item['dados']))) ?><?php endif ?>
    </td></tr>
  <?php endforeach ?>
  </tbody></table>
</div>
<?php layout_end(); ?>
