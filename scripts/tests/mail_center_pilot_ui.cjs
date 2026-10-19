const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
function cssFile(relative) {
  const absolute=path.join(root,relative);
  return fs.readFileSync(absolute,'utf8').replace(/^\uFEFF/,'').replace(/@import\s+(?:url\()?['"]([^'"]+)['"]\)?\s*;/g,(_,url)=>/^https?:/.test(url)?'':cssFile(url.startsWith('/web/')?url.slice(1):path.relative(root,path.resolve(path.dirname(absolute),url))));
}
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});let checks=0;
  try{
    for(const theme of ['light','dark'])for(const width of [1280,390,360]){
      const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.route('**/*',route=>route.abort());
      for(const name of ['inbox','message','compose','review','unknown','setup','unified','unified-inbox','unified-sent','zoho-inbox','outlook-inbox']){
        let html=fs.readFileSync(path.join(root,`_deploy/mail-pilot-${name}.html`),'utf8').replace(/<link\b[^>]*>/g,'').replace(/<script\b[^>]*\bsrc=[^>]*>\s*<\/script>/g,'');
        await page.setContent(html);await page.addStyleTag({content:cssFile('web/assets/css/core_admin.css')+'\n'+cssFile(`web/assets/css/themes/${theme}.css`)+'\n'+cssFile('web/assets/css/admin/central_contas.css')});
        if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw new Error(`Overflow ${name}/${theme}/${width}`);
        if(await page.locator('input[type=password]').count())throw new Error('Campo de senha indevido');
        if(!await page.locator('.c-header').count() || !await page.locator('.c-sidebar').count())throw new Error('Menus do Core ausentes');
        if(await page.locator('.mail-standalone').count())throw new Error('Central abriu fora do layout Core');
        if(!await page.locator('body.mail-core-mode').count())throw new Error('Modo e-mail ausente');
        const tree=page.getByRole('navigation',{name:'Caixas e pastas',exact:true});
        if(!await tree.getByRole('link',{name:'Todos os e-mails',exact:true}).isVisible())throw new Error('Todos os e-mails inacessível');
        const allSelected=(await page.locator('[data-mail-account-switch]').inputValue())==='/web/admin/central_contas/unified.php';
        if(allSelected){
          if(await tree.locator('.mail-account-accordion').count())throw new Error('Todos mostra caixas individuais');
          if(name!=='setup'&&!await tree.getByRole('button',{name:'↻ Sincronizar todos',exact:true}).isVisible())throw new Error('Sincronizar todos ausente');
          for(const folder of ['Caixa de entrada','Enviados','Rascunhos','Spam','Lixeira'])if(!await tree.getByRole('link',{name:folder,exact:true}).isVisible())throw new Error('Pasta unificada ausente: '+folder);
        }else{
          if(await tree.locator('.mail-account-accordion').count()||await tree.getByRole('link',{name:'Todos desta conta',exact:true}).count())throw new Error('Conta individual repete o seletor');
          const allLink=tree.getByRole('link',{name:'Todos os e-mails',exact:true});if(!(await allLink.getAttribute('href')).includes('messages.php?account='))throw new Error('Todos saiu da conta selecionada');
          if(!await tree.locator('.mail-individual-folders').isVisible())throw new Error('Pastas individuais ocultas');
          if(!await tree.locator('.mail-individual-folders .mail-folder-link').first().locator('span').getByText('Caixa de entrada',{exact:true}).isVisible())throw new Error('Pasta principal sem nome traduzido');
        }
        if(width===1280){const main=await page.locator('.mail-client-main').boundingBox();const menu=await tree.boundingBox();if(menu.x+menu.width>main.x+1)throw new Error('Sanfona fora da esquerda');}
        if(['inbox','unified','unified-inbox','unified-sent','zoho-inbox','outlook-inbox'].includes(name)){
          if(await page.locator('.mail-search-clean').count()!==1||await page.locator('.mail-inbox-clean .mail-nav,.mail-inbox-clean .c-page-header').count())throw new Error('Cabeçalho da caixa poluído');
          if(!await page.locator('[data-mail-account-switch]').count())throw new Error('Seletor de conta ausente');
          if(!await tree.getByRole('link',{name:'⚙ Configurações',exact:true}).isVisible())throw new Error('Configurações fora do menu');
          if(await page.locator('[data-mail-reader]').isVisible())throw new Error('Painel de leitura abriu sem seleção');
        }
        if(width>=1024){
          if((await page.locator('.c-sidebar').boundingBox()).width>60)throw new Error('Menu Core não recolhido');
          await page.getByRole('button',{name:'Alternar menu do Core',exact:true}).click();
          if((await page.locator('.c-sidebar').boundingBox()).width<200)throw new Error('Menu Core não expande');
          await page.getByRole('button',{name:'Alternar menu do Core',exact:true}).click();
        }
        if(name==='unified'||name==='unified-inbox'){
          const colors=await page.locator('.mail-message-row').evaluateAll(rows=>[...new Set(rows.map(row=>row.style.getPropertyValue('--mail-account-color').trim()))]);
          if(colors.length<2||colors.some(color=>!/^#[0-9a-f]{6}$/.test(color)))throw new Error('Contas sem identificação por cor da função');
        }
        if(name==='compose'||name==='review'){
          if(!await page.getByRole('button',{name:'Salvar rascunho e revisar'}).isVisible())throw new Error('Compositor sem rascunho');
          if(await page.locator('input[name=from]').count())throw new Error('Remetente controlado pelo navegador');
        }
        if(name==='review'){
          await page.addScriptTag({content:fs.readFileSync(path.join(root,'web/assets/js/central_contas.js'),'utf8')});
          const send=page.getByRole('button',{name:'Enviar conteúdo salvo'});if(!await send.isVisible())throw new Error('Confirmação ausente');
          await page.locator('textarea[name=body]').fill('Alteração ainda não salva');if(!await send.isDisabled())throw new Error('Alterações não salvas permitiram envio');
        }
        if(name==='unknown'&&(!await page.getByText('Resultado incerto — confira antes de reenviar',{exact:true}).isVisible()||await page.getByRole('button',{name:'Enviar conteúdo salvo'}).count()))throw new Error('Envio incerto permite reenvio');
        if(name==='message'&&!await page.getByText('Corpo sintético privado',{exact:true}).isVisible())throw new Error('Leitura não renderizada');
        if(name==='setup'&&await page.getByRole('button',{name:'Autorizar no Google'}).count())throw new Error('Configuração pendente liberou OAuth');
        if(width===1280||width===390)await page.screenshot({path:path.join(root,`_deploy/mail-pilot-${name}-${theme}-${width}.png`),fullPage:true,animations:'disabled'});
        checks++;
      }
      if(errors.length)throw new Error(errors.join('; '));await page.close();
    }
    console.log(`PASS: ${checks} combinações visuais do piloto; leitura, revisão, bloqueio de alterações não salvas e resultado incerto.`);
  }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});

