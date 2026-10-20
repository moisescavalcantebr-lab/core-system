const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const script=fs.readFileSync(path.join(root,'modules/meu_time/web/admin/meu_time/appearance.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
  const fixture=fs.readFileSync(path.join(root,'_deploy/team-one-preview.html'),'utf8').replace(/<script>\s*document\.addEventListener\('DOMContentLoaded',\(\)=>\{[\s\S]*?<\/script>/,'<script>'+script+'</script>');
  for(const delayed of [false,true]){
   const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('http://avatars.test/**',async route=>{
    if(route.request().url().endsWith('/missing.png')){if(delayed)await new Promise(r=>setTimeout(r,150));return route.fulfill({status:404,body:''});}
    if(route.request().url().endsWith('/photo.png'))return route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aT1kAAAAASUVORK5CYII=','base64')});
    return route.fulfill({contentType:'text/html',body:fixture.replace('<div class="c-page c-team-page">','<div class="c-page c-team-page"><img class="test-missing c-team-token-avatar" src="/missing.png" alt="Atleta Teste" data-team-avatar-initials="AT"><img class="test-photo c-team-token-avatar" src="/photo.png" alt="Foto válida" data-team-avatar-initials="FV">')});
   });
   await page.goto('http://avatars.test/');
   await page.locator('span.test-missing').waitFor();
   assert.equal(await page.locator('span.test-missing').textContent(),'AT');
   assert.equal(await page.locator('span.test-missing').getAttribute('aria-label'),'Atleta Teste');
   assert(await page.locator('img.test-photo').evaluate(image=>image.complete&&image.naturalWidth>0));
   assert.deepEqual(errors,[]);await page.close();
  }
  console.log('PASS: fotos válidas preservadas; fotos ausentes substituídas por iniciais antes e depois de DOMContentLoaded.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
