<?php
/**
 * Layout compartilhado do painel admin
 * Uso: layout_start('Título', 'page-key');  ...HTML...  layout_end();
 */

$NAV = [
    'index'    => ['📊', 'Dashboard',       'index.php'],
    'quotes'   => ['📋', 'Pedidos',         'quotes.php'],
    'propostas'=> ['📄', 'Propostas',       'propostas.php'],
    'content'  => ['✏️', 'Editar Textos',   'content.php'],
    'images'   => ['🖼️', 'Editar Imagens',  'images.php'],
    'settings' => ['⚙️', 'Configurações',   'settings.php'],
];

function navBadge(): int {
    static $n = -1;
    if ($n < 0) {
        $r = db()->query("SELECT COUNT(*) FROM quotes WHERE status = 'novo'");
        $n = (int)($r ? $r->fetchColumn() : 0);
    }
    return $n;
}

function layout_start(string $title, string $activePage): void {
    global $NAV;
    $badge = navBadge();
    $user  = e($_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Axion Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Bebas+Neue&display=swap" rel="stylesheet">
<style>
:root{--navy:#080F1E;--dark:#0D1B3E;--mid:#132E6A;--orange:#E8751A;--orange2:#FF8F2C;--sky:#3AAFE4;--white:#fff;--gray:#7B8899;--gray2:#B8C4D0;--green:#22c55e;--red:#ef4444;--yellow:#f59e0b;--sw:220px}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;font-family:'Inter',sans-serif;background:var(--navy);color:var(--white);overflow-x:hidden}
a{color:inherit;text-decoration:none}
/* Layout */
.layout{display:flex;height:100vh;overflow:hidden}
/* Sidebar */
.sidebar{width:var(--sw);background:#050B16;border-right:1px solid rgba(58,175,228,.1);display:flex;flex-direction:column;flex-shrink:0}
.sb-head{padding:22px 18px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:10px}
.sb-head img{height:32px}
.sb-badge{background:linear-gradient(135deg,var(--orange),var(--orange2));border-radius:5px;padding:2px 8px;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase}
.sb-nav{flex:1;padding:10px 0;overflow-y:auto}
.nav-item{display:flex;align-items:center;gap:11px;padding:11px 18px;color:var(--gray);font-size:13.5px;font-weight:500;cursor:pointer;transition:.2s;position:relative;border:none;background:none;width:100%;text-align:left}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.04)}
.nav-item.active{color:#fff;background:rgba(58,175,228,.1)}
.nav-item.active::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:3px;background:var(--orange);border-radius:0 3px 3px 0}
.nav-icon{font-size:15px;width:20px;text-align:center}
.n-badge{margin-left:auto;background:var(--orange);color:#fff;border-radius:100px;padding:2px 8px;font-size:11px;font-weight:700}
.sb-foot{padding:14px 18px;border-top:1px solid rgba(255,255,255,.06)}
.btn-logout{display:flex;align-items:center;gap:9px;width:100%;padding:10px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:9px;color:#fca5a5;font-size:13px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:.25s}
.btn-logout:hover{background:rgba(239,68,68,.2)}
/* Main */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 28px;height:60px;background:#060D1A;border-bottom:1px solid rgba(58,175,228,.1);flex-shrink:0}
.topbar h1{font-family:'Bebas Neue',sans-serif;font-size:21px;letter-spacing:1px}
.tb-right{display:flex;align-items:center;gap:16px}
.tb-time{font-size:12px;color:var(--gray)}
.user-chip{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:100px;padding:5px 14px}
.user-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--orange2));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.user-chip span{font-size:12.5px;color:var(--gray2)}
.page-area{flex:1;overflow-y:auto;padding:26px 28px}
/* Cards */
.card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:22px 24px;margin-bottom:22px}
.card-title{font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.kpi{background:linear-gradient(145deg,rgba(19,46,106,.3),rgba(13,27,62,.5));border:1px solid rgba(58,175,228,.1);border-radius:13px;padding:20px}
.kpi-label{font-size:11.5px;color:var(--gray);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px}
.kpi-val{font-family:'Bebas Neue',sans-serif;font-size:42px;line-height:1}
.kpi-val.o{color:var(--orange)}.kpi-val.s{color:var(--sky)}.kpi-val.g{color:var(--green)}.kpi-val.y{color:var(--yellow)}
/* Table */
.tbl-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px}
.filter-pills{display:flex;gap:8px;flex-wrap:wrap}
.fpill{padding:6px 16px;border-radius:100px;font-size:12.5px;font-weight:600;border:1px solid rgba(255,255,255,.12);background:transparent;color:var(--gray2);cursor:pointer;text-decoration:none;transition:.2s;font-family:'Inter',sans-serif}
.fpill:hover{border-color:var(--sky);color:var(--sky)}
.fpill.active{background:linear-gradient(135deg,var(--orange),var(--orange2));border-color:transparent;color:#fff}
table{width:100%;border-collapse:collapse;font-size:13.5px}
thead th{padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid rgba(255,255,255,.07)}
tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:.2s}
tbody tr:hover{background:rgba(255,255,255,.025)}
tbody td{padding:13px 14px;vertical-align:middle}
.td-name{font-weight:600}.td-sub{font-size:12px;color:var(--gray);margin-top:2px}
/* Badges */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor}
.b-novo{background:rgba(58,175,228,.15);color:var(--sky);border:1px solid rgba(58,175,228,.3)}
.b-andamento{background:rgba(245,158,11,.12);color:var(--yellow);border:1px solid rgba(245,158,11,.25)}
.b-concluido{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.25)}
.b-cancelado{background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.25)}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:8px;font-size:13.5px;font-weight:600;border:none;cursor:pointer;font-family:'Inter',sans-serif;transition:.25s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,var(--orange),var(--orange2));color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,117,26,.4)}
.btn-secondary{background:rgba(58,175,228,.1);border:1px solid rgba(58,175,228,.2);color:var(--sky)}
.btn-secondary:hover{background:rgba(58,175,228,.2)}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.btn-danger:hover{background:rgba(239,68,68,.2)}
.btn-ghost{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--gray2)}
.btn-ghost:hover{border-color:var(--gray2);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
/* Forms */
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fg label{font-size:11.5px;font-weight:600;color:var(--gray2);letter-spacing:.4px;text-transform:uppercase}
.fg input,.fg select,.fg textarea{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:11px 14px;color:#fff;font-size:14px;font-family:'Inter',sans-serif;outline:none;transition:.25s}
.fg textarea{min-height:80px;resize:vertical}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--sky);background:rgba(58,175,228,.05)}
.fg select option{background:#0D1B3E}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:14px}
/* Alerts */
.alert{border-radius:9px;padding:11px 16px;font-size:13.5px;margin-bottom:16px}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#86efac}
.alert-info{background:rgba(58,175,228,.08);border:1px solid rgba(58,175,228,.2);color:var(--sky)}
/* Empty */
.empty{text-align:center;padding:52px 20px;color:var(--gray)}
.empty-icon{font-size:44px;margin-bottom:10px}
/* Image cards */
.img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.img-card{border:1px solid rgba(255,255,255,.08);border-radius:13px;overflow:hidden;background:rgba(255,255,255,.02)}
.img-preview{height:130px;overflow:hidden;position:relative;background:#0D1B3E}
.img-preview img{width:100%;height:100%;object-fit:cover}
.img-preview .no-img{display:flex;align-items:center;justify-content:center;height:100%;font-size:30px;color:var(--gray)}
.img-body{padding:12px 14px}
.img-body h4{font-size:13px;font-weight:600;margin-bottom:3px}
.img-body p{font-size:11.5px;color:var(--gray);margin-bottom:10px}
.img-actions{display:flex;gap:8px}
/* Detail view */
.detail-grid{display:grid;grid-template-columns:140px 1fr;gap:8px 12px;font-size:13.5px}
.detail-grid dt{color:var(--gray);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;padding-top:2px}
.detail-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:12px 14px;color:var(--gray2);line-height:1.7;font-size:13.5px;margin-top:6px}
/* Responsive */
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,1fr)}.frow{grid-template-columns:1fr}}
@media(max-width:680px){.sidebar{width:58px}.nav-item span:not(.nav-icon){display:none}.sb-badge{display:none}.sb-head img{height:26px}}
</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sb-head">
      <img src="/img/logo.png" alt="Axion" onerror="this.style.display='none'">
      <span class="sb-badge">ADM</span>
    </div>
    <nav class="sb-nav">
      <?php foreach ($NAV as $key => [$icon, $label, $href]): ?>
        <a href="/admin/<?= $href ?>" class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
          <span class="nav-icon"><?= $icon ?></span>
          <span><?= e($label) ?></span>
          <?php if ($key === 'quotes' && $badge > 0): ?>
            <span class="n-badge"><?= $badge ?></span>
          <?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
    <div class="sb-foot">
      <form method="POST" action="/admin/logout.php">
        <?= csrfField() ?>
        <button type="submit" class="btn-logout">🚪 <span>Sair</span></button>
      </form>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <h1><?= e($title) ?></h1>
      <div class="tb-right">
        <span class="tb-time" id="tb-time"></span>
        <div class="user-chip">
          <div class="user-av"><?= strtoupper($user[0]) ?></div>
          <span><?= $user ?></span>
        </div>
      </div>
    </header>
    <div class="page-area">
<?php
} // fim layout_start

function layout_end(): void {
?>
    </div><!-- .page-area -->
  </div><!-- .main -->
</div><!-- .layout -->
<script>
(function clock(){
  const el = document.getElementById('tb-time');
  if (!el) return;
  el.textContent = new Date().toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
  setTimeout(clock, 10000);
})();
</script>
</body>
</html>
<?php
} // fim layout_end
