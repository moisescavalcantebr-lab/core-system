<style>
.a-page,.a-panel { min-width:0; }
.a-panel { grid-column:1/-1; }
.a-panel td a,.a-section a { color:#60a5fa; }
.a-page h2,.a-panel h3 { font-size:17px; margin:0 0 16px; }
.a-page label { display:flex; flex-direction:column; gap:6px; font-size:13px; min-width:0; }
.a-page input,.a-page select,.a-page textarea { box-sizing:border-box; min-width:0; width:100%; }
.a-section { padding:22px 0; border-bottom:1px solid var(--border-color,#d4d4d8); }
.a-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; padding:18px 0; border-block:1px solid var(--border-color,#d4d4d8); }
.a-metrics > div { min-width:0; }
.a-metrics span { display:block; font-size:12px; margin-bottom:7px; color:var(--text-secondary,#52525b); }
.a-metrics strong { display:block; font-size:19px; overflow-wrap:anywhere; }
.a-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.a-actions form { margin:0; }
.a-filters { display:grid; grid-template-columns:repeat(4,minmax(100px,1fr)) auto; gap:12px; align-items:end; margin:22px 0; }
.a-form-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.a-notes { margin-top:16px; }
.a-scroll { overflow-x:auto; max-width:100%; }
.a-history { min-width:940px; }
.a-page td,.a-panel td,.a-page th,.a-panel th { font-size:13px; }
.a-page td:not(:nth-child(2)),.a-panel td { white-space:nowrap; }
.a-history small { display:block; margin-top:4px; color:var(--text-secondary,#52525b); }
.a-error { padding:12px; border:1px solid #e87967; border-radius:4px; font-size:13px; overflow-wrap:anywhere; }
.a-status { font-size:12px; }
.a-status-overdue { color:#f87171; }
.a-status-settled { color:#14b8a6; }
.a-check { flex-direction:row!important; align-items:center; }
.a-check input { width:17px; height:17px; }
.a-legend { display:flex; gap:20px; flex-wrap:wrap; font-size:12px; margin-bottom:18px; }
.a-legend span::before { content:''; display:inline-block; width:9px; height:9px; margin-right:7px; background:var(--series); }
.a-receive { --series:#14b8a6; }
.a-pay { --series:#e87967; }
.a-chart { min-width:580px; padding:10px 4px; }
.a-plot { display:grid; grid-template-columns:repeat(var(--columns),minmax(0,1fr)); gap:10px; height:170px; border-bottom:1px solid var(--border-color,#d4d4d8); background:repeating-linear-gradient(to top,transparent 0,transparent 41px,var(--border-color,#d4d4d8) 42px); }
.a-bars { display:flex; align-items:flex-end; justify-content:center; gap:4px; height:100%; min-width:0; }
.a-bar { width:22px; max-width:35%; background:var(--series); border-radius:2px 2px 0 0; }
.a-chart-labels { display:grid; grid-template-columns:repeat(var(--columns),minmax(0,1fr)); gap:10px; margin-top:9px; font-size:11px; text-align:center; }
.a-details { margin-top:14px; font-size:13px; }
.a-details summary { cursor:pointer; padding:8px 0; }
.a-categories { display:grid; gap:14px; }
.a-category { display:grid; grid-template-columns:minmax(90px,1fr) minmax(70px,2fr) auto; gap:12px; align-items:center; font-size:13px; }
.a-category > span:first-child { overflow-wrap:anywhere; }
.a-category-track { height:10px; background:var(--border-color,#d4d4d8); }
.a-category-fill { height:100%; background:#3b82f6; }
.a-caption { color:var(--text-secondary,#52525b); font-size:12px; margin:0 0 14px; }
@media(max-width:900px) { .a-form-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.a-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:650px) { .a-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }.a-metrics strong { font-size:17px; }.a-form-grid { grid-template-columns:1fr; }.a-page .c-page-header { flex-wrap:wrap; gap:12px; }.a-category { grid-template-columns:minmax(75px,1fr) minmax(50px,1fr) auto; gap:8px; } }
</style>
<script>document.querySelectorAll('[data-contas-confirm]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.contasConfirm)) event.preventDefault(); }));</script>
<script>requestAnimationFrame(() => document.querySelectorAll('.a-chart[data-recent]').forEach(chart => { const scroll = chart.parentElement; const active = [...chart.querySelectorAll('.a-bar')].filter(bar => bar.getBoundingClientRect().height > 0).pop(); if (active && scroll.scrollWidth > scroll.clientWidth) { const target = active.getBoundingClientRect(); const bounds = scroll.getBoundingClientRect(); scroll.scrollLeft += target.left - bounds.left - bounds.width / 2 + target.width / 2; } }));</script>
