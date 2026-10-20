const fs=require('node:fs');const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage();let state={count:1,latest:{id:10,login:'operador',acao:'atualizar_peca'}};const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const script=fs.readFileSync('js/producao-alertas.js','utf8');
  await page.route('http://producao.test/**',route=>{
   const path=new URL(route.request().url()).pathname;
   if(path.endsWith('.js'))return route.fulfill({contentType:'application/javascript',body:script});
   if(path.endsWith('producao_alertas.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify(state)});
   return route.fulfill({contentType:'text/html',body:'<html><head><meta charset="UTF-8"></head><body><span data-prod-notifications hidden>0</span><script src="/js/producao-alertas.js"></script></body></html>'});
  });
  await page.goto('http://producao.test/');await page.waitForFunction(()=>document.querySelector('[data-prod-notifications]').textContent==='1');
  assert.equal(await page.locator('[data-prod-notifications]').isVisible(),true);assert.equal(await page.locator('[data-prod-toast]').count(),0);
  state={count:2,latest:{id:11,login:'<img src=x>',acao:'atualizar_peca'}};
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForSelector('[data-prod-toast]');assert.equal(await page.locator('[data-prod-toast] img').count(),0);assert.match(await page.locator('[data-prod-toast]').innerText(),/<img src=x>/);assert.equal(await page.locator('[data-prod-toast]').getAttribute('href'),'/admin/notificacoes.php');
  state={count:0,latest:null};await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForFunction(()=>document.querySelector('[data-prod-notifications]').hidden===true);
  assert.deepEqual(errors,[]);console.log('PASS: contador de não lidas, aviso para nova alteração, link de conferência, leitura e texto protegido contra HTML.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
