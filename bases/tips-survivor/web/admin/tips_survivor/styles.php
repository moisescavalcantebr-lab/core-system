<style>
.tips-tabs {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.tips-tabs .is-active {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, .16);
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.tips-card {
    border: 1px solid var(--border);
    background: rgba(15, 23, 42, .48);
    padding: 16px;
    min-height: 128px;
}

.tips-card span,
.tips-card small {
    color: var(--muted);
}

.tips-card strong {
    display: block;
    margin-top: 8px;
    font-size: 26px;
    color: var(--text);
}

.tips-panel-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr);
    gap: 12px;
}

.tips-mini-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.tips-mini-grid > div {
    border: 1px solid var(--border);
    padding: 10px;
}

.tips-mini-grid span {
    display: block;
    color: var(--muted);
    font-size: 12px;
}

.tips-mini-grid strong {
    display: block;
    margin-top: 4px;
}

.tips-competition-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.tips-competition-card {
    display: grid;
    gap: 14px;
    align-content: space-between;
    min-height: 260px;
    border: 1px solid var(--border);
    padding: 16px;
}

.tips-competition-card h4 {
    margin: 12px 0 6px;
    font-size: 20px;
}

.tips-competition-card p {
    color: var(--muted);
}

.tips-rules-list {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tips-rules-list li {
    border: 1px solid var(--border);
    padding: 10px;
    color: var(--muted);
}

.tips-form {
    display: grid;
    gap: 14px;
}

.tips-form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.tips-form label {
    display: grid;
    gap: 6px;
    color: var(--muted);
    font-size: 13px;
}

.tips-form input,
.tips-form select,
.tips-form textarea {
    width: 100%;
}

.tips-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.tips-actions form {
    margin: 0;
}

.tips-inline-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    margin: 0;
}

.tips-inline-form input[type="number"] {
    width: 64px;
}

.tips-inline-form select {
    width: auto;
    min-width: 128px;
}

@media (max-width: 980px) {
    .tips-grid,
    .tips-panel-grid,
    .tips-form-grid,
    .tips-mini-grid,
    .tips-competition-cards {
        grid-template-columns: 1fr;
    }

    .tips-tabs {
        justify-content: flex-start;
    }

    .tips-actions,
    .tips-inline-form {
        justify-content: flex-start;
    }
}
</style>
