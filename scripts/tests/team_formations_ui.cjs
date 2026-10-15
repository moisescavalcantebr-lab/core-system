const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    for (const width of [1280, 1024, 360]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const count of ['one', 'two', 'renamed', 'crowded', 'config']) {
        await page.route('http://team-test.local/**', route => route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(root, `_deploy/team-${count}-preview.html`), 'utf8') }));
        await page.goto(`http://team-test.local/${count}`);
        if (count === 'config') {
          if (await page.locator('[name=time_1_name]').inputValue() !== 'Azul & Branco') throw new Error('Nome salvo ausente');
          if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`Overflow configuracao ${width}`);
          await page.screenshot({ path: path.join(root, `_deploy/team-config-${width}.png`), fullPage: true });
          continue;
        }
        const expected = count === 'one' ? 1 : 2;
        if (await page.locator('.c-team-field').count() !== expected) throw new Error('Quantidade de campos incorreta');
        if (count === 'renamed' && !await page.locator('.c-team-field-card h3').last().innerText().then(text => text.includes('Equipe <Verde>'))) throw new Error('Nome personalizado ausente');
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`Overflow ${width}/${count}`);
        const first = await page.locator('[data-team-key=time_1]').boundingBox();
        const available = await page.locator('.c-team-left-column').boundingBox();
        if (width > 900) {
          if (first.x + first.width > available.x + 1 || Math.abs(first.y - available.y) > 1) throw new Error('Disponiveis nao estao a direita do Time 1');
          if (expected === 2) {
            const second = await page.locator('[data-team-key=time_2]').boundingBox();
            if (available.x + available.width > second.x + 1 || Math.abs(second.y - available.y) > 1) throw new Error('Disponiveis nao estao entre os times');
          }
        } else {
          if (first.y + first.height > available.y + 1) throw new Error('Blocos nao empilhados no celular');
          if (expected === 2) {
            const second = await page.locator('[data-team-key=time_2]').boundingBox();
            if (available.y + available.height > second.y + 1) throw new Error('Ordem dos blocos incorreta no celular');
          }
        }
        for (const field of await page.locator('.c-team-field').all()) {
          const box = await field.boundingBox();
          if (!box || box.width < 200 || box.height < 250) throw new Error('Campo mal dimensionado');
        }
        await page.locator('[data-team-hide-x]').first().uncheck();
        await page.locator('[data-team-hide-x]').first().check();
        if (!await page.locator('.c-team-page').evaluate(el => el.classList.contains('is-hiding-field-actions'))) throw new Error('Controle de acoes nao funcionou');
        if (!await page.getByText('Jogadores disponíveis', { exact: true }).isVisible()) throw new Error('Disponiveis ausentes');
        const avatar = page.locator('[data-available-page]:not([hidden]) summary').first();
        await avatar.click();
        if (!await page.locator('details[open] .c-team-available-menu').isVisible()) throw new Error('Avatar nao abriu escolha de time');
        await page.keyboard.press('Escape');
        if (await page.locator('details[open]').count()) throw new Error('Escape nao fechou menu');
        if (width > 900) {
          const field = await page.locator('.c-team-field').first().boundingBox();
          const stage = await page.locator('.c-team-available-stage').boundingBox();
          if (Math.abs(field.y - stage.y) > 4 || Math.abs(field.y + field.height - stage.y - stage.height) > 4) throw new Error('Avatares nao alinham com altura do campo');
        }
        if (count === 'crowded') {
          const sides = page.locator('[data-available-page="0"] .c-team-available-side');
          for (const side of await sides.all()) {
            if (await side.locator('summary').count() !== 15) throw new Error('Grupo nao tem quinze avatares');
            const cells = await side.locator('summary').all();
            const boxes = await Promise.all(cells.map(cell => cell.boundingBox()));
            if (Math.abs(boxes[0].y - boxes[2].y) > 1 || boxes[3].y <= boxes[0].y) throw new Error('Grade nao tem tres por linha');
          }
          await page.locator('[data-available-next]').click();
          if (!await page.locator('[data-available-page="1"]').isVisible()) throw new Error('Nao mostrou atletas excedentes');
          if (!await page.locator('[data-available-next]').isDisabled()) throw new Error('Paginacao ultrapassa ultima pagina');
          await page.locator('[data-available-prev]').click();
          if (!await page.locator('[data-available-page="0"]').isVisible()) throw new Error('Nao retornou primeira pagina');
        }
        await page.screenshot({ path: path.join(root, `_deploy/team-${count}-${width}.png`), fullPage: true });
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: campos, disponiveis e controles em desktop, tablet e celular, sem overflow ou erro JS.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
