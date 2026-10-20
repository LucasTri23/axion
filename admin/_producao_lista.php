<?php // Recebe $rows, $osId e $manager da página autenticada de produção. ?>
<section class="card prod-list-card">
  <div class="prod-section-heading"><div><span class="prod-eyebrow">LISTA DE FABRICAÇÃO</span><h2>Peças da OS <span class="prod-count"><?= count($rows) ?></span></h2><p>Consulte o avanço, confira os pesos e abra uma peça para atualizar.</p></div><span class="prod-table-hint">Desenho e romaneio nos detalhes de cada peça</span></div>
  <div class="prod-toolbar"><label class="prod-check"><input type="checkbox" data-prod-all><span>Selecionar resultado filtrado</span></label><span class="prod-selection-caption" aria-live="polite"><strong data-prod-count>0</strong> selecionadas · <strong data-prod-selection-weight>0,000 kg</strong></span></div>
  <?php if(!$rows): ?><div class="empty"><p>Nenhuma peça encontrada.</p><a class="btn btn-secondary" href="?os=<?= $osId ?>">Limpar filtros</a></div><?php else: ?>
  <div class="prod-scroll" tabindex="0" aria-label="Lista de peças da OS"><table class="prod-table prod-piece-table">
  <thead><tr><th class="prod-select-cell"><span class="prod-sr-only">Selecionar</span></th><th>Peça / material</th><th>Data</th><th>Qtd.</th><th>Peso total</th><th>Responsável</th><th>Status e avanço</th><th class="prod-action-cell">Ação</th></tr></thead><tbody>
  <?php foreach($rows as $p): $grams=prodPesoGramas((string)$p['peso_unitario'])*(int)$p['quantidade'];$advance=prodAvanco(json_decode($p['etapas'],true)); ?>
    <tr>
      <td class="prod-select-cell"><input type="checkbox" data-prod-grams="<?= $grams ?>" aria-label="<?= e('Selecionar '.$p['tag'].' '.$p['descricao']) ?>"></td>
      <td class="prod-piece-cell" data-label="Peça"><strong class="prod-code"><?= e($p['tag']?:'Peça #'.$p['id']) ?></strong><span class="prod-material"><?= e($p['descricao']) ?></span><details class="prod-row-details"><summary>Desenho e detalhes</summary><dl><dt>Desenho</dt><dd><?= e($p['desenho']?:'Não informado') ?></dd><dt>Revisão</dt><dd><?= e($p['revisao']?:'—') ?></dd><dt>Avulsas</dt><dd><?= e($p['avulsas']?:'—') ?></dd><dt>Romaneio</dt><dd><?= e($p['romaneio']?:'Não informado') ?></dd></dl></details></td>
      <td class="prod-nowrap" data-label="Data"><?= e($p['data']?date('d/m/Y',strtotime($p['data'])):'—') ?></td>
      <td class="prod-number" data-label="Quantidade"><?= (int)$p['quantidade'] ?></td>
      <td class="prod-weight-cell" data-label="Peso"><strong class="prod-nowrap"><?= number_format($grams/1000,3,',','.') ?> <small>kg</small></strong><span class="prod-cell-note prod-nowrap"><?= number_format((float)$p['peso_unitario'],3,',','.') ?> kg / peça</span></td>
      <td class="prod-owner-cell" data-label="Responsável"><?= e($p['responsavel']?:'Não definido') ?></td>
      <td class="prod-stage-cell" data-label="Produção"><span class="prod-stage prod-stage-<?= e($p['status']) ?>"><?= e(PROD_STATUS[$p['status']]??$p['status']) ?></span><div class="prod-progress"><progress max="100" value="<?= $advance ?>" aria-label="Avanço da peça"></progress><span><?= number_format($advance,2,',','.') ?>%</span></div></td>
      <td class="prod-action-cell"><a class="btn btn-secondary btn-sm" href="?os=<?= $osId ?>&edit=<?= (int)$p['id'] ?>#editar"><?= $manager?'Editar peça':'Atualizar' ?></a></td>
    </tr>
  <?php endforeach ?></tbody></table></div><?php endif ?>
</section>
