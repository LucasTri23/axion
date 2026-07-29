/* ── AXION — JavaScript principal ──────────────────────────────── */

// ── DATA: galeria de imagens ──────────────────────────────────────
const BASE = 'img/WhatsApp%20Image%202026-05-19%20at%20';
const IMGS = [
  [BASE+'21.31.28.jpeg',           'pintura',    'Mesa Elevatória — Pintura Industrial'],
  [BASE+'21.31.28%20%281%29.jpeg', 'estruturas', 'Treliça Metálica em Fabricação'],
  [BASE+'21.31.29.jpeg',           'calderaria', 'Içamento de Silo Industrial'],
  [BASE+'21.31.29%20%281%29.jpeg', 'calderaria', 'Silo Cônico — Montagem'],
  [BASE+'21.31.29%20%282%29.jpeg', 'pintura',    'Plataforma Elevatória Pintada'],
  [BASE+'21.31.29%20%283%29.jpeg', 'pintura',    'Mesa Elevatória Industrial'],
  [BASE+'21.31.29%20%284%29.jpeg', 'calderaria', 'Componentes em Chapa Metálica'],
  [BASE+'21.31.30.jpeg',           'calderaria', 'Duto Industrial em Chapa'],
  [BASE+'21.31.31.jpeg',           'estruturas', 'Estrutura Metálica Industrial'],
  [BASE+'21.31.31%20%281%29.jpeg', 'pintura',    'Suportes — Pintura Anticorrosiva'],
  [BASE+'21.31.32.jpeg',           'pintura',    'Berços de Correia Transportadora'],
  [BASE+'21.31.32%20%281%29.jpeg', 'pintura',    'Estrutura de Correia Pintada'],
  [BASE+'21.31.34.jpeg',           'calderaria', 'Caixa de Transferência'],
  [BASE+'21.31.34%20%281%29.jpeg', 'estruturas', 'Estruturas Pintadas — Laranja'],
  [BASE+'21.31.34%20%282%29.jpeg', 'pintura',    'Tubulações — Pintura Epóxi'],
  [BASE+'21.31.34%20%283%29.jpeg', 'estruturas', 'Suportes Estruturais Pintados'],
  [BASE+'21.31.35.jpeg',           'calderaria', 'Elevador de Canecas'],
  [BASE+'21.31.35%20%281%29.jpeg', 'calderaria', 'Grande Estrutura em Fabricação'],
  [BASE+'21.31.35%20%282%29.jpeg', 'pintura',    'Estrutura Industrial Pintada'],
  [BASE+'21.31.35%20%283%29.jpeg', 'pintura',    'Tubulações Industriais'],
  [BASE+'21.31.35%20%284%29.jpeg', 'calderaria', 'Calha Industrial'],
  [BASE+'21.31.36.jpeg',           'calderaria', 'Anel de Silo — Calderaria'],
  [BASE+'21.31.36%20%281%29.jpeg', 'calderaria', 'Flange Industrial'],
  [BASE+'21.31.36%20%282%29.jpeg', 'calderaria', 'Tambor Industrial'],
  [BASE+'21.31.36%20%283%29.jpeg', 'calderaria', 'Calha de Transferência'],
  [BASE+'21.31.37.jpeg',           'pintura',    'Tubulações Azuis Entregues'],
  [BASE+'21.31.37%20%281%29.jpeg', 'pintura',    'Plataformas e Passarelas'],
  [BASE+'21.31.37%20%282%29.jpeg', 'pintura',    'Escadas e Passarelas Industriais'],
  [BASE+'21.31.37%20%283%29.jpeg', 'estruturas', 'Suporte Estrutural Industrial'],
];
const CAT_LABEL = { estruturas: 'Estruturas', pintura: 'Pintura', calderaria: 'Calderaria' };

// ── GALERIA ───────────────────────────────────────────────────────
let currentFilter = 'all';
let filteredImgs  = [];

function renderGallery(filter) {
  currentFilter = filter;
  filteredImgs  = filter === 'all' ? IMGS : IMGS.filter(i => i[1] === filter);

  const grid = document.getElementById('gal-grid');
  grid.innerHTML = filteredImgs.map((img, idx) => `
    <div class="gal-item" data-idx="${idx}">
      <img src="${img[0]}" alt="${img[2]}" loading="lazy">
      <div class="gal-ov">
        <span class="gal-cat">${CAT_LABEL[img[1]]}</span>
        <span class="gal-title">${img[2]}</span>
      </div>
    </div>
  `).join('');

  grid.querySelectorAll('.gal-item').forEach(el =>
    el.addEventListener('click', () => openLB(+el.dataset.idx))
  );
}

