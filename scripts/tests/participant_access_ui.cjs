const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  try {
    for (const width of [1280,360]) {
      const page = await browser.newPage({viewport:{width,height:900}});
      const errors=[];
      page.on('pageerror',error=>errors.push(error.message));
      for (const name of ['optional-create','optional-edit','optional-linked','optional-index','optional-error','required-create','required-edit']) {
        await page.goto('about:blank');
        await page.setContent(fs.readFileSync(path.join(root,`_deploy/participants-${name}-preview.html`),'utf8'));
        const checkbox=page.locator('#participant-create-access');
        const fields=page.locator('#participant-access-fields');
        if (name==='optional-create'||name==='optional-edit') {
          if (!await checkbox.isVisible()||await fields.isVisible()) throw new Error('Acesso opcional nao inicia desmarcado');
          if (!await page.locator('input[name=email]').isDisabled()) throw new Error('Campos ocultos nao foram desabilitados');
          await checkbox.check();
          if (!await fields.isVisible()||await page.locator('input[name=email]').isDisabled()) throw new Error('Nao permitiu optar por acesso');
          if (!await page.locator('input[name=password]').evaluate(el=>el.required)) throw new Error('Acesso novo sem senha obrigatoria');
          await checkbox.uncheck();
          if (await fields.isVisible()||!await page.locator('input[name=password]').isDisabled()) throw new Error('Nao voltou ao cadastro sem acesso');
        }
        if (name==='optional-linked') {
          if (await checkbox.count()||!await fields.isVisible()) throw new Error('Permitiu desvincular acesso existente');
          if (await page.locator('input[name=password]').evaluate(el=>el.required)) throw new Error('Exigiu nova senha para conta existente');
          if (!await page.getByText('Acesso bloqueado',{exact:true}).isVisible()) throw new Error('Estado de acesso nao aparece separado');
          if (await page.locator('select[name=access_status]').inputValue()!=='blocked') throw new Error('Editor nao preservou estado de acesso');
        }
        if (name.startsWith('required-')) {
          if (await checkbox.count()||!await fields.isVisible()) throw new Error('Outra base mudou para acesso opcional');
          if (name==='required-create'&&!await page.locator('input[name=password]').evaluate(el=>el.required)) throw new Error('Cadastro obrigatorio sem senha');
        }
        if (name==='optional-error'&&(!await fields.isVisible()||await page.locator('input[name=password]').inputValue())) throw new Error('Recuperacao de formulario insegura');
        if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw new Error(`Overflow: ${name}/${width}`);
        await page.screenshot({path:path.join(root,`_deploy/participants-${name}-${width}.png`),fullPage:true});
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: Participantes desktop/mobile, acesso opcional, obrigatoriedade, preservacao e ausencia de erros JS.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
