'use strict';
// Real designer/bridge scripts, same-origin frames and a temporary in-memory API.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}) });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        const page = await context.newPage();
        const errors = [], posts = [];
        let wrongTheme = false;
        let state = { theme: 'demo', revision: 'one', canRestore: false, rules: [
            { id: 'heading', label: 'Heading', selector: 'h1.sample', mode: 'all', breakpoint: 'all', state: 'normal', declarations: { 'font-size': '22px', color: '#aaaaaa' } }
        ] };
        const config = { theme: 'demo', endpoint: '/api', stylesheetUrl: '/designer.css', styleUrl: '/saved.css', canSave: true };
        const sample = '<section class="xs-example" id="xs-heading" data-xs-section="typography"><header><h2>Heading hierarchy</h2><p class="xs-designer-actions" hidden><button data-xs-inspect>Inspect example</button><button data-xs-edit>Edit styles</button></p></header><div data-xs-specimen><h1 class="sample">A community worth sharing</h1></div></section>';
        const baseStyle = '<style>body{margin:20px;background:white;color:#172033;font-family:system-ui}h1.sample{font-size:18px}#xs-gallery{max-width:100%}.xs-designer-actions[hidden]{display:none}.xs-controls{padding:16px;background:#f5f7fb;border:1px solid #cbd2df}</style>';
        page.on('pageerror', error => errors.push(error.message));
        await context.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/api') {
                if (route.request().method() === 'POST') {
                    const body = route.request().postDataJSON(); posts.push(body);
                    assert.equal(body.token, 'test-token');
                    assert.equal(body.revision, state.revision);
                    state = { ...state, revision: 'next', rules: body.rules };
                }
                return route.fulfill({ json: { ok: true, state, canSave: true, token: 'test-token' } });
            }
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            if (url.pathname === '/saved.css') {
                const declarations = Object.entries(state.rules[0]?.declarations || {}).map(([key, value]) => `${key}:${value}!important`).join(';');
                return route.fulfill({ contentType: 'text/css', body: `h1.sample{${declarations}}` });
            }
            const frame = url.searchParams.get('preview_frame') === '1';
            const theme = frame && wrongTheme ? 'another-theme' : 'demo';
            const shell = frame ? '' : 'data-xs-workspace data-xs-preview-url="/modules/xstyles/index.php?preview_frame=1&amp;section=typography"';
            const boot = frame ? '' : `<script type="application/json" id="cssholmes-designer-config">${JSON.stringify(config)}</script>`;
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>${baseStyle}<link id="cssholmes-theme-style" rel="stylesheet" href="/saved.css"></head><body><main id="xs-gallery" data-xs-theme="${theme}" ${shell}><h1>Theme style guide</h1><p><button data-cssholmes-designer>Open theme designer</button></p>${sample}</main>${boot}</body></html>` });
        });
        await page.goto('https://gallery.test/modules/xstyles/index.php');
        await page.addStyleTag({ path: path.join(root, 'assets/css/gallery-designer.css') });
        for (const file of ['designer-diagnostics.js', 'designer.js', 'gallery-designer.js']) await page.addScriptTag({ path: path.join(root, 'assets/js', file) });
        const editor = page.locator('#cssholmes-designer');
        await page.getByRole('button', { name: 'Inspect example', exact: true }).click();
        await editor.locator('[data-diagnostics][open]').waitFor();
        await editor.getByRole('button', { name: 'Adjust text color', exact: true }).waitFor();
        await editor.getByRole('button', { name: 'Adjust text color', exact: true }).click();
        assert.equal(await editor.locator('[data-property=color]').evaluate(input => input.getRootNode().activeElement === input), true);
        await page.getByRole('button', { name: 'Edit styles', exact: true }).click();
        assert.equal(await editor.getByLabel('Selector', { exact: true }).inputValue(), 'h1.sample');
        const size = editor.locator('[data-property="font-size"]');
        await size.fill('30px');
        const preview = page.locator('[data-cssholmes-preview]');
        await preview.locator('summary').click();
        const waitFrames = async (count) => {
            await page.waitForFunction(count => {
                const frames = [...document.querySelectorAll('[data-cssholmes-preview] iframe')];
                return frames.length === count && frames.every(frame => frame.contentDocument?.querySelector('.sample'))
                    && [...document.querySelectorAll('.cssholmes-frame-status')].every(item => item.textContent === 'Preview ready');
            }, count);
        };
        const sizes = () => preview.locator('iframe').evaluateAll(frames => frames.map(frame => frame.contentWindow.getComputedStyle(frame.contentDocument.querySelector('.sample')).fontSize));
        await waitFrames(2);
        assert.deepEqual(await sizes(), ['22px', '30px']);
        assert.deepEqual(posts, [], 'Opening previews must never publish');
        assert.equal(await preview.locator('iframe').evaluateAll(frames => frames.every(frame => !frame.src.includes('30px'))), true, 'Drafts never enter frame URLs');
        await editor.getByRole('button', { name: 'Undo', exact: true }).click();
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '22px');
        await page.waitForFunction(() => { const f=document.querySelectorAll('[data-cssholmes-preview] iframe')[1]; return f.contentWindow.getComputedStyle(f.contentDocument.querySelector('.sample')).fontSize === '22px'; });
        await editor.getByRole('button', { name: 'Redo', exact: true }).click();
        await preview.locator('[data-draft-view]').selectOption('responsive');
        await waitFrames(3);
        assert.deepEqual(await preview.locator('iframe').evaluateAll(frames => frames.map(frame => frame.contentWindow.innerWidth)), [360, 768, 1280]);
        await editor.locator('[data-context=breakpoint]').selectOption('mobile');
        await size.fill('40px');
        await page.waitForFunction(() => { const f=document.querySelector('[data-cssholmes-preview] iframe'); return f.contentWindow.getComputedStyle(f.contentDocument.querySelector('.sample')).fontSize === '40px'; });
        assert.deepEqual(await sizes(), ['40px', '30px', '30px']);
        if (process.env.CSSHOLMES_GALLERY_SCREENSHOT) await page.screenshot({ path: process.env.CSSHOLMES_GALLERY_SCREENSHOT, fullPage: true });
        await editor.getByRole('button', { name: 'Discard draft', exact: true }).click();
        await page.waitForFunction(() => [...document.querySelectorAll('[data-cssholmes-preview] iframe')].every(f => f.contentWindow.getComputedStyle(f.contentDocument.querySelector('.sample')).fontSize === '22px'));
        await editor.locator('[data-context=breakpoint]').selectOption('all');
        await size.fill('32px');
        await editor.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Saved to theme.');
        await waitFrames(3);
        await page.waitForFunction(() => [...document.querySelectorAll('[data-cssholmes-preview] iframe')].every(f => f.contentWindow.getComputedStyle(f.contentDocument.querySelector('.sample')).fontSize === '32px'));
        assert.equal(posts.length, 1, 'Only explicit publication writes');
        wrongTheme = true;
        await preview.getByRole('button', { name: 'Refresh previews', exact: true }).click();
        await page.waitForFunction(() => [...document.querySelectorAll('.cssholmes-frame-status')].every(item => item.textContent.startsWith('Preview unavailable')));
        assert.equal(await preview.locator('iframe').evaluateAll(frames => frames.every(f => !f.contentDocument.getElementById('cssholmes-frame-draft') && !f.contentDocument.getElementById('cssholmes-theme-style').disabled)), true, 'Another theme must never receive draft CSS');
        await preview.locator('summary').click();
        await page.waitForFunction(() => !document.querySelector('[data-cssholmes-preview] iframe'));
        await page.evaluate(() => { window.CssHolmesDesigner.close(); });
        assert.deepEqual(errors, []);
        await page.setViewportSize({ width: 360, height: 800 });
        assert.equal(await preview.evaluate(element => element.scrollWidth <= element.clientWidth + 2), true);
        config.autoOpen = true;
        const automatic = await context.newPage();
        await automatic.goto('https://gallery.test/modules/xstyles/index.php?designer=1');
        for (const file of ['designer.js', 'gallery-designer.js']) await automatic.addScriptTag({ path: path.join(root, 'assets/js', file) });
        assert.equal(await automatic.locator('#xs-gallery').evaluate(element => element.classList.contains('cssholmes-editor-visible')), true, 'An already-open designer must reserve gallery space');
        await automatic.evaluate(() => window.CssHolmesDesigner.close());
        assert.equal(await automatic.locator('#xs-gallery').evaluate(element => element.classList.contains('cssholmes-editor-visible')), false);
        console.log('Gallery integration: inspect/repair, published vs draft, 3 widths, breakpoint CSS, undo/redo/discard, explicit save, foreign-theme rejection and cleanup passed.');
        await context.close();
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
