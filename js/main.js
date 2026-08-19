/* ── AXION — JavaScript principal ──────────────────────────────────
   Organizado em funções init*() independentes. GSAP/ScrollTrigger/Lenis
   são progressivos: se o CDN falhar, o site continua 100% funcional
   (conteúdo, galeria, filtros, lightbox e formulário não dependem deles).
────────────────────────────────────────────────────────────────── */

document.documentElement.classList.remove('no-js');

const HAS_GSAP   = typeof window.gsap !== 'undefined';
const HAS_LENIS  = typeof window.Lenis !== 'undefined';
const REDUCED    = matchMedia('(prefers-reduced-motion: reduce)').matches;
const IS_DESKTOP = matchMedia('(hover: hover) and (pointer: fine)').matches;

if (!HAS_GSAP) document.documentElement.classList.add('no-gsap');
if (HAS_GSAP && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

/* =========================================================
   DATA — galeria de imagens (estático; a galeria em si não é
   administrável pelo painel, apenas os textos/imagens fixas são)
========================================================= */
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

let currentFilter = 'all';
let filteredImgs  = [];

/* =========================================================
   NAVEGAÇÃO — scroll state, menu mobile, seção ativa
========================================================= */
function initNavigation() {
  const nav = document.getElementById('nav');
  const ham = document.getElementById('ham');
  const mnav = document.getElementById('mnav');

  const setScrolled = () => nav.classList.toggle('sc', scrollY > 48);
  setScrolled();
  addEventListener('scroll', setScrolled, { passive: true });

  function closeMenu() {
    ham.classList.remove('open');
    mnav.classList.remove('open');
    document.body.style.overflow = '';
    ham.setAttribute('aria-expanded', 'false');
    ham.setAttribute('aria-label', 'Abrir menu');
  }
  function openMenu() {
    ham.classList.add('open');
    mnav.classList.add('open');
    document.body.style.overflow = 'hidden';
    ham.setAttribute('aria-expanded', 'true');
    ham.setAttribute('aria-label', 'Fechar menu');
  }
  ham.addEventListener('click', () => {
    ham.classList.contains('open') ? closeMenu() : openMenu();
  });
  mnav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && mnav.classList.contains('open')) closeMenu();
  });

  // indicador de seção ativa
  const navLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];
  const sections = navLinks
    .map(a => document.querySelector(a.getAttribute('href')))
    .filter(Boolean);

  if (sections.length) {
    const activate = id => {
      navLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === `#${id}`));
    };
    const secIO = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) activate(e.target.id); });
    }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });
    sections.forEach(s => secIO.observe(s));
  }
}

/* =========================================================
   SMOOTH SCROLL — Lenis + âncoras + integração com ScrollTrigger
========================================================= */
let lenis = null;

function initSmoothScroll() {
  if (HAS_LENIS && !REDUCED) {
    lenis = new Lenis({
      duration: 1.05,
      smoothWheel: true,
      wheelMultiplier: 1,
      touchMultiplier: 1.1,
    });
    lenis.on('scroll', () => { if (HAS_GSAP && window.ScrollTrigger) ScrollTrigger.update(); });

    if (HAS_GSAP) {
      gsap.ticker.add(t => lenis.raf(t * 1000));
      gsap.ticker.lagSmoothing(0);
    } else {
      requestAnimationFrame(function raf(t) { lenis.raf(t); requestAnimationFrame(raf); });
    }
  }

  // âncoras — funciona com ou sem Lenis, não quebra navegação por teclado
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const href = a.getAttribute('href');
      if (!href || href === '#') return;
      const target = document.querySelector(href);
      if (!target) return;
      e.preventDefault();
      const navH = document.getElementById('nav').offsetHeight;
      if (lenis) {
        lenis.scrollTo(target, { offset: -navH + 8, duration: 1.15 });
      } else {
        const y = target.getBoundingClientRect().top + scrollY - navH + 8;
        scrollTo({ top: y, behavior: REDUCED ? 'auto' : 'smooth' });
      }
      history.pushState(null, '', href);
    });
  });
}

