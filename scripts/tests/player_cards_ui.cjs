const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'../..');
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});
  try {
    for (const theme of ['dark','sunset','light']) {
      for (const width of [1440,1024,640,360,320]) {
        const page=await browser.newPage({viewport:{width,height:900},isMobile:width<641,hasTouch:width<641});
        const errors=[];page.on('pageerror',e=>errors.push(e.message));
        await page.route('http://cards-test.local/**',route=>{
          if (route.request().url().endsWith('card-photo.svg')) return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="#375b49"/><text x="40" y="48" text-anchor="middle" fill="white" font-size="24">JD</text></svg>'});
          return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(root,'_deploy/player-cards-preview.html'),'utf8')});
        });
        await page.goto('http://cards-test.local/index.php');
        await page.locator('#card-test-theme').evaluate((el,css)=>el.textContent=css,fs.readFileSync(path.join(root,`bases/futebol/web/assets/css/themes/${theme}.css`),'utf8'));
        const cards=page.locator('.c-player-row-card');
        if(await cards.count()!==14)throw Error('Cards ausentes');
        if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${theme}/${width}`);
        const photo=page.locator('.c-player-row-card[data-player-id="1"] img');
        if(!await photo.evaluate(el=>el.complete&&el.naturalWidth>0))throw Error('Foto nao carregou');
        if(await cards.locator('[data-ranking-state=pending]').count()!==14)throw Error('Ranking ausente');
        for (const card of await cards.all()) {
          const bounds=await card.boundingBox();
          for(const child of await card.locator('h2,.c-player-card-avatar,.c-player-participation,.c-player-formation').all()){
            const b=await child.boundingBox();
            if(b.x<bounds.x-1||b.x+b.width>bounds.x+bounds.width+1)throw Error(`Conteudo fora do card ${width}`);
          }
        }
        await cards.first().locator('summary').click();
        if(!await cards.first().locator('.c-player-card-menu').isVisible())throw Error('Menu nao abriu');
        await cards.nth(1).locator('summary').click();
        await page.waitForTimeout(80);
        if(await page.locator('details[open]').count()!==1)throw Error('Menus simultaneos');
        await page.keyboard.press('Escape');
        if(await page.locator('details[open]').count())throw Error('Escape nao fechou menu');
        await cards.first().locator('summary').click();
        page.once('dialog',dialog=>dialog.dismiss());
        await cards.first().locator('button[type=submit]').click();
        if(!page.url().endsWith('index.php'))throw Error('Cancelamento nao bloqueou desativacao');
        await page.keyboard.press('Escape');
        if(width<=640){
          const a=await cards.nth(0).boundingBox(),b=await cards.nth(1).boundingBox(),c=await cards.nth(2).boundingBox();
          if(Math.abs(a.y-b.y)>1||c.y<=a.y)throw Error('Celular nao tem duas colunas');
        }
        await page.screenshot({path:path.join(root,`_deploy/player-cards-${theme}-${width}.png`),fullPage:true});
        if(errors.length)throw Error(errors.join('\n'));
        await page.close();
      }
    }
    console.log('PASS: cards desktop/celular, temas escuro/claro/sunset, foto, menu, confirmacao e ranking reservado, sem overflow ou erros JS.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
