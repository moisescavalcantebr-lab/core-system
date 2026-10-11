const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

(async () => {
    const root = path.resolve(__dirname, '../..');
    const read = name => fs.readFileSync(path.join(root, name), 'utf8');
    const css = ['core_base.css', 'admin/layout.css', 'admin/components.css', 'themes/dark.css']
        .map(name => read('bases/marketing/web/assets/css/' + name)).join('\n');
    const imagePath = process.env.LANDING_PAGE_TEST_IMAGE;
    if (!imagePath) throw new Error('Defina LANDING_PAGE_TEST_IMAGE para uma imagem de teste local.');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        const context = await browser.newContext({ permissions: ['clipboard-read', 'clipboard-write'] });
        const requests = [];
        await context.route('**/*', async route => {
            const url = new URL(route.request().url());
            requests.push({ url: url.href, method: route.request().method() });
            if (url.pathname.endsWith('/storage/uploads/test.png')) {
                await route.fulfill({ path: imagePath });
            } else if (url.hostname === 'example.com') {
                await route.fulfill({ body: 'Destino', contentType: 'text/html' });
            } else {
                const kind = url.pathname.split('/').pop();
                const content = read('_deploy/banners-' + kind + '-preview.html');
                const body = kind.startsWith('public') ? content : '<style>' + css + '</style><body class="c-app"><main style="padding:20px">' + content + '</main></body>';
                await route.fulfill({ body, contentType: 'text/html' });
            }
        });
        const page = await context.newPage();
        for (const width of [1280, 360]) {
            await page.setViewportSize({ width, height: 900 });
            for (const kind of ['public', 'public-four', 'public-email', 'form', 'index', 'leads']) {
                await page.goto('http://localhost:8000/test-preview/' + kind);
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
                if (overflow) throw new Error(kind + ': overflow em ' + width);
                if (kind.startsWith('public')) {
                    const expectedBanners = kind === 'public-four' ? 4 : (kind === 'public-email' ? 0 : 2);
                    if (await page.locator('.primary-image').count() !== 1 || await page.locator('a.banner').count() !== expectedBanners) throw new Error('Quantidade incorreta');
                    if (kind === 'public-email') {
                        if (await page.locator('.lead-card input:not([type=hidden])').count() !== 1 || (await page.locator('input[name=email]').getAttribute('required')) === null) throw new Error('Captura de e-mail incorreta');
                        await page.locator('input[name=email]').fill('teste@example.com');
                        if (!await page.locator('.lead-card form').evaluate(form => form.checkValidity())) throw new Error('Formulario so e-mail invalido');
                    }
                    if (expectedBanners) await page.locator('.banner img').first().scrollIntoViewIfNeeded();
                    await page.waitForFunction(() => [...document.images].every(img => img.complete && img.naturalWidth > 0));
                    const boxes = await page.locator('.banner').evaluateAll(items => items.map(el => el.getBoundingClientRect().toJSON()));
                    if (width > 860 && boxes.length > 1 && Math.abs(boxes[0].width - boxes[1].width) > 1) throw new Error('Banners com tamanhos distintos');
                    const heading = await page.locator('.page-intro').boundingBox();
                    const image = await page.locator('.primary-image').boundingBox();
                    const copy = await page.locator('.page-copy').boundingBox();
                    const form = await page.locator('.lead-card').boundingBox();
                    if (width > 860 && (Math.abs(heading.y - image.y) > 1 || Math.abs(image.x - form.x) > 1 || Math.abs(image.width - form.width) > 1 || image.y + image.height > form.y)) throw new Error('Colunas desalinhadas');
                    if (width <= 860 && !(heading.y < image.y && image.y < copy.y && copy.y < form.y)) throw new Error('Ordem mobile incorreta');
                    if (kind === 'public-four') {
                        if (width > 860 && boxes.some(box => Math.abs(box.y - boxes[0].y) > 1)) throw new Error('Banners fora da mesma linha');
                        if (width <= 860 && (Math.abs(boxes[0].y - boxes[1].y) > 1 || Math.abs(boxes[2].y - boxes[3].y) > 1 || boxes[2].y <= boxes[0].y)) throw new Error('Grade mobile nao e 2x2');
                    }
                }
                if (kind === 'leads') {
                    const select = page.locator('.lead-status-form select').first();
                    const styled = await select.evaluate(el => getComputedStyle(el).backgroundColor !== 'rgb(255, 255, 255)' && el.getBoundingClientRect().height >= 36);
                    if (!styled) throw new Error('Status de lead sem estilo');
                }
                await page.screenshot({ path: path.join(root, '_deploy/banners-' + kind + '-' + width + '.png'), fullPage: true });
                console.log(kind + ' ' + width + ': OK');
            }
        }
        await page.goto('http://localhost:8000/test-preview/index');
        await page.locator('[data-copy-link]').click();
        await page.getByText('Link copiado', { exact: true }).waitFor();
        const copied = await page.evaluate(() => navigator.clipboard.readText());
        if (copied !== 'http://localhost:8000/test/landing_page.php?slug=pagina-teste') throw new Error('Link copiado incorreto: ' + copied);
        await page.goto('http://localhost:8000/test-preview/public');
        await page.locator('a.banner').first().click();
        if (page.url() !== 'https://example.com/destino-a' || requests.some(request => request.method === 'POST')) throw new Error('Banner nao navegou diretamente');
        console.log('Clipboard, destino direto e ausencia de POST: OK');
        await context.close();
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
