<?php
/**
 * Editor de conteúdo do site — OWASP A03, A04
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db      = db();
$saved   = false;
$error   = '';
$activeSection = 'hero';

// Carrega todo o conteúdo atual do BD
function getContent(): array {
    $rows = db()->query("SELECT content_key, content_value FROM site_content")->fetchAll();
    $map  = [];
    foreach ($rows as $r) $map[$r['content_key']] = $r['content_value'];
    return $map;
}

function setContent(string $key, string $value): void {
    db()->prepare(
        "INSERT INTO site_content (content_key, content_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = NOW()"
    )->execute([$key, $value]);
}

function deleteContent(string $key): void {
    db()->prepare("DELETE FROM site_content WHERE content_key = ?")->execute([$key]);
}

// ── Processar POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $section = $_POST['section'] ?? '';
    $activeSection = in_array($section, ['hero','services','about','diff','contact'], true) ? $section : 'hero';
    $reset   = isset($_POST['reset']);

    $sections = [
        'hero'    => ['hero_badge','hero_l1','hero_l2','hero_l3','hero_sub','kpi1_num','kpi1_label','kpi2_num','kpi2_label','kpi3_num','kpi3_label'],
        'about'   => ['about_p1','about_p2'],
        'diff'    => ['diff1_title','diff1_text','diff2_title','diff2_text','diff3_title','diff3_text','diff4_title','diff4_text','diff5_title','diff5_text','diff6_title','diff6_text'],
        'contact' => ['contact_phone','contact_email','contact_address','contact_hours'],
    ];

    if ($section === 'services') {
        $titles = $_POST['service_title'] ?? [];
        $descriptions = $_POST['service_description'] ?? [];
        $images = $_POST['service_image'] ?? [];
        $items = $_POST['service_items'] ?? [];
        $services = [];
        foreach ($titles as $i => $title) {
            $title = substr(trim(strip_tags($title)), 0, 120);
            if ($title === '') continue;
            $image = trim($images[$i] ?? '');
            if ($image !== '' && !preg_match('~^(?:/|https?://)~i', $image)) $image = '';
            $bullets = [];
            foreach (preg_split('/\r?\n/', $items[$i] ?? '') as $item) {
                $item = substr(trim(strip_tags($item)), 0, 180);
                if ($item !== '') $bullets[] = $item;
            }
            $services[] = [
                'title' => $title,
                'description' => substr(trim(strip_tags($descriptions[$i] ?? '')), 0, 600),
                'image' => substr($image, 0, 500),
                'items' => array_slice($bullets, 0, 10),
            ];
        }
        if ($reset) deleteContent('services_json');
        elseif ($services) setContent('services_json', json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $saved = true;
    } elseif (array_key_exists($section, $sections)) {
        if ($reset) {
            foreach ($sections[$section] as $key) deleteContent($key);
        } else {
            foreach ($sections[$section] as $key) {
                $val = trim($_POST[$key] ?? '');
                // OWASP A03: strip_tags para evitar injeção de HTML nos campos de texto simples
                $val = strip_tags($val);
                if ($val !== '') setContent($key, $val);
                else deleteContent($key);
            }
        }
        $saved = true;
    }
}

$c = getContent();
$v = fn(string $k, string $default = '') => $c[$k] ?? $default;

layout_start('Editar Textos', 'content');
?>

<?php if ($saved): ?>
  <div class="alert alert-success">✔ Conteúdo salvo. As alterações aparecem no site ao recarregar.</div>
<?php endif ?>

<div class="alert alert-info">
  💡 Os textos editados aqui substituem os padrões do site automaticamente.
  Use <strong>Restaurar Padrão</strong> para reverter uma seção ao texto original.
</div>

<!-- Abas -->
<div style="display:flex;gap:4px;background:rgba(255,255,255,.03);border-radius:10px;padding:4px;margin-bottom:22px" id="tabs">
  <button type="button" class="btn btn-ghost btn-sm tab-btn" data-tab="hero">Hero</button>
  <button type="button" class="btn btn-ghost btn-sm tab-btn" data-tab="services">Serviços</button>
  <button type="button" class="btn btn-ghost btn-sm tab-btn" data-tab="about">Sobre</button>
  <button type="button" class="btn btn-ghost btn-sm tab-btn" data-tab="diff">Diferenciais</button>
  <button type="button" class="btn btn-ghost btn-sm tab-btn" data-tab="contact">Contato</button>
</div>

<!-- SERVICOS -->
<?php
$serviceDefaults = [
  ['title'=>'Fabricação e Montagem de Estruturas','description'=>'Projetamos e fabricamos estruturas metálicas sob medida — de galpões industriais a suportes especializados — com rigoroso controle de qualidade.','image'=>'/img/WhatsApp%20Image%202026-05-19%20at%2021.31.34%20%281%29.jpeg','items'=>['Estruturas metálicas industriais','Galpões e coberturas metálicas','Mezaninos e plataformas','Escadas, corrimãos e passarelas']],
  ['title'=>'Pintura a Jato','description'=>'Jateamento abrasivo e pintura industrial com produtos de alta performance, garantindo proteção anticorrosiva e durabilidade máxima para suas estruturas.','image'=>'/img/WhatsApp%20Image%202026-05-19%20at%2021.31.32%20%281%29.jpeg','items'=>['Jateamento abrasivo','Pintura anticorrosiva','Tratamento e preparo de superfícies','Pintura epóxi e poliuretano']],
  ['title'=>'Calderaria em Geral','description'=>'Executamos serviços completos de calderaria em geral, desenvolvendo soluções sob medida para atender às necessidades específicas de cada cliente e projeto.','image'=>'/img/WhatsApp%20Image%202026-05-19%20at%2021.31.29.jpeg','items'=>['Silos, tanques e reservatórios','Dutos e tubulações industriais','Vasos de pressão','Peças e componentes sob medida']],
];
$services = json_decode($c['services_json'] ?? '', true);
if (!is_array($services) || !$services) $services = $serviceDefaults;
?>
<div class="card tab-panel" id="tab-services" style="display:none">
  <div class="card-title">Serviços do site</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="section" value="services">
    <div id="service-admin-rows">
      <?php foreach ($services as $service): ?>
      <div class="service-admin-row" style="border:1px solid rgba(255,255,255,.1);padding:16px;border-radius:9px;margin-bottom:14px">
        <div class="fg"><label>Nome do serviço</label><input name="service_title[]" value="<?= e($service['title'] ?? '') ?>" required maxlength="120"></div>
        <div class="fg"><label>Descrição</label><textarea name="service_description[]" maxlength="600"><?= e($service['description'] ?? '') ?></textarea></div>
        <div class="fg"><label>Imagem (endereço da imagem)</label><input name="service_image[]" value="<?= e($service['image'] ?? '') ?>" maxlength="500" placeholder="/img/foto.jpg ou https://..."></div>
        <div class="fg"><label>Tópicos (um por linha)</label><textarea name="service_items[]"><?= e(implode("\n", $service['items'] ?? [])) ?></textarea></div>
        <button type="button" class="btn btn-danger btn-sm remove-service">Remover serviço</button>
      </div>
      <?php endforeach ?>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="button" id="add-service" class="btn btn-secondary">Adicionar novo serviço</button>
      <button type="submit" class="btn btn-primary">Salvar serviços</button>
      <button type="submit" name="reset" value="1" class="btn btn-ghost" onclick="return confirm('Restaurar os três serviços originais?')">Restaurar padrão</button>
    </div>
  </form>
</div>

<template id="service-admin-template">
  <div class="service-admin-row" style="border:1px solid rgba(255,255,255,.1);padding:16px;border-radius:9px;margin-bottom:14px">
    <div class="fg"><label>Nome do serviço</label><input name="service_title[]" required maxlength="120"></div>
    <div class="fg"><label>Descrição</label><textarea name="service_description[]" maxlength="600"></textarea></div>
    <div class="fg"><label>Imagem (endereço da imagem)</label><input name="service_image[]" maxlength="500" placeholder="/img/foto.jpg ou https://..."></div>
    <div class="fg"><label>Tópicos (um por linha)</label><textarea name="service_items[]"></textarea></div>
    <button type="button" class="btn btn-danger btn-sm remove-service">Remover serviço</button>
  </div>
</template>

<!-- HERO -->
<div class="card tab-panel" id="tab-hero">
  <div class="card-title">🦸 Seção Hero</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="section" value="hero">
    <div class="fg"><label>Badge ("Excelência em...")</label>
      <input type="text" name="hero_badge" maxlength="120" value="<?= e($v('hero_badge','Excelência em Metalurgia Industrial')) ?>">
    </div>
    <div class="frow">
      <div class="fg"><label>Linha 1 do Título</label>
        <input type="text" name="hero_l1" maxlength="60" value="<?= e($v('hero_l1','CONSTRUINDO')) ?>">
      </div>
      <div class="fg"><label>Linha 2 (laranja)</label>
        <input type="text" name="hero_l2" maxlength="60" value="<?= e($v('hero_l2','O FUTURO')) ?>">
      </div>
    </div>
    <div class="fg"><label>Linha 3 (azul)</label>
      <input type="text" name="hero_l3" maxlength="60" value="<?= e($v('hero_l3','COM PRECISÃO')) ?>">
    </div>
    <div class="fg"><label>Subtítulo</label>
      <textarea name="hero_sub" maxlength="300"><?= e($v('hero_sub','Soluções completas em fabricação e montagem de estruturas metálicas, pintura a jato industrial e calderaria — qualidade e segurança em cada projeto.')) ?></textarea>
    </div>
    <div class="frow">
      <div class="fg"><label>KPI 1 — Número</label><input type="text" name="kpi1_num" maxlength="20" value="<?= e($v('kpi1_num','10+')) ?>"></div>
      <div class="fg"><label>KPI 1 — Descrição</label><input type="text" name="kpi1_label" maxlength="60" value="<?= e($v('kpi1_label','Anos de Mercado')) ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>KPI 2 — Número</label><input type="text" name="kpi2_num" maxlength="20" value="<?= e($v('kpi2_num','500+')) ?>"></div>
      <div class="fg"><label>KPI 2 — Descrição</label><input type="text" name="kpi2_label" maxlength="60" value="<?= e($v('kpi2_label','Projetos Concluídos')) ?>"></div>
    </div>
    <div class="frow">
      <div class="fg"><label>KPI 3 — Número</label><input type="text" name="kpi3_num" maxlength="20" value="<?= e($v('kpi3_num','100%')) ?>"></div>
      <div class="fg"><label>KPI 3 — Descrição</label><input type="text" name="kpi3_label" maxlength="60" value="<?= e($v('kpi3_label','Comprometimento')) ?>"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="btn btn-primary">💾 Salvar Hero</button>
      <button type="submit" name="reset" value="1" class="btn btn-ghost"
        onclick="return confirm('Restaurar textos do Hero ao padrão original?')">↩ Restaurar Padrão</button>
    </div>
  </form>
</div>

<!-- SOBRE -->
<div class="card tab-panel" id="tab-about" style="display:none">
  <div class="card-title">👥 Seção Sobre Nós</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="section" value="about">
    <div class="fg"><label>Parágrafo 1</label>
      <textarea name="about_p1" maxlength="600"><?= e($v('about_p1','A Axion é uma empresa especializada em soluções metalúrgicas e industriais, comprometida com a excelência em cada projeto. Nossa equipe de profissionais altamente qualificados utiliza equipamentos de última geração para entregar resultados superiores.')) ?></textarea>
    </div>
    <div class="fg"><label>Parágrafo 2</label>
      <textarea name="about_p2" maxlength="600"><?= e($v('about_p2','Atuamos em diversos segmentos industriais, oferecendo serviços integrados que vão desde a concepção do projeto até a entrega final — sempre com foco em segurança, qualidade e cumprimento de prazos.')) ?></textarea>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 Salvar Sobre</button>
      <button type="submit" name="reset" value="1" class="btn btn-ghost"
        onclick="return confirm('Restaurar textos?')">↩ Restaurar Padrão</button>
    </div>
  </form>
</div>

<!-- DIFERENCIAIS -->
<div class="card tab-panel" id="tab-diff" style="display:none">
  <div class="card-title">⭐ Seção Diferenciais</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="section" value="diff">
    <?php
    $diffDefaults = [
      1 => ['Equipe Especializada',  'Profissionais com vasta experiência no setor metalúrgico, com treinamentos contínuos e certificações técnicas atualizadas.'],
      2 => ['Projetos Sob Medida',   'Desenvolvemos soluções personalizadas para cada cliente, com análise técnica detalhada e adequação às necessidades específicas.'],
      3 => ['Controle de Qualidade', 'Rigorosos processos de inspeção em todas as etapas, garantindo conformidade com as normas ABNT e ASME vigentes.'],
      4 => ['Prazo e Confiabilidade','Comprometimento absoluto com os prazos acordados, com planejamento detalhado e comunicação transparente durante todo o processo.'],
      5 => ['Atendimento Completo',  'Da concepção à entrega final, acompanhamos cada fase com total suporte técnico e atendimento personalizado e dedicado.'],
      6 => ['Segurança em Primeiro', "Todos os serviços seguem rigorosas normas de segurança do trabalho, com EPI's adequados e procedimentos certificados pelas NR's."],
    ];
    for ($i = 1; $i <= 6; $i++): ?>
    <div class="frow" style="margin-bottom:8px">
      <div class="fg" style="margin-bottom:0">
        <label>Diferencial <?= $i ?> — Título</label>
        <input type="text" name="diff<?= $i ?>_title" maxlength="80"
               value="<?= e($v("diff{$i}_title", $diffDefaults[$i][0])) ?>">
      </div>
      <div class="fg" style="margin-bottom:0">
        <label>Diferencial <?= $i ?> — Texto</label>
        <input type="text" name="diff<?= $i ?>_text" maxlength="300"
               value="<?= e($v("diff{$i}_text", $diffDefaults[$i][1])) ?>">
      </div>
    </div>
    <?php endfor ?>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button type="submit" class="btn btn-primary">💾 Salvar Diferenciais</button>
      <button type="submit" name="reset" value="1" class="btn btn-ghost"
        onclick="return confirm('Restaurar diferenciais?')">↩ Restaurar Padrão</button>
    </div>
  </form>
</div>

<!-- CONTATO -->
<div class="card tab-panel" id="tab-contact" style="display:none">
  <div class="card-title">📞 Informações de Contato</div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="section" value="contact">
    <div class="frow">
      <div class="fg"><label>Telefone / WhatsApp</label>
        <input type="text" name="contact_phone" maxlength="60" value="<?= e($v('contact_phone','(12) 99651-3704')) ?>">
      </div>
      <div class="fg"><label>E-mail</label>
        <input type="text" name="contact_email" maxlength="120" value="<?= e($v('contact_email','adm@axionindustrial.com.br')) ?>">
      </div>
    </div>
    <div class="fg"><label>Endereço</label>
      <input type="text" name="contact_address" maxlength="200"
             value="<?= e($v('contact_address','Rua Itamar Raul Mendes, 30 - São Paulo, Matozinhos - MG, 35720-000')) ?>">
    </div>
    <div class="fg"><label>Horário de Atendimento</label>
      <input type="text" name="contact_hours" maxlength="100"
             value="<?= e($v('contact_hours','Seg–Sex: 8h às 18h | Sáb: 8h às 12h')) ?>">
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 Salvar Contato</button>
      <button type="submit" name="reset" value="1" class="btn btn-ghost"
        onclick="return confirm('Restaurar contato?')">↩ Restaurar Padrão</button>
    </div>
  </form>
</div>

<script>
(function () {
  function openTab(name) {
    var buttons = document.querySelectorAll('.tab-btn');
    var panels = document.querySelectorAll('.tab-panel');
    for (var i = 0; i < buttons.length; i++) {
      var active = buttons[i].getAttribute('data-tab') === name;
      buttons[i].className = active ? 'btn btn-primary btn-sm tab-btn' : 'btn btn-ghost btn-sm tab-btn';
    }
    for (var j = 0; j < panels.length; j++) {
      panels[j].style.display = panels[j].id === 'tab-' + name ? '' : 'none';
    }
  }
  var buttons = document.querySelectorAll('.tab-btn');
  for (var i = 0; i < buttons.length; i++) {
    buttons[i].onclick = function () { openTab(this.getAttribute('data-tab')); };
  }
  openTab(<?= json_encode($activeSection) ?>);

  var addService = document.getElementById('add-service');
  if (addService) addService.onclick = function () {
    var template = document.getElementById('service-admin-template');
    document.getElementById('service-admin-rows').appendChild(template.content.cloneNode(true));
  };
  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('.remove-service') : null;
    if (!button) return;
    var row = button.closest('.service-admin-row');
    if (row && row.parentNode) row.parentNode.removeChild(row);
  });
}());
</script>

<?php layout_end(); ?>
