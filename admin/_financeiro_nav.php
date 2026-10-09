<?php
/**
 * Sub-navegação compartilhada do módulo Financeiro / Centros de Custo.
 * Uso: defina $finTab (uma das chaves abaixo) antes de incluir este arquivo.
 */
$FIN_TABS = [
    'geral' => ['Visão geral',       'financeiro.php'],
    'fluxo' => ['Fluxo de caixa',    'financeiro_fluxo.php'],
    'obras' => ['Custo por obra',    'financeiro_obras.php'],
    'jato'  => ['Jato e pintura',    'financeiro_jato.php'],
    'folha' => ['Folha de pagamento','financeiro_folha.php'],
];
?>
<div class="filter-pills" style="margin-bottom:20px">
  <?php foreach ($FIN_TABS as $key => [$label, $href]): ?>
    <a href="/admin/<?= $href ?>" class="fpill <?= ($finTab ?? '') === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach ?>
</div>
