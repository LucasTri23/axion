<?php
/**
 * Gestão de orçamentos — OWASP A01, A03, A04
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db = db();
$statusLabel = [
    'novo'      => ['b-novo',      'Novo'],
    'andamento' => ['b-andamento', 'Em Andamento'],
    'concluido' => ['b-concluido', 'Concluído'],
    'cancelado' => ['b-cancelado', 'Cancelado'],
];

// ── Ações POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['quote_id'] ?? 0);

    if ($action === 'update_status' && $id > 0) {
        $allowed = ['novo','andamento','concluido','cancelado'];
        $status  = $_POST['status'] ?? '';
        if (in_array($status, $allowed, true)) {
            $db->prepare("UPDATE quotes SET status=?, updated_at=NOW() WHERE id=?")
               ->execute([$status, $id]);
        }
        redirect('/admin/quotes.php?id=' . $id . '&saved=1');
    }

    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM quotes WHERE id=?")->execute([$id]);
        redirect('/admin/quotes.php?deleted=1');
    }
}

// ── Ver detalhe de um pedido ──────────────────────────────────────
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($viewId > 0) {
    $q = $db->prepare("SELECT * FROM quotes WHERE id = ? LIMIT 1");
    $q->execute([$viewId]);
    $quote = $q->fetch();
    if (!$quote) redirect('/admin/quotes.php');

    layout_start('Orçamento #' . $viewId, 'quotes');
    [$cls, $lbl] = $statusLabel[$quote['status']] ?? ['b-novo','Novo'];
?>
  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">✔ Status atualizado com sucesso.</div>
  <?php endif ?>

  <div style="display:flex;gap:12px;margin-bottom:18px">
    <a href="/admin/quotes.php" class="btn btn-ghost btn-sm">← Voltar à lista</a>
    <a href="mailto:<?= e($quote['email']) ?>?subject=Orçamento%20Axion%20Industrial&body=Olá%20<?= rawurlencode($quote['name']) ?>,"
       class="btn btn-primary btn-sm">✉ Responder por E-mail</a>
  </div>

  <div class="card">
    <div class="card-title">📋 Detalhes do Pedido <span class="badge <?= $cls ?>"><?= $lbl ?></span></div>
    <dl class="detail-grid">
      <dt>Nome</dt>        <dd><?= e($quote['name']) ?></dd>
      <dt>Empresa</dt>     <dd><?= e($quote['company'] ?: '—') ?></dd>
      <dt>E-mail</dt>      <dd><a href="mailto:<?= e($quote['email']) ?>" style="color:var(--sky)"><?= e($quote['email']) ?></a></dd>
      <dt>Telefone</dt>    <dd><?= e($quote['phone'] ?: '—') ?></dd>
      <dt>Serviço</dt>     <dd><?= e($quote['service'] ?: '—') ?></dd>
      <dt>Recebido em</dt> <dd><?= e(date('d/m/Y H:i', strtotime($quote['created_at']))) ?></dd>
    </dl>
    <div style="margin-top:14px">
      <div style="font-size:11.5px;font-weight:600;color:var(--gray2);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Descrição do Projeto</div>
      <div class="detail-box"><?= nl2br(e($quote['description'] ?: '(sem descrição)')) ?></div>
    </div>
  </div>

  <div class="card" style="max-width:400px">
    <div class="card-title">Alterar Status</div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="quote_id" value="<?= (int)$quote['id'] ?>">
      <div class="fg">
        <label>Status atual</label>
        <select name="status">
          <?php foreach ($statusLabel as $val => [$c, $l]): ?>
            <option value="<?= $val ?>" <?= $quote['status'] === $val ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:4px">
        <button type="submit" class="btn btn-primary btn-sm">💾 Salvar</button>
        <button type="submit" form="form-delete" class="btn btn-danger btn-sm">🗑 Excluir</button>
      </div>
    </form>
    <form id="form-delete" method="POST" onsubmit="return confirm('Excluir este pedido permanentemente?')">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="quote_id" value="<?= (int)$quote['id'] ?>">
    </form>
  </div>

<?php
    layout_end();
    exit;
}

// ── Lista de pedidos ──────────────────────────────────────────────
$filter  = $_GET['status'] ?? 'all';
$allowed = ['all','novo','andamento','concluido','cancelado'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$search = trim($_GET['q'] ?? '');

$sql    = "SELECT id, name, company, email, phone, service, status, created_at FROM quotes";
$params = [];
$where  = [];

if ($filter !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $where[]  = "(name LIKE ? OR email LIKE ? OR company LIKE ? OR service LIKE ?)";
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();

layout_start('Pedidos de Orçamento', 'quotes');
?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success">🗑 Pedido excluído com sucesso.</div>
<?php endif ?>

<div class="card">
  <div class="tbl-header">
    <div class="filter-pills">
      <?php foreach ([
        ['all','Todos'], ['novo','Novos'], ['andamento','Em Andamento'],
        ['concluido','Concluídos'], ['cancelado','Cancelados']
      ] as [$v, $l]): ?>
        <a href="?status=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="fpill <?= $filter === $v ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach ?>
    </div>
    <form method="GET" style="display:flex;gap:8px">
      <input type="hidden" name="status" value="<?= e($filter) ?>">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar..."
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 12px;color:#fff;font-size:13px;outline:none;width:180px">
      <button type="submit" class="btn btn-ghost btn-sm">🔍</button>
    </form>
  </div>

  <?php if (!$quotes): ?>
    <div class="empty"><div class="empty-icon">🔍</div><p>Nenhum pedido encontrado.</p></div>
  <?php else: ?>
    <table>
      <thead><tr>
        <th>Nome / Empresa</th>
        <th>Contato</th>
        <th>Serviço</th>
        <th>Data</th>
        <th>Status</th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($quotes as $q):
          [$cls, $lbl] = $statusLabel[$q['status']] ?? ['b-novo','Novo'];
        ?>
          <tr>
            <td>
              <div class="td-name"><?= e($q['name']) ?></div>
              <div class="td-sub"><?= e($q['company']) ?></div>
            </td>
            <td>
              <div style="font-size:13px"><?= e($q['email']) ?></div>
              <div class="td-sub"><?= e($q['phone']) ?></div>
            </td>
            <td style="font-size:13px"><?= e($q['service'] ?: '—') ?></td>
            <td style="font-size:12px;color:var(--gray)"><?= e(date('d/m/Y H:i', strtotime($q['created_at']))) ?></td>
            <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            <td>
              <a href="/admin/quotes.php?id=<?= (int)$q['id'] ?>" class="btn btn-secondary btn-sm">Ver</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<?php layout_end(); ?>
