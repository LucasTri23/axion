(() => {
  document.querySelectorAll('.prod-piece-table,.prod-preview-table').forEach(table => {
    const rows=[...table.querySelectorAll('tbody > tr')];
    if(rows.length<=25)return;
    let page=0;
    const pager=document.createElement('div');pager.className='prod-pager';pager.setAttribute('aria-label','Paginação das peças');
    const label=document.createElement('span');label.setAttribute('aria-live','polite');
    const actions=document.createElement('div');
    const previous=document.createElement('button');previous.type='button';previous.className='btn btn-ghost btn-sm';previous.textContent='← Anterior';
    const next=document.createElement('button');next.type='button';next.className='btn btn-secondary btn-sm';next.textContent='Próxima →';
    actions.append(previous,next);pager.append(label,actions);table.closest('.prod-scroll').after(pager);
    function show(){
      rows.forEach((row,index)=>{row.hidden=index<page*25||index>=(page+1)*25;});
      label.textContent=`${page*25+1}–${Math.min((page+1)*25,rows.length)} de ${rows.length} peças · página ${page+1}/${Math.ceil(rows.length/25)}`;
      previous.disabled=page===0;next.disabled=(page+1)*25>=rows.length;
      table.closest('.prod-scroll').scrollTop=0;
    }
    previous.addEventListener('click',()=>{if(page>0){page--;show();}});
    next.addEventListener('click',()=>{if((page+1)*25<rows.length){page++;show();}});
    show();
  });
  const boxes = [...document.querySelectorAll('[data-prod-grams]')];
  const all = document.querySelector('[data-prod-all]');
  const output = document.querySelector('[data-prod-peso]');
  if (!all || !output) return;
  const format = new Intl.NumberFormat('pt-BR', {minimumFractionDigits:3,maximumFractionDigits:3});
  function update() {
    let grams = 0, count = 0;
    boxes.forEach(box => { if(box.checked) { grams += Number(box.dataset.prodGrams); count++; } box.closest('tr').classList.toggle('prod-selected',box.checked); });
    output.textContent = format.format(grams/1000) + ' kg';
    document.querySelectorAll('[data-prod-selection-weight]').forEach(el=>{el.textContent=output.textContent;});
    document.querySelectorAll('[data-prod-count]').forEach(el=>{el.textContent=String(count);});
    all.checked = boxes.length > 0 && count === boxes.length;
    all.indeterminate = count > 0 && count < boxes.length;
    all.disabled = boxes.length === 0;
  }
  all.addEventListener('change', () => { boxes.forEach(box => {box.checked=all.checked;});update(); });
  boxes.forEach(box => box.addEventListener('change',update));
  update();
})();
