const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const browser = await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
  try {
    const page = await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    const script=fs.readFileSync('js/producao.js','utf8');
    const html = grams => `<html><head><meta charset="UTF-8"></head><body><div data-prod-peso>0,000 kg</div><label><input type="checkbox" data-prod-all>Selecionar filtro</label><table>${grams.map((g,i)=>`<tr><td><input type="checkbox" aria-label="Peça ${i+1}" data-prod-grams="${g}"></td></tr>`).join('')}</table><script src="/js/producao.js"></script></body></html>`;
    await page.route('http://producao.test/**',route=>route.request().url().endsWith('.js')?route.fulfill({contentType:'application/javascript',body:script}):route.fulfill({contentType:'text/html',headers:{'Content-Security-Policy':"default-src 'self';script-src 'self'"},body:html([1001,2999,5000])}));
    await page.goto('http://producao.test/');
    await page.getByLabel('Peça 1').check();
    assert.equal(await page.locator('[data-prod-peso]').innerText(),'1,001 kg');
    await page.getByLabel('Peça 2').check();
    assert.equal(await page.locator('[data-prod-peso]').innerText(),'4,000 kg');
    assert.equal(await page.locator('[data-prod-all]').evaluate(el=>el.indeterminate),true);
    await page.locator('[data-prod-all]').check();
    assert.equal(await page.locator('[data-prod-peso]').innerText(),'9,000 kg');
    assert.equal(await page.locator('.prod-selected').count(),3);
    await page.locator('[data-prod-all]').uncheck();
    assert.equal(await page.locator('[data-prod-peso]').innerText(),'0,000 kg');
    await page.reload();
    assert.equal(await page.locator('[data-prod-peso]').innerText(),'0,000 kg');
    await page.unroute('http://producao.test/**');
    await page.route('http://producao.test/**',route=>route.request().url().endsWith('.js')?route.fulfill({contentType:'application/javascript',body:script}):route.fulfill({contentType:'text/html',body:html([])}));
    await page.goto('http://producao.test/empty');
    assert.equal(await page.locator('[data-prod-all]').isDisabled(),true);
    assert.deepEqual(errors,[]);
    console.log('PASS: seleção de peças, gramas exatas, total filtrado selecionável, limpar, estado parcial, reinício da seleção, lista vazia e CSP.');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
