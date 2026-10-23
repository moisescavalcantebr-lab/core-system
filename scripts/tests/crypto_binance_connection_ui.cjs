const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {request,chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const f=JSON.parse(fs.readFileSync(path.join(root,'_deploy/crypto-local-test.json'),'utf8'));
 const base='https://localhost:8443/projects/'+f.slug+'/web/admin/crypto_binance';
 const options=session=>({ignoreHTTPSErrors:true,storageState:{cookies:[{name:'PHPSESSID',value:session,domain:'localhost',path:'/',secure:true,httpOnly:true,sameSite:'Lax',expires:-1}],origins:[]}});
 const api=await request.newContext(options(f.session)),client=await request.newContext(options(f.client_session)),guest=await request.newContext({ignoreHTTPSErrors:true});
 const cross=await request.newContext(options(f.cross_session)),inactive=await request.newContext(options(f.inactive_session));
 const key='k'.repeat(40),secret='s'.repeat(40);let browser;
 const post=form=>api.post(base+'/connection.php',{form:{_csrf:f.csrf,...form},maxRedirects:0});
 const saved=async value=>{const r=await api.get(base+'/index.php');assert.equal(r.status(),200);const h=await r.text();assert(h.includes('data-saved="'+value+'"'));assert(!h.includes(key)&&!h.includes(secret));assert(!/Warning:|Fatal error:/.test(h));return h};
 try{
  assert.equal((await guest.post(base+'/connection.php',{form:{action:'disconnect'},maxRedirects:0})).status(),302);
  for(const route of ['index.php','connection.php','read.php'])assert.equal((await client.get(base+'/'+route,{maxRedirects:0})).status(),403,'client '+route);
  assert.equal((await api.get(base+'/connection.php')).status(),405);
  assert.equal((await cross.get(base+'/index.php',{maxRedirects:0})).status(),302,'cross-project');
  assert.equal((await inactive.get(base+'/index.php',{maxRedirects:0})).status(),403,'inactive-admin');
  assert.equal((await post({action:'save',api_key:key,api_secret:secret,read_only:'1',_csrf:'wrong'})).status(),403);
  assert.equal((await post({action:'save',api_key:key,api_secret:secret})).status(),303);await saved('0');
  assert.equal((await post({action:'save',api_key:key,api_secret:secret,read_only:'1'})).status(),303);await saved('1');
  assert.equal((await post({action:'save',api_key:'bad',api_secret:secret,read_only:'1'})).status(),303);await saved('1');
  const pageHtml=await saved('1');assert(!pageHtml.includes('name="api_key" value='));assert(!pageHtml.includes('name="api_secret" value='));
  const invalid=await api.post(base+'/read.php',{form:{_csrf:f.csrf,mode:'account',symbols:'../'},maxRedirects:0});assert.equal(invalid.status(),303);await saved('1');
  assert.equal((await post({action:'disconnect'})).status(),303);await saved('1');
  browser=await chromium.launch({channel:'msedge',headless:true});const context=await browser.newContext({ignoreHTTPSErrors:true,storageState:await api.storageState()});
  for(const width of [1280,390]){
   const page=await context.newPage();await page.setViewportSize({width,height:850});await page.goto(base+'/index.php');await page.locator('#cb-connection summary').click();
   assert.equal(await page.locator('#cb-key').inputValue(),'');assert.equal(await page.locator('#cb-secret').inputValue(),'');assert.equal(await page.locator('button[value="account"]').isVisible(),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   await page.screenshot({path:path.join(root,'_deploy/crypto-connection-'+width+'.png'),fullPage:true});await page.close();
  }
  const page=await context.newPage();await page.goto(base+'/index.php');page.once('dialog',d=>d.dismiss());await page.getByRole('button',{name:'Desconectar',exact:true}).click();assert.equal(await page.locator('#cb-connection').getAttribute('data-saved'),'1');
  page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'Desconectar',exact:true}).click();await page.waitForLoadState();await page.reload();assert.equal(await page.locator('#cb-connection').getAttribute('data-saved'),'0');await page.close();
  await saved('0');
  console.log('PASS: salvar/recarregar/substituir, CSRF e CLIENT bloqueados, GET recusado, segredos ausentes, desktop/celular, confirmacao de desconexao e historico preservado.');
 }finally{if(browser)await browser.close();await api.dispose();await client.dispose();await guest.dispose();await cross.dispose();await inactive.dispose()}
})().catch(e=>{console.error(e.message);process.exitCode=1});
