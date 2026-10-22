// Execute após folha_crud_test.php, que gera as telas com dados fictícios no TEMP.
const assert=require('node:assert/strict');
const path=require('node:path');
const os=require('node:os');
const {pathToFileURL}=require('node:url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage();
  for(const width of [1366,390]){
   await page.setViewportSize({width,height:1000});
   for(const screen of ['list','profile']){
    await page.goto(pathToFileURL(path.join(os.tmpdir(),`axion-folha-${screen}-test.html`)).href);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${screen}: página alargou em ${width}px`);
    const edit=page.locator('details').first();
    await edit.locator('summary').click();
    await assert.doesNotReject(()=>edit.locator('input[name="nome"]').fill('Nome para edição'));
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Formulário aberto alargou a página');
    if(screen==='list'){
     await page.locator('select[name="situacao"]').selectOption('pendente');
     assert.equal(await page.locator('select[name="situacao"]').inputValue(),'pendente');
     const buttons=await page.locator('.staff-filters button,.staff-filters a').evaluateAll(elements=>elements.map(e=>{const r=e.getBoundingClientRect();return {height:r.height,top:r.top};}));
     assert.ok(buttons.every(b=>b.height>=40),'Botão pequeno para toque');
     assert.ok(Math.abs(buttons[0].top-buttons[1].top)<2,'Botões dos filtros desalinhados');
    }
   }
  }
  console.log('PASS: layout desktop/celular, edição acessível e botões alinhados.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
