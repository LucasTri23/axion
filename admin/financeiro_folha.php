<?php
/**
 * Financeiro — Folha de pagamento — OWASP A01, A03, A04, A09
 * Chaves PIX completas disponíveis à equipe autenticada para pagamentos.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_folha_pagamentos.php';

$db = db();

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_func', 'edit_func'], true)) {
        $nome = trim($_POST['nome'] ?? '');
        $id = $action === 'edit_func' ? (int)($_POST['id'] ?? 0) : 0;
        foreach (['nome' => 150, 'cpf' => 20, 'telefone' => 30, 'pix_chave' => 150, 'banco_info' => 150] as $field => $limit) {
            if (!preg_match('/^.{0,' . $limit . '}$/us', trim($_POST[$field] ?? ''))) {
                redirect('/admin/financeiro_folha.php?err=dados_invalidos');
            }
        }
        if ($nome === '' || ($action === 'edit_func' && $id <= 0)) redirect('/admin/financeiro_folha.php?err=dados_invalidos');
        $check = $db->prepare('SELECT id FROM centro_funcionarios WHERE nome=? AND id<>?');
        $check->execute([$nome, $id]);
        if ($check->fetchColumn()) redirect('/admin/financeiro_folha.php?err=nome_duplicado');
        if ($action === 'edit_func') {
            $check = $db->prepare('SELECT id FROM centro_funcionarios WHERE id=?');
            $check->execute([$id]);
            if (!$check->fetchColumn()) redirect('/admin/financeiro_folha.php?err=dados_invalidos');
        }
    }

    if ($action === 'add_func') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome !== '') {
            $stmt = $db->prepare(
                'INSERT INTO centro_funcionarios (nome, cpf, telefone, pix_chave, banco_info) VALUES (?,?,?,?,?)'
            );
            $stmt->execute([$nome, trim($_POST['cpf'] ?? ''), trim($_POST['telefone'] ?? ''), trim($_POST['pix_chave'] ?? ''), trim($_POST['banco_info'] ?? '')]);
            auditLog('create', 'centro_funcionarios', (int)$db->lastInsertId(), ['nome' => $nome]);
        }
        redirect('/admin/financeiro_folha.php?saved=1');
    }

    if ($action === 'edit_func') {
        $id   = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id > 0 && $nome !== '') {
            $db->prepare('UPDATE centro_funcionarios SET nome=?, cpf=?, telefone=?, pix_chave=?, banco_info=? WHERE id=?')
               ->execute([$nome, trim($_POST['cpf'] ?? ''), trim($_POST['telefone'] ?? ''), trim($_POST['pix_chave'] ?? ''), trim($_POST['banco_info'] ?? ''), $id]);
            auditLog('update', 'centro_funcionarios', $id, ['nome' => $nome]);
        }
        redirect('/admin/financeiro_folha.php?saved=1');
    }

    if ($action === 'toggle_func') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('UPDATE centro_funcionarios SET ativo = 1 - ativo WHERE id=?')->execute([$id]);
            auditLog('update', 'centro_funcionarios', $id, ['acao' => 'toggle_ativo']);
        }
        redirect('/admin/financeiro_folha.php?saved=1');
    }

    if ($action === 'delete_func') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $chk = $db->prepare('SELECT COUNT(*) FROM centro_folha_pagamentos WHERE funcionario_id=?');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                redirect('/admin/financeiro_folha.php?err=funcionario_com_pagamentos');
            }
            $stmt = $db->prepare('SELECT * FROM centro_funcionarios WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_funcionarios WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_funcionarios', $id, $old ?: []);
        }
        redirect('/admin/financeiro_folha.php?deleted=1');
    }

    if ($action === 'add_periodo') {
        $data = $_POST['data_periodo'] ?? '';
        $tipo = ($_POST['tipo'] ?? '') === 'Adiantamento' ? 'Adiantamento' : 'Fechamento';
        $competencia = $_POST['competencia'] ?? '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $competencia) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data) || !checkdate((int)substr($data,5,2),(int)substr($data,8,2),(int)substr($data,0,4))) redirect('/admin/financeiro_folha.php?err=dados_invalidos');
        if ($data) {
            $db->prepare('INSERT INTO centro_folha_periodos (data_periodo, tipo, competencia) VALUES (?,?,?)')->execute([$data, $tipo, $competencia]);
            auditLog('create', 'centro_folha_periodos', (int)$db->lastInsertId(), ['data_periodo' => $data, 'tipo' => $tipo, 'competencia' => $competencia]);
        }
        redirect('/admin/financeiro_folha.php?saved=1');
    }

    if ($action === 'edit_periodo') {
        $id = (int)($_POST['id'] ?? 0);
        $data = $_POST['data_periodo'] ?? '';
        $tipo = ($_POST['tipo'] ?? '') === 'Adiantamento' ? 'Adiantamento' : 'Fechamento';
        $competencia = $_POST['competencia'] ?? '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $competencia)) redirect('/admin/financeiro_folha.php?err=dados_invalidos');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($id > 0 && $parsed && $parsed->format('Y-m-d') === $data) {
            $db->prepare('UPDATE centro_folha_periodos SET data_periodo=?, tipo=?, competencia=? WHERE id=?')->execute([$data, $tipo, $competencia, $id]);
            auditLog('update', 'centro_folha_periodos', $id, ['data_periodo' => $data, 'tipo' => $tipo, 'competencia' => $competencia]);
            redirect('/admin/financeiro_folha.php?saved=1');
        }
        redirect('/admin/financeiro_folha.php?err=dados_invalidos');
    }

    if ($action === 'delete_periodo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_folha_periodos WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_folha_periodos WHERE id=?')->execute([$id]); // cascade apaga pagamentos
            auditLog('delete', 'centro_folha_periodos', $id, $old ?: []);
        }
        redirect('/admin/financeiro_folha.php?deleted=1');
    }

    if ($action === 'add_pagamento') {
        $periodoId = (int)($_POST['periodo_id'] ?? 0);
        $funcId    = (int)($_POST['funcionario_id'] ?? 0);
        $valor     = toFloat($_POST['valor'] ?? '0');
        $vt        = toFloat($_POST['vale_transporte'] ?? '0');
        $obs       = trim($_POST['observacao'] ?? '');
        if (!is_finite($valor) || !is_finite($vt) || $valor < 0 || $vt < 0 || $valor > 9999999999.99 || $vt > 9999999999.99 || !preg_match('/^.{0,255}$/us', $obs)) {
            redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&err=dados_invalidos');
        }
        if ($periodoId > 0 && $funcId > 0) {
            $db->prepare('INSERT INTO centro_folha_pagamentos (periodo_id, funcionario_id, valor, vale_transporte, observacao,situacao_pagamento,valor_pago) VALUES (?,?,?,?,?,?,?)')
               ->execute([$periodoId, $funcId, $valor, $vt, $obs, 'pendente', '0.00']);
            auditLog('create', 'centro_folha_pagamentos', (int)$db->lastInsertId(), compact('periodoId', 'funcId', 'valor', 'vt'));
        }
        redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&saved=1');
    }

    if ($action === 'edit_pagamento') {
        $id = (int)($_POST['id'] ?? 0);
        $periodoId = (int)($_POST['periodo_id'] ?? 0);
        $valor = toFloat($_POST['valor'] ?? '0');
        $vt = toFloat($_POST['vale_transporte'] ?? '0');
        $obs = trim($_POST['observacao'] ?? '');
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT * FROM centro_folha_pagamentos WHERE id=? AND periodo_id=? FOR UPDATE');
        $stmt->execute([$id, $periodoId]);
        $old = $stmt->fetch();
        $expected = (int)round(($valor+$vt)*100);
        $paid = $old && $old['valor_pago'] !== null ? folhaCentavos($old['valor_pago']) : null;
        if ($old && is_finite($valor) && is_finite($vt) && $valor >= 0 && $vt >= 0 && $valor <= 9999999999.99 && $vt <= 9999999999.99 && ($paid === null || $paid <= $expected) && preg_match('/^.{0,255}$/us', $obs)) {
            $status = $paid === null ? 'a_confirmar' : ($paid > 0 ? ($paid >= $expected ? 'pago' : 'parcial') : 'pendente');
            $db->prepare('UPDATE centro_folha_pagamentos SET valor=?, vale_transporte=?, observacao=?,situacao_pagamento=? WHERE id=? AND periodo_id=?')
               ->execute([$valor, $vt, $obs, $status, $id, $periodoId]);
            auditLog('update', 'centro_folha_pagamentos', $id, ['antes'=>$old,'valor'=>$valor,'vt'=>$vt,'obs'=>$obs,'situacao'=>$status]);
            $db->commit();
            redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&saved=1');
        }
        $db->rollBack();
        redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&err=dados_invalidos');
    }

    if ($action === 'delete_pagamento') {
        $id        = (int)($_POST['id'] ?? 0);
        $periodoId = (int)($_POST['periodo_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_folha_pagamentos WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_folha_pagamentos WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_folha_pagamentos', $id, $old ?: []);
        }
        redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&deleted=1');
    }
}

layout_start('Financeiro — Folha de pagamento', 'financeiro');
$finTab = 'folha';
require __DIR__ . '/_financeiro_nav.php';
?><div style="margin-bottom:18px"><a class="btn btn-secondary" href="/admin/financeiro_funcionarios.php">Funcion?rios e pagamentos do m?s</a></div><?php
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Excluído com sucesso.</div><?php endif ?>
<?php if (($_GET['err'] ?? '') === 'dados_invalidos'): ?><div class="alert alert-error">Confira os dados informados. A alteração não foi salva.</div><?php endif ?>
<?php if (($_GET['err'] ?? '') === 'nome_duplicado'): ?><div class="alert alert-error">Já existe um funcionário com esse nome. Confira o cadastro antes de salvar.</div><?php endif ?>
<?php if (($_GET['err'] ?? '') === 'funcionario_com_pagamentos'): ?>
  <div class="alert alert-error">⚠️ Este funcionário tem pagamentos já registrados e não pode ser excluído — use "Desativar" para removê-lo das novas folhas mantendo o histórico.</div>
<?php endif ?>

<?php
$periodoId = (int)($_GET['periodo_id'] ?? 0);

if ($periodoId > 0):
    $stmt = $db->prepare('SELECT * FROM centro_folha_periodos WHERE id=?');
    $stmt->execute([$periodoId]);
    $periodo = $stmt->fetch();
    if (!$periodo) redirect('/admin/financeiro_folha.php');

    $stmt = $db->prepare(
        'SELECT p.*, f.nome AS funcionario_nome, f.pix_chave FROM centro_folha_pagamentos p
         JOIN centro_funcionarios f ON f.id = p.funcionario_id
         WHERE p.periodo_id = ? ORDER BY f.nome'
    );
    $stmt->execute([$periodoId]);
    $pagamentos = $stmt->fetchAll();

    $funcionariosAtivos = $db->query('SELECT id, nome FROM centro_funcionarios WHERE ativo=1 ORDER BY nome')->fetchAll();

    $totalPeriodo = array_sum(array_map(fn($p) => (float)$p['valor'] + (float)$p['vale_transporte'], $pagamentos));
?>
  <div style="margin-bottom:16px"><a href="/admin/financeiro_folha.php" class="btn btn-ghost btn-sm">← Voltar à folha</a></div>

  <div class="card">
    <div class="card-title">📅 Período <?= e(date('d/m/Y', strtotime($periodo['data_periodo']))) ?> — <?= e($periodo['tipo']) ?></div>
    <div style="font-size:14px;color:var(--gray2)">Total do período: <strong class="mono"><?= e(brl($totalPeriodo)) ?></strong></div>
  </div>

  <div class="card">
    <div class="card-title">➕ Novo pagamento</div>
    <?php if (!$funcionariosAtivos): ?>
      <div class="text-muted">Cadastre um funcionário ativo primeiro (na lista abaixo) para lançar pagamentos.</div>
    <?php else: ?>
      <form method="POST" class="frow" style="align-items:flex-end;flex-wrap:wrap">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_pagamento">
        <input type="hidden" name="periodo_id" value="<?= (int)$periodoId ?>">
        <div class="fg">
          <label>Funcionário</label>
          <select name="funcionario_id" required>
            <?php foreach ($funcionariosAtivos as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['nome']) ?></option><?php endforeach ?>
          </select>
        </div>
        <div class="fg"><label>Valor (R$)</label><input type="number" name="valor" step="0.01" min="0" required></div>
        <div class="fg"><label>Vale transporte (R$)</label><input type="number" name="vale_transporte" step="0.01" min="0" value="0"></div>
        <div class="fg"><label>Observação</label><input type="text" name="observacao" maxlength="255"></div>
        <div class="fg"><button type="submit" class="btn btn-primary">Adicionar</button></div>
      </form>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="card-title">📋 Pagamentos do período</div>
    <?php if (!$pagamentos): ?>
      <div class="empty"><div class="empty-icon">📭</div><p>Nenhum pagamento lançado neste período ainda.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Funcionário / PIX</th><th class="num">Valor</th><th class="num">Vale Transp.</th><th>Observação</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pagamentos as $p): ?>
            <tr>
              <td class="td-name"><a href="/admin/financeiro_funcionarios.php?id=<?= (int)$p['funcionario_id'] ?>&amp;mes=<?= e($periodo['competencia'] ?? substr($periodo['data_periodo'],0,7)) ?>"><?= e($p['funcionario_nome']) ?></a><div class="mono" style="font-size:12px;overflow-wrap:anywhere;user-select:all">PIX: <?= e($p['pix_chave'] ?: 'Não informado') ?></div></td>
              <td class="mono" style="text-align:right"><?= e(brl($p['valor'])) ?></td>
              <td class="mono" style="text-align:right"><?= $p['vale_transporte'] > 0 ? e(brl($p['vale_transporte'])) : '—' ?></td>
              <td style="font-size:12.5px"><?= e($p['observacao'] ?: '—') ?></td>
              <td>
                <details>
                  <summary class="btn btn-secondary btn-sm">✏️ Editar pagamento</summary>
                  <form method="POST" class="frow" style="margin:12px 0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_pagamento">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="periodo_id" value="<?= (int)$periodoId ?>">
                    <div class="fg"><label>Valor (R$)</label><input type="number" name="valor" min="0" step="0.01" value="<?= e($p['valor']) ?>" required></div>
                    <div class="fg"><label>Vale transporte (R$)</label><input type="number" name="vale_transporte" min="0" step="0.01" value="<?= e($p['vale_transporte']) ?>" required></div>
                    <div class="fg"><label>Observação</label><input type="text" name="observacao" maxlength="255" value="<?= e($p['observacao']) ?>"></div>
                    <div class="fg"><button class="btn btn-primary btn-sm" type="submit">Salvar pagamento</button></div>
                  </form>
                </details>
                <form method="POST" onsubmit="return confirm('Excluir este pagamento?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_pagamento">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="periodo_id" value="<?= (int)$periodoId ?>">
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
  $periodos = $db->query(
      "SELECT p.*, COALESCE(SUM(pg.valor + pg.vale_transporte),0) AS total, COUNT(pg.id) AS qtd
       FROM centro_folha_periodos p LEFT JOIN centro_folha_pagamentos pg ON pg.periodo_id = p.id
       GROUP BY p.id ORDER BY p.data_periodo DESC"
  )->fetchAll();

?>

  <div class="card">
    <div class="card-title">📅 Períodos de folha</div>
    <form method="POST" class="frow" style="align-items:flex-end;margin-bottom:16px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_periodo">
      <div class="fg"><label>Data</label><input type="date" name="data_periodo" required></div>
      <div class="fg"><label>Competência</label><input type="month" name="competencia" value="<?= e(folhaMes('')) ?>" required></div>
      <div class="fg">
        <label>Tipo</label>
        <select name="tipo">
          <option value="Fechamento">Fechamento</option>
          <option value="Adiantamento">Adiantamento</option>
        </select>
      </div>
      <div class="fg"><button type="submit" class="btn btn-primary">Adicionar período</button></div>
    </form>

    <?php if (!$periodos): ?>
      <div class="empty"><div class="empty-icon">📅</div><p>Nenhum período cadastrado ainda.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Data</th><th>Tipo</th><th class="num">Pagamentos</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($periodos as $p): ?>
            <tr>
              <td><?= e(date('d/m/Y', strtotime($p['data_periodo']))) ?><div class="td-sub">Competência <?= e($p['competencia'] ?? substr($p['data_periodo'],0,7)) ?></div></td>
              <td><span class="badge <?= $p['tipo']==='Adiantamento' ? 'b-andamento' : 'b-novo' ?>"><?= e($p['tipo']) ?></span></td>
              <td class="mono" style="text-align:right"><?= (int)$p['qtd'] ?></td>
              <td class="mono" style="text-align:right"><?= e(brl($p['total'])) ?></td>
              <td style="white-space:nowrap">
                <a href="/admin/financeiro_folha.php?periodo_id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Ver pagamentos</a>
                <details style="margin:8px 0;white-space:normal">
                  <summary class="btn btn-secondary btn-sm">✏️ Editar período</summary>
                  <form method="POST" class="frow" style="margin-top:12px">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_periodo">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <div class="fg"><label>Data</label><input type="date" name="data_periodo" value="<?= e($p['data_periodo']) ?>" required></div>
                    <div class="fg"><label>Competência</label><input type="month" name="competencia" value="<?= e($p['competencia'] ?? substr($p['data_periodo'],0,7)) ?>" required></div>
                    <div class="fg"><label>Tipo</label><select name="tipo"><option value="Fechamento" <?= $p['tipo'] === 'Fechamento' ? 'selected' : '' ?>>Fechamento</option><option value="Adiantamento" <?= $p['tipo'] === 'Adiantamento' ? 'selected' : '' ?>>Adiantamento</option></select></div>
                    <div class="fg"><button type="submit" class="btn btn-primary btn-sm">Salvar período</button></div>
                  </form>
                </details>
                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este período e todos os pagamentos lançados nele?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_periodo">
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
<?php endif ?>

<?php layout_end(); ?>
