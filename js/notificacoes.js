(() => {
  'use strict';
  let stack;
  const seen=new WeakSet();
  function notify(message,type='success',options={}) {
    const text=String(message||'').trim();
    if(!text)return null;
    if(!stack){stack=document.createElement('div');stack.className='ax-toast-stack';stack.setAttribute('aria-label','Notificações');document.body.append(stack);}
    while(stack.children.length>=4)stack.firstElementChild.dismiss?.();
    const kind=['success','error','info','warning'].includes(type)?type:'info';
    const toast=document.createElement('div');toast.className='ax-toast ax-toast-'+kind;toast.setAttribute('role',kind==='error'?'alert':'status');toast.setAttribute('aria-atomic','true');
    const icon=document.createElement('span');icon.className='ax-toast-icon';icon.setAttribute('aria-hidden','true');icon.textContent={success:'✓',error:'!',info:'i',warning:'!'}[kind];
    const content=document.createElement('div');content.className='ax-toast-content';
    const title=document.createElement('strong');title.textContent={success:'Tudo certo',error:'Não foi possível concluir',info:'Aviso',warning:'Atenção'}[kind];
    const body=document.createElement('p');body.textContent=text;content.append(title,body);
    if(options.href){const link=document.createElement('a');link.href=options.href;link.textContent=options.linkText||'Conferir';content.append(link);}
    const close=document.createElement('button');close.type='button';close.className='ax-toast-close';close.setAttribute('aria-label','Fechar notificação');close.textContent='×';
    const progress=document.createElement('span');progress.className='ax-toast-progress';progress.setAttribute('aria-hidden','true');
    toast.append(icon,content,close,progress);
    const duration=5000;let remaining=duration,started=performance.now(),timer;const paused=new Set();
    function dismiss(){clearTimeout(timer);toast.remove();}
    function pause(reason){if(paused.size===0){clearTimeout(timer);remaining=Math.max(0,remaining-(performance.now()-started));toast.classList.add('is-paused');}paused.add(reason);}
    function resume(reason){paused.delete(reason);if(paused.size===0){started=performance.now();toast.classList.remove('is-paused');timer=setTimeout(dismiss,remaining);}}
    toast.dismiss=dismiss;close.addEventListener('click',dismiss);
    toast.addEventListener('mouseenter',()=>pause('hover'));toast.addEventListener('mouseleave',()=>resume('hover'));
    toast.addEventListener('focusin',()=>pause('focus'));toast.addEventListener('focusout',event=>{if(!toast.contains(event.relatedTarget))resume('focus');});
    toast.addEventListener('keydown',event=>{if(event.key==='Escape')dismiss();});
    stack.append(toast);timer=setTimeout(dismiss,duration);return toast;
  }
  window.axionToast=notify;
  function scan(root){
    const selector='.alert-success,.alert-error,.alert-danger,[data-toast]';
    const nodes=[...(root.matches?.(selector)?[root]:[]),...root.querySelectorAll(selector)];
    nodes.forEach(node=>{
      if(seen.has(node)||node.closest('.ax-toast-stack,[data-toast-inline]'))return;
      seen.add(node);
      const kind=node.dataset.toast||((node.classList.contains('alert-error')||node.classList.contains('alert-danger'))?'error':'success');
      const link=node.querySelector('a');
      const toast=notify(node.textContent,kind,link?{href:link.href,linkText:link.textContent}:{});
      if(toast)node.remove();
    });
  }
  scan(document);
  new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)scan(node);}))).observe(document.body,{childList:true,subtree:true});
})();
