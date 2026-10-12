const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const browser = await chromium.launch({channel: 'msedge', headless: true});
  try {
    for (const width of [1280, 360]) {
      const page = await browser.newPage({viewport: {width, height: 900}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const name of ['form', 'index', 'detail', 'conversation', 'core', 'core-detail']) {
        await page.setContent(fs.readFileSync(path.join(root, `_deploy/traffic-${name}-preview.html`), 'utf8'));
        if (name === 'form') {
          if (!await page.locator('[data-guidance-field]').isVisible()) throw new Error('Campo de duvida oculto em orientacao');
          await page.locator('select[name=platform]').selectOption('meta');
          if (await page.locator('[data-guidance-field]').isVisible()) throw new Error('Campo de duvida visivel sem orientacao');
          await page.locator('select[name=platform]').selectOption('recommend');
          if (!await page.locator('[data-guidance-field]').isVisible()) throw new Error('Campo de duvida nao reapareceu');
          if (await page.locator('[data-page-field]').isVisible()) throw new Error('Seletor de pagina visivel para destino externo');
          await page.locator('#traffic-type').selectOption('page');
          if (!await page.locator('[data-page-field]').isVisible() || await page.locator('[data-url-field]').isVisible()) throw new Error('Alternancia de destino falhou');
          await page.locator('#traffic-type').selectOption('whatsapp');
          if (!await page.locator('[data-url-field]').isVisible()) throw new Error('Link do WhatsApp oculto');
          const style = await page.locator('input[name=title]').evaluate(el => ({height: el.getBoundingClientRect().height, bg: getComputedStyle(el).backgroundColor}));
          if (style.height < 35 || style.bg === 'rgb(255, 255, 255)') throw new Error('Campo sem estilo');
        }
        if (name === 'core-detail') {
          if (!await page.locator('button[value=close_conversation]').isVisible() || !await page.locator('button[value=prepared]').isDisabled()) throw new Error('Conversa aberta nao bloqueou preparacao');
          await page.locator('#traffic-message-template').selectOption('reviewing');
          const message = page.locator('textarea[name=message]');
          if (!await message.inputValue() || !await page.locator('button[value=reply]').isVisible()) throw new Error('Modelo ou resposta indisponivel');
          await message.fill('Texto personalizado');
          page.once('dialog', dialog => dialog.dismiss());
          await page.locator('#traffic-message-template').selectOption('received');
          if (await message.inputValue() !== 'Texto personalizado') throw new Error('Modelo sobrescreveu resposta sem confirmacao');
        }
        if (name === 'conversation' && !await page.locator('textarea[name=message]').isVisible()) throw new Error('Cliente sem campo de resposta');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        if (overflow) throw new Error(`Overflow em ${name} / ${width}`);
        await page.screenshot({path: path.join(root, `_deploy/traffic-${name}-${width}.png`), fullPage: true});
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: desktop/mobile, formularios, alternancia de destino, tabelas e ausencia de erros JS.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
