<style>
.eq-page,.eq-panel { min-width:0; }
.eq-panel { grid-column:1/-1; }
.eq-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.eq-actions form { margin:0; }
.eq-tabs { display:flex; flex-wrap:wrap; gap:8px; margin:18px 0; }
.eq-tabs [aria-current] { border-color:#38bdf8; color:#7dd3fc; }
.eq-section { padding:22px 0; border-bottom:1px solid var(--border-color,#334155); }
.eq-section h2,.eq-section h3 { font-size:17px; margin:0 0 16px; }
.eq-page label,.eq-panel label { display:flex; flex-direction:column; gap:7px; font-size:13px; min-width:0; }
.eq-page input,.eq-page select,.eq-page textarea,.eq-panel input,.eq-panel select { box-sizing:border-box; width:100%; min-width:0; }
.eq-page input[type=date],.eq-page input[type=month] { color-scheme:dark; }
.eq-form { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.eq-filters { display:grid; grid-template-columns:180px minmax(140px,1fr) auto; gap:12px; align-items:end; margin:18px 0; }
.eq-wide { grid-column:1/-1; }
.eq-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:20px; padding:20px 0; border-block:1px solid var(--border-color,#334155); }
.eq-metrics div { min-width:0; }
.eq-metrics span,.eq-metrics small { display:block; color:var(--text-secondary,#94a3b8); font-size:12px; margin:6px 0; }
.eq-metrics strong { display:block; font-size:21px; overflow-wrap:anywhere; }
.eq-scroll { max-width:100%; overflow-x:auto; }
.eq-page table,.eq-panel table { width:100%; }
.eq-readings { min-width:1150px; }
.eq-reasons { min-width:570px; }
.eq-page td,.eq-page th,.eq-panel td,.eq-panel th { font-size:13px; }
.eq-page td small,.eq-panel td small { display:block; color:var(--text-secondary,#94a3b8); font-size:11px; margin-top:4px; }
.eq-page th small { display:block; font-size:10px; margin-top:4px; }
.eq-period { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:16px 0; }
.eq-period p { margin:5px 0; }
.eq-page td a,.eq-panel td a { color:#7dd3fc; }
.eq-error { border:1px solid #fb7185; border-radius:4px; padding:12px; overflow-wrap:anywhere; }
.eq-legend { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; margin:14px 0; }
.eq-legend i { display:inline-block; width:9px; height:9px; margin-right:5px; }
.eq-total { background:#38bdf8; }
.eq-executed { background:#2dd4bf; }
.eq-sent { background:#fb923c; }
.eq-lis { background:#a3e635; }
.eq-leit { background:#f472b6; }
.eq-chart { display:grid; grid-template-columns:repeat(var(--eq-columns,12),minmax(35px,1fr)); gap:10px; min-width:650px; height:230px; padding-top:20px; }
.eq-column { display:grid; grid-template-rows:190px 20px; gap:8px; min-width:0; }
.eq-bars { display:flex; align-items:end; justify-content:center; gap:3px; height:190px; border-bottom:1px solid var(--border-color,#334155); background:repeating-linear-gradient(to top,transparent 0,transparent 46px,var(--border-color,#334155) 47px,transparent 48px); }
.eq-bar { width:11px; flex:0 0 11px; min-height:0; }
.eq-column small { text-align:center; font-size:10px; white-space:nowrap; }
.eq-comparison { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
.eq-comparison p { margin:8px 0; font-size:13px; }
.eq-positive { color:#a3e635; }
.eq-negative { color:#fda4af; }
.eq-muted { color:var(--text-secondary,#94a3b8); font-size:12px; }
.eq-section summary { font-size:13px; cursor:pointer; margin:16px 0; }
@media(max-width:760px) {
 .eq-form { grid-template-columns:repeat(2,minmax(0,1fr)); }
 .eq-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
 .eq-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
 .eq-filters button { grid-column:1/-1; }
 .eq-comparison { grid-template-columns:1fr; }
 .eq-daily-table { min-width:0; display:block; }
 .eq-daily-table thead { display:none; }
 .eq-daily-table tbody { display:block; }
 .eq-daily-table tbody tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; padding:18px 0; border-bottom:1px solid var(--border-color,#334155); }
 .eq-daily-table tbody td { display:block; min-width:0; border:0; padding:0; overflow-wrap:anywhere; }
 .eq-daily-table td[data-label]::before { content:attr(data-label); display:block; margin-bottom:5px; font-size:11px; color:var(--text-secondary,#94a3b8); }
 .eq-daily-table td:last-child,.eq-daily-table td[colspan] { grid-column:1/-1; }
}
@media(max-width:420px) { .eq-form { grid-template-columns:1fr; } }
</style>
<script>
document.querySelectorAll('[data-eq-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!confirm(form.dataset.eqConfirm)) event.preventDefault();
}));
(() => {
    const charts = Array.from(document.querySelectorAll('.eq-chart'));
    const position = chart => {
        const bars = Array.from(chart.querySelectorAll('.eq-bar')).filter(bar => parseFloat(bar.style.height) > 0);
        const last = bars.at(-1), scroll = chart.parentElement;
        if (last && scroll.clientWidth > 0 && scroll.scrollWidth > scroll.clientWidth) scroll.scrollLeft = last.offsetLeft - scroll.offsetLeft - scroll.clientWidth / 2;
    };
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(entries => entries.forEach(entry => position(entry.target.firstElementChild)));
        charts.forEach(chart => observer.observe(chart.parentElement));
    }
    requestAnimationFrame(() => charts.forEach(position));
})();
</script>
