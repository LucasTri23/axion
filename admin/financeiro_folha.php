<?php
/**
 * Financeiro — Folha de pagamento — OWASP A01, A03, A04, A09
 * Dados pessoais (CPF, PIX) são mascarados nas listagens (LGPD).
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

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
        if ($data) {
            $db->prepare('INSERT INTO centro_folha_periodos (data_periodo, tipo) VALUES (?,?)')->execute([$data, $tipo]);
            auditLog('create', 'centro_folha_periodos', (int)$db->lastInsertId(), ['data_periodo' => $data, 'tipo' => $tipo]);
        }
        redirect('/admin/financeiro_folha.php?saved=1');
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
        if ($periodoId > 0 && $funcId > 0) {
            $db->prepare('INSERT INTO centro_folha_pagamentos (periodo_id, funcionario_id, valor, vale_transporte, observacao) VALUES (?,?,?,?,?)')
               ->execute([$periodoId, $funcId, $valor, $vt, $obs]);
            auditLog('create', 'centro_folha_pagamentos', (int)$db->lastInsertId(), compact('periodoId', 'funcId', 'valor', 'vt'));
        }
        redirect('/admin/financeiro_folha.php?periodo_id=' . $periodoId . '&saved=1');
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
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Excluído com sucesso.</div><?php endif ?>
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
        'SELECT p.*, f.nome AS funcionario_nome FROM centro_folha_pagamentos p
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
        <thead><tr><th>Funcionário</th><th class="num">Valor</th><th class="num">Vale Transp.</th><th>Observação</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pagamentos as $p): ?>
            <tr>
              <td class="td-name"><?= e($p['funcionario_nome']) ?></td>
              <td class="mono" style="text-align:right"><?= e(brl($p['valor'])) ?></td>
              <td class="mono" style="text-align:right"><?= $p['vale_transporte'] > 0 ? e(brl($p['vale_transporte'])) : '—' ?></td>
              <td style="font-size:12.5px"><?= e($p['observacao'] ?: '—') ?></td>
              <td>
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

  $funcionarios = $db->query('SELECT * FROM centro_funcionarios ORDER BY ativo DESC, nome')->fetchAll();
?>

  <div class="card">
    <div class="card-title">👷 Funcionários</div>

    <details class="add-form">
      <summary><span class="btn btn-secondary btn-sm">+ Novo funcionário</span></summary>
      <form method="POST" class="frow" style="margin-top:12px;flex-wrap:wrap">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_func">
        <div class="fg"><label>Nome</label><input type="text" name="nome" maxlength="150" required></div>
        <div class="fg"><label>CPF</label><input type="text" name="cpf" maxlength="20"></div>
        <div class="fg"><label>Telefone</label><input type="text" name="telefone" maxlength="30"></div>
        <div class="fg"><label>Chave PIX</label><input type="text" name="pix_chave" maxlength="150"></div>
        <div class="fg"><label>Dados bancários</label><input type="text" name="banco_info" maxlength="150" placeholder="Agência / conta"></div>
        <div class="fg"><button type="submit" class="btn btn-primary">Adicionar</button></div>
      </form>
    </details>

    <?php if (!$funcionarios): ?>
      <div class="empty"><div class="empty-icon">👷</div><p>Nenhum funcionário cadastrado ainda.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th><th>PIX</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($funcionarios as $f): $fid = 'func-edit-' . (int)$f['id']; ?>
            <tr>
              <td class="td-name"><?= e($f['nome']) ?></td>
              <td class="mono" style="font-size:12.5px"><?= e(maskDoc($f['cpf'])) ?></td>
              <td style="font-size:12.5px"><?= e($f['telefone'] ?: '—') ?></td>
              <td class="mono" style="font-size:12.5px"><?= e(maskDoc($f['pix_chave'])) ?></td>
              <td><span class="badge <?= $f['ativo'] ? 'b-concluido' : 'b-cancelado' ?>"><?= $f['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td style="white-space:nowrap">
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('<?= $fid ?>').hidden=!document.getElementById('<?= $fid ?>').hidden">✏️</button>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_func">
                  <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm"><?= $f['ativo'] ? '⏸ Desativar' : '▶ Ativar' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este funcionário? (só é possível se ele não tiver pagamentos registrados)')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_func">
                  <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                </form>
              </td>
            </tr>
            <tr id="<?= $fid ?>" hidden>
              <td colspan="6">
                <form method="POST" class="frow" style="flex-wrap:wrap;padding:10px 0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="edit_func">
                  <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                  <div class="fg"><label>Nome</label><input type="text" name="nome" value="<?= e($f['nome']) ?>" required></div>
                  <div class="fg"><label>CPF</label><input type="text" name="cpf" value="<?= e($f['cpf']) ?>"></div>
                  <div class="fg"><label>Telefone</label><input type="text" name="telefone" value="<?= e($f['telefone']) ?>"></div>
                  <div class="fg"><label>Chave PIX</label><input type="text" name="pix_chave" value="<?= e($f['pix_chave']) ?>"></div>
                  <div class="fg"><label>Dados bancários</label><input type="text" name="banco_info" value="<?= e($f['banco_info']) ?>"></div>
                  <div class="fg"><button type="submit" class="btn btn-primary btn-sm">💾 Salvar</button></div>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="card-title">📅 Períodos de folha</div>
    <form method="POST" class="frow" style="align-items:flex-end;margin-bottom:16px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_periodo">
      <div class="fg"><label>Data</label><input type="date" name="data_periodo" required></div>
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
              <td><?= e(date('d/m/Y', strtotime($p['data_periodo']))) ?></td>
              <td><span class="badge <?= $p['tipo']==='Adiantamento' ? 'b-andamento' : 'b-novo' ?>"><?= e($p['tipo']) ?></span></td>
              <td class="mono" style="text-align:right"><?= (int)$p['qtd'] ?></td>
              <td class="mono" style="text-align:right"><?= e(brl($p['total'])) ?></td>
              <td style="white-space:nowrap">
                <a href="/admin/financeiro_folha.php?periodo_id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Ver pagamentos</a>
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