document.querySelectorAll('.fbtn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.fbtn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderGallery(btn.dataset.filter);
  });
});

renderGallery('all');

// ── LIGHTBOX ──────────────────────────────────────────────────────
const lb       = document.getElementById('lb');
const lbImg    = document.getElementById('lb-img');
const lbTitle  = document.getElementById('lb-title');
const lbCounter= document.getElementById('lb-counter');
let lbIdx = 0;
let lbSet = IMGS;

function openLB(idx, set) {
  lbSet = set || filteredImgs;
  lbIdx = idx;
  updateLB();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLB() {
  lb.classList.remove('open');
  document.body.style.overflow = '';
}

function updateLB() {
  lbImg.classList.add('fading');
  setTimeout(() => {
    lbImg.src          = lbSet[lbIdx][0];
    lbTitle.textContent  = lbSet[lbIdx][2];
    lbCounter.textContent = `${lbIdx + 1} / ${lbSet.length}`;
    lbImg.classList.remove('fading');
  }, 200);
}

function nextLB() { lbIdx = (lbIdx + 1) % lbSet.length; updateLB(); }
function prevLB() { lbIdx = (lbIdx - 1 + lbSet.length) % lbSet.length; updateLB(); }

document.getElementById('lb-close').onclick = closeLB;
document.getElementById('lb-next').onclick  = nextLB;
document.getElementById('lb-prev').onclick  = prevLB;
lb.addEventListener('click', e => { if (e.target === lb) closeLB(); });

document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape')      closeLB();
  if (e.key === 'ArrowRight')  nextLB();
  if (e.key === 'ArrowLeft')   prevLB();
});

document.querySelectorAll('.sc-thumb').forEach(t =>
  t.addEventListener('click', () => openLB(+t.dataset.lb, IMGS))
);

// ── PARTÍCULAS (canvas hero) ──────────────────────────────────────
const cvs = document.getElementById('cvs');
const ctx  = cvs.getContext('2d');
let W, H, pts;

function initCvs() {
  W = cvs.width  = innerWidth;
  H = cvs.height = innerHeight;
  pts = Array.from({ length: 80 }, () => ({
    x:  Math.random() * W,
    y:  Math.random() * H,
    vx: (Math.random() - .5) * .5,
    vy: (Math.random() - .5) * .5,
    r:  Math.random() * 1.8 + .5,
    c:  Math.random() > .5 ? '#3AAFE4' : '#E8751A',
    a:  Math.random() * .4 + .15,
  }));
}

initCvs();
addEventListener('resize', initCvs);

(function tick() {
  ctx.clearRect(0, 0, W, H);
  pts.forEach((p, i) => {
    p.x = (p.x + p.vx + W) % W;
    p.y = (p.y + p.vy + H) % H;

    ctx.beginPath();
    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
    ctx.fillStyle   = p.c;
    ctx.globalAlpha = p.a;
    ctx.fill();

    for (let j = i + 1; j < pts.length; j++) {
      const q = pts[j];
      const d = Math.hypot(p.x - q.x, p.y - q.y);
      if (d < 120) {
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(q.x, q.y);
        ctx.strokeStyle  = p.c;
        ctx.globalAlpha  = (1 - d / 120) * .13;
        ctx.lineWidth    = .5;
        ctx.stroke();
      }
    }
  });
  ctx.globalAlpha = 1;
  requestAnimationFrame(tick);
})();

// ── NAV SCROLL ────────────────────────────────────────────────────
const nav = document.getElementById('nav');
addEventListener('scroll', () => nav.classList.toggle('sc', scrollY > 48));

// ── FADE-UP (Intersection Observer) ──────────────────────────────
const io = new IntersectionObserver(
  entries => entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); }),
  { threshold: .1 }
);
document.querySelectorAll('.fu').forEach(el => io.observe(el));

// ── CONTADORES DE ESTATÍSTICAS ────────────────────────────────────
function counter(el, target) {
  let n = 0;
  const step = target / 120;
  const t = setInterval(() => {
    n = Math.min(n + step, target);
    el.textContent = Math.floor(n);
    if (n >= target) clearInterval(t);
  }, 16);
}

const sio = new IntersectionObserver(e => {
  if (e[0].isIntersecting) {
    document.querySelectorAll('[data-target]').forEach(el => counter(el, +el.dataset.target));
    sio.disconnect();
  }
}, { threshold: .5 });
sio.observe(document.getElementById('stats'));

