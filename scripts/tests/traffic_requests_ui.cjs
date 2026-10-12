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
      for (const name of ['form', 'index', 'detail', 'conversation', 'core', 'core-detail', 'core-operation-ready', 'core-operation-active', 'client-operation', 'client-approval', 'core-approval', 'client-adjustments', 'report-partial', 'report-final', 'client-delivery', 'core-delivery']) {
        await page.goto('about:blank');
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
        if (name === 'core-operation-ready' && !await page.locator('input[name=external_campaign_id]').isVisible()) throw new Error('Planejamento sem identificacao externa');
        if (name === 'core-operation-active') {
          if (!await page.locator('input[name=report_date]').isVisible() || await page.locator('input[name=external_campaign_id]').count()) throw new Error('Controles de operacao incorretos');
          if (await page.locator('input[name=link_clicks]').inputValue() !== '45') throw new Error('Edicao nao carregou resultados existentes');
        }
        if (name === 'client-operation' && await page.locator('input[name=report_date]').count()) throw new Error('Cliente recebeu editor de resultados');
        if (name === 'client-approval') {
          if (!await page.locator('button[value=approve]').isVisible() || !await page.locator('button[value=request_changes]').isVisible()) throw new Error('Decisoes do cliente ausentes');
          if (!await page.locator('input[name=approval_confirmed]').evaluate(el => el.required)) throw new Error('Confirmacao nao obrigatoria');
          if (!await page.locator('button[value=request_changes]').evaluate(el => el.form.querySelector('textarea[name=message]').required)) throw new Error('Motivo de ajustes nao obrigatorio');
        }
        if (name === 'core-approval' && !await page.getByRole('button', {name: 'Registrar etapa', exact: true}).isDisabled()) throw new Error('Studio avancou sem aprovacao');
        if (name === 'client-adjustments' && await page.locator('button[value=approve]').count()) throw new Error('Decisao encerrada permaneceu editavel');
        if (name.startsWith('report-')) {
          if (!await page.getByRole('link', {name:'Exportar resultados CSV',exact:true}).isVisible() || !await page.getByRole('button', {name:'Imprimir relatorio',exact:true}).isVisible()) throw new Error('Relatorio sem exportacao');
          if (!await page.getByRole('heading', {name:'Cobertura dos dados',exact:true}).isVisible()) throw new Error('Cobertura ausente');
          await page.emulateMedia({media:'print'});
          if (await page.locator('.toolbar').isVisible()) throw new Error('Controles aparecem na impressao');
          if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error('Relatorio corta colunas na impressao');
          if (name === 'report-final') await page.screenshot({path:path.join(root, `_deploy/traffic-report-final-print-${width}.png`),fullPage:true});
          await page.emulateMedia({media:'screen'});
        }
        if (name === 'core-delivery' && await page.locator('input[name=report_date]').count()) throw new Error('Entrega final recebeu editor');
        if (name.endsWith('operation-active') || name === 'client-operation') {
          if (!await page.locator('.traffic-chart-bar').isVisible()) throw new Error('Grafico de resultados vazio');
        }
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
