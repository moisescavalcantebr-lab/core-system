<?php if(!isset($formationKeys,$team)){http_response_code(404);exit;} ?>
<style>
.c-team-page { --team-avatar-size: 38px; }
.c-team-page .c-card:hover { transform: none; }
.c-team-page .c-page-header { gap: 10px; margin-bottom: 10px; }
.c-team-page .c-page-title { margin: 0 0 4px; }
.c-team-page .c-page-subtitle { margin: 0; }
.c-team-page .c-page-content { gap: 8px; }
.c-team-view-tabs { display: none; }
.c-team-page .c-team-layout { height: var(--team-board-height, 640px); grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: "time1 available" "info info"; grid-template-rows: minmax(0, 1fr) auto; gap: 12px; align-items: stretch; }
.c-team-page .c-team-layout[data-team-count="2"] { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-areas: "time1 time2" "available available" "info info"; grid-template-rows: minmax(0, 1fr) var(--team-pool-height, 190px) auto; }
.c-team-page .c-team-field-card { display: grid; grid-template-rows: auto minmax(0, 1fr); align-content: start; gap: 8px; min-height: 0; }
.c-team-page .c-team-field-card h3, .c-team-page .c-team-available-header h3 { margin: 0; font-size: 13px; line-height: 20px; }
.c-team-page .c-team-field-card h3 { display: flex; justify-content: space-between; gap: 8px; }
.c-team-page .c-team-field-card h3 small { font-size: 11px; padding: 0 8px; border: 1px solid var(--border-color); border-radius: 12px; font-weight: 500; white-space: nowrap; }
.c-team-page .c-team-field-card .c-team-field { height: var(--team-pitch-height, 400px); width: calc(var(--team-pitch-height, 400px) * .75); max-width: 100%; min-height: 0; margin: 0 auto; align-self: center; container-type: inline-size; border: 1px solid #8bc49d99; border-radius: 9px; background-color: #18643d; background-image: radial-gradient(ellipse at 50% 25%, #75c77528, transparent 72%), repeating-linear-gradient(0deg, #ffffff09 0 12.5%, #00000008 12.5% 25%); background-size: auto; box-shadow: inset 0 0 26px #062b3044, 0 5px 18px #0002; }
.c-team-page .c-team-field::after { content: ''; position: absolute; inset: 9px; border: 1px solid #e8ffedb8; border-radius: 2px; pointer-events: none; }
.c-team-page .c-team-field--futsal { background-color: #315f78; }
.c-team-page .c-team-field--beach { background-color: #ad925f; }
.c-team-page .c-team-field--society { background-color: #1b724a; }
.c-team-page .c-team-field-line { left: 9px; right: 9px; background: #e8ffedb8; }
.c-team-page .c-team-field-circle { width: 24%; height: auto; aspect-ratio: 1; border-color: #e8ffedb8; }
.c-team-page .c-team-field-spot { width: 4px; height: 4px; background: #effff2; }
.c-team-page .c-team-field-box { left: 27%; width: 46%; height: 16%; border-color: #e8ffedb8; }
.c-team-page .c-team-field-box-top, .c-team-page .c-team-field-goal-top { top: 9px; }
.c-team-page .c-team-field-box-bottom, .c-team-page .c-team-field-goal-bottom { bottom: 9px; }
.c-team-page .c-team-field-goal { height: 2.5%; border-color: #f4fff8d9; background: repeating-linear-gradient(90deg, transparent 0 4px, #fff4 4px 5px); }
.c-team-small-box { position: absolute; left: 38%; width: 24%; height: 6%; border: 1px solid #e8ffedb8; pointer-events: none; }
.c-team-small-box--top { top: 9px; border-top: 0; }
.c-team-small-box--bottom { bottom: 9px; border-bottom: 0; }
.c-team-penalty-arc { position: absolute; left: 50%; width: 24%; height: 12%; border: 1px solid #e8ffed8f; border-radius: 50%; transform: translateX(-50%); pointer-events: none; }
.c-team-penalty-arc--top { top: calc(16% - 9px); clip-path: inset(50% 0 0); }
.c-team-penalty-arc--bottom { bottom: calc(16% - 9px); clip-path: inset(0 0 50%); }
.c-team-page .c-team-token { width: clamp(46px, 19cqw, 76px); transform: translate(-50%, -50%); gap: 1px; }
.c-team-page .c-team-token-avatar, .c-team-page .c-team-available-player .c-team-avatar-initial { width: var(--team-avatar-size); height: var(--team-avatar-size); max-width: none; border: 2px solid #e1ffea; color: #174a32; background: linear-gradient(145deg, #edfff3, #9fd9b8); box-shadow: 0 2px 7px #001b2670; font-size: 12px !important; margin: 0; }
.c-team-page .c-team-token strong { width: 100%; max-width: 100%; margin-top: 2px; padding: 2px 4px; background: #082b23d9; border-radius: 4px; font-size: clamp(9px, 3cqw, 12px); line-height: 1.1; }
.c-team-page .c-team-token > span:not(.c-team-token-avatar) { position: absolute; top: calc(var(--team-avatar-size) - 8px); left: calc(50% + var(--team-avatar-size) / 2 - 4px); transform: translateX(-50%); padding: 1px 3px; margin: 0; border: 1px solid #a9e4ba88; background: #082b23f2; border-radius: 3px; color: #ddf9e5; font-size: 8px; line-height: 1.1; opacity: 1; }
.c-team-page .c-team-position-slot { width: var(--team-avatar-size); height: var(--team-avatar-size); font-size: 10px; color: #e5ffedaa; border-color: #e5ffed66; background: #09362633; box-shadow: none; }
.c-team-page .c-team-token:focus-within form { opacity: 1; }
.c-team-page .c-team-toggle-x { top: 13px; right: 13px; border-radius: 12px; background: #073329d9; padding: 4px 6px; }
.c-team-page .c-team-left-column, .c-team-page .c-team-reserve-card { min-height: 0; height: 100%; }
.c-team-page .c-team-reserve-card { gap: 8px; }
.c-team-page .c-team-available-stage { min-height: 0; flex: 1; height: auto; }
.c-team-page .c-team-available-page { grid-template-columns: repeat(var(--available-columns, 6), minmax(0, 1fr)); grid-template-rows: repeat(var(--available-rows, 5), minmax(0, 1fr)); align-content: start; gap: 4px; }
.c-team-page .c-team-available-player { align-self: start; }
.c-team-page .c-team-available-player summary { gap: 3px; padding: 3px 2px; border: 1px solid transparent; border-radius: 8px; }
.c-team-page .c-team-available-player summary:hover { background: color-mix(in srgb, var(--text-primary) 5%, transparent); border-color: var(--border-color); }
.c-team-page .c-team-available-name { font-size: 11px; line-height: 13px; }
.c-team-page .c-team-available-position { font-size: 10px; line-height: 11px; }
.c-team-page .c-team-available-menu { position: fixed; top: 50%; left: 50%; right: auto; width: min(340px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); transform: translate(-50%, -50%); overflow-y: auto; z-index: 1200; padding: 18px; border-radius: 12px; box-shadow: 0 16px 60px #0007, 0 0 0 100vmax #020a14aa; }
.c-team-page .c-team-available-menu form { margin-top: 12px; }
.c-team-page .c-team-available-pagination[hidden] { display: none; }
.c-team-page .c-team-info-card { padding: 0; }
.c-team-page .c-team-info-list { gap: 6px; }
.c-team-page .c-team-info-list > div { border-radius: 6px; padding: 5px 8px; background: color-mix(in srgb, var(--text-primary) 3%, transparent); min-width: 0; }
.c-team-page .c-team-info-list span { margin-bottom: 3px; font-size: 10px; }
.c-team-page .c-team-info-list strong { font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media(max-width:900px) {
 .c-team-view-tabs { display: flex; gap: 4px; padding: 4px; border: 1px solid var(--border-color); border-radius: 9px; }
 .c-team-view-tabs button { flex: 1; min-width: 0; padding: 8px 4px; border: 0; border-radius: 6px; background: transparent; color: var(--text-secondary); font: inherit; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
 .c-team-view-tabs button[aria-pressed="true"] { color: var(--text-primary); background: color-mix(in srgb, var(--text-primary) 10%, transparent); font-weight: 600; }
 .c-team-page.is-visual-ready .c-team-layout, .c-team-page.is-visual-ready .c-team-layout[data-team-count="2"] { grid-template-columns: minmax(0,1fr); grid-template-areas: "active" "info"; grid-template-rows: minmax(0,1fr) auto; gap: 8px; }
 .c-team-page.is-visual-ready .c-team-field-card, .c-team-page.is-visual-ready .c-team-left-column { display: none; grid-area: active; }
 .c-team-page.is-visual-ready .c-team-field-card.is-current { display: grid; }
 .c-team-page.is-visual-ready .c-team-left-column.is-current { display: block; }
 .c-team-page .c-team-header-actions { width: auto; justify-content: flex-start; gap: 5px; }
 .c-team-page .c-team-header-actions .c-btn-secondary { padding: 6px 8px; font-size: 11px; }
 .c-team-page .c-team-formations-toggle { flex-wrap: wrap; gap: 4px; font-size: 11px; }
 .c-team-page .c-team-info-list { grid-template-columns: repeat(3,minmax(0,1fr)); gap: 4px; }
 .c-team-page .c-team-info-list > div { padding: 4px 6px; }
 .c-team-page .c-team-field-card .c-team-field { max-width: 100%; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const page=document.querySelector('.c-team-page'), layout=page?.querySelector('.c-team-layout');if(!layout)return;
 page.querySelectorAll('img[data-team-avatar-initials]').forEach(image=>{
   const fallback=()=>{const initials=document.createElement('span');initials.className=image.className;initials.textContent=image.dataset.teamAvatarInitials;initials.setAttribute('aria-label',image.alt);image.replaceWith(initials);};
   image.addEventListener('error',fallback,{once:true});
   if(image.complete&&image.naturalWidth===0)fallback();
 });
 const pool=page.querySelector('.c-team-left-column'),stage=pool.querySelector('.c-team-available-stage');
 const players=[...stage.querySelectorAll('.c-team-available-player')],nav=pool.querySelector('.c-team-available-pagination');
 const prev=nav.querySelector('[data-available-prev]'),next=nav.querySelector('[data-available-next]'),counter=nav.querySelector('[data-available-counter]');
 const tabs=[...page.querySelectorAll('[data-team-view]')],fields=[...page.querySelectorAll('[data-team-key]')];
 let active='time_1',capacity=30,index=0,frame;
 page.classList.add('is-visual-ready');
 function showAvailable(){
   const pages=Math.max(1,Math.ceil(players.length/capacity));index=Math.min(index,pages-1);stage.replaceChildren();
   const grid=document.createElement('div');grid.className='c-team-available-page';grid.dataset.availablePage=String(index);
   players.slice(index*capacity,(index+1)*capacity).forEach(player=>grid.append(player));stage.append(grid);
   nav.hidden=pages<=1;prev.disabled=index===0;next.disabled=index===pages-1;counter.textContent=(index+1)+'/'+pages;
 }
 function closeMenus(){players.forEach(player=>player.open=false);}
 function fit(){
   const mobile=matchMedia('(max-width:900px)').matches;
   fields.forEach(field=>field.classList.toggle('is-current',field.dataset.teamKey===active));pool.classList.toggle('is-current',active==='available');
   tabs.forEach(tab=>tab.setAttribute('aria-pressed',String(tab.dataset.teamView===active)));
   const footer=document.querySelector('.c-footer'), main=page.closest('.c-content');
   const bottom=(footer?.getBoundingClientRect().height||0)+(main?parseFloat(getComputedStyle(main).paddingBottom)||0:0)+(parseFloat(getComputedStyle(document.body).paddingBottom)||0)+8;
   const height=Math.max(280,(window.visualViewport?.height||innerHeight)-layout.getBoundingClientRect().top-bottom);
   layout.style.setProperty('--team-board-height',height+'px');
   if(!mobile&&fields.length===2){
     const columns=Math.max(1,Math.min(12,Math.floor(layout.clientWidth/64)));
     const needed=36+Math.max(1,Math.ceil(Math.min(players.length,30)/columns))*76;
     const maximum=Math.max(100,height-page.querySelector('.c-team-info-card').getBoundingClientRect().height-24-288);
     layout.style.setProperty('--team-pool-height',Math.min(height*.4,maximum,Math.max(120,needed))+'px');
   }
   let pitch=0;
   fields.forEach(field=>{if(!field.getClientRects().length)return;const heading=field.querySelector('h3');const h=Math.max(180,Math.min(field.clientHeight-heading.offsetHeight-8,field.clientWidth/.75));field.style.setProperty('--team-pitch-height',h+'px');pitch=h;});
   page.style.setProperty('--team-avatar-size',Math.min(44,Math.max(28,Math.floor((pitch||400)*.085)))+'px');
   if(!mobile||active==='available'){
     const columns=Math.max(1,Math.min(mobile?6:(fields.length===2?12:6),Math.floor(stage.clientWidth/64)));
     const cellHeight=parseFloat(page.style.getPropertyValue('--team-avatar-size'))+38;
     const rows=Math.max(1,Math.floor(stage.clientHeight/cellHeight));const updated=Math.max(1,Math.min(30,columns*rows));
     if(capacity!==updated){capacity=updated;index=0;}
     stage.style.setProperty('--available-columns',columns);stage.style.setProperty('--available-rows',Math.min(rows,Math.max(1,Math.ceil(Math.min(players.length,capacity)/columns))));
     showAvailable();
   }
 }
 tabs.forEach(tab=>tab.addEventListener('click',()=>{active=tab.dataset.teamView;closeMenus();fit();}));
 prev.addEventListener('click',()=>{index=Math.max(0,index-1);closeMenus();showAvailable();});
 next.addEventListener('click',()=>{index=Math.min(Math.ceil(players.length/capacity)-1,index+1);closeMenus();showAvailable();});
 addEventListener('resize',()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(fit);});window.visualViewport?.addEventListener('resize',()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(fit);});
 requestAnimationFrame(fit);
});
</script>
