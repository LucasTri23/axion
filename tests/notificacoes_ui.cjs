const fs=require('node:fs');const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage({viewport:{width:1366,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://axion.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname.endsWith('.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('js/notificacoes.js','utf8')});
   if(url.pathname.endsWith('.css'))return route.fulfill({contentType:'text/css',body:fs.readFileSync('css/notificacoes.css','utf8')});
   return route.fulfill({contentType:'text/html',headers:{'Content-Security-Policy':"default-src 'self';script-src 'self';style-src 'self'"},body:'<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/notificacoes.css"></head><body><div class="alert alert-success">Salvo com sucesso.</div><div class="alert alert-error">Confira os campos.</div><div class="alert alert-info">Instrução permanente</div><div class="prod-import-warnings">Avisos de conferência</div><script src="/notificacoes.js" defer></script></body></html>'});
  });
  await page.goto('http://axion.test/');await page.waitForSelector('.ax-toast');
  assert.equal(await page.locator('.ax-toast').count(),2);assert.equal(await page.locator('.alert-success,.alert-error').count(),0);
  assert.equal(await page.locator('.alert-info,.prod-import-warnings').count(),2);assert.equal(await page.getByRole('alert').count(),1);
  let box=await page.locator('.ax-toast-stack').boundingBox();assert(box.x>900&&box.y<25);
  await page.getByRole('button',{name:'Fechar notificação'}).first().click();assert.equal(await page.locator('.ax-toast').count(),1);
  await page.mouse.move(0,0);await page.waitForTimeout(5250);assert.equal(await page.locator('.ax-toast').count(),0);
  await page.evaluate(()=>window.axionToast('<img src=x onerror=alert(1)>','success'));assert.equal(await page.locator('.ax-toast img').count(),0);
  await page.locator('.ax-toast-close').focus();await page.waitForTimeout(5250);assert.equal(await page.locator('.ax-toast').count(),1);
  await page.keyboard.press('Escape');assert.equal(await page.locator('.ax-toast').count(),0);
  await page.evaluate(()=>{const message=document.createElement('div');message.className='alert alert-danger';message.textContent='Erro dinâmico';document.body.append(message);});await page.waitForSelector('.ax-toast-error');
  await page.evaluate(()=>{for(let i=0;i<6;i++)window.axionToast('Mensagem '+i,'info');});assert.equal(await page.locator('.ax-toast').count(),4);
  await page.setViewportSize({width:390,height:844});box=await page.locator('.ax-toast-stack').boundingBox();assert(box.x>=0&&box.x+box.width<=390);
  assert.deepEqual(errors,[]);console.log('PASS: sucesso/erro, posição, fechamento após 5s, fechar/Escape, pausa no foco, mensagens dinâmicas, pilha, proteção de texto, avisos permanentes e mobile.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
