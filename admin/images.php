<?php
/**
 * Gerenciador de imagens — OWASP A08: Software and Data Integrity
 * - MIME via finfo (não confia no Content-Type do browser)
 * - Extensão via whitelist
 * - Nome aleatório gerado com random_bytes
 * - PHP bloqueado na pasta uploads via .htaccess
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db    = db();
$msg   = '';
$msgCls = 'alert-success';

// Definição das imagens gerenciáveis
$IMAGE_DEFS = [
    'img_hero_bg'   => ['Fundo do Hero',            'Imagem de fundo da seção principal (hero)'],
    'img_service1'  => ['Serviço — Estruturas',     'Card do serviço de Fabricação de Estruturas'],
    'img_service2'  => ['Serviço — Pintura',        'Card do serviço de Pintura a Jato'],
    'img_service3'  => ['Serviço — Calderaria',     'Card do serviço de Calderaria'],
    'img_about'     => ['Foto — Sobre Nós',         'Imagem da seção Sobre'],
];

// Carrega imagens do BD
function getImages(): array {
    $rows = db()->query("SELECT image_key, filename FROM site_images")->fetchAll();
    $map  = [];
    foreach ($rows as $r) $map[$r['image_key']] = $r['filename'];
    return $map;
}

// ── Processar POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';
    $key    = $_POST['image_key'] ?? '';

    // Valida key contra lista conhecida (OWASP A01: controle de acesso)
    if (!array_key_exists($key, $IMAGE_DEFS)) {
        $msg    = 'Chave de imagem inválida.';
        $msgCls = 'alert-error';
        goto render;
    }

    if ($action === 'upload' && isset($_FILES['image_file'])) {
        $file = $_FILES['image_file'];

        // Verifica erros de upload do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg    = 'Erro no upload. Verifique o tamanho do arquivo.';
            $msgCls = 'alert-error';
            goto render;
        }

        // OWASP A08: tamanho
        if ($file['size'] > MAX_FILE_BYTES) {
            $msg    = 'Arquivo muito grande (máximo 3 MB).';
            $msgCls = 'alert-error';
            goto render;
        }

        // OWASP A08: MIME real via finfo (não confia em $_FILES['type'])
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($file['tmp_name']);
        if (!in_array($mimeReal, ALLOWED_MIME, true)) {
            $msg    = 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou GIF.';
            $msgCls = 'alert-error';
            goto render;
        }

        // OWASP A08: extensão via whitelist (não via Content-Type do browser)
        $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($origExt, ALLOWED_EXT, true)) {
            $msg    = 'Extensão de arquivo não permitida.';
            $msgCls = 'alert-error';
            goto render;
        }

        // Nome aleatório seguro — NUNCA usa nome original
        $newName = bin2hex(random_bytes(16)) . '.' . $origExt;
        $dest    = UPLOAD_DIR . $newName;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $msg    = 'Falha ao salvar o arquivo. Verifique permissões da pasta uploads/.';
            $msgCls = 'alert-error';
            goto render;
        }

        // Remove arquivo anterior se existir
        $prev = $db->prepare("SELECT filename FROM site_images WHERE image_key = ? LIMIT 1");
        $prev->execute([$key]);
        if ($old = $prev->fetchColumn()) {
            $oldPath = UPLOAD_DIR . $old;
            if (is_file($oldPath)) @unlink($oldPath);
        }

        // Salva no BD
        $db->prepare(
            "INSERT INTO site_images (image_key, filename, original_name, mime_type, file_size)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE filename=VALUES(filename), original_name=VALUES(original_name),
             mime_type=VALUES(mime_type), file_size=VALUES(file_size), updated_at=NOW()"
        )->execute([$key, $newName, basename($file['name']), $mimeReal, $file['size']]);

        $msg = '✔ Imagem "' . $IMAGE_DEFS[$key][0] . '" atualizada com sucesso!';
    }

    if ($action === 'delete') {
        $row = $db->prepare("SELECT filename FROM site_images WHERE image_key = ? LIMIT 1");
        $row->execute([$key]);
        if ($f = $row->fetchColumn()) {
            $path = UPLOAD_DIR . $f;
            if (is_file($path)) @unlink($path);
        }
        $db->prepare("DELETE FROM site_images WHERE image_key = ?")->execute([$key]);
        $msg = '↩ Imagem "' . $IMAGE_DEFS[$key][0] . '" revertida para o original.';
    }
}

render:
$images = getImages();

layout_start('Editar Imagens', 'images');
?>

<?php if ($msg): ?>
  <div class="alert <?= $msgCls ?>"><?= e($msg) ?></div>
<?php endif ?>

<div class="alert alert-info">
  📸 Formatos aceitos: JPG, PNG, WEBP, GIF · Tamanho máximo: 3 MB ·
  O arquivo original não é apagado — use <strong>↩ Original</strong> para reverter.
</div>

<div class="img-grid">
  <?php foreach ($IMAGE_DEFS as $key => [$label, $desc]):
    $hasCustom  = isset($images[$key]);
    $previewUrl = $hasCustom ? (UPLOAD_URL . $images[$key]) : '';
  ?>
  <div class="img-card">
    <div class="img-preview">
      <?php if ($hasCustom): ?>
        <img src="<?= e($previewUrl) ?>" alt="<?= e($label) ?>">
      <?php else: ?>
        <div class="no-img">🖼️</div>
      <?php endif ?>
    </div>
    <div class="img-body">
      <h4><?= e($label) ?></h4>
      <p><?= e($desc) ?> · <?= $hasCustom ? '<span style="color:var(--sky)">Personalizada</span>' : '<span style="color:var(--gray)">Original</span>' ?></p>
      <div class="img-actions">
        <!-- Upload -->
        <form method="POST" enctype="multipart/form-data" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="image_key" value="<?= e($key) ?>">
          <label class="btn btn-secondary btn-sm" style="cursor:pointer">
            📁 Trocar
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif"
                   style="display:none" onchange="this.form.submit()">
          </label>
        </form>
        <!-- Reverter -->
        <?php if ($hasCustom): ?>
        <form method="POST" style="display:inline"
              onsubmit="return confirm('Reverter para a imagem original?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="image_key" value="<?= e($key) ?>">
          <button type="submit" class="btn btn-ghost btn-sm">↩ Original</button>
        </form>
        <?php else: ?>
          <button class="btn btn-ghost btn-sm" disabled>↩ Original</button>
        <?php endif ?>
      </div>
    </div>
  </div>
  <?php endforeach ?>
</div>

<?php layout_end(); ?>
