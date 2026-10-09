document.getElementById('add-escopo')?.addEventListener('click', () => {
  const tpl = document.getElementById('tpl-escopo').content.cloneNode(true);
  document.getElementById('escopo-rows').appendChild(tpl);
  addBoldControls();
});
document.getElementById('add-valor')?.addEventListener('click', () => {
  const tpl = document.getElementById('tpl-valor').content.cloneNode(true);
  document.getElementById('valor-rows').appendChild(tpl);
  addBoldControls();
});

document.getElementById('add-item')?.addEventListener('click', () => {
  const tpl = document.getElementById('tpl-item').content.cloneNode(true);
  document.getElementById('itens-rows').appendChild(tpl);
});
document.addEventListener('click', e => {
  if (e.target.classList.contains('remove-row')) {
    e.target.closest('.escopo-row, .item-row, .valor-row')?.remove();
  }
});

function toggleBold(textarea) {
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selected = textarea.value.slice(start, end);
  if (selected.startsWith('**') && selected.endsWith('**') && selected.length > 4) {
    textarea.setRangeText(selected.slice(2, -2), start, end, 'select');
  } else if (textarea.value.slice(start - 2, start) === '**' && textarea.value.slice(end, end + 2) === '**') {
    textarea.setRangeText(selected, start - 2, end + 2, 'select');
  } else {
    const text = selected || 'texto em negrito';
    textarea.setRangeText('**' + text + '**', start, end, 'select');
    textarea.setSelectionRange(start + 2, start + 2 + text.length);
  }
  textarea.focus();
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function addBoldControls() {
  document.querySelectorAll('textarea').forEach(textarea => {
    if (textarea.dataset.boldReady || textarea.disabled || textarea.readOnly) return;
    textarea.dataset.boldReady = '1';
    const tools = document.createElement('div');
    tools.className = 'text-format-tools';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-ghost btn-sm';
    button.textContent = 'Negrito';
    button.title = 'Selecione um trecho e clique para aplicar ou remover negrito';
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', () => toggleBold(textarea));
    const help = document.createElement('span');
    help.className = 'format-help';
    help.textContent = 'Selecione um trecho ou escreva **texto** para usar negrito na proposta.';
    tools.append(button, help);
    textarea.before(tools);
  });
}

addBoldControls();
