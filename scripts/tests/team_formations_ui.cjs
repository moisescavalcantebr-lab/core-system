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
      for (const count of ['one', 'two']) {
        await page.route('http://team-test.local/**', route => route.fulfill({ contentType: 'text/html', body: fs.readFileSync(path.join(root, `_deploy/team-${count}-preview.html`), 'utf8') }));
        await page.goto(`http://team-test.local/${count}`);
        const expected = count === 'one' ? 1 : 2;
        if (await page.locator('.c-team-field').count() !== expected) throw new Error('Quantidade de campos incorreta');
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error(`Overflow ${width}/${count}`);
        for (const field of await page.locator('.c-team-field').all()) {
          const box = await field.boundingBox();
          if (!box || box.width < 200 || box.height < 250) throw new Error('Campo mal dimensionado');
        }
        await page.locator('[data-team-hide-x]').first().uncheck();
        await page.locator('[data-team-hide-x]').first().check();
        if (!await page.locator('.c-team-page').evaluate(el => el.classList.contains('is-hiding-field-actions'))) throw new Error('Controle de acoes nao funcionou');
        if (!await page.getByText('Jogadores disponíveis', { exact: true }).isVisible()) throw new Error('Disponiveis ausentes');
        await page.screenshot({ path: path.join(root, `_deploy/team-${count}-${width}.png`), fullPage: true });
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: campos, disponiveis e controles em desktop, tablet e celular, sem overflow ou erro JS.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
