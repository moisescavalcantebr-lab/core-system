const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
function cssFile(relative) {
  const absolute = path.join(root, relative);
  return fs.readFileSync(absolute, 'utf8').replace(/^\uFEFF/,'').replace(/@import\s+(?:url\()?['"]([^'"]+)['"]\)?\s*;/g, (_, url) => {
    if (url.startsWith('/web/')) return cssFile(url.slice(1));
    if (/^https?:/.test(url)) return '';
    return cssFile(path.relative(root, path.resolve(path.dirname(absolute), url)));
  });
}
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  let checks = 0;
  try {
    for (const theme of ['light','dark']) {
      for (const width of [1280,390,360]) {
        const page=await browser.newPage({viewport:{width,height:900}});
        await page.route('**/*', route=>route.abort());
        for (const name of ['empty','overview','accounts','edit']) {
          let html=fs.readFileSync(path.join(root,`_deploy/mail-center-${name}.html`),'utf8');
          html=html.replace(/<link\b[^>]*>/g,'').replace(/<script\b[^>]*\bsrc=[^>]*>\s*<\/script>/g,'');
          await page.setContent(html);
          await page.addStyleTag({content:cssFile('web/assets/css/core_admin.css')+'\n'+cssFile(`web/assets/css/themes/${theme}.css`)+'\n'+cssFile('web/assets/css/admin/central_contas.css')});
          if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw new Error(`Overflow ${name}/${theme}/${width}`);
          if(width>=1024&&await page.evaluate(()=>document.querySelector('.mail-center').getBoundingClientRect().left<document.querySelector('.c-sidebar').getBoundingClientRect().right)) throw new Error('Conteúdo sobreposto pela sidebar');
          if(await page.locator('input[type=password]').count()) throw new Error('Campo de senha indevido');
          if(!await page.getByRole('navigation',{name:'Central de E-mail',exact:true}).isVisible()) throw new Error('Navegação da Central ausente');
          if(name==='accounts'||name==='edit') {
            const options=await page.locator('select[name=provider_id] option').allTextContents();
            if(options.join('|')!=='Selecione|Gmail|Outlook|Zoho Mail') throw new Error('Provedores incorretos');
            for(const field of ['Nome da conta','Provedor','Endereço de e-mail','Função do e-mail','Projeto vinculado','Observações de uso']) {
              if(!await page.getByLabel(field,{exact:false}).first().isVisible()) throw new Error(`Campo inacessível: ${field}`);
            }
            if(name==='edit'&&await page.getByLabel('Nome da conta',{exact:true}).inputValue()!=='Cadastros atualizados') throw new Error('Edição não carregada');
          }
          if(name==='empty'&&!await page.getByRole('link',{name:'Cadastrar primeira conta'}).isVisible()) throw new Error('Estado vazio incorreto');
          const prefix=`_deploy/mail-center-${name}-${theme}-${width}.png`;
          if(width===1280||width===390) await page.screenshot({path:path.join(root,prefix),fullPage:true,animations:'disabled'});
          checks++;
        }
        await page.close();
      }
    }
    console.log(`PASS: ${checks} combinações de tela/tema/largura, campos acessíveis, provedores e ausência de overflow.`);
  } finally { await browser.close(); }
})().catch(error=>{console.error(error.message);process.exitCode=1;});
