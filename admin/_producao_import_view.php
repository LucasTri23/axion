<?php // Visualização da importação. Dados e permissões vêm do controller autenticado. ?>
<link rel="stylesheet" href="/css/producao.css?v=20261019-ui2">
<div class="prod-page-heading"><div><a class="prod-back" href="/admin/producao.php?os=<?= $os ?>">← Voltar para OS <?= e($order['numero']) ?></a><h2>Importar lista de fabricação</h2><p>Envie o Excel, confira os itens e confirme a inclusão na OS.</p></div><span class="prod-file-pill">Excel · .xlsx</span></div>
<?php if($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
<ol class="prod-stepper" aria-label="Etapas da importação"><li class="is-done"><span>1</span>Enviar arquivo</li><li class="<?= $preview?'is-current':'' ?>" <?= $preview?'aria-current="step"':'' ?>><span>2</span>Conferir peças</li><li><span>3</span>Confirmar importação</li></ol>
<details class="card prod-upload-card" <?= $preview?'':'open' ?>>
  <summary class="prod-summary"><span><?= $preview?'Arquivo enviado: '.e($preview['nome']):'Escolher planilha de fabricação' ?></span><small><?= $preview?'Trocar arquivo':'Até 20 MB' ?></small></summary>
  <form method="POST" enctype="multipart/form-data" class="prod-upload-form">
    <?= csrfField() ?><input type="hidden" name="action" value="preview"><input type="hidden" name="os_id" value="<?= $os ?>">
    <div class="fg"><label>Lista de fabricação (.xlsx)</label><input type="file" name="planilha" accept=".xlsx" required><span class="prod-field-help">O arquivo será conferido antes de salvar qualquer peça.</span></div>
    <div class="prod-upload-action"><button type="submit" class="btn btn-primary">Conferir planilha <span aria-hidden="true">→</span></button></div>
  </form>
  <details class="prod-format-help"><summary>Ver cabeçalhos e orientações do modelo</summary><p>DATA, AVULSAS, DESCRIÇÃO DO MATERIAL, TAG, Nº DESENHO, QUANTIDADE, PESO, RESPONSÁVEL, Nº ROMANEIO e STATUS FABRICAÇÃO.</p><p>PESO é unitário em kg. GRD é aceito como alternativa à DATA. Etapas opcionais: PREP, MONT, SOLDA, ACAB, PINT, EXD. Responsáveis cadastrados são vinculados automaticamente; nomes sem login são preservados para conferência.</p></details>
</details>
<?php if($preview): $result=$preview['result'];$grams=0;foreach($result['rows'] as $row)$grams+=prodPesoGramas($row['peso_unitario'])*(int)$row['quantidade'];$warningCount=count($result['warnings']??[]); ?>
<section class="card prod-preview-card">
  <div class="prod-section-heading"><div><span class="prod-eyebrow">CONFERÊNCIA</span><h2>Revise sua lista</h2><p>Confira quantidades e pesos antes de incluir as peças.</p></div><span class="prod-file-pill"><?= e($preview['nome']) ?></span></div>
  <div class="prod-import-metrics"><div><span>Itens da lista</span><strong><?= count($result['rows']) ?></strong></div><div><span>Peso total calculado</span><strong><?= number_format($grams/1000,3,',','.') ?> <small>kg</small></strong></div><div class="<?= $warningCount?'has-warning':'' ?>"><span>Avisos para revisar</span><strong><?= $warningCount ?></strong></div></div>
  <div class="prod-weight-notice"><strong>Peso unitário × quantidade = peso total.</strong><span>Se a planilha antiga já informa o peso total na coluna PESO, ajuste o arquivo antes de continuar.</span></div>
  <?php if($result['errors']): ?><div class="prod-import-errors" role="alert"><strong><?= count($result['errors']) ?> erros precisam ser corrigidos</strong><ul><?php foreach($result['errors'] as $message): ?><li><?= e($message) ?></li><?php endforeach ?></ul></div><?php endif ?>
  <?php if($warningCount): ?><details class="prod-import-warnings"><summary><span>Revisar <?= $warningCount ?> avisos</span><small>A importação pode continuar após sua confirmação</small></summary><p>Os valores originais serão guardados no histórico. As peças com fabricação incompleta não serão marcadas como expedidas automaticamente.</p><ul><?php foreach($result['warnings'] as $warning): ?><li><strong>Linha <?= (int)$warning['linha'] ?> · <?= e($warning['tag']??'') ?></strong><span><?= e($warning['motivo']) ?></span></li><?php endforeach ?></ul></details><?php endif ?>
  <div class="prod-preview-toolbar"><strong>Peças identificadas <span class="prod-count"><?= count($result['rows']) ?></span></strong><span>Arraste a tabela para ver todas as colunas, se necessário</span></div>
  <div class="prod-scroll prod-preview-scroll" tabindex="0" aria-label="Prévia das peças a importar">
    <table class="prod-table prod-preview-table"><thead><tr><th>Linha / data</th><th>Material</th><th>TAG e desenho</th><th>Qtd.</th><th>Peso unitário</th><th>Peso total</th><th>Responsável</th><th>Status e avanço</th></tr></thead><tbody>
    <?php foreach($result['rows'] as $row): $advance=prodAvanco($row['etapas']); ?>
      <tr><td class="prod-nowrap"><strong>#<?= (int)$row['linha'] ?></strong><span class="prod-cell-note"><?= e($row['data']?date('d/m/Y',strtotime($row['data'])):'Sem data') ?></span></td><td class="prod-material-cell"><?= e($row['descricao']) ?></td><td class="prod-identity-cell"><strong class="prod-code"><?= e($row['tag']?:'Sem TAG') ?></strong><span class="prod-cell-note prod-drawing"><?= e($row['desenho']) ?></span></td><td class="prod-number"><?= (int)$row['quantidade'] ?></td><td class="prod-nowrap"><?= number_format((float)$row['peso_unitario'],3,',','.') ?> kg</td><td class="prod-nowrap"><strong><?= number_format(prodPesoGramas($row['peso_unitario'])*(int)$row['quantidade']/1000,3,',','.') ?> kg</strong></td><td class="prod-owner-cell"><?= e($row['responsavel']?:'Não definido') ?></td><td class="prod-stage-cell"><span class="prod-stage prod-stage-<?= e($row['status']) ?>"><?= e(PROD_STATUS[$row['status']]) ?></span><div class="prod-progress"><progress max="100" value="<?= $advance ?>" aria-label="Avanço da peça"></progress><span><?= number_format($advance,2,',','.') ?>%</span></div></td></tr>
    <?php endforeach ?></tbody></table>
  </div>
  <?php if(!$result['errors']): ?>
  <form method="POST" class="prod-import-footer">
    <?= csrfField() ?><input type="hidden" name="action" value="confirmar"><input type="hidden" name="os_id" value="<?= $os ?>"><input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>">
    <div class="prod-confirmations"><label class="prod-import-confirm"><input type="checkbox" name="peso_confirmado" required><span><strong>Conferi os pesos e as quantidades</strong><small>A coluna PESO contém o peso de cada peça.</small></span></label><?php if($warningCount): ?><label class="prod-import-confirm"><input type="checkbox" name="avisos_confirmados" required><span><strong>Li os <?= $warningCount ?> avisos da lista</strong><small>Vou conferir as peças indicadas no PCP após a importação.</small></span></label><?php endif ?></div>
    <div class="prod-confirm-action"><button type="submit" class="btn btn-primary">Importar <?= count($result['rows']) ?> peças <span aria-hidden="true">→</span></button><span>Os registros serão adicionados à OS <?= e($order['numero']) ?>.</span></div>
  </form>
  <?php else: ?><div class="prod-import-footer"><p>Corrija os erros e envie o arquivo novamente. Nenhuma peça foi importada.</p></div><?php endif ?>
</section>
<?php endif ?>
<script src="/js/producao.js?v=20261019-ui2" defer></script>
