(() => {
  const compose = document.getElementById('mail-compose');
  const confirm = document.getElementById('mail-confirm-send');
  if (!compose || !confirm) return;
  const dirty = () => {
    confirm.querySelectorAll('button, input[type=checkbox]').forEach(control => { control.disabled = true; });
    const warning = document.getElementById('mail-unsaved-warning');
    if (warning) warning.hidden = false;
  };
  compose.addEventListener('input', dirty);
  compose.addEventListener('change', dirty);
  confirm.addEventListener('submit', () => { confirm.querySelector('button[type=submit]').disabled = true; });
})();

// Delegation includes message controls loaded into the side-by-side reader.
document.addEventListener('submit',event=>{
  const form=event.target;
  if(!(form instanceof HTMLFormElement)||!form.matches('[data-mail-remote-action]'))return;
  if(form.dataset.submitting==='yes'){event.preventDefault();return;}
  form.dataset.submitting='yes';form.setAttribute('aria-busy','true');
});

(() => {
  const reader = document.querySelector('[data-mail-reader]');
  if (!reader || document.querySelector('[data-mail-reading-mode]')?.dataset.mailReadingMode==='full') return;
  const content=reader.querySelector('[data-mail-reader-content]')||reader;
  const layout=reader.closest('.mail-reader-layout');
  const close=document.querySelector('[data-mail-close-reader]');
  let pending;
  document.querySelector('[data-mail-close-reader]')?.addEventListener('click',()=>{pending?.abort();reader.hidden=true;close.hidden=true;layout.classList.remove('has-message');reader.removeAttribute('aria-busy');document.querySelector('[data-mail-read].is-selected')?.focus();});
  document.querySelectorAll('[data-mail-read]').forEach(link => {
    link.addEventListener('click', async event => {
      if(event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      reader.hidden=false;close.hidden=false;layout.classList.add('has-message');
      pending?.abort();const controller=new AbortController();pending=controller;
      document.querySelectorAll('[data-mail-read]').forEach(item=>item.classList.toggle('is-selected',item===link));
      const status=document.createElement('div');status.className='mail-reading-empty';status.textContent='Carregando mensagem…';content.replaceChildren(status);reader.setAttribute('aria-busy','true');
      try {
        const url=new URL(link.href,location.href);url.searchParams.set('preview','1');
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{'Accept':'text/html'}});
        if(!response.ok)throw new Error('Unavailable');
        const parsed=new DOMParser().parseFromString(await response.text(),'text/html');const article=parsed.querySelector('article[data-mail-message-preview]');
        if(!article)throw new Error('Unavailable');
        if(controller.signal.aborted)return;
        content.replaceChildren(document.importNode(article,true));
        if(matchMedia('(max-width: 1200px)').matches)reader.scrollIntoView({behavior:'smooth',block:'start'});
      } catch(error) {
        if(error.name==='AbortError')return;
        const message=document.createElement('div');message.className='mail-reading-empty';message.textContent='Não foi possível carregar a prévia. ';
        if(new URL(link.href,location.href).searchParams.get('source')==='unified'){message.textContent='Não foi possível carregar a mensagem. Atualize a página e tente novamente.';}
        else{const open=document.createElement('a');open.href=link.href;open.textContent='Abrir mensagem';message.append(open);}content.replaceChildren(message);
      } finally {if(pending===controller)reader.removeAttribute('aria-busy');}
    });
  });
})();

(() => {
  document.querySelector('[data-mail-account-switch]')?.addEventListener('change',event=>{
    const url=new URL(event.target.value,location.href);
    if(url.origin===location.origin && ['/web/admin/central_contas/messages.php','/web/admin/central_contas/unified.php'].includes(url.pathname))location.assign(url.href);
  });
})();
if(matchMedia('(max-width: 650px)').matches)document.querySelectorAll('.mail-account-accordion').forEach(group=>{group.open=false;});
(() => {
  const form=document.querySelector('[data-mail-sync-all]');
  if(!form)return;
  form.addEventListener('submit',()=>{const button=form.querySelector('button');button.disabled=true;button.textContent='Sincronizando…';form.setAttribute('aria-busy','true');});
  addEventListener('pageshow',()=>{const button=form.querySelector('button');button.disabled=false;button.textContent='↻ Sincronizar todos';form.removeAttribute('aria-busy');});
})();
(() => {
  const root=document.querySelector('[data-mail-mark-filter]');if(!root)return;
  root.querySelectorAll('[data-mail-mark-form]').forEach(form=>form.addEventListener('submit',async event=>{
    const button=event.submitter;if(!button)return;event.preventDefault();
    const body=new FormData(form);body.set('mark',button.value);const entry=form.closest('[data-mail-message-entry]');const status=root.querySelector('[data-mail-mark-status]');
    form.querySelectorAll('button').forEach(b=>b.disabled=true);status.hidden=true;
    try{
      const response=await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body});if(!response.ok)throw new Error();
      const result=await response.json();const marks=result.marks;if(!marks||Number(marks.id)!==Number(entry.dataset.messageId))throw new Error();
      for(const field of ['favorite','flagged','pinned']){const enabled=Number(marks['is_'+field])===1;const control=form.querySelector('[data-mark-field="'+field+'"]');control.setAttribute('aria-pressed',String(enabled));control.value=field+':'+Number(!enabled);if(field==='favorite')control.textContent=enabled?'★':'☆';}
      entry.classList.toggle('is-flagged',Number(marks.is_flagged)===1);entry.dataset.pinned=String(Number(marks.is_pinned));
      const filter=root.dataset.mailMarkFilter;if(filter!=='all'&&Number(marks['is_'+filter])!==1)entry.remove();
      else{const list=entry.parentElement;[...list.children].sort((a,b)=>Number(b.dataset.pinned)-Number(a.dataset.pinned)||b.dataset.received.localeCompare(a.dataset.received)||Number(b.dataset.messageId)-Number(a.dataset.messageId)).forEach(row=>list.append(row));}
      if(!root.querySelector('[data-mail-message-entry]')){status.textContent='Nenhuma mensagem nesta seleção.';status.hidden=false;}
    }catch(error){status.textContent='Não foi possível salvar. Atualize a página e tente novamente.';status.hidden=false;}
    finally{form.querySelectorAll('button').forEach(b=>b.disabled=false);}
  }));
})();