/* =========================================================
   HERO — reveal em sequência, parallax, glow no mouse
========================================================= */
function initHeroAnimations() {
  const hero = document.getElementById('hero');

  if (HAS_GSAP) {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    tl.set('.ht .mask > *', { yPercent: 112 })
      .set(['.badge', '.hs', '.hb', '.hkpis', '.eng-strip'], { opacity: 0, y: 18 })
      .to('.badge', { opacity: 1, y: 0, duration: .6 }, .1)
      .to('.ht .mask > *', { yPercent: 0, duration: .85, stagger: .12 }, .25)
      .to('.hs', { opacity: 1, y: 0, duration: .7 }, .75)
      .to('.hb', { opacity: 1, y: 0, duration: .6 }, .9)
      .to('.hkpis', { opacity: 1, y: 0, duration: .6 }, 1.02)
      .to('.eng-strip', { opacity: 1, y: 0, duration: .6 }, 1.12);
  }

  // parallax sutil do plano de fundo + zoom lento
  if (HAS_GSAP && window.ScrollTrigger && !REDUCED) {
    gsap.to('.hero-bg', {
      yPercent: 12,
      ease: 'none',
      scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true },
    });
    gsap.to('.hero-bg', { scale: 1.08, duration: 14, ease: 'none' });
  }

  // trilha PROJETO → FABRICAÇÃO → PINTURA → MONTAGEM → ENTREGA reage ao scroll
  const engSpans = document.querySelectorAll('.eng-strip span:not(.eng-arrow)');
  if (HAS_GSAP && window.ScrollTrigger && engSpans.length && !REDUCED) {
    gsap.set(engSpans, { color: 'rgba(184,196,208,.35)' });
    engSpans.forEach((span, i) => {
      gsap.to(span, {
        color: '#fff',
        ease: 'none',
        scrollTrigger: {
          trigger: hero,
          start: `top+=${i * 12}% top`,
          end: `top+=${i * 12 + 20}% top`,
          scrub: true,
        },
      });
    });
  }
}

