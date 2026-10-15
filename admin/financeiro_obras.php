<?php
/**
 * Financeiro — Custo por obra — OWASP A01, A03, A04, A09
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();

$CATEGORIAS = [
    'material'  => 'Material',
    'mao_obra'  => 'Mão de obra',
    'terceiros' => 'Terceiros',
    'impostos'  => 'Impostos',
];

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_obra') {
        $nome = trim($_POST['nome'] ?? '');
        $os   = trim($_POST['os'] ?? '');
        $cli  = trim($_POST['cliente'] ?? '');
        if ($nome !== '') {
            $db->prepare('INSERT INTO centro_obras (os, nome, cliente) VALUES (?,?,?)')
               ->execute([$os, $nome, $cli]);
            auditLog('create', 'centro_obras', (int)$db->lastInsertId(), ['nome' => $nome, 'os' => $os, 'cliente' => $cli]);
        }
        redirect('/admin/financeiro_obras.php?saved=1');
    }

    if ($action === 'edit_obra') {
        $id     = (int)($_POST['id'] ?? 0);
        $nome   = trim($_POST['nome'] ?? '');
        $os     = trim($_POST['os'] ?? '');
        $cli    = trim($_POST['cliente'] ?? '');
        $status = ($_POST['status'] ?? '') === 'concluido' ? 'concluido' : 'ativo';
        if ($id > 0 && $nome !== '') {
            $db->prepare('UPDATE centro_obras SET nome=?, os=?, cliente=?, status=? WHERE id=?')
               ->execute([$nome, $os, $cli, $status, $id]);
            auditLog('update', 'centro_obras', $id, ['nome' => $nome, 'os' => $os, 'cliente' => $cli, 'status' => $status]);
        }
        redirect('/admin/financeiro_obras.php?saved=1');
    }

    if ($action === 'delete_obra') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_obras WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_obras WHERE id=?')->execute([$id]); // cascade apaga custos
            auditLog('delete', 'centro_obras', $id, $old ?: []);
        }
        redirect('/admin/financeiro_obras.php?deleted=1');
    }

    if ($action === 'add_custo') {
        $obraId    = (int)($_POST['obra_id'] ?? 0);
        $categoria = array_key_exists($_POST['categoria'] ?? '', $CATEGORIAS) ? $_POST['categoria'] : 'material';
        $descricao = trim($_POST['descricao'] ?? '');
        $valor     = toFloat($_POST['valor'] ?? '0');
        $data      = $_POST['data'] ?: null;
        if ($obraId > 0 && $valor >= 0) {
            $db->prepare('INSERT INTO centro_custos_obra (obra_id, categoria, descricao, valor, data) VALUES (?,?,?,?,?)')
               ->execute([$obraId, $categoria, $descricao, $valor, $data]);
            auditLog('create', 'centro_custos_obra', (int)$db->lastInsertId(), compact('obraId', 'categoria', 'descricao', 'valor'));
        }
        redirect('/admin/financeiro_obras.php?obra_id=' . $obraId . '&saved=1');
    }

    if ($action === 'edit_custo') {
        $id = (int)($_POST['id'] ?? 0);
        $obraId = (int)($_POST['obra_id'] ?? 0);
        $categoria = $_POST['categoria'] ?? '';
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = toFloat($_POST['valor'] ?? '0');
        $data = $_POST['data'] ?? '';
        $parsed = $data === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        $stmt = $db->prepare('SELECT id FROM centro_custos_obra WHERE id=? AND obra_id=?');
        $stmt->execute([$id, $obraId]);
        if ($stmt->fetchColumn() && isset($CATEGORIAS[$categoria]) && is_finite($valor) && $valor >= 0 && $valor <= 9999999999.99 && preg_match('/^.{0,255}$/us', $descricao) && ($data === '' || ($parsed && $parsed->format('Y-m-d') === $data))) {
            $db->prepare('UPDATE centro_custos_obra SET categoria=?, descricao=?, valor=?, data=? WHERE id=? AND obra_id=?')
               ->execute([$categoria, $descricao, $valor, $data ?: null, $id, $obraId]);
            auditLog('update', 'centro_custos_obra', $id, compact('categoria', 'descricao', 'valor', 'data'));
            redirect('/admin/financeiro_obras.php?obra_id=' . $obraId . '&saved=1');
        }
        redirect('/admin/financeiro_obras.php?obra_id=' . $obraId . '&err=dados_invalidos');
    }

    if ($action === 'delete_custo') {
        $id     = (int)($_POST['id'] ?? 0);
        $obraId = (int)($_POST['obra_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM centro_custos_obra WHERE id=?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $db->prepare('DELETE FROM centro_custos_obra WHERE id=?')->execute([$id]);
            auditLog('delete', 'centro_custos_obra', $id, $old ?: []);
        }
        redirect('/admin/financeiro_obras.php?obra_id=' . $obraId . '&deleted=1');
    }
}

layout_start('Financeiro — Custo por obra', 'financeiro');
$finTab = 'obras';
require __DIR__ . '/_financeiro_nav.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">✔ Salvo com sucesso.</div><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">🗑 Excluído com sucesso.</div><?php endif ?>
<?php if (($_GET['err'] ?? '') === 'dados_invalidos'): ?><div class="alert alert-error">Confira os dados do custo. A alteração não foi salva.</div><?php endif ?>

<?php
$obraId = (int)($_GET['obra_id'] ?? 0);

if ($obraId > 0):
    $stmt = $db->prepare('SELECT * FROM centro_obras WHERE id=?');
    $stmt->execute([$obraId]);
    $obra = $stmt->fetch();
    if (!$obra) redirect('/admin/financeiro_obras.php');

    $stmt = $db->prepare('SELECT * FROM centro_custos_obra WHERE obra_id=? ORDER BY data DESC, id DESC');
    $stmt->execute([$obraId]);
    $custos = $stmt->fetchAll();

    $subtotais = ['material' => 0, 'mao_obra' => 0, 'terceiros' => 0, 'impostos' => 0];
    foreach ($custos as $c) { $subtotais[$c['categoria']] += (float)$c['valor']; }
    $totalObra = array_sum($subtotais);
?>
  <div style="margin-bottom:16px"><a href="/admin/financeiro_obras.php" class="btn btn-ghost btn-sm">← Voltar às obras</a></div>

  <div class="card">
    <div class="card-title">🏗 <?= e($obra['nome']) ?> <span class="text-muted">OS <?= e($obra['os'] ?: '—') ?></span></div>
    <div class="kpi-grid">
      <?php foreach ($subtotais as $cat => $val): ?>
        <div class="kpi"><div class="kpi-label"><?= e($CATEGORIAS[$cat]) ?></div><div class="kpi-val s mono" style="font-size:26px"><?= e(brl($val)) ?></div></div>
      <?php endforeach ?>
    </div>
    <div style="font-size:14px;color:var(--gray2)">Total apurado: <strong class="mono"><?= e(brl($totalObra)) ?></strong></div>
  </div>

  <div class="card">
    <div class="card-title">➕ Novo custo</div>
    <form method="POST" class="frow" style="align-items:flex-end;flex-wrap:wrap">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_custo">
      <input type="hidden" name="obra_id" value="<?= (int)$obraId ?>">
      <div class="fg">
        <label>Categoria</label>
        <select name="categoria">
          <?php foreach ($CATEGORIAS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="fg"><label>Descrição</label><input type="text" name="descricao" maxlength="255"></div>
      <div class="fg"><label>Valor (R$)</label><input type="number" name="valor" step="0.01" min="0" required></div>
      <div class="fg"><label>Data</label><input type="date" name="data"></div>
      <div class="fg"><button type="submit" class="btn btn-primary">Adicionar</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">📋 Custos lançados</div>
    <?php if (!$custos): ?>
      <div class="empty"><div class="empty-icon">📭</div><p>Nenhum custo lançado para esta obra ainda.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Categoria</th><th>Descrição</th><th>Data</th><th class="num">Valor</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($custos as $c): ?>
            <tr>
              <td><span class="badge" style="background:rgba(58,175,228,.1);color:var(--sky)"><?= e($CATEGORIAS[$c['categoria']] ?? $c['categoria']) ?></span></td>
              <td><?= e($c['descricao'] ?: '—') ?></td>
              <td style="font-size:12.5px;color:var(--gray)"><?= e($c['data'] ? date('d/m/Y', strtotime($c['data'])) : '—') ?></td>
              <td class="mono" style="text-align:right"><?= e(brl($c['valor'])) ?></td>
              <td>
                <details>
                  <summary class="btn btn-secondary btn-sm">✏️ Editar custo</summary>
                  <form method="POST" class="frow" style="margin:12px 0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_custo">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="obra_id" value="<?= (int)$obraId ?>">
                    <div class="fg"><label>Categoria</label><select name="categoria"><?php foreach ($CATEGORIAS as $key => $label): ?><option value="<?= e($key) ?>" <?= $c['categoria'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
                    <div class="fg"><label>Descrição</label><input type="text" name="descricao" maxlength="255" value="<?= e($c['descricao']) ?>"></div>
                    <div class="fg"><label>Valor (R$)</label><input type="number" name="valor" min="0" step="0.01" value="<?= e($c['valor']) ?>" required></div>
                    <div class="fg"><label>Data</label><input type="date" name="data" value="<?= e($c['data'] ?? '') ?>"></div>
                    <div class="fg"><button type="submit" class="btn btn-primary btn-sm">Salvar custo</button></div>
                  </form>
                </details>
                <form method="POST" onsubmit="return confirm('Excluir este custo?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_custo">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="obra_id" value="<?= (int)$obraId ?>">
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
  $obras = $db->query(
      "SELECT o.*, COALESCE(SUM(c.valor),0) AS total
       FROM centro_obras o LEFT JOIN centro_custos_obra c ON c.obra_id = o.id
       GROUP BY o.id ORDER BY o.created_at DESC"
  )->fetchAll();
?>

  <div class="card">
    <div class="card-title">➕ Nova obra / projeto</div>
    <form method="POST" class="frow" style="align-items:flex-end;flex-wrap:wrap">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_obra">
      <div class="fg"><label>Nome da obra / projeto</label><input type="text" name="nome" maxlength="255" required></div>
      <div class="fg"><label>OS</label><input type="text" name="os" maxlength="20" placeholder="2601"></div>
      <div class="fg"><label>Cliente</label><input type="text" name="cliente" maxlength="255"></div>
      <div class="fg"><button type="submit" class="btn btn-primary">Adicionar obra</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">🏗 Obras cadastradas</div>
    <?php if (!$obras): ?>
      <div class="empty"><div class="empty-icon">🏗</div><p>Nenhuma obra cadastrada ainda.</p></div>
    <?php else: ?>
      <div class="obra-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
        <?php foreach ($obras as $o): $fid = 'obra-edit-' . (int)$o['id']; ?>
          <div class="card" style="margin:0">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
              <div>
                <div class="text-muted">OS <?= e($o['os'] ?: '—') ?></div>
                <div style="font-weight:700;font-size:15px"><?= e($o['nome']) ?></div>
                <div class="text-muted"><?= e($o['cliente'] ?: 'sem cliente definido') ?></div>
              </div>
              <span class="badge <?= $o['status']==='concluido' ? 'b-concluido' : 'b-andamento' ?>"><?= $o['status']==='concluido' ? 'Concluída' : 'Ativa' ?></span>
            </div>
            <div class="mono" style="font-size:20px;margin:10px 0"><?= e(brl($o['total'])) ?></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <a href="/admin/financeiro_obras.php?obra_id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">Ver custos</a>
              <a class="btn btn-secondary btn-sm" href="/admin/financeiro_obras.php?edit_obra=<?= (int)$o['id'] ?>#<?= $fid ?>">✏️ Editar</a>
              <form method="POST" onsubmit="return confirm('Excluir esta obra e todos os custos lançados nela?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_obra">
                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
              </form>
            </div>
            <?php if ((int)($_GET['edit_obra'] ?? 0) === (int)$o['id']): ?>
            <form method="POST" id="<?= $fid ?>" style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="edit_obra">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <div class="fg"><label>Nome</label><input type="text" name="nome" value="<?= e($o['nome']) ?>" required></div>
              <div class="fg"><label>OS</label><input type="text" name="os" value="<?= e($o['os']) ?>"></div>
              <div class="fg"><label>Cliente</label><input type="text" name="cliente" value="<?= e($o['cliente']) ?>"></div>
              <div class="fg">
                <label>Status</label>
                <select name="status">
                  <option value="ativo" <?= $o['status']==='ativo'?'selected':'' ?>>Ativa</option>
                  <option value="concluido" <?= $o['status']==='concluido'?'selected':'' ?>>Concluída</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">💾 Salvar</button>
              <a href="/admin/financeiro_obras.php" class="btn btn-ghost btn-sm">Cancelar</a>
            </form>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
<?php endif ?>

<?php layout_end(); ?>
