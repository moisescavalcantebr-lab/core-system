const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  for(const width of [1280,390]){
   const page=await browser.newPage({viewport:{width,height:850}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.setContent(fs.readFileSync(path.join(root,'_deploy/football-finance-form-preview.html'),'utf8'));
   assert.equal(await page.locator('#financeType').inputValue(),'');assert.equal(await page.locator('#teamPartyKind').inputValue(),'');
   assert.equal(await page.locator('#teamPartyKind option').count(),3);
   assert.equal(await page.locator('#teamPlayer').isVisible(),false);
   await page.locator('#teamPartyKind').selectOption('player');
   assert.equal(await page.locator('#teamPlayer').isVisible(),true);assert(await page.locator('#teamPlayer').evaluate(e=>e.required));
   assert((await page.locator('#teamPlayer').innerText()).includes('Jogador <dois>'));
   await page.locator('#teamPlayer').selectOption('1');
   await page.locator('#teamPartyKind').selectOption('external');assert.equal(await page.locator('#teamPlayer').isEnabled(),false);assert(await page.locator('#teamExternal').evaluate(e=>e.required));
   await page.locator('#financeType').selectOption('expense');assert.equal(await page.locator('#teamPartyHeading').innerText(),'Quem recebe?');
   assert(await page.locator('[name=due_date]').evaluate(e=>e.required));
   await page.locator('[name=status]').selectOption('paid');assert(await page.locator('[name=paid_at]').evaluate(e=>e.required));assert.equal(await page.locator('[name=due_date]').evaluate(e=>e.required),false);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   assert.deepEqual(errors,[]);await page.screenshot({path:path.join(root,`_deploy/football-finance-form-${width}.png`),fullPage:true});
   await page.setContent(fs.readFileSync(path.join(root,'_deploy/football-finance-history-preview.html'),'utf8'));
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);await page.close();
  }
  console.log('PASS: formulario sem selecao implicita, jogador/identificacao externa, datas obrigatorias e tela adaptada a desktop/celular.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