/* =========================================================
   SCROLL ANIMATIONS — reveals genéricos (fade-up, clip, stagger)
========================================================= */
function initScrollAnimations() {
  // fallback universal por IntersectionObserver — sempre ativo
  const io = new IntersectionObserver(
    entries => entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); }),
    { threshold: .12 }
  );
  document.querySelectorAll('.fu').forEach(el => io.observe(el));

  if (!HAS_GSAP || !window.ScrollTrigger || REDUCED) return;

  // títulos de seção — mask reveal vertical
  gsap.utils.toArray('.stitle').forEach(title => {
    gsap.from(title, {
      yPercent: 100,
      opacity: 0,
      duration: .8,
      ease: 'power3.out',
      scrollTrigger: { trigger: title, start: 'top 88%' },
    });
  });

  // imagem "Sobre" — clip-path reveal com bloco de cor
  const aboutImg = document.querySelector('.about-img');
  const aboutReveal = document.querySelector('.about-reveal');
  if (aboutImg && aboutReveal) {
    gsap.timeline({ scrollTrigger: { trigger: aboutImg, start: 'top 78%' } })
      .fromTo(aboutReveal, { scaleX: 1 }, { scaleX: 0, duration: .9, ease: 'power3.inOut' })
      .fromTo(aboutImg.querySelector('img'), { scale: 1.25 }, { scale: 1, duration: 1.1, ease: 'power3.out' }, '-=.9');

    gsap.to(aboutImg.querySelector('img'), {
      yPercent: -8,
      ease: 'none',
      scrollTrigger: { trigger: aboutImg, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  }

  // fundo do CTA final — leve parallax
  const ctaBg = document.querySelector('.cta-bg');
  if (ctaBg) {
    gsap.to(ctaBg, {
      yPercent: 14,
      ease: 'none',
      scrollTrigger: { trigger: '#cta-final', start: 'top bottom', end: 'bottom top', scrub: true },
    });
  }

  // palavras grandes de fundo — leve deslocamento no scroll
  gsap.utils.toArray('.bg-word').forEach(word => {
    gsap.to(word, {
      xPercent: -6,
      ease: 'none',
      scrollTrigger: { trigger: word.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  });
}

/* =========================================================
   CONTADORES — dispara uma única vez ao entrar na viewport
========================================================= */
function counter(el, target) {
  let n = 0;
  const step = target / 90;
  const t = setInterval(() => {
    n = Math.min(n + step, target);
    el.textContent = Math.floor(n);
    if (n >= target) { el.textContent = target; clearInterval(t); }
  }, 16);
}

function initCounters() {
  const stats = document.getElementById('stats');
  if (!stats) return;
  const targets = stats.querySelectorAll('[data-target]');
  if (REDUCED) { targets.forEach(el => el.textContent = el.dataset.target); return; }
  const sio = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) {
      targets.forEach(el => counter(el, +el.dataset.target));
      sio.disconnect();
    }
  }, { threshold: .5 });
  sio.observe(stats);
}

/* =========================================================
   SERVIÇOS — efeito "scan industrial" nas imagens dos cards
========================================================= */
function initServices() {
  if (HAS_GSAP && window.ScrollTrigger && !REDUCED) {
    ScrollTrigger.batch('.sc-card', {
      start: 'top 85%',
      onEnter: batch => gsap.to(batch, { opacity: 1, y: 0, duration: .6, stagger: .12, ease: 'power2.out' }),
      once: true,
    });
    gsap.set('.sc-card', { opacity: 0, y: 30 });
  }
  // a classe "in" (que também anima a lista interna via CSS) já é
  // aplicada pelo IntersectionObserver genérico de .fu em initScrollAnimations()
}

/* =========================================================
   PROCESSO INDUSTRIAL — linha percorre as etapas conforme o scroll
========================================================= */
function initProcessTimeline() {
  const track = document.querySelector('.proc-track');
  const fill  = document.querySelector('.proc-line-fill');
  const steps = document.querySelectorAll('.proc-step');
  if (!track || !steps.length) return;

  if (!HAS_GSAP || !window.ScrollTrigger || REDUCED) {
    // fallback simples: ativa tudo quando a seção entra na tela
    const io = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) {
        steps.forEach(s => s.classList.add('active'));
        if (fill) fill.style.width = '100%';
        io.disconnect();
      }
    }, { threshold: .3 });
    io.observe(track);
    return;
  }

  ScrollTrigger.create({
    trigger: track,
    start: 'top 70%',
    end: 'bottom 60%',
    scrub: .6,
    onUpdate: self => {
      const p = self.progress;
      if (fill) fill.style.width = (p * 100) + '%';
      const activeCount = Math.round(p * steps.length);
      steps.forEach((s, i) => s.classList.toggle('active', i < activeCount));
    },
  });
}

/* =========================================================
   GALERIA
========================================================= */
function renderGallery(filter) {
  currentFilter = filter;
  filteredImgs  = filter === 'all' ? IMGS : IMGS.filter(i => i[1] === filter);

  const grid = document.getElementById('gal-grid');
  grid.innerHTML = filteredImgs.map((img, idx) => `
    <div class="gal-item" data-idx="${idx}" tabindex="0" role="button" aria-label="Ver imagem: ${img[2]}">
      <img src="${img[0]}" alt="${img[2]}" loading="lazy">
      <div class="gal-ov">
        <span class="gal-cat">${CAT_LABEL[img[1]]}</span>
        <span class="gal-title">${img[2]}</span>
      </div>
      <span class="gal-plus" aria-hidden="true">＋</span>
    </div>
  `).join('');

  grid.querySelectorAll('.gal-item').forEach(el => {
    el.addEventListener('click', () => openLB(+el.dataset.idx));
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLB(+el.dataset.idx); }
    });
  });

  // stagger de entrada — funciona com ou sem GSAP
  const items = [...grid.querySelectorAll('.gal-item')];
  if (REDUCED) {
    items.forEach(el => el.classList.add('show'));
  } else {
    items.forEach((el, i) => setTimeout(() => el.classList.add('show'), Math.min(i, 16) * 35));
  }
}

