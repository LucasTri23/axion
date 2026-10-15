(() => {
  'use strict';
  const panel = document.querySelector('[data-fin-selection]');
  if (!panel) return;
  const checks = [...document.querySelectorAll('[data-selection-id]')].filter(input => !input.disabled);
  const selectPage = document.querySelector('[data-selection-page]');
  const clear = panel.querySelector('[data-selection-clear]');
  const list = panel.querySelector('[data-selection-list]');
  const message = panel.querySelector('[data-selection-message]');
  const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  let selected = new Set(checks.filter(input => input.checked).map(input => Number(input.dataset.selectionId)));
  let busy = false;

  function synchronize() {
    checks.forEach(input => {
      input.checked = selected.has(Number(input.dataset.selectionId));
      input.closest('tr').classList.toggle('is-selected', input.checked);
    });
    if (selectPage) {
      const count = checks.filter(input => input.checked).length;
      selectPage.checked = checks.length > 0 && count === checks.length;
      selectPage.indeterminate = count > 0 && count < checks.length;
      selectPage.disabled = busy || checks.length === 0;
    }
  }

  function render(data) {
    selected = new Set(data.items.map(item => item.id));
    panel.querySelector('[data-selection-count]').textContent = String(data.quantidade);
    panel.querySelector('[data-selection-pay]').textContent = currency.format(data.pagar / 100);
    panel.querySelector('[data-selection-receive]').textContent = currency.format(data.receber / 100);
    list.replaceChildren();
    data.items.forEach(item => {
      const li = document.createElement('li');
      const text = document.createElement('span');
      const due = item.vencimento ? item.vencimento.split('-').reverse().join('/') : 'Sem vencimento';
      text.textContent = [due, item.tipo === 'SAIDA' ? 'A pagar' : 'A receber', item.descricao || item.cliente, currency.format(item.centavos / 100)].join(' · ');
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-ghost btn-sm';
      remove.dataset.selectionRemove = String(item.id);
      remove.textContent = 'Remover';
      li.append(text, remove);
      list.append(li);
    });
  }

  async function update(action, ids = []) {
    if (busy) { synchronize(); return; }
    busy = true;
    panel.setAttribute('aria-busy', 'true');
    checks.forEach(input => { input.disabled = true; });
    clear.disabled = true;
    if (selectPage) selectPage.disabled = true;
    message.textContent = 'Atualizando a soma…';
    const body = new URLSearchParams({ action, csrf_token: panel.dataset.csrf });
    ids.forEach(id => body.append('ids[]', String(id)));
    try {
      const response = await fetch('/admin/financeiro_selecao.php', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body });
      if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Sua sessão pode ter expirado. Atualize a página para continuar.');
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Não foi possível atualizar a seleção.');
      render(data);
      message.textContent = data.quantidade ? 'Seleção atualizada. Valores conferidos no cadastro.' : 'Marque as contas que deseja incluir no planejamento.';
    } catch (error) {
      message.textContent = error.message || 'Falha de conexão. Atualize a página e tente novamente.';
    } finally {
      busy = false;
      panel.removeAttribute('aria-busy');
      checks.forEach(input => { input.disabled = false; });
      clear.disabled = false;
      synchronize();
    }
  }

  checks.forEach(input => input.addEventListener('change', () => update(input.checked ? 'adicionar' : 'remover', [input.dataset.selectionId])));
  selectPage?.addEventListener('change', () => update(selectPage.checked ? 'adicionar' : 'remover', checks.map(input => input.dataset.selectionId)));
  clear.addEventListener('click', () => update('limpar'));
  list.addEventListener('click', event => {
    const remove = event.target.closest('[data-selection-remove]');
    if (remove && !busy) update('remover', [remove.dataset.selectionRemove]);
  });
  synchronize();
})();
