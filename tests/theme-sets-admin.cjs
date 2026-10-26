/* Browser check with the existing Playwright installation; no new dependency. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const labels = {};
        for (const match of fs.readFileSync(path.join(root, 'language/english/admin.php'), 'utf8').matchAll(/define\('_AM_CSSHOLMES_SETS_(\w+)', '([^']*)'\);/g)) labels[match[1].toLowerCase()] = match[2];
        const page = await browser.newPage();
        page.setDefaultTimeout(10000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const sets = [
            { id: 'one', theme: 'alpha', name: '<img src=x onerror=alert(1)>', author: { id: 1, name: '<b>Admin</b>' }, created: '2026-10-01T00:00:00Z', modified: '2026-10-02T00:00:00Z', likes: 1, liked: false, revision: 'set-1', editable: true },
            { id: 'two', theme: 'alpha', name: 'Popular', author: { id: 2, name: 'Member' }, created: '2026-10-01T00:00:00Z', modified: '2026-10-03T00:00:00Z', likes: 9, liked: false, revision: 'set-2', editable: false }
        ];
        const posts = [];
        let stale = false;
        await page.route('https://library.test/**', async route => {
            const req = route.request();
            const url = new URL(req.url());
            if (url.pathname === '/sets.php') {
                const body = req.method() === 'POST' ? req.postDataJSON() : null;
                if (body) {
                    posts.push(body);
                    assert.equal(body.token, 'fresh-token');
                    if (stale) return route.fulfill({ status: 409, json: { ok: false, error: 'Conflict' } });
                }
                return route.fulfill({ json: {
                    ok: true, token: 'fresh-token', canSave: true, isAdmin: true,
                    state: { theme: body?.theme || url.searchParams.get('theme') || 'alpha', revision: 'live-1', rules: [] },
                    library: { themes: ['alpha', 'empty'], sets, palettes: [{ id: 'builtin-paper', name: 'Paper', colors: ['#ffffff', '#111111'], contrast: [{ name: 'Text / background', ratio: 18.88, pass: true }] }] },
                    preview: body?.op === 'previewImport' ? { document: body.document, rules: [], warnings: ['Review this warning'] } : undefined
                } });
            }
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body><div id="cssholmes-theme-sets"></div><script type="application/json" id="cssholmes-theme-sets-config">${JSON.stringify({ libraryEndpoint: '/sets.php', previewUrl: '/xstyles/', labels })}</script></body></html>` });
        });
        await page.goto('https://library.test/');
        await page.addStyleTag({ content: 'body{font:12px Verdana,sans-serif}th{background:#2e323b;color:#fff;padding:4px}.outer{border:2px solid #ccc}.head{background:#afafaf}.odd{background:#fff}.even{background:#efefef}' });
        await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'assets/css/theme-sets-admin.css'), 'utf8') });
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/js/theme-sets-admin.js'), 'utf8') });
        const app = page.locator('#cssholmes-theme-sets');
        await app.locator('thead th').first().waitFor();
        const headingColors = await app.locator('thead th').first().evaluate(el => { const style = getComputedStyle(el); return [style.color, style.backgroundColor]; });
        const standardColors = await page.evaluate(() => {
            const table = document.createElement('table'); table.className = 'outer';
            const head = table.createTHead().insertRow(); const cell = document.createElement('th'); head.append(cell);
            document.body.append(table);
            const style = getComputedStyle(cell), colors = [style.color, style.backgroundColor];
            table.remove(); return colors;
        });
        assert.deepEqual(headingColors, standardColors, 'Column headings must use the same colors as other admin tables');
        assert.equal(await app.evaluate(el => getComputedStyle(el).fontFamily === getComputedStyle(document.body).fontFamily), true, 'The module should inherit the admin font');
        const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => { value /= 255; return value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4; }).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
        const levels = headingColors.map(luminance).sort((a, b) => b - a);
        assert.ok((levels[0] + .05) / (levels[1] + .05) >= 4.5, 'Column headings must remain readable with native admin th styles');
        assert.equal(await app.locator('table.outer').count(), 1, 'Use the standard admin table class');
        const categoryColors = await app.locator('.chs-category th').first().evaluate(el => { const style = getComputedStyle(el); return [style.color, style.backgroundColor]; });
        const categoryLevels = categoryColors.map(luminance).sort((a, b) => b - a);
        assert.ok((categoryLevels[0] + .05) / (categoryLevels[1] + .05) >= 4.5, 'Theme category names must remain readable');
        const sample = app.locator('tr[data-sample]');
        assert.equal(await sample.count(), 1, 'Empty theme gets a clearly marked sample row');
        assert.match(await sample.textContent(), /Admin/);
        assert.match(await sample.textContent(), /Sample/);
        assert.equal(await sample.getByRole('button', { name: 'Delete', exact: true }).isDisabled(), true, 'Unsaved examples cannot be deleted as real sets');
        assert.equal(new URL(await sample.getByRole('link', { name: 'Edit', exact: true }).getAttribute('href')).searchParams.get('theme_set'), 'original');
        const owned = app.locator('tbody').first().locator('tr').nth(1);
        assert.equal(await owned.getByRole('link', { name: 'Edit', exact: true }).count(), 1);
        assert.equal(await owned.getByRole('button', { name: 'Clone', exact: true }).count(), 1);
        await app.getByLabel('Show sample rows', { exact: true }).uncheck();
        await app.getByText('No named sets for this theme.', { exact: true }).waitFor();
        assert.ok(await app.getByText('No named sets for this theme.', { exact: true }).evaluate(el => el.getBoundingClientRect().height <= 32), 'Empty rows should stay compact');
        assert.equal(await app.locator('tbody').count(), 2, 'Installed themes retain empty categories');
        assert.equal(await app.locator('img').count(), 0, 'Names are plain text');
        await app.getByLabel('Sort sets', { exact: true }).selectOption('modified');
        assert.equal(await app.locator('tbody').first().locator('tr').nth(1).locator('th').textContent(), 'Popular', 'ISO date sorting uses timestamp values');
        await app.getByLabel('Sort sets', { exact: true }).selectOption('likes');
        assert.equal(await app.locator('tbody').first().locator('tr').nth(1).locator('th').textContent(), 'Popular');
        const popular = app.getByRole('rowheader', { name: 'Popular', exact: true }).locator('..');
        const preview = new URL(await popular.getByRole('link', { name: 'Preview', exact: true }).getAttribute('href'));
        assert.equal(preview.searchParams.get('xoops_theme_select'), 'alpha');
        assert.equal(preview.searchParams.get('theme_set'), 'two');
        page.once('dialog', dialog => dialog.dismiss());
        await popular.getByRole('button', { name: 'Activate', exact: true }).click();
        await page.waitForTimeout(100);
        assert.equal(posts.length, 0, 'Cancelling activation never writes');
        page.once('dialog', dialog => dialog.accept());
        await popular.getByRole('button', { name: 'Activate', exact: true }).click();
        await app.getByRole('status').filter({ hasText: 'Set activated.' }).waitFor();
        assert.equal(posts[0].revision, 'live-1');
        assert.equal(posts[0].id, 'two');
        await popular.getByRole('button', { name: 'Like', exact: true }).click();
        await app.getByRole('status').filter({ hasText: 'Like updated.' }).waitFor();
        assert.equal(posts.at(-1).liked, true);
        page.once('dialog', dialog => dialog.accept('Popular copy'));
        await popular.getByRole('button', { name: 'Clone', exact: true }).click();
        await app.getByRole('status').filter({ hasText: 'Set cloned. It has not been activated.' }).waitFor();
        assert.equal(posts.at(-1).op, 'cloneSet');
        assert.equal(posts.at(-1).id, 'two');
        assert.equal(posts.at(-1).name, 'Popular copy');
        const beforeReset = posts.length;
        page.once('dialog', dialog => dialog.dismiss());
        await app.getByRole('button', { name: 'Reset to original', exact: true }).first().click();
        await app.getByRole('status').filter({ hasText: 'Action cancelled.' }).waitFor();
        assert.equal(posts.length, beforeReset, 'Cancelling reset never writes');
        page.once('dialog', dialog => dialog.accept());
        await app.getByRole('button', { name: 'Reset to original', exact: true }).first().click();
        await app.getByRole('status').filter({ hasText: 'Original theme styles restored. Named sets are kept.' }).waitFor();
        assert.equal(posts.at(-1).op, 'reset');
        assert.equal(posts.at(-1).revision, 'live-1');
        await app.getByLabel('JSON file', { exact: true }).setInputFiles({ name: 'set.json', mimeType: 'application/json', buffer: Buffer.from('{"format":"test","rules":[]}') });
        await app.getByRole('button', { name: 'Review import', exact: true }).click();
        await app.getByText('Review this warning', { exact: true }).waitFor();
        assert.equal(posts.at(-1).op, 'previewImport', 'Import review does not save or activate');
        await app.getByRole('button', { name: 'Import named set', exact: true }).click();
        await app.getByRole('status').filter({ hasText: 'Named set imported. It has not been activated.' }).waitFor();
        assert.equal(posts.at(-1).op, 'importSet');
        stale = true;
        page.once('dialog', dialog => dialog.accept());
        await popular.getByRole('button', { name: 'Activate', exact: true }).click();
        await app.getByRole('status').filter({ hasText: 'Settings changed elsewhere. Reload and review before trying again.' }).waitFor();
        assert.equal(posts.filter(post => post.op === 'activate').length, 2, 'Conflicts never retry an overwrite');
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await app.evaluate(el => el.scrollWidth <= el.clientWidth), true, 'Narrow layout contains table overflow');
        await app.evaluate(el => { el.dir = 'rtl'; });
        assert.equal(await app.getByLabel('Theme', { exact: true }).isVisible(), true);
        if (process.env.THEME_SETS_SCREENSHOT) {
            sets[0].name = 'Ocean'; sets[0].author.name = 'Admin';
            await app.evaluate(el => { el.dir = 'ltr'; });
            await page.setViewportSize({ width: 1440, height: 1000 });
            await app.getByRole('button', { name: 'Reload library', exact: true }).click();
            await app.getByLabel('Show sample rows', { exact: true }).check();
            await page.screenshot({ path: process.env.THEME_SETS_SCREENSHOT });
        }
        assert.deepEqual(errors, []);
        console.log('Theme set admin: categories, sorting, safe text, preview links, confirmed writes, import review and conflicts passed.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
