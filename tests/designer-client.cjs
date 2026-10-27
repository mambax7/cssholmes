/* Browser integration check; use an existing Playwright installation, no new dependency. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(10000);
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        let saved = { theme: 'test', revision: 0, rules: [], css: '', canRestore: false };
        let previous = null;
        let conflict = false;
        const posts = [];
        await page.route('https://designer.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/api') {
                if (route.request().method() === 'POST') {
                    const body = route.request().postDataJSON();
                    posts.push(body);
                    if (conflict) return route.fulfill({ status: 409, json: { ok: false, error: 'Another editor saved. Reload before saving.' } });
                    assert.equal(body.token, 'csrf-test');
                    assert.equal(body.revision, saved.revision);
                    if (body.op === 'restore') {
                        saved = { ...previous, revision: saved.revision + 1, canRestore: false };
                    } else {
                        previous = saved;
                        saved = { ...saved, rules: body.rules, revision: saved.revision + 1, canRestore: true };
                    }
                }
                return route.fulfill({ json: { ok: true, state: saved, token: 'csrf-test', canSave: true, canExport: true } });
            }
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            if (url.pathname === '/library') {
                assert.equal(route.request().method(), 'GET', 'Diagnostics previews never write library data');
                return route.fulfill({ json: { ok: true, library: { sets: [{ id: 'contrast-set', theme: 'test', name: 'Contrast sample', author: { name: 'Test' }, likes: 0 }], palettes: [] }, preview: { rules: [{ selector: '.xoops-comment-template .xoops-comment-text > h4', label: 'Comment titles', mode: 'all', breakpoint: 'all', state: 'normal', declarations: { color: '#777777' } }], warnings: [] } } });
            }
            if (url.pathname === '/saved.css') return route.fulfill({ contentType: 'text/css', body: '' });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><link id="cssholmes-theme-style" rel="stylesheet" href="/saved.css"><style>.xoops-comment-text > h4{font-size:18px;color:rgb(20,20,20)}body{margin:0}button{font-size:60px!important}</style></head><body>
                <div id="xs-gallery"><header class="xs-header"><h4><a href="/leave">Gallery controls</a></h4></header>
                <div data-xs-specimen><div id="xs-comment" class="xoops-comment-template"><div class="xoops-comment-text"><h4><a href="/leave">First comment</a></h4><div class="message-text">Body</div></div></div></div>
                <div data-xs-specimen><div class="xoops-comment-template"><div class="xoops-comment-text"><h4>Second comment</h4></div></div></div></div>
                <script type="application/json" id="cssholmes-designer-config">${JSON.stringify({ theme: 'test', endpoint: '/api', libraryEndpoint: '/library', styleUrl: '/saved.css', stylesheetUrl: '/designer.css', token: 'csrf-test', canSave: true, canExport: true })}</script>
                </body></html>` });
        });
        await page.goto('https://designer.test/');
        await page.addScriptTag({ path: path.join(root, 'assets/js/designer-diagnostics.js') });
        await page.addScriptTag({ content: fs.existsSync(path.join(root, 'assets/js/designer.js')) ? fs.readFileSync(path.join(root, 'assets/js/designer.js'), 'utf8') : '// Designer is not implemented yet.' });
        assert.equal(await page.evaluate(() => typeof window.CssHolmesDesigner), 'object', 'Designer must expose its public API');
        const panel = page.locator('#cssholmes-designer');
        const field = name => panel.locator(`[data-property="${name}"]`);
        await page.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.xoops-comment-text > h4')));
        await panel.getByLabel('Font size', { exact: true }).waitFor();
        await field('font-family').fill('"Open Sans", Arial, sans-serif');
        assert.equal(await field('font-family').evaluate(input => input.checkValidity()), true);
        await panel.getByRole('button', { name: 'Undo', exact: true }).click();
        await field('font-family').fill('A,A' + ' ,A'.repeat(25) + ',');
        assert.equal(await field('font-family').evaluate(input => input.checkValidity()), false, 'Reject malformed font lists without backtracking');
        await field('font-family').fill('');
        assert.equal(await panel.getByLabel('Selector', { exact: true }).inputValue(), '.xoops-comment-template .xoops-comment-text > h4');
        assert.match(await panel.locator('[data-count]').textContent(), /2/);
        await panel.locator('[data-diagnostics] > summary').click();
        assert.match(await panel.locator('[data-diagnostics-sample]').textContent(), /first of 2/);
        await page.evaluate(() => {
            document.body.style.background = 'white';
            document.documentElement.style.setProperty('--xtf-color-primary', '#123456');
            document.querySelector('.xoops-comment-text > h4').textContent = 'First comment';
        });
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent.includes(':1'));
        await panel.getByLabel('Search tokens', { exact: true }).fill('--xtf-color');
        assert.match(await panel.locator('[data-token-list]').textContent(), /--xtf-color-primary/);
        const beforeRefresh = await panel.locator('[data-contrast]').textContent();
        await panel.getByRole('button', { name: 'Refresh diagnostics', exact: true }).click();
        assert.equal(posts.length, 0, 'Inspection and refresh never save');
        assert.equal(await panel.getByRole('button', { name: 'Undo', exact: true }).isDisabled(), true, 'Inspection never changes history');
        await field('color').fill('#777777');
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent.includes('4.48:1'));
        assert.match(await panel.locator('[data-contrast]').textContent(), /Normal text AA.*Fail/);
        await panel.getByRole('button', { name: 'Undo', exact: true }).click();
        await page.waitForFunction(value => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent === value, beforeRefresh);
        await page.evaluate(() => document.querySelector('.xoops-comment-text > h4').style.color = 'rgb(115,120,116)');
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent.includes('4.50:1'));
        assert.match(await panel.locator('[data-contrast]').textContent(), /Normal text AA.*Fail/, 'Threshold comparison precedes rounding');
        await page.evaluate(() => document.querySelector('.xoops-comment-text > h4').style.color = '');
        await panel.getByLabel('Style sets', { exact: true }).selectOption('contrast-set');
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent.includes('4.48:1'));
        await panel.getByLabel('Compare original', { exact: true }).check();
        await page.waitForFunction(value => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent === value, beforeRefresh);
        await panel.getByLabel('Compare original', { exact: true }).uncheck();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-contrast]').textContent.includes('4.48:1'));
        await panel.getByRole('button', { name: 'Discard draft', exact: true }).click();
        assert.equal(posts.length, 0, 'Set and compare diagnostics never publish');
        await page.evaluate(() => {
            const style = document.createElement('style');
            style.textContent = ':root[data-theme=dark]{--xtf-color-primary:#abcdef} :root.dark-mode{--xtf-color-primary:#fedcba} @media(prefers-color-scheme:dark){:root{--system-mode:dark}}';
            document.head.append(style);
            document.documentElement.style.removeProperty('--xtf-color-primary');
            document.documentElement.dataset.theme = 'dark';
        });
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-token-list]').textContent.includes('#abcdef'));
        await page.evaluate(() => document.documentElement.classList.add('dark-mode'));
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-token-list]').textContent.includes('#fedcba'));
        await panel.getByLabel('Search tokens', { exact: true }).fill('--system-mode');
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-token-list]').textContent.includes('--system-mode'));
        await page.emulateMedia({ colorScheme: 'light' });
        await page.evaluate(() => { delete document.documentElement.dataset.theme; document.documentElement.classList.remove('dark-mode'); });
        await page.evaluate(() => {
            window.diagnosticCalls = 0;
            const original = CssHolmesDiagnostics.inspect;
            CssHolmesDiagnostics.inspect = element => { window.diagnosticCalls++; return original(element); };
            CssHolmesDesigner.close();
            document.body.style.background = 'black';
        });
        await page.waitForTimeout(180);
        assert.equal(await page.evaluate(() => diagnosticCalls), 0, 'Observers pause while closed');
        await page.evaluate(() => CssHolmesDesigner.open());
        await page.waitForFunction(() => diagnosticCalls > 0);
        await page.evaluate(() => {
            document.body.style.background = 'white';
            document.querySelector('.xoops-comment-text > h4').innerHTML = '<a href="/leave">First comment</a>';
        });
        await panel.locator('[data-diagnostics] > summary').click();
        const alignment = field('text-align');
        assert.equal(await alignment.evaluate(el => el.tagName), 'SELECT', 'Fixed choices must use an unfiltered dropdown');
        const alignmentOptions = ['', 'start', 'end', 'left', 'right', 'center', 'justify'];
        assert.deepEqual(await alignment.locator('option').evaluateAll(options => options.map(option => option.value)), alignmentOptions);
        await alignment.selectOption('right');
        assert.equal(await alignment.locator('option:checked').inputValue(), 'right');
        assert.deepEqual(await alignment.locator('option').evaluateAll(options => options.map(option => option.value)), alignmentOptions, 'Every choice remains available after selecting');
        await alignment.selectOption('center');
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).textAlign), 'center');
        await panel.getByRole('button', { name: 'Undo', exact: true }).click();
        assert.equal(await alignment.inputValue(), 'right');
        await panel.getByRole('button', { name: 'Redo', exact: true }).click();
        assert.equal(await alignment.inputValue(), 'center');
        await alignment.selectOption('');
        assert.equal(await alignment.locator('option:checked').textContent(), 'Theme default');
        await field('font-size').fill('28px');
        await field('color').fill('#cc0000');
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '28px');
        assert.equal(await page.locator('.xoops-comment-text > h4').last().evaluate(el => getComputedStyle(el).color), 'rgb(204, 0, 0)');
        await panel.getByRole('button', { name: 'Undo', exact: true }).click();
        assert.equal(await field('color').inputValue(), '');
        await panel.getByRole('button', { name: 'Redo', exact: true }).click();
        assert.equal(await field('color').inputValue(), '#cc0000');
        await field('color').fill('red');
        assert.equal(await field('color').getAttribute('aria-invalid'), 'true', 'Client rejects named colors unsupported by server');
        await field('color').fill('rgba(204, 0, 0, .5)');
        assert.equal(await field('color').getAttribute('aria-invalid'), 'false');
        await field('color').fill('#cc0000');
        await field('font-size').fill('url(https://bad.test/)');
        assert.equal(await panel.getByRole('button', { name: 'Save to theme', exact: true }).isDisabled(), true);
        await field('font-size').fill('28px');
        await panel.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Saved to theme.');
        assert.equal(posts.length, 1);
        assert.equal(posts[0].rules[0].selector, '.xoops-comment-template .xoops-comment-text > h4');
        assert.ok(!JSON.stringify(posts[0]).includes('xs-comment'));
        await panel.getByRole('button', { name: 'Reset selected', exact: true }).click();
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '18px');
        await panel.getByRole('button', { name: 'Discard draft', exact: true }).click();
        assert.equal(await field('font-size').inputValue(), '28px');
        await field('font-size').fill('30px');
        conflict = true;
        await panel.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: 'Another editor' }).waitFor();
        assert.equal(await field('font-size').inputValue(), '30px', 'Conflict retains unsaved draft');
        conflict = false;
        await panel.getByRole('button', { name: 'Discard draft', exact: true }).click();
        await panel.locator('.more summary').click();
        await field('font-size').fill('34px');
        page.once('dialog', dialog => dialog.dismiss());
        await panel.getByRole('button', { name: 'Reload saved settings', exact: true }).click();
        assert.equal(await field('font-size').inputValue(), '34px', 'Canceled reload preserves draft');
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Reload saved settings', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-property="font-size"]').value === '28px');
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Restore previous save', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Previous save restored.');
        assert.equal(saved.rules.length, 0);
        await panel.getByRole('button', { name: 'Pick element', exact: true }).click();
        await page.locator('.xoops-comment-text a').click();
        assert.equal(page.url(), 'https://designer.test/');
        assert.equal(await panel.getByLabel('Selector', { exact: true }).inputValue(), '.xoops-comment-template .xoops-comment-text > h4');
        await page.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.xs-header h4')));
        assert.equal(await panel.getByLabel('Selector', { exact: true }).inputValue(), '.xoops-comment-template .xoops-comment-text > h4');
        await panel.getByRole('button', { name: 'Pick element', exact: true }).click();
        await page.locator('.xs-header a').click();
        assert.equal(page.url(), 'https://designer.test/', 'Picking gallery chrome cannot navigate away');
        assert.equal(await panel.getByLabel('Selector', { exact: true }).inputValue(), '.xoops-comment-template .xoops-comment-text > h4');
        await field('font-size').fill('29px');
        await page.evaluate(() => window.CssHolmesDesigner.close());
        assert.equal(await page.evaluate(() => document.querySelector('#cssholmes-theme-style').disabled), false);
        await page.evaluate(() => window.CssHolmesDesigner.open());
        assert.equal(await field('font-size').inputValue(), '29px', 'Close preserves draft');
        await page.setViewportSize({ width: 320, height: 680 });
        assert.ok(await panel.locator('[data-panel]').evaluate(el => el.getBoundingClientRect().width <= 320));
        assert.equal(await panel.getByRole('button', { name: 'Pick element', exact: true }).evaluate(el => getComputedStyle(el).fontSize), '13px', 'Theme CSS cannot restyle shadow controls');
        assert.match(await panel.locator('h2').evaluate(el => getComputedStyle(el).fontFamily), /system-ui/, 'Panel text uses the isolated editor font');
        await panel.getByRole('button', { name: 'Discard draft', exact: true }).click();
        await panel.locator('[data-context=breakpoint]').selectOption('mobile');
        await field('font-size').fill('31px');
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '31px');
        await page.setViewportSize({ width: 1100, height: 800 });
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '18px');
        await panel.locator('[data-context=breakpoint]').selectOption('desktop');
        await panel.locator('[data-context=mode]').selectOption('dark');
        await field('font-size').fill('32px');
        await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'light'));
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '18px');
        await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '32px');
        await page.evaluate(() => { document.documentElement.removeAttribute('data-bs-theme'); document.documentElement.dataset.theme = 'dark'; });
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '32px', 'XTF data-theme activates the same draft');
        await page.evaluate(() => document.documentElement.dataset.bsTheme = 'light');
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '18px', 'Explicit Bootstrap mode takes precedence');
        await page.emulateMedia({ colorScheme: 'dark' });
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '18px', 'System preference cannot override explicit mode');
        await page.evaluate(() => { document.documentElement.dataset.bsTheme = 'auto'; document.documentElement.dataset.theme = 'auto'; });
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '32px', 'Auto mode follows system preference');
        await page.evaluate(() => { document.documentElement.dataset.bsTheme = 'dark'; delete document.documentElement.dataset.theme; });
        await page.emulateMedia({ colorScheme: 'light' });
        await panel.locator('[data-context=state]').selectOption('hover');
        await field('font-size').fill('33px');
        await page.locator('.xoops-comment-text > h4').first().hover();
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '33px');
        await field('font-size').fill('');
        assert.equal(await page.locator('.xoops-comment-text > h4').first().evaluate(el => getComputedStyle(el).fontSize), '32px', 'Blank removes only current scoped declaration');
        const cssDownload = await Promise.all([
            page.waitForEvent('download'), panel.getByRole('button', { name: 'Export CSS', exact: true }).click()
        ]).then(results => results[0]);
        const stream = await cssDownload.createReadStream();
        const chunks = [];
        for await (const chunk of stream) chunks.push(chunk);
        const exported = Buffer.concat(chunks).toString('utf8');
        assert.match(exported, /@media \(max-width: 575px\)/);
        assert.match(exported, /:root:is\(\[data-bs-theme="dark"\]/);
        assert.match(exported, /32px !important/);
        await page.evaluate(() => document.documentElement.dir = 'rtl');
        assert.equal(await panel.locator('[data-panel]').evaluate(el => el.getBoundingClientRect().x), 0, 'Inspector follows RTL direction');
        await page.evaluate(() => document.documentElement.dir = 'ltr');
        await panel.getByRole('button', { name: 'Discard draft', exact: true }).click();
        await panel.getByRole('button', { name: 'Pick element', exact: true }).click();
        await page.locator('.xoops-comment-text a').focus();
        await page.keyboard.press('Enter');
        assert.equal(page.url(), 'https://designer.test/');
        assert.equal(await panel.getByLabel('Selector', { exact: true }).inputValue(), '.xoops-comment-template .xoops-comment-text > h4');
        await panel.getByRole('button', { name: 'Pick element', exact: true }).click();
        await page.keyboard.press('Escape');
        assert.equal(await panel.locator('[data-panel]').isVisible(), true);
        if (process.env.DESIGNER_SCREENSHOT) {
            await page.setViewportSize({ width: 320, height: 680 });
            await page.screenshot({ path: process.env.DESIGNER_SCREENSHOT });
        }
        const previewPage = await browser.newPage();
        await previewPage.route('https://designer.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/api') return route.fulfill({ json: { ok: true, state: saved, token: 'csrf-test', canSave: false } });
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            return route.fulfill({ contentType: 'text/html', body: '<!doctype html><body><p class="sample">Sample</p><script type="application/json" id="cssholmes-designer-config">' + JSON.stringify({ theme: 'test', endpoint: '/api', stylesheetUrl: '/designer.css', canSave: false }) + '</script></body>' });
        });
        await previewPage.goto('https://designer.test/');
        await previewPage.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await previewPage.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.sample')));
        await previewPage.locator('#cssholmes-designer').getByLabel('Font size', { exact: true }).fill('24px');
        assert.equal(await previewPage.locator('.sample').evaluate(el => getComputedStyle(el).fontSize), '24px');
        assert.equal(await previewPage.locator('#cssholmes-designer').getByRole('button', { name: 'Save to theme', exact: true }).isDisabled(), true);
        await previewPage.locator('#cssholmes-designer').locator('details.more').evaluate(element => element.open = true);
        for (const name of ['Export CSS', 'Export JSON']) {
            assert.equal(await previewPage.locator('#cssholmes-designer').getByRole('button', { name, exact: true }).isDisabled(), true, 'Preview access must not enable downloads');
        }
        const firstSave = await browser.newPage();
        let firstRules = [];
        await firstSave.route('https://designer.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/api') {
                if (route.request().method() === 'POST') firstRules = route.request().postDataJSON().rules;
                return route.fulfill({ json: { ok: true, state: { theme: 'test', revision: firstRules.length, rules: firstRules, canRestore: Boolean(firstRules.length) }, token: 'csrf-test', canSave: true, canExport: true } });
            }
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            if (url.pathname === '/saved.css') return route.fulfill({ contentType: 'text/css', body: firstRules.map(rule => `${rule.selector}{font-size:${rule.declarations['font-size']}!important}`).join('\n') });
            return route.fulfill({ contentType: 'text/html', body: '<!doctype html><body><p class="sample">Sample</p><script type="application/json" id="cssholmes-designer-config">' + JSON.stringify({ theme: 'test', endpoint: '/api', styleUrl: '/saved.css', stylesheetUrl: '/designer.css', canSave: true, canExport: true }) + '</script></body>' });
        });
        await firstSave.goto('https://designer.test/');
        await firstSave.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await firstSave.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.sample')));
        await firstSave.locator('#cssholmes-designer').getByLabel('Font size', { exact: true }).fill('27px');
        await firstSave.locator('#cssholmes-designer').getByRole('button', { name: 'Save to theme', exact: true }).click();
        await firstSave.waitForFunction(() => document.querySelector('#cssholmes-theme-style'));
        await firstSave.evaluate(() => window.CssHolmesDesigner.close());
        await firstSave.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '27px');
        assert.equal(await firstSave.evaluate(() => document.querySelector('#cssholmes-theme-style').disabled), false, 'First save creates a persistent stylesheet and close enables it');
        assert.deepEqual(pageErrors, []);
        console.log('Designer browser checks passed: diagnostics, selection, live styles, history, validation, save/reset/discard/restore, conflicts, safe mouse/keyboard picking, close/reopen, responsive modes/states, preview-only and Shadow DOM isolation.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
