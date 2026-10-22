// Local regression only. Reuses the synthetic crypto test session, never real passwords.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { request } = require('playwright');
const root = path.resolve(__dirname, '../..');
(async () => {
  const fixture = JSON.parse(fs.readFileSync(path.join(root, '_deploy/crypto-local-test.json'), 'utf8'));
  const nonce = crypto.randomBytes(24).toString('hex');
  const name = '_runtime-check-' + crypto.randomBytes(12).toString('hex') + '.php';
  const file = path.join(root, 'web', name);
  const api = await request.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [
    { name: 'PHPSESSID', value: fixture.session, domain: 'localhost', path: '/', secure: true, httpOnly: true, sameSite: 'Lax', expires: -1 }
  ], origins: [] } });
  try {
    fs.writeFileSync(file, '<?php if (!hash_equals(' + JSON.stringify(nonce) + ', $_SERVER["HTTP_X_RUNTIME_CHECK"] ?? "")) { http_response_code(404); exit; } header("Cache-Control: no-store"); header("Content-Type: application/json"); echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"pdo_mysql"=>extension_loaded("pdo_mysql"),"gd"=>extension_loaded("gd")]);');
    const hidden = await api.get('https://localhost:8443/web/' + name); assert.equal(hidden.status(), 404);
    const check = await api.get('https://localhost:8443/web/' + name, { headers: { 'X-Runtime-Check': nonce } });
    assert.equal(check.status(), 200);
    const runtime = await check.json();
    assert.match(runtime.version, /^8\.3\./); assert(Number(runtime.version.split('.')[2]) >= 36);
    assert.equal(runtime.sapi, 'apache2handler'); assert(runtime.pdo_mysql && runtime.gd);
    for (const route of ['/web/admin/dashboard.php', '/web/admin/projects/index.php', '/web/admin/modules/index.php', '/web/admin/pages/index.php', '/web/admin/central_contas/accounts.php']) {
      const response = await api.get('https://localhost:8443' + route, { maxRedirects: 0 });
      assert.equal(response.status(), 200, route);
      assert(!/Warning:|Fatal error:|Deprecated:|Parse error:/.test(await response.text()), route);
    }
    const config = await api.get('https://localhost:8443/env/env.production.php'); assert.equal(config.status(), 403);
    console.log('PASS: web PHP ' + runtime.version + ' via Apache, extensoes, paginas autenticadas do Core, projetos, modulos, paginas e Central; configuracao privada bloqueada.');
  } finally {
    if (fs.existsSync(file)) fs.unlinkSync(file);
    await api.dispose();
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
