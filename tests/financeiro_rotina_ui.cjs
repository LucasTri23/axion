const assert=require('node:assert/strict');
const os=require('node:os');const path=require('node:path');const {pathToFileURL}=require('node:url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage();
  for(const width of [1366,390]){
   await page.setViewportSize({width,height:1000});
   for(const screen of ['recorrentes','rotina']){
    await page.goto(pathToFileURL(path.join(os.tmpdir(),`axion-${screen}-test.html`)).href);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${screen}: overflow em ${width}px`);
    if(screen==='recorrentes'){
     const confirmation=page.locator('form').filter({has:page.locator('input[name="action"][value="gerar"]')});
     assert.equal(await confirmation.evaluate(e=>e.checkValidity()),false,'Geração não deve aceitar confirmação desmarcada');
     await confirmation.locator('input[type="checkbox"]').check();
     assert.equal(await confirmation.evaluate(e=>e.checkValidity()),true,'Conferência marcada deve permitir gerar');
     await page.locator('details>summary').click();
     await page.locator('#rec-descricao').fill('Conta mensal');
     const buttons=await page.locator('.routine-toolbar button').boundingBox();assert.ok(buttons.height>=40,'Botão precisa permitir toque');
     assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Cadastro aberto não deve alargar página');
    }else{
     const target=await page.locator('.routine-proximas').getAttribute('href');assert.match(target,/limite=7/,'Atalho deve limitar a sete dias');
     const account=await page.locator('.routine-columns .routine-item').first().getAttribute('href');assert.match(account,/edit=\d+/,'Conta deve abrir registro específico');
    }
    await page.screenshot({path:path.join(os.tmpdir(),`axion-${screen}-${width}.png`),fullPage:true});
   }
  }
  console.log('PASS: resumo e recorrências em desktop/celular, atalhos e conferência obrigatória.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
