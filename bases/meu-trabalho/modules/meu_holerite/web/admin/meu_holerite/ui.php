<style>
.h-page { min-width: 0; }
.h-page label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; min-width: 0; }
.h-page input, .h-page select, .h-page textarea { min-width: 0; width: 100%; box-sizing: border-box; }
.h-page h2 { font-size: 17px; margin: 0 0 16px; }
.h-filters { display: grid; grid-template-columns: 110px minmax(160px, 1fr) minmax(130px, 190px) auto; gap: 12px; align-items: end; margin-bottom: 22px; }
.h-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; padding: 20px 0; border-block: 1px solid var(--border-color, #d4d4d8); }
.h-metrics div { min-width: 0; }
.h-metrics span { display: block; font-size: 12px; color: var(--text-secondary, #52525b); margin-bottom: 8px; }
.h-metrics strong { display: block; font-size: 21px; overflow-wrap: anywhere; }
.h-section { padding: 24px 0; border-bottom: 1px solid var(--border-color, #d4d4d8); }
.h-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.h-section-head h2 { margin: 0; }
.h-section-head > span, .h-empty { color: var(--text-secondary, #52525b); font-size: 13px; }
.h-legend { display: flex; gap: 20px; font-size: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.h-legend span::before { content: ''; display: inline-block; width: 9px; height: 9px; margin-right: 7px; background: var(--series); }
.h-income { --series: #14b8a6; }
.h-deduction { --series: #e87967; }
.h-net { --series: #a3a33d; }
.h-chart-scroll, .h-table-scroll { overflow-x: auto; max-width: 100%; }
.h-chart { min-width: 540px; padding: 12px 4px; }
.h-plot { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 8px; height: 180px; border-bottom: 1px solid var(--border-color, #d4d4d8); background: repeating-linear-gradient(to top, transparent 0, transparent 44px, var(--border-color, #d4d4d8) 45px); }
.h-bars { height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 3px; position: relative; min-width: 0; }
.h-bar { width: 18px; max-width: 28%; background: var(--series); border-radius: 2px 2px 0 0; }
.h-below-zero { transform: translateY(100%); border-radius: 0 0 2px 2px; }
.h-chart-labels { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 8px; text-align: center; font-size: 11px; margin-top: 10px; }
.h-chart-negative .h-chart-labels { margin-top: 190px; }
.h-compare { grid-template-columns: minmax(130px, 220px) minmax(130px, 220px) auto; justify-content: start; }
.h-page td, .h-page th { font-size: 13px; }
.h-page td:not(:first-child) { white-space: nowrap; }
.h-history { min-width: 880px; }
.h-history small { display: block; margin-top: 4px; color: var(--text-secondary, #52525b); }
.h-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.h-actions form { margin: 0; }
.h-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.h-page .h-check { flex-direction: row; align-items: center; align-self: end; min-height: 40px; }
.h-check input { width: 17px; height: 17px; }
.h-item { display: grid; grid-template-columns: 70px minmax(140px, 1fr) 90px 120px 110px auto; gap: 10px; align-items: end; padding: 12px 0; border-bottom: 1px solid var(--border-color, #d4d4d8); }
.h-item button { min-height: 38px; }
.h-totals { grid-template-columns: repeat(3, minmax(0, 1fr)); border: 0; margin-top: 12px; }
.h-notes { margin-top: 18px; }
.h-error { padding: 12px; border: 1px solid #e87967; border-radius: 4px; font-size: 13px; overflow-wrap: anywhere; }
#holerite-form > .h-actions { padding-top: 22px; }
@media (max-width: 1050px) { .h-item { grid-template-columns: 70px minmax(140px, 1fr) 90px; } .h-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 650px) {
    .h-filters, .h-compare { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .h-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .h-metrics strong { font-size: 18px; }
    .h-form-grid { grid-template-columns: 1fr; }
    .h-item { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .h-item-description { grid-column: 1 / -1; grid-row: 1; }
    .h-page .c-page-header { flex-wrap: wrap; gap: 12px; }
}
</style>
<script>
(() => {
    document.querySelectorAll('[data-holerite-delete]').forEach(form => form.addEventListener('submit', event => {
        if (!confirm('Excluir este holerite e seus itens?')) event.preventDefault();
    }));
    const form = document.getElementById('holerite-form');
    if (!form) return;
    const list = document.getElementById('holerite-items');
    const add = document.getElementById('holerite-add');
    const error = document.getElementById('holerite-item-error');
    let next = list.querySelectorAll('[data-item]').length;
    const prototype = list.firstElementChild.cloneNode(true);
    const cents = raw => {
        let value = raw.trim();
        if (value.includes(',')) {
            if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{1,2}$/.test(value)) return null;
            value = value.replaceAll('.', '').replace(',', '.');
        }
        const match = /^(\d{1,10})(?:\.(\d{1,2}))?$/.exec(value);
        return match ? Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')) : null;
    };
    const money = value => new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'}).format(value / 100);
    const refresh = () => {
        let gross = 0, deductions = 0, invalid = false;
        list.querySelectorAll('[data-item]').forEach(row => {
            const input = row.querySelector('[data-amount]');
            if (input.value.trim() === '') return;
            const value = cents(input.value);
            if (value === null) { invalid = true; return; }
            if (row.querySelector('[data-kind]').value === 'income') gross += value;
            else deductions += value;
        });
        [['gross', gross], ['deductions', deductions], ['net', gross - deductions]].forEach(([key, value]) => {
            document.getElementById('holerite-' + key).textContent = invalid ? '-' : money(value);
        });
        add.disabled = list.children.length >= 100;
    };
    list.addEventListener('input', refresh);
    list.addEventListener('change', refresh);
    list.addEventListener('click', event => {
        if (event.target.closest('[data-remove]')) {
            if (list.children.length > 1) event.target.closest('[data-item]').remove();
            else event.target.closest('[data-item]').querySelectorAll('input').forEach(input => { input.value = ''; });
            refresh();
        }
    });
    add.addEventListener('click', () => {
        if (list.children.length >= 100) return;
        const row = prototype.cloneNode(true);
        row.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace(/items\[\d+\]/, 'items[' + next + ']');
            if (input.tagName === 'INPUT') input.value = '';
            else input.value = 'income';
        });
        next++;
        list.append(row);
        row.querySelector('input[name$="[description]"]').focus();
        refresh();
    });
    form.addEventListener('submit', event => {
        let invalid = false;
        list.querySelectorAll('[data-item]').forEach(row => {
            const description = row.querySelector('input[name$="[description]"]').value.trim();
            const amount = row.querySelector('[data-amount]').value.trim();
            if (description || amount) invalid ||= !description || cents(amount) === null;
        });
        if (invalid) {
            event.preventDefault();
            error.textContent = 'Confira a descricao e o valor dos itens.';
            error.hidden = false;
        } else error.hidden = true;
    });
    refresh();
})();
</script>
