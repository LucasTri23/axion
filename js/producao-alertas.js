(() => {
  const badge=document.querySelector('[data-prod-notifications]');
  if(!badge)return;
  let latest=null;
  async function refresh(){
    if(document.hidden)return;
    try{
      const response=await fetch('/admin/producao_alertas.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!response.ok||response.redirected)return;
      const data=await response.json();
      badge.textContent=String(data.count);badge.hidden=data.count===0;
      const id=Number(data.latest?.id||0);
      if(latest!==null&&id>latest){
        document.querySelector('[data-prod-toast]')?.remove();
        const toast=document.createElement('a');
        toast.dataset.prodToast='';toast.className='alert alert-success';toast.setAttribute('role','status');
        toast.href='/admin/notificacoes.php';
        toast.textContent='Nova atualização de produção: '+data.latest.login+' · '+data.latest.acao.replaceAll('_',' ')+'. Clique para conferir.';
        toast.style.cssText='position:fixed;right:20px;bottom:20px;max-width:420px;z-index:100;box-shadow:0 8px 24px #0006';
        document.body.append(toast);setTimeout(()=>toast.remove(),12000);
      }
      latest=Math.max(latest||0,id);
    }catch{/* A próxima consulta recupera falhas transitórias sem interromper o trabalho. */}
  }
  refresh();setInterval(refresh,30000);document.addEventListener('visibilitychange',refresh);
})();