function initGalleryFilters() {
  document.querySelectorAll('.fbtn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('active')) return;
      document.querySelectorAll('.fbtn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const grid = document.getElementById('gal-grid');
      const items = [...grid.querySelectorAll('.gal-item')];

      if (REDUCED || !items.length) { renderGallery(btn.dataset.filter); return; }

      gsapOrCss(items, { opacity: 0, y: 10, duration: .25 }, () => renderGallery(btn.dataset.filter));
    });
  });
}

function gsapOrCss(items, props, done) {
  if (HAS_GSAP) {
    gsap.to(items, { ...props, stagger: .01, onComplete: done });
  } else {
    items.forEach(el => el.style.transition = `opacity ${props.duration}s`);
    items.forEach(el => el.style.opacity = 0);
    setTimeout(done, props.duration * 1000);
  }
}

function initGallery() {
  renderGallery('all');
}

/* =========================================================
   LIGHTBOX
========================================================= */
const lb        = document.getElementById('lb');
const lbImg     = document.getElementById('lb-img');
const lbTitle   = document.getElementById('lb-title');
const lbCounter = document.getElementById('lb-counter');
let lbIdx = 0;
let lbSet = IMGS;
let lastFocused = null;

function openLB(idx, set) {
  lbSet = set || filteredImgs;
  lbIdx = idx;
  updateLB();
  lastFocused = document.activeElement;
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
  document.getElementById('lb-close').focus();
}

function closeLB() {
  lb.classList.remove('open');
  document.body.style.overflow = '';
  if (lastFocused) lastFocused.focus();
}

function updateLB() {
  lbImg.classList.add('fading');
  setTimeout(() => {
    lbImg.src            = lbSet[lbIdx][0];
    lbImg.alt             = lbSet[lbIdx][2] || '';
    lbTitle.textContent   = lbSet[lbIdx][2] || '';
    lbCounter.textContent = `${lbIdx + 1} / ${lbSet.length}`;
    lbImg.classList.remove('fading');
  }, 200);
}

function nextLB() { lbIdx = (lbIdx + 1) % lbSet.length; updateLB(); }
function prevLB() { lbIdx = (lbIdx - 1 + lbSet.length) % lbSet.length; updateLB(); }

function initLightbox() {
  document.getElementById('lb-close').onclick = closeLB;
  document.getElementById('lb-next').onclick  = nextLB;
  document.getElementById('lb-prev').onclick  = prevLB;
  lb.addEventListener('click', e => { if (e.target === lb) closeLB(); });

  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape')     closeLB();
    if (e.key === 'ArrowRight') nextLB();
    if (e.key === 'ArrowLeft')  prevLB();
  });

  document.querySelectorAll('.sc-thumb').forEach(t =>
    t.addEventListener('click', () => openLB(+t.dataset.lb, IMGS))
  );

  // swipe em mobile
  let touchX = null;
  lb.addEventListener('touchstart', e => { touchX = e.changedTouches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', e => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) dx < 0 ? nextLB() : prevLB();
    touchX = null;
  }, { passive: true });
}

/* =========================================================
   PARTÍCULAS (canvas hero) — leve, sem custo em scroll
========================================================= */
function initHeroCanvas() {
  if (REDUCED) return;
  const cvs = document.getElementById('cvs');
  if (!cvs) return;
  const ctx = cvs.getContext('2d');
  let W, H, pts;
  const countFor = w => w < 768 ? 40 : 80;

  function initCvs() {
    W = cvs.width  = innerWidth;
    H = cvs.height = innerHeight;
    pts = Array.from({ length: countFor(innerWidth) }, () => ({
      x: Math.random() * W,
      y: Math.random() * H,
      vx: (Math.random() - .5) * .5,
      vy: (Math.random() - .5) * .5,
      r: Math.random() * 1.8 + .5,
      c: Math.random() > .5 ? '#3AAFE4' : '#E8751A',
      a: Math.random() * .4 + .15,
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
      ctx.fillStyle = p.c;
      ctx.globalAlpha = p.a;
      ctx.fill();
      for (let j = i + 1; j < pts.length; j++) {
        const q = pts[j];
        const d = Math.hypot(p.x - q.x, p.y - q.y);
        if (d < 120) {
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.strokeStyle = p.c;
          ctx.globalAlpha = (1 - d / 120) * .13;
          ctx.lineWidth = .5;
          ctx.stroke();
        }
      }
    });
    ctx.globalAlpha = 1;
    requestAnimationFrame(tick);
  })();
}

