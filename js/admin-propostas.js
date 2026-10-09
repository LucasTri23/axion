document.getElementById('add-escopo')?.addEventListener('click', () => {
  const tpl = document.getElementById('tpl-escopo').content.cloneNode(true);
  document.getElementById('escopo-rows').appendChild(tpl);
});
document.getElementById('add-valor')?.addEventListener('click', () => {
  const tpl = document.getElementById('tpl-valor').content.cloneNode(true);
  document.getElementById('valor-rows').appendChild(tpl);
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
