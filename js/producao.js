(() => {
  const boxes = [...document.querySelectorAll('[data-prod-grams]')];
  const all = document.querySelector('[data-prod-all]');
  const output = document.querySelector('[data-prod-peso]');
  if (!all || !output) return;
  const format = new Intl.NumberFormat('pt-BR', {minimumFractionDigits:3,maximumFractionDigits:3});
  function update() {
    let grams = 0, count = 0;
    boxes.forEach(box => { if(box.checked) { grams += Number(box.dataset.prodGrams); count++; } box.closest('tr').classList.toggle('prod-selected',box.checked); });
    output.textContent = format.format(grams/1000) + ' kg';
    all.checked = boxes.length > 0 && count === boxes.length;
    all.indeterminate = count > 0 && count < boxes.length;
    all.disabled = boxes.length === 0;
  }
  all.addEventListener('change', () => { boxes.forEach(box => {box.checked=all.checked;});update(); });
  boxes.forEach(box => box.addEventListener('change',update));
  update();
})();