/* =========================================================
   BOTÕES MAGNÉTICOS — desktop apenas, deslocamento sutil
========================================================= */
function initMagneticButtons() {
  if (!IS_DESKTOP || REDUCED) return;
  document.querySelectorAll('.magnetic').forEach(btn => {
    const strength = 10;
    const moveX = HAS_GSAP ? gsap.quickTo(btn, 'x', { duration: .4, ease: 'power3.out' }) : null;
    const moveY = HAS_GSAP ? gsap.quickTo(btn, 'y', { duration: .4, ease: 'power3.out' }) : null;

    btn.addEventListener('mousemove', e => {
      const r = btn.getBoundingClientRect();
      const dx = ((e.clientX - r.left) / r.width  - .5) * strength;
      const dy = ((e.clientY - r.top)  / r.height - .5) * strength;
      if (moveX) { moveX(dx); moveY(dy); }
      else { btn.style.transform = `translate(${dx}px, ${dy}px)`; }
    });
    btn.addEventListener('mouseleave', () => {
      if (moveX) { moveX(0); moveY(0); }
      else { btn.style.transform = ''; }
    });
  });
}

/* =========================================================
   CURSOR INTERATIVO — apenas desktop com ponteiro fino
========================================================= */
function initCursor() {
  if (!IS_DESKTOP || REDUCED) return;
  const dot  = document.getElementById('cursorDot');
  const ring = document.getElementById('cursorRing');
  if (!dot || !ring) return;

  document.body.classList.add('has-cursor');

  let rx = 0, ry = 0, x = 0, y = 0;
  addEventListener('mousemove', e => { x = e.clientX; y = e.clientY; dot.style.left = x + 'px'; dot.style.top = y + 'px'; });

  (function loop() {
    rx += (x - rx) * .18;
    ry += (y - ry) * .18;
    ring.style.left = rx + 'px';
    ring.style.top  = ry + 'px';
    requestAnimationFrame(loop);
  })();

  const bigTargets = 'a, button, .fbtn, input, select, textarea, .sc-thumb';
  document.addEventListener('mouseover', e => {
    if (e.target.closest('.gal-item')) { ring.classList.add('view'); ring.classList.add('big'); }
    else if (e.target.closest(bigTargets)) { ring.classList.add('big'); }
  });
  document.addEventListener('mouseout', e => {
    if (e.target.closest('.gal-item') || e.target.closest(bigTargets)) {
      ring.classList.remove('big');
      ring.classList.remove('view');
    }
  });
}

/* =========================================================
   CONTENT CUSTOMIZER — busca textos/imagens do servidor (MySQL)
   Contrato preservado 1:1 com api/get-content.php e o painel admin.
========================================================= */
function initContentLoader() {
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

    if (texts.services_json) {
      try {
        const services = JSON.parse(texts.services_json);
        const grid = document.querySelector('#servicos .srv-grid');
        if (grid && Array.isArray(services) && services.length) {
          grid.innerHTML = '';
          services.forEach((service, index) => {
            const card = document.createElement('div');
            card.className = 'sc-card in';
            const imageWrap = document.createElement('div');
            imageWrap.className = 'sc-img scan-wrap';
            if (service.image) {
              const image = document.createElement('img');
              image.src = service.image;
              image.alt = service.title || '';
              image.loading = 'lazy';
              imageWrap.appendChild(image);
            }
            const overlay = document.createElement('div');
            overlay.className = 'sc-img-ov';
            imageWrap.appendChild(overlay);
            const body = document.createElement('div');
            body.className = 'sc-body';
            const number = document.createElement('div');
            number.className = 'sc-icon';
            number.textContent = String(index + 1).padStart(2, '0');
            const title = document.createElement('h3');
            title.textContent = service.title || '';
            const description = document.createElement('p');
            description.textContent = service.description || '';
            const list = document.createElement('ul');
            list.className = 'sc-list';
            (service.items || []).forEach(item => {
              const li = document.createElement('li');
              li.textContent = item;
              list.appendChild(li);
            });
            body.append(number, title, description, list);
            card.append(imageWrap, body);
            grid.appendChild(card);
          });

          const serviceSelect = document.getElementById('f-service');
          if (serviceSelect) {
            const selected = serviceSelect.value;
            serviceSelect.innerHTML = '<option value="">Selecione um serviço...</option>';
            services.forEach(service => {
              const option = document.createElement('option');
              option.value = service.title || '';
              option.textContent = service.title || '';
              serviceSelect.appendChild(option);
            });
            serviceSelect.value = selected;
          }
        }
      } catch (_) {}
    }
  }

  fetch('/api/get-content.php', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : null)
    .then(d => { if (d && d.ok) applyContent(d.texts || {}, d.images || {}); })
    .catch(() => {});
}

