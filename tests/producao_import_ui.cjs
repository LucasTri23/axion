const fs=require('node:fs');const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 try{
  const page=await browser.newPage();const source=fs.readFileSync('admin/_producao_import_view.php','utf8');
  const form=source.match(/<form method="POST" enctype="multipart\/form-data" class="prod-upload-form">[\s\S]*?<\/form>/)[0].replace(/<\?[\s\S]*?\?>/g,'');
  const css=fs.readFileSync('admin/_layout.php','utf8').match(/<style>([\s\S]*?)<\/style>/)[1]+fs.readFileSync('css/producao.css','utf8');
  await page.route('http://producao.test/**',route=>new URL(route.request().url()).pathname.endsWith('.css')?route.fulfill({contentType:'text/css',body:css}):route.fulfill({contentType:'text/html',body:`<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/test.css"></head><body class="prod-workspace"><div style="padding:20px"><div class="card">${form}</div></div></body></html>`}));
  await page.setViewportSize({width:1366,height:900});await page.goto('http://producao.test/');
  let input=await page.locator('input[type=file]').boundingBox(),button=await page.locator('button[type=submit]').boundingBox();
  assert(Math.abs(input.y+input.height-button.y-button.height)<2,'Upload e botão devem alinhar na base');assert(button.height>=44);
  await page.setViewportSize({width:390,height:844});
  input=await page.locator('input[type=file]').boundingBox();button=await page.locator('button[type=submit]').boundingBox();
  assert(button.y>=input.y+input.height,'Botão deve ficar abaixo no celular');assert(button.x+button.width<=390);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);
  console.log('PASS: alinhamento do upload e botão no desktop, botão abaixo no celular e sem overflow horizontal.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
