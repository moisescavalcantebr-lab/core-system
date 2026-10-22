const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium,request}=require('playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const fixture=JSON.parse(fs.readFileSync(path.join(root,'_deploy/crypto-local-test.json'),'utf8'));
 const base='https://localhost:8443/projects/'+fixture.slug+'/web';
 const api=await request.newContext({ignoreHTTPSErrors:true,storageState:{cookies:[{name:'PHPSESSID',value:fixture.session,domain:'localhost',path:'/',secure:true,httpOnly:true,sameSite:'Lax',expires:-1}],origins:[]}});
 const guest=await request.newContext({ignoreHTTPSErrors:true});
 let browser;
 try {
  const denied=await guest.get(base+'/admin/crypto_binance/index.php',{maxRedirects:0});assert.equal(denied.status(),302);assert.match(denied.headers().location,/login|create-password/);
  const noGrant=await guest.get(base+'/create-password.php');assert.equal(noGrant.status(),400,'anonymous-setup');
  const first=await api.get(base+'/');assert.equal(first.status(),200);assert((await first.text()).includes('Criar acesso local'));
  const badSetup=await api.post(base+'/create-password.php',{form:{_csrf:'wrong',password:'synthetic-password',confirm_password:'synthetic-password'},maxRedirects:0});assert.equal(badSetup.status(),403,'setup-csrf');
  const password=require('node:crypto').randomBytes(24).toString('base64url');
  const setup=await api.post(base+'/create-password.php',{form:{_csrf:fixture.csrf,password,confirm_password:password}});assert.equal(setup.status(),200,'setup');assert((await setup.text()).includes('Binance - leitura'));
  const second=await api.get(base+'/create-password.php');assert.equal(second.status(),400,'setup-existing-admin');
  const wrong=await api.post(base+'/admin/crypto_binance/read.php',{form:{mode:'demo',_csrf:'wrong'},maxRedirects:0});assert.equal(wrong.status(),403,'csrf');
  const get=await api.get(base+'/admin/crypto_binance/read.php');assert.equal(get.status(),405);
  const remote=await api.get(base+'/admin/crypto_binance/index.php',{headers:{Host:'not-local.example'},maxRedirects:0});assert.equal(remote.status(),403,'local-only');
  const secretValue='synthetic-invalid-secret';
  const invalid=await api.post(base+'/admin/crypto_binance/read.php',{form:{mode:'account',symbols:'BTCUSDT',api_key:'invalid',api_secret:secretValue,read_only:'1',_csrf:fixture.csrf}});
  const invalidHtml=await invalid.text();assert(!invalidHtml.includes(secretValue));assert(!invalidHtml.includes('name="api_secret" value='));
  const result=await api.post(base+'/admin/crypto_binance/read.php',{form:{mode:'demo',_csrf:fixture.csrf}});assert.equal(result.status(),200);
  const html=await result.text();assert(html.includes('Demonstracao - valores ficticios'));assert(html.includes('Nao validado'));assert(!html.includes('Warning:'));assert(!html.includes('Fatal error'));
  const blocked=await api.get('https://localhost:8443/projects/'+fixture.slug+'/app/config/database.php');assert.equal(blocked.status(),403,'private-config');
  browser=await chromium.launch({channel:'msedge',headless:true});
  const state=await api.storageState();
  const context=await browser.newContext({ignoreHTTPSErrors:true,storageState:state});
  for(const width of [1280,390]) {
   const page=await context.newPage({viewport:{width,height:850}});await page.setViewportSize({width,height:850});
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto(base+'/admin/crypto_binance/index.php');
   assert.equal(await page.locator('h1').innerText(),'Binance - leitura');
   await page.locator('summary').click();
   assert.equal(await page.locator('#cb-secret').inputValue(),'');
   assert.equal(await page.locator('#cb-key').getAttribute('type'),'password');
   assert.equal(await page.locator('button[value="account"]').isVisible(),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false,'Layout excedeu '+width);
   await page.screenshot({path:path.join(root,'_deploy/crypto-binance-'+width+'.png'),fullPage:true});
   assert.deepEqual(errors,[]);await page.close();
  }
  const cookies=(await api.storageState()).cookies;fixture.session=cookies.find(c=>c.name==='PHPSESSID').value;fs.writeFileSync(path.join(root,'_deploy/crypto-local-test.json'),JSON.stringify(fixture));
  console.log('PASS: primeiro acesso local sem email, bloqueio anonimo e de redefinicao; rotas autenticadas, CSRF, GET bloqueado, segredos ausentes, demo, arquivos privados e layout 1280/390.');
 } finally { if(browser)await browser.close();await api.dispose();await guest.dispose(); }
})().catch(e=>{console.error(e.message);process.exitCode=1});
