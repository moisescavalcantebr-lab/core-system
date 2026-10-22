const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const {request}=require('playwright');
(async()=>{
 const file=path.resolve(__dirname,'../../_deploy/crypto-local-test.json'),f=JSON.parse(fs.readFileSync(file,'utf8'));
 const base='https://localhost:8443/projects/'+f.slug+'/web';
 const api=await request.newContext({ignoreHTTPSErrors:true});
 try{
  for(const token of ['invalid-token',f.expired_token,f.used_token]){const r=await api.get(base+'/create-password.php?token='+token);assert.equal(r.status(),403);assert(!(await r.text()).includes('Warning'));}
  const url=base+'/create-password.php?token='+f.access_token;
  const get=await api.get(url);assert.equal(get.status(),200);const html=await get.text();assert(html.includes('Confirme a senha'));assert(!html.includes('Warning'));assert(!html.includes('Connection refused'));
  const csrf=html.match(/name="_csrf" value="([a-f0-9]+)"/)[1];
  const wrong=await api.post(url,{form:{_csrf:'wrong',password:'synthetic-password',confirm_password:'synthetic-password'},maxRedirects:0});assert.equal(wrong.status(),403);
  const mismatch=await api.post(url,{form:{_csrf:csrf,password:'synthetic-password',confirm_password:'different-password'}});assert.equal(mismatch.status(),200);assert((await mismatch.text()).includes('Confirme a mesma senha'));
  const password=crypto.randomBytes(24).toString('base64url');
  const success=await api.post(url,{form:{_csrf:csrf,password,confirm_password:password},maxRedirects:0});assert.equal(success.status(),303);assert(success.headers().location.endsWith('/admin/login.php'));
  const replay=await api.get(url);assert.equal(replay.status(),403);
  const login=await api.get(base+'/admin/login.php');const loginCsrf=(await login.text()).match(/name="_csrf" value="([a-f0-9]+)"/)[1];
  const authenticated=await api.post(base+'/admin/login_action.php',{form:{_csrf:loginCsrf,email:f.owner_email,password}});assert.equal(authenticated.status(),200);assert((await authenticated.text()).includes('Dashboard'));
  console.log('PASS: link local com token, CSRF, confirmacao, redirecionamento e login reais; token reutilizado recusado, sem avisos de conexao.');
 }finally{await api.dispose();}
})().catch(e=>{console.error(e.message);process.exitCode=1});
