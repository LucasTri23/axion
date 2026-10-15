const fs = require('node:fs');
const assert = require('node:assert/strict');
// PLAYWRIGHT_MODULE can point to an existing Playwright installation.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}) });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 950 } });
    const script = fs.readFileSync('js/financeiro-contas.js', 'utf8');
    const css = fs.readFileSync('css/financeiro.css', 'utf8');
    const source = fs.readFileSync('admin/financeiro_contas.php', 'utf8');
    const panel = source.match(/<section class="fin-selection"[\s\S]*?<\/section>/)[0]
      .replace(/<\?[\s\S]*?\?>/g, '')
      .replace(/(<ul data-selection-list>)[\s\S]*?(<\/ul>)/, '$1$2');
    const fixtures = [
      { id: 1, tipo: 'SAIDA', centavos: 1010, descricao: 'Fornecedor A', vencimento: '2026-10-15' },
      { id: 2, tipo: 'SAIDA', centavos: 20, descricao: '<script>injection</script>', vencimento: null },
      { id: 3, tipo: 'ENTRADA', centavos: 500, descricao: 'Cliente', vencimento: '2026-10-16' },
    ];
    let ids = new Set();
    let fail = false;
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('http://financeiro.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/js/financeiro-contas.js') return route.fulfill({ contentType: 'application/javascript', body: script });
      if (url.pathname === '/css/financeiro.css') return route.fulfill({ contentType: 'text/css', body: css });
      if (url.pathname === '/admin/financeiro_selecao.php') {
        if (fail) return route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: 'Falha de teste' }) });
        const body = new URLSearchParams(route.request().postData());
        const action = body.get('action');
        const incoming = body.getAll('ids[]').map(Number);
        assert.equal(route.request().method(), 'POST');
        if (action === 'limpar') ids.clear();
        if (action === 'adicionar') incoming.forEach(id => ids.add(id));
        if (action === 'remover') incoming.forEach(id => ids.delete(id));
        const items = fixtures.filter(row => ids.has(row.id));
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ items, quantidade: items.length, pagar: items.filter(r => r.tipo === 'SAIDA').reduce((sum,r) => sum+r.centavos,0), receber: items.filter(r => r.tipo === 'ENTRADA').reduce((sum,r) => sum+r.centavos,0) }) });
      }
      const visible = url.searchParams.get('page') === '2' ? [3] : [1,2];
      const table = `<label><input type="checkbox" data-selection-page> Selecionar página</label><table class="fin-accounts-table"><tbody>${visible.map(id => `<tr><td><input type="checkbox" data-selection-id="${id}" ${ids.has(id) ? 'checked' : ''}></td><td>Conta ${id}</td></tr>`).join('')}<tr><td><input type="checkbox" data-selection-id="99" disabled></td><td>Pago</td></tr></tbody></table>`;
      return route.fulfill({ contentType: 'text/html', headers: { 'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'" }, body: `<!doctype html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="/css/financeiro.css"></head><body style="background:#080f1e;color:white;font-family:Arial;--orange:#e8751a;--gray2:#b8c4d0;--sky:#3aafe4">${panel}${table}<script src="/js/financeiro-contas.js" defer></script></body></html>` });
    });
    await page.goto('http://financeiro.test/');
    const waitForCount = count => page.waitForFunction(n => document.querySelector('[data-selection-count]').textContent === String(n) && !document.querySelector('[data-fin-selection]').hasAttribute('aria-busy'), count);
    await page.locator('[data-selection-id="1"]').check();
    await waitForCount(1);
    assert.match(await page.locator('[data-selection-pay]').innerText(), /10,10/);
    await page.locator('[data-selection-page]').check();
    await waitForCount(2);
    assert.match(await page.locator('[data-selection-pay]').innerText(), /10,30/);
    assert.equal(await page.locator('[data-selection-id="99"]').isChecked(), false);
    assert.equal(await page.locator('[data-selection-list] script').count(), 0);
    await page.goto('http://financeiro.test/?page=2');
    await page.locator('[data-selection-id="3"]').check();
    await waitForCount(3);
    assert.match(await page.locator('[data-selection-pay]').innerText(), /10,30/);
    assert.match(await page.locator('[data-selection-receive]').innerText(), /5,00/);
    await page.locator('.fin-selection-details summary').click();
    await page.locator('[data-selection-remove="1"]').click();
    await waitForCount(2);
    assert.match(await page.locator('[data-selection-pay]').innerText(), /0,20/);
    fail = true;
    await page.locator('[data-selection-id="3"]').uncheck();
    await page.waitForFunction(() => document.querySelector('[data-selection-message]').textContent === 'Falha de teste');
    assert.equal(await page.locator('[data-selection-id="3"]').isChecked(), true);
    fail = false;
    await page.locator('[data-selection-clear]').click();
    await waitForCount(0);
    assert.match(await page.locator('[data-selection-pay]').innerText(), /0,00/);
    assert.match(await page.locator('[data-selection-receive]').innerText(), /0,00/);
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(await page.locator('[data-fin-selection]').evaluate(el => getComputedStyle(el).position), 'static');
    assert.deepEqual(errors, []);
    console.log('PASS: seleção individual/página, centavos, pagar/receber separados, persistência entre páginas, remoção, erro de rede, limpar, CSP e mobile.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
