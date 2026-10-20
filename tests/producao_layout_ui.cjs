const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage({viewport:{width:1366,height:1000}});
  const css=fs.readFileSync('admin/_layout.php','utf8').match(/<style>([\s\S]*?)<\/style>/)[1]+fs.readFileSync('css/producao.css','utf8');
  const script=fs.readFileSync('js/producao.js','utf8');
  const partial=fs.readFileSync('admin/_producao_lista.php','utf8');
  const rowTemplate=partial.match(/<tr>\s*<td class="prod-select-cell">[\s\S]*?<\/tr>/)[0];
  function value(code){
   if(code.includes("$p['tag']"))return '11-4400-SSR-1028';
   if(code.includes("$p['descricao']"))return 'SUPORTE DE DISTRIBUIÇÃO DE VAPOR';
   if(code.includes("$p['desenho']"))return 'MF00954858/11-4400-SSR-1028';
   if(code.includes("$p['data']"))return '19/10/2026';
   if(code.includes("$p['quantidade']"))return '2';
   if(code.includes("$p['responsavel']"))return 'Leandro';
   if(code.includes('PROD_STATUS'))return 'Pintura';
   if(code.includes("$p['status']"))return 'pintura';
   if(code.includes('number_format($advance'))return '75,00';
   if(code.includes('number_format'))return '1.191,760';
   if(code.includes('$advance'))return '75';
   if(code.includes('$grams'))return '2383520';
   if(code.includes('$manager'))return 'Editar peça';
   return '1';
  }
  const row=rowTemplate.replace(/<\?=([\s\S]*?)\?>/g,(_,code)=>value(code));
  let piece=partial.replace(/<\?php if\(!\$rows\):[\s\S]*?<\?php else: \?>/,'');
  piece=piece.replace(/<\?php foreach\(\$rows as \$p\):[\s\S]*?<\?php endforeach \?>/,row.repeat(58));
  piece=piece.replace(/<\?=([\s\S]*?)\?>/g,(_,code)=>code.includes('count($rows)')?'58':'1').replace(/<\?php[\s\S]*?\?>/g,'');
  const importSource=fs.readFileSync('admin/_producao_import_view.php','utf8');
  const footer=importSource.match(/<form method="POST" class="prod-import-footer">[\s\S]*?<\/form>/)[0].replace(/<\?=([\s\S]*?)\?>/g,(_,code)=>code.includes('csrfField')?'':code.includes('warningCount')?'66':code.includes('count(')?'540':'001').replace(/<\?php[\s\S]*?\?>/g,'');
  const preview=`<div class="prod-page-heading"><div><a class="prod-back">← Voltar para OS 001</a><h2>Importar lista de fabricação</h2><p>Envie o Excel, confira os itens e confirme a inclusão na OS.</p></div><span class="prod-file-pill">Excel · .xlsx</span></div><ol class="prod-stepper"><li class="is-done"><span>1</span>Enviar arquivo</li><li class="is-current"><span>2</span>Conferir peças</li><li><span>3</span>Confirmar importação</li></ol><section class="card prod-preview-card"><div class="prod-section-heading"><div><span class="prod-eyebrow">CONFERÊNCIA</span><h2>Revise sua lista</h2><p>Confira quantidades e pesos antes de incluir as peças.</p></div><span class="prod-file-pill">PV 140 - AVANÇO TCM - SUPORTES.xlsx</span></div><div class="prod-import-metrics"><div><span>Itens da lista</span><strong>540</strong></div><div><span>Peso total calculado</span><strong>19.798,550 <small>kg</small></strong></div><div class="has-warning"><span>Avisos para revisar</span><strong>66</strong></div></div><div class="prod-weight-notice"><strong>Peso unitário × quantidade = peso total.</strong><span>Confira a coluna PESO antes de continuar.</span></div><details class="prod-import-warnings"><summary><span>Revisar 66 avisos</span><small>A importação pode continuar após sua confirmação</small></summary><p>Valores originais preservados no histórico.</p></details><div class="prod-scroll"><table class="prod-table prod-preview-table"><thead><tr><th>Data</th><th>TAG</th><th>Peso total</th><th>Situação</th></tr></thead><tbody>${'<tr><td class="prod-nowrap">19/10/2026</td><td class="prod-code">11-4400-SSR-1028</td><td class="prod-nowrap">1.191,760 kg</td><td><span class="prod-stage prod-stage-pintura">Pintura</span></td></tr>'.repeat(8)}</tbody></table></div>${footer}</section>`;
  await page.route('http://producao.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname.endsWith('.css'))return route.fulfill({contentType:'text/css',body:css});
   if(url.pathname.endsWith('.js'))return route.fulfill({contentType:'application/javascript',body:script});
   return route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/style.css"></head><body class="prod-workspace"><div class="layout"><aside class="sidebar"></aside><main class="main"><header class="topbar"><h1>Produção / PCP</h1></header><div class="page-area"><div data-prod-peso>0,000 kg</div>${url.pathname==='/preview'?preview:piece}</div></main></div><script src="/js/producao.js" defer></script></body></html>`});
  });
  await page.goto('http://producao.test/list');
  assert.equal(await page.locator('.prod-piece-table tbody tr:not([hidden])').count(),25);
  await page.locator('[data-prod-all]').check();assert.equal(await page.locator('[data-prod-count]').innerText(),'58');
  await page.getByRole('button',{name:'Próxima →'}).click();assert.equal(await page.locator('.prod-piece-table tbody tr:not([hidden])').count(),25);assert.equal(await page.locator('[data-prod-count]').innerText(),'58');
  const date=await page.locator('.prod-piece-table tbody tr:not([hidden]) .prod-nowrap').first().evaluate(el=>({whiteSpace:getComputedStyle(el).whiteSpace,height:el.clientHeight}));assert.equal(date.whiteSpace,'nowrap');
  await page.locator('.prod-piece-table tbody tr:not([hidden]) .prod-row-details summary').first().click();assert.equal(await page.locator('.prod-piece-table tbody tr:not([hidden]) .prod-row-details').first().getAttribute('open'),'');
  if(process.env.UI_SCREENSHOT_DIR){fs.mkdirSync(process.env.UI_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.UI_SCREENSHOT_DIR,'producao-desktop.png')});}
  await page.setViewportSize({width:390,height:844});assert.equal(await page.locator('.prod-piece-table').evaluate(el=>getComputedStyle(el).display),'block');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.goto('http://producao.test/preview');assert.equal(await page.locator('.prod-import-warnings').getAttribute('open'),null);assert.equal(await page.locator('.prod-import-confirm').count(),2);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  const labels=await page.locator('.prod-import-confirm').evaluateAll(nodes=>nodes.map(el=>el.getBoundingClientRect().y));assert(labels[1]>labels[0]+20);
  if(process.env.UI_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.UI_SCREENSHOT_DIR,'importacao-mobile.png')});
  await page.setViewportSize({width:1366,height:1000});if(process.env.UI_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.UI_SCREENSHOT_DIR,'importacao-desktop.png')});
  console.log('PASS: paginação de 25 itens, seleção entre páginas, detalhes expansíveis, datas sem quebra, cartões mobile, avisos recolhidos e confirmações separadas.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
