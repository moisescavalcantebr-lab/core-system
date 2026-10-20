const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    for (const [width,height] of [[1280,900],[1024,768],[1194,634],[360,800],[390,667]]) {
      if(process.env.TEAM_VIEWPORTS&&!process.env.TEAM_VIEWPORTS.split(',').includes(String(width)))continue;
      const page = await browser.newPage({ viewport: { width, height } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const count of ['one', 'two', 'renamed', 'crowded', 'fourteen', 'flexible', 'full', 'free', 'thirty', 'full-roster', 'config']) {
        console.log(`Verificando ${count} ${width}x${height}`);
        await page.route('http://team-test.local/**', route => route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(root, `_deploy/team-${count}-preview.html`), 'utf8') }));
        await page.goto(`http://team-test.local/${count}`);
        if (count === 'config') {
          if (await page.locator('[name=time_1_name]').inputValue() !== 'Azul & Branco') throw new Error('Nome salvo ausente');
          if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`Overflow configuracao ${width}`);
          await page.screenshot({ path: path.join(root, `_deploy/team-config-${width}.png`), fullPage: true });
          continue;
        }
        const expected = ['one', 'full', 'free'].includes(count) ? 1 : 2;
        if (count==='free' && await page.locator('[name=second_team], [name=team_key] option[value=time_2]').count()) throw new Error('Gratis oferece segundo time');
        if (await page.locator('.c-team-field').count() !== expected) throw new Error('Quantidade de campos incorreta');
        if (count === 'renamed' && !await page.locator('.c-team-field-card h3').last().innerText().then(text => text.includes('Equipe <Verde>'))) throw new Error('Nome personalizado ausente');
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`Overflow ${width}/${count}`);
        const first = await page.locator('[data-team-key=time_1]').boundingBox();
        const available = await page.locator('.c-team-left-column').boundingBox();
        if (width > 900) {
          if (expected === 2) {
            const second = await page.locator('[data-team-key=time_2]').boundingBox();
            if(first.x+first.width>second.x+1||Math.abs(first.y-second.y)>1||available.y<first.y+first.height-1)throw new Error('Dois campos devem ficar lado a lado, com disponiveis abaixo');
          }else if (first.x + first.width > available.x + 1 || Math.abs(first.y - available.y) > 1) throw new Error('Disponiveis nao estao a direita do Time 1');
        } else {
          if(available!==null)throw new Error('Disponiveis devem usar aba propria no celular');
          if (expected === 2) {
            const second = await page.locator('[data-team-key=time_2]').boundingBox();
            if(second!==null)throw new Error('Celular exibe dois campos simultaneamente');
            await page.locator('[data-team-view=time_2]').click();
            if(!await page.locator('[data-team-key=time_2]').isVisible())throw new Error('Aba do segundo time nao abre');
            await page.locator('[data-team-view=time_1]').click();
          }
        }
        for (const field of await page.locator('.c-team-field:visible').all()) {
          const box = await field.boundingBox();
          if (!box || box.width < 160 || box.height < 220) throw new Error('Campo mal dimensionado');
        }
        await page.locator('[data-team-hide-x]').first().uncheck();
        await page.locator('[data-team-hide-x]').first().check();
        if (!await page.locator('.c-team-page').evaluate(el => el.classList.contains('is-hiding-field-actions'))) throw new Error('Controle de acoes nao funcionou');
        if(width<=900&&['free','full-roster'].includes(count))await page.screenshot({path:path.join(root,`_deploy/team-${count}-field-${width}.png`),fullPage:true});
        if(width<=900)await page.locator('[data-team-view=available]').click();
        if (!await page.getByText('Jogadores disponíveis', { exact: true }).isVisible()) throw new Error('Disponiveis ausentes');
        const avatar = page.locator('[data-available-page]:not([hidden]) summary').first();
        const availableAvatar = await avatar.locator('.c-team-avatar-initial').boundingBox();
        for (const summary of await page.locator('[data-available-page]:not([hidden]) summary').all()) {
          const photo = await summary.locator('.c-team-avatar-initial').boundingBox();
          const name = summary.locator('.c-team-available-name');
          const position = summary.locator('.c-team-available-position');
          if (!await name.isVisible() || !await position.isVisible() || !(await name.innerText()).trim() || !(await position.innerText()).trim()) throw new Error('Nome ou posicao ausente abaixo do disponivel');
          const nameBox = await name.boundingBox();
          const positionBox = await position.boundingBox();
          if (nameBox.y < photo.y + photo.height || positionBox.y < nameBox.y + nameBox.height) throw new Error('Identificacao sobrepoe avatar');
        }
        for (const fieldAvatar of await page.locator('.c-team-field:visible .c-team-token-avatar').all()) {
          const box = await fieldAvatar.boundingBox();
          if (!box || !availableAvatar || Math.abs(box.width - availableAvatar.width) > 0.5 || Math.abs(box.height - availableAvatar.height) > 0.5) throw new Error(`Avatar em campo difere dos disponiveis: ${width}/${count}`);
          const field = await fieldAvatar.locator('xpath=ancestor::*[contains(concat(" ", normalize-space(@class), " "), " c-team-field ")]').boundingBox();
          if (box.x < field.x || box.y < field.y || box.x + box.width > field.x + field.width || box.y + box.height > field.y + field.height) throw new Error('Avatar ultrapassa o campo');
        }
        await avatar.click();
        if (!await page.locator('details[open] .c-team-available-menu').isVisible()) throw new Error('Avatar nao abriu escolha de time');
        const assignment = page.locator('details[open] [data-assignment-form]');
        if (await assignment.locator('[name=update_primary_position]').isChecked()) throw new Error('Atualizacao da principal marcada por padrao');
        async function checkVacancies() {
          const state = await assignment.evaluate(form => {
            const team = form.elements.team_key.value;
            const groups = Array.from(form.elements.field_slot.querySelectorAll('optgroup'));
            const active = groups.find(group => group.dataset.team === team);
            const suggested = Array.from(active.children).find(option => option.dataset.suggested === '1');
            return { invalid: groups.some(group => group.dataset.team !== team && (!group.disabled || !group.hidden)),
              expected: suggested ? suggested.value : '', actual: form.elements.field_slot.value,
              vacancies: active.children.length, disabled: form.querySelector('[data-assignment-submit]').disabled };
          });
          if (state.invalid || state.expected !== state.actual || state.disabled !== (state.vacancies === 0)) throw new Error('Selecao ou sugestao de vagas incorreta');
        }
        await checkVacancies();
        if (expected === 2) {
          await assignment.locator('[name=update_primary_position]').check();
          await assignment.locator('[name=team_key]').selectOption('time_2');
          await checkVacancies();
          if (await assignment.locator('[name=update_primary_position]').isChecked()) throw new Error('Trocar time manteve confirmacao antiga');
        }
        const choices = assignment.locator('optgroup:not([disabled]) option');
        if (count === 'full' && (await choices.count() || !await assignment.locator('[data-assignment-submit]').isDisabled())) throw new Error('Time cheio permitiu escalar');
        if (count === 'flexible') {
          if (await choices.count() !== 1 || await choices.first().getAttribute('value') !== 'atacante:0') throw new Error('Exibiu posicao removida ou vaga ocupada');
          await assignment.locator('[name=field_slot]').selectOption('atacante:0');
          await assignment.locator('[name=update_primary_position]').check();
          const values = await assignment.evaluate(form => Object.fromEntries(new FormData(form)));
          if (values.team_key !== 'time_2' || values.field_slot !== 'atacante:0' || values.update_primary_position !== '1') throw new Error('Formulario nao envia escolha completa');
          const menu = await page.locator('details[open] .c-team-available-menu').boundingBox();
          if (menu.x < 0 || menu.x + menu.width > width) throw new Error('Menu ultrapassa a tela');
          await page.screenshot({ path: path.join(root, `_deploy/team-assignment-${width}.png`), fullPage: true });
        }
        await page.keyboard.press('Escape');
        if (await page.locator('details[open]').count()) throw new Error('Escape nao fechou menu');
        if (count === 'crowded' || count === 'fourteen') {
          const grid = page.locator('[data-available-page="0"]');
            if (!(await grid.locator('summary').count())) throw new Error('Grade sem jogadores');
            const cells = await grid.locator('summary').all();
            const boxes = await Promise.all(cells.map(cell => cell.boundingBox()));
            const photos = await Promise.all(cells.map(cell => cell.locator('.c-team-avatar-initial').boundingBox()));
            const columns=await grid.evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length);
            for (let i = 0; i < boxes.length; i++) {
              if (photos[i].width < 28) throw new Error('Avatar pequeno');
              if (i % columns && photos[i - 1].x + photos[i - 1].width > photos[i].x + 1) throw new Error('Fotos sobrepostas');
              if (i % columns && boxes[i - 1].x + boxes[i - 1].width > boxes[i].x + 1) throw new Error('Avatares sobrepostos');
              if (i >= columns && boxes[i - columns].y + boxes[i - columns].height > boxes[i].y + 1) throw new Error('Nomes ou posicoes sobrepoem outra linha');
            }
        }
        if (count === 'crowded') {
          await page.locator('[data-available-next]').click();
          if (!await page.locator('[data-available-page="1"]').isVisible()) throw new Error('Nao mostrou atletas excedentes');
          let remaining=10;while(!await page.locator('[data-available-next]').isDisabled()&&remaining-->0)await page.locator('[data-available-next]').click();
          if(remaining<=0)throw new Error('Paginacao nao termina');
          while(!await page.locator('[data-available-prev]').isDisabled())await page.locator('[data-available-prev]').click();
          if (!await page.locator('[data-available-page="0"]').isVisible()) throw new Error('Nao retornou primeira pagina');
        }
        if(await page.evaluate(()=>document.documentElement.scrollHeight>innerHeight+2))throw new Error(`Rolagem vertical ${width}/${count}`);
        await page.screenshot({ path: path.join(root, `_deploy/team-${count}-${width}.png`), fullPage: true });
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: campos, disponiveis e controles em desktop, tablet e celular, sem overflow ou erro JS.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
