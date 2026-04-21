<?php

$title = 'UI Playground';

ob_start();
?>

<div class="c-page">

    <!-- HEADER -->
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">UI Playground</h1>
            <p class="c-page-subtitle">Criar e testar componentes</p>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="c-page-content">

        <div class="card">

            <div class="grid grid-2 gap-md mb-10">

                <div>
                    <label>Nome do componente</label>
                    <input id="component-name" class="input" placeholder="Ex: button-primary">
                </div>

                <div>
                    <label>Categoria</label>
                    <input id="component-category" class="input" placeholder="Ex: buttons">
                </div>

            </div>

            <div class="grid grid-2 gap-md">

                <!-- EDITOR -->
                <div>

                    <label>HTML</label>
                    <textarea id="playground-html" class="input-code"></textarea>

                    <label>CSS</label>
                    <textarea id="playground-css" class="input-code"></textarea>

                    <div class="flex gap-md mt-10">

                        <button class="btn btn--primary" onclick="runPlayground()">Executar</button>

                        <button class="btn" onclick="setState('base')">Normal</button>
                        <button class="btn" onclick="setState('hover')">Hover</button>
                        <button class="btn" onclick="setState('active')">Active</button>
                        <button class="btn" onclick="setState('disabled')">Disabled</button>

                        <button class="btn btn--primary" onclick="saveComponent()">Salvar</button>

                    </div>

                </div>

                <!-- PREVIEW -->
                <div>
                    <label>Preview</label>
                    <iframe id="previewFrame" style="width:100%; height:300px; border:1px solid #ddd;"></iframe>
                </div>

            </div>

        </div>

    </div>

</div>

<script>

let currentState = 'base';

/* =========================
UTIL
========================= */

function slugify(text){
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g,'-')
        .replace(/-+/g,'-')
        .replace(/^-|-$/g,'');
}

/* =========================
STATE
========================= */

function setState(state){
    currentState = state;
    runPlayground();
}

/* =========================
CSS STATES
========================= */

function parseStates(cssRaw){

    const states = {
        base: '',
        hover: '',
        active: '',
        disabled: ''
    };

    let current = 'base';

    cssRaw.split('\n').forEach(line => {

        const l = line.toLowerCase();

        if (l.includes('/* hover */')) { current = 'hover'; return; }
        if (l.includes('/* active */')) { current = 'active'; return; }
        if (l.includes('/* disabled */')) { current = 'disabled'; return; }

        states[current] += line + '\n';
    });

    return states;
}

/* =========================
PLAYGROUND
========================= */

function runPlayground(){

    const name = document.getElementById('component-name').value;
    const html = document.getElementById('playground-html').value;
    const cssRaw = document.getElementById('playground-css').value;

    const slug = slugify(name);

    if (!slug) return;

    const states = parseStates(cssRaw);

    const css = `
        .c-${slug} { ${states.base} }
        .c-${slug}.is-hover { ${states.hover} }
        .c-${slug}.is-active { ${states.active} }
        .c-${slug}.is-disabled { ${states.disabled} }
    `;

    const stateClass = currentState !== 'base' ? ' is-' + currentState : '';

    const frame = document.getElementById('previewFrame');
    const doc = frame.contentDocument || frame.contentWindow.document;

    doc.open();
    doc.write(`
        <style>
            body { padding:20px; font-family:sans-serif; }
            ${css}
        </style>

        <div class="c-${slug}${stateClass}">
            ${html}
        </div>
    `);
    doc.close();
}

/* =========================
SAVE
========================= */

function saveComponent(){

    const name = document.getElementById('component-name').value;
    const category = document.getElementById('component-category').value;
    const code = document.getElementById('playground-html').value;
    const css  = document.getElementById('playground-css').value;

    if (!name || !category){
        alert('Preencha nome e categoria');
        return;
    }

    fetch('/admin/ui_registry/save.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ name, category, code, css })
    })
    .then(r => r.text())
    .then(msg => alert(msg || 'Salvo'));
}

</script>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';