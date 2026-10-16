const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  try {
    for (const width of [1280,360]) {
      const page = await browser.newPage({viewport:{width,height:900}});
      const errors = [];
      page.on('pageerror',error=>errors.push(error.message));
      for (const name of ['championship','training','tracking-edit','tracking-index','friendly','friendly-lineup']) {
        await page.route('http://football-test.local/**',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(root,`_deploy/football-${name}-preview.html`),'utf8')}));
        await page.goto(`http://football-test.local/${name}`);
        if (name==='championship') {
          if (!await page.getByText('Pontos do meu time',{exact:true}).isVisible()) throw new Error('Pontos ausentes');
          if (!await page.getByText('Posição oficial',{exact:true}).isVisible()) throw new Error('Posicao ausente');
          const points = page.locator('.c-dashboard-card').filter({has:page.getByText('Pontos do meu time',{exact:true})});
          if (await points.locator('.c-metric').innerText() !== '4') throw new Error('Pontos do campeonato incorretos');
        }
        if (name==='tracking-edit' && await page.locator('[name=official_position]').inputValue()!=='4') throw new Error('Posicao nao recuperada');
        if (name==='friendly' || name==='friendly-lineup') {
          const selector = name==='friendly' ? '.c-match-field-player' : '.c-lineup-token';
          const attacker = page.locator(selector).filter({has:page.getByText('Atleta 2',{exact:true})});
          if (!await attacker.isVisible() || await attacker.locator('small').innerText()!=='CA') throw new Error('Posicao escalada nao aparece no amistoso');
          if (!await attacker.locator('img, span').first().isVisible()) throw new Error('Avatar do amistoso ausente');
        }
        if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw new Error(`Overflow ${name}/${width}`);
        await page.screenshot({path:path.join(root,`_deploy/football-${name}-${width}.png`),fullPage:true});
      }
      if (errors.length) throw new Error(errors.join('\n'));
      await page.close();
    }
    console.log('PASS: campeonato, treino e posicao oficial em desktop/celular, sem overflow ou erros JS.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