// ── CONTENT CUSTOMIZER — busca textos/imagens do servidor (MySQL) ─
(function loadCustomContent() {
  const textMap = {
    hero_badge:      'c-hero-badge',
    hero_l1:         'c-hero-l1',
    hero_l2:         'c-hero-l2',
    hero_l3:         'c-hero-l3',
    hero_sub:        'c-hero-sub',
    kpi1_num:        'c-kpi1n',  kpi1_label:    'c-kpi1l',
    kpi2_num:        'c-kpi2n',  kpi2_label:    'c-kpi2l',
    kpi3_num:        'c-kpi3n',  kpi3_label:    'c-kpi3l',
    about_p1:        'c-about-p1',
    about_p2:        'c-about-p2',
    diff1_title:     'c-diff1t', diff1_text:    'c-diff1p',
    diff2_title:     'c-diff2t', diff2_text:    'c-diff2p',
    diff3_title:     'c-diff3t', diff3_text:    'c-diff3p',
    diff4_title:     'c-diff4t', diff4_text:    'c-diff4p',
    diff5_title:     'c-diff5t', diff5_text:    'c-diff5p',
    diff6_title:     'c-diff6t', diff6_text:    'c-diff6p',
    contact_phone:   'c-contact-phone',
    contact_email:   'c-contact-email',
    contact_address: 'c-contact-address',
    contact_hours:   'c-contact-hours',
  };
  const imgMap = {
    img_hero_bg:  'c-hero-bg',
    img_service1: 'c-img-service1',
    img_service2: 'c-img-service2',
    img_service3: 'c-img-service3',
    img_about:    'c-img-about',
  };

  function applyContent(texts, images) {
    for (const [key, id] of Object.entries(textMap)) {
      if (texts[key]) {
        const el = document.getElementById(id);
        if (el) el.textContent = texts[key];
      }
    }
    if (images.img_hero_bg) {
      const bg = document.getElementById('c-hero-bg');
      if (bg) bg.style.backgroundImage = `url(${images.img_hero_bg})`;
    }
    for (const [key, id] of Object.entries(imgMap)) {
      if (key === 'img_hero_bg') continue;
      if (images[key]) {
        const el = document.getElementById(id);
        if (el) el.src = images[key];
      }
    }
  }

  fetch('/api/get-content.php', { cache: 'force-cache' })
    .then(r => r.ok ? r.json() : null)
    .then(d => { if (d && d.ok) applyContent(d.texts || {}, d.images || {}); })
    .catch(() => {});
})();

// ── FORMULÁRIO DE ORÇAMENTO — envia para API PHP ──────────────────
document.getElementById('cform').addEventListener('submit', async e => {
  e.preventDefault();

  const btn   = document.getElementById('sendBtn');
  const name  = document.getElementById('f-name').value.trim();
  const email = document.getElementById('f-email').value.trim();

  if (!name || !email) {
    document.getElementById('f-name').focus();
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Enviando...';

  const body = new FormData();
  body.append('name',        name);
  body.append('company',     document.getElementById('f-company').value.trim());
  body.append('email',       email);
  body.append('phone',       document.getElementById('f-phone').value.trim());
  body.append('service',     document.getElementById('f-service').value);
  body.append('description', document.getElementById('f-desc').value.trim());

  const sendIcon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2">
    <line x1="22" y1="2" x2="11" y2="13"/>
    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
  </svg>`;

  try {
    const res  = await fetch('/api/submit-quote.php', { method: 'POST', body });
    const data = await res.json();

    if (data.ok) {
      btn.innerHTML       = '✓ Mensagem Enviada com Sucesso!';
      btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
      document.getElementById('cform').reset();
      setTimeout(() => {
        btn.innerHTML        = 'Enviar Mensagem ' + sendIcon;
        btn.style.background = '';
        btn.disabled         = false;
      }, 3500);
    } else {
      btn.textContent      = data.msg || 'Erro ao enviar. Tente novamente.';
      btn.style.background = 'linear-gradient(135deg,#ef4444,#dc2626)';
      setTimeout(() => {
        btn.innerHTML        = 'Enviar Mensagem ' + sendIcon;
        btn.style.background = '';
        btn.disabled         = false;
      }, 3000);
    }
  } catch {
    btn.textContent      = 'Erro de conexão. Tente novamente.';
    btn.style.background = 'linear-gradient(135deg,#ef4444,#dc2626)';
    setTimeout(() => {
      btn.innerHTML        = 'Enviar Mensagem ' + sendIcon;
      btn.style.background = '';
      btn.disabled         = false;
    }, 3000);
  }
});
