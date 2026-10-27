const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        const errors = [];
        const hostile = '<img src=x onerror="window.injected=true">';
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://profiles.test/**', route => {
            const url = new URL(route.request().url());
            if (url.pathname.startsWith('/assets/')) {
                const file = path.join(__dirname, '..', url.pathname);
                return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(file, 'utf8') });
            }
            if (url.pathname === '/theme.json') return route.fulfill({ json: { tokens: { ['--token-' + hostile]: 'black' } } });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="en" data-theme="dark"><body>
                <label for="explicit">Name</label><input id="explicit">
                <label>Implicit label<input id="implicit"></label>
                <input id="orphan"><input id="named" aria-label="Name"><span id="label">Description</span><textarea id="referenced" aria-labelledby="label"></textarea>
                <select id="broken" aria-labelledby="missing"><option>One</option></select>
                <div class="xtf-slot-widget xmf-card" id="good-widget"><p>Plain child</p></div>
                <div class="xtf-slot-widget" id="bad-widget"><p>Plain child</p></div>
                <p id="light" style="background: white">Hardcoded light background</p>
                <p data-test-target style="color:black; margin:100px 0">Editable text</p>
                <script src="/assets/js/holmes.js?moduleUrl=javascript:alert(1)&initialProfiles=a11y,xtf-theme,xtf-widget&themeManifestUrl=/theme.json&perms=overlay,edit,export&scope=${encodeURIComponent(hostile)}&themeKey=${encodeURIComponent(hostile)}"></script>
            </body></html>` });
        });
        await page.goto('https://profiles.test/');
        await page.waitForFunction(() => document.querySelector('#orphan').hasAttribute('data-holmes-unlabelled'));
        assert.match(await page.locator('#cssholmes-profile-a11y').getAttribute('href'), /^https:\/\/profiles.test\/assets\/css\/profiles\/a11y.css$/);
        assert.equal(await page.locator('.cssholmes-scope').textContent(), hostile, 'Scope must display as text');
        const severity = (selector, profile) => page.locator(selector).evaluate((element, profile) => getComputedStyle(element).getPropertyValue('--holmes-' + profile).trim(), profile);
        for (const id of ['explicit', 'implicit', 'named', 'referenced']) {
            assert.equal(await page.locator('#' + id).getAttribute('data-holmes-unlabelled'), null, id);
        }
        assert.equal(await severity('#orphan', 'a11y'), 'warning');
        assert.equal(await severity('#broken', 'a11y'), 'warning');
        assert.equal(await severity('body', 'a11y'), 'warning', 'Missing main must contribute to the counter');
        assert.equal(await severity('#good-widget', 'xtf-widget'), 'info', 'The root carries the widget prefix');
        assert.equal(await severity('#good-widget p', 'xtf-widget'), 'none', 'Plain children need no widget prefix');
        assert.equal(await severity('#bad-widget', 'xtf-widget'), 'warning');
        assert.equal(await severity('#light', 'xtf-theme'), 'warning', 'Dark mode on html must match');
        await page.evaluate(() => {
            document.documentElement.removeAttribute('data-theme');
            document.body.dataset.theme = 'dark';
            const label = document.createElement('label');
            label.htmlFor = 'orphan'; label.textContent = 'Added label'; document.body.append(label);
        });
        await page.waitForFunction(() => !document.querySelector('#orphan').hasAttribute('data-holmes-unlabelled'));
        assert.equal(await severity('#light', 'xtf-theme'), 'warning', 'Dark mode on body must match');
        await page.locator('label[for="orphan"]').evaluate(label => label.remove());
        await page.waitForFunction(() => document.querySelector('#orphan').hasAttribute('data-holmes-unlabelled'));
        await page.locator('[data-test-target]').evaluate((element, hostile) => { element.id = hostile; element.dataset.widget = hostile; }, hostile);
        await page.locator('.cssholmes-mini-icon').click();
        await page.locator('[data-action="inspect"]').click();
        await page.locator('[data-test-target]').click();
        await page.waitForFunction(() => document.querySelector('.cssholmes-detail-name').textContent.includes('<img'));
        await page.locator('[data-action="edit-token"]').click();
        await page.locator('[data-role="token-value"]').first().waitFor();
        assert.equal(await page.locator('#cssholmes-toolbar img').count(), 0, 'Selector, widget and token text must not create markup');
        await page.locator('[data-action="close-token-editor"]').click();
        page.once('dialog', dialog => dialog.accept(hostile));
        await page.locator('[data-action="edit-text"]').click();
        assert.match(await page.locator('.cssholmes-export').textContent(), /<img/);
        assert.equal(await page.locator('#cssholmes-toolbar img').count(), 0, 'Change summaries and exports must remain text');
        assert.equal(await page.evaluate(() => window.injected), undefined);
        assert.deepEqual(errors, []);
        console.log('Overlay profiles passed: labels, missing main, widget roots, dark mode, safe asset URLs and hostile display/export text.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
