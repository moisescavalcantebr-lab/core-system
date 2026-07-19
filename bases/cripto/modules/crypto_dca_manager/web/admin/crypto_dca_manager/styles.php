<style>
.crypto-actions,
.crypto-section-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.crypto-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.crypto-card,
.crypto-panel {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.crypto-card span,
.crypto-card small,
.crypto-read span,
.crypto-read small {
    display: block;
    color: var(--text-secondary);
}

.crypto-card strong {
    display: block;
    margin: 6px 0;
    font-size: 22px;
}

.crypto-dashboard-grid,
.crypto-two-col {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, .55fr);
    gap: 12px;
}

.crypto-read {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.crypto-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.crypto-form-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.crypto-inline-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.crypto-inline-actions form {
    margin: 0;
}

.crypto-inline-review {
    display: grid;
    grid-template-columns: 120px minmax(160px, 220px) minmax(180px, 1fr) auto;
    gap: 8px;
    align-items: end;
}

.crypto-inline-review input,
.crypto-inline-review select {
    width: 100%;
}

@media (max-width: 900px) {
    .crypto-metrics,
    .crypto-dashboard-grid,
    .crypto-two-col,
    .crypto-form-grid,
    .crypto-form-grid.three,
    .crypto-read,
    .crypto-inline-review {
        grid-template-columns: 1fr;
    }
}
</style>
