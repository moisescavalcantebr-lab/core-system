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
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   await page.setContent(fs.readFileSync(path.join(root,'_deploy/football-finance-charges-preview.html'),'utf8'));
   await page.locator('#teamNewCharge summary').click();
   assert.equal(await page.locator('[name=beneficiary_player_id]').inputValue(),'');
   assert(await page.locator('[name=beneficiary_player_id]').evaluate(e=>e.required));
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   await page.setContent(fs.readFileSync(path.join(root,'_deploy/football-finance-charge-detail-preview.html'),'utf8'));
   assert.equal(await page.locator('#chargePartyKind').inputValue(),'');
   assert.equal(await page.locator('#teamChargePayment [name=amount]').getAttribute('max'),'70.00');
   await page.locator('#teamChargePayment [name=amount]').fill('70.01');
   assert(await page.locator('#teamChargePayment [name=amount]').evaluate(e=>e.validity.rangeOverflow));
   await page.locator('#teamChargePayment [name=amount]').fill('30.00');
   await page.locator('#chargePartyKind').selectOption('player');
   assert.equal(await page.locator('#chargePayer').inputValue(),'');assert(await page.locator('#chargePayer').evaluate(e=>e.required));
   await page.locator('#chargePartyKind').selectOption('external');assert.equal(await page.locator('#chargePayer').isEnabled(),false);
   assert(await page.locator('#chargeExternalPayer').evaluate(e=>e.required));
   assert.equal(await page.locator('#teamChargePayment [name=request_key]').inputValue().then(key=>/^[a-f0-9]{64}$/.test(key)),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);assert.deepEqual(errors,[]);
   await page.screenshot({path:path.join(root,`_deploy/football-finance-charge-${width}.png`),fullPage:true});await page.close();
  }
  console.log('PASS: formularios sem selecao implicita, cobranca/recebimento, pagador distinto, limite do saldo, datas e telas desktop/celular.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