/* =========================================================
   FORMULÁRIO DE ORÇAMENTO — envia para api/submit-quote.php
   Contrato preservado: mesmos nomes de campo, mesmo endpoint,
   mesmo honeypot/timestamp anti-bot.
========================================================= */
function initQuoteForm() {
  const form = document.getElementById('cform');
  if (!form) return;

  document.getElementById('f-ts').value = Date.now();

  const btn        = document.getElementById('sendBtn');
  const btnDefault  = btn.innerHTML;
  const fName  = document.getElementById('f-name');
  const fEmail = document.getElementById('f-email');

  function setState(state, msg) {
    if (state === 'loading') {
      btn.disabled = true;
      btn.innerHTML = `<span class="spin"></span> Enviando...`;
      btn.style.background = '';
    } else if (state === 'success') {
      btn.innerHTML = '✓ Mensagem Enviada com Sucesso!';
      btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
    } else if (state === 'error') {
      btn.innerHTML = msg || 'Erro ao enviar. Tente novamente.';
      btn.style.background = 'linear-gradient(135deg,#ef4444,#dc2626)';
    } else {
      btn.disabled = false;
      btn.innerHTML = btnDefault;
      btn.style.background = '';
    }
  }

  [fName, fEmail].forEach(f => f.addEventListener('input', () => f.classList.remove('err')));

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const name  = fName.value.trim();
    const email = fEmail.value.trim();
    let invalid = false;

    if (!name) { fName.classList.add('err'); invalid = true; }
    if (!email) { fEmail.classList.add('err'); invalid = true; }
    if (invalid) { (fName.classList.contains('err') ? fName : fEmail).focus(); return; }

    setState('loading');

    const body = new FormData();
    body.append('name',        name);
    body.append('company',     document.getElementById('f-company').value.trim());
    body.append('email',       email);
    body.append('phone',       document.getElementById('f-phone').value.trim());
    body.append('service',     document.getElementById('f-service').value);
    body.append('description', document.getElementById('f-desc').value.trim());
    body.append('website',     document.getElementById('f-website').value);
    body.append('form_ts',     document.getElementById('f-ts').value);

    try {
      const res  = await fetch('/api/submit-quote.php', { method: 'POST', body });
      const data = await res.json();

      if (data.ok) {
        setState('success');
        form.reset();
        document.getElementById('f-ts').value = Date.now();
        setTimeout(() => setState('idle'), 3500);
      } else {
        setState('error', data.msg);
        setTimeout(() => setState('idle'), 3000);
      }
    } catch {
      setState('error', 'Erro de conexão. Tente novamente.');
      setTimeout(() => setState('idle'), 3000);
    }
  });
}

/* =========================================================
   BOOT
========================================================= */
initNavigation();
initSmoothScroll();
initHeroAnimations();
initScrollAnimations();
initCounters();
initServices();
initProcessTimeline();
initGallery();
initGalleryFilters();
initLightbox();
initContentLoader();
initQuoteForm();
