/* Uses an existing Playwright installation; the library never publishes previews. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage({ viewport: { width: 320, height: 800 } });
        page.setDefaultTimeout(5000);
        const rule = size => ({ selector: 'p.sample', label: 'Sample', mode: 'all', breakpoint: 'all', state: 'normal', declarations: { 'font-size': size } });
        let state = { theme: 'test', revision: 1, rules: [rule('22px')], canRestore: true };
        const sets = [{ id: 'one', theme: 'test', name: 'First', author: { id: 1, name: 'Owner' }, likes: 0, liked: false, revision: 1, editable: true }, { id: 'other', theme: 'other', name: 'Elsewhere', author: { id: 2, name: 'Other' }, likes: 3, liked: false, revision: 1, editable: false }];
        const portable = { format: 'cssholmes-theme-set', version: 1, name: 'Portable', sourceTheme: 'test', rules: [rule('30px')] };
        const posts = [], publications = [];
        let initialSetId = '', canSave = true;
        const setRules = new Map(sets.map(set => [set.id, [rule('26px')]]));
        const base = () => ({ ok: true, token: 'shared-token', canSave, canExport: canSave, state, library: { themes: ['test', 'other'], sets, palettes: [{ id: 'palette-calm', name: 'Calm', colors: ['#ffffff', '#111111'], contrast: [{ name: 'Text', ratio: 18.8, pass: true }] }] } });
        const handleRoute = async route => {
            const request = route.request(), url = new URL(request.url());
            if (url.pathname === '/api') {
                if (request.method() === 'POST') publications.push(request.postDataJSON());
                return route.fulfill({ json: base() });
            }
            if (url.pathname === '/library') {
                let result = base();
                if (request.method() === 'POST') {
                    const body = request.postDataJSON();
                    posts.push(body);
                    assert.equal(body.token, 'shared-token');
                    if (body.op === 'saveSet') {
                        result.set = { ...sets[0], id: 'new', name: body.name };
                        sets.push(result.set);
                        setRules.set(result.set.id, body.rules);
                    }
                    if (body.op === 'updateSet') {
                        const selected = sets.find(set => set.id === body.id);
                        if (body.setRevision !== selected.revision) return route.fulfill({ status: 409, json: { ok: false, token: 'shared-token', error: 'Set changed elsewhere.' } });
                        selected.revision += 1;
                        selected.name = body.name;
                        setRules.set(selected.id, body.rules);
                        result.set = selected;
                    }
                    if (body.op === 'like') { sets[0].liked = body.liked; sets[0].likes = body.liked ? 1 : 0; }
                    if (body.op === 'exportCurrent') result.document = { ...portable, rules: body.rules };
                    if (body.op === 'previewImport') result.preview = { rules: portable.rules, warnings: [], document: portable };
                    if (body.op === 'reset') { assert.equal(body.revision, state.revision); state = { ...state, revision: state.revision + 1, rules: [] }; result.state = state; }
                } else if (url.searchParams.get('action') === 'preview') {
                    const selected = sets.find(set => set.id === url.searchParams.get('id'));
                    result.preview = { rules: setRules.get(selected.id), warnings: selected.id === 'other' ? ['Skipped custom selector from another theme.'] : [], document: portable, set: { id: selected.id, revision: selected.revision } };
                }
                return route.fulfill({ json: result });
            }
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            if (url.pathname === '/saved.css') return route.fulfill({ contentType: 'text/css', body: state.rules.length ? '.sample{font-size:22px!important}' : '' });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><style>.sample{font-size:18px}</style><link id="cssholmes-theme-style" rel="stylesheet" href="/saved.css"></head><body><p class="sample">Sample</p><script id="cssholmes-designer-config" type="application/json">${JSON.stringify({ theme: 'test', endpoint: '/api', libraryEndpoint: '/library', styleUrl: '/saved.css', stylesheetUrl: '/designer.css', canSave: true, canExport: true, initialSetId })}</script></body></html>` });
        };
        await page.route('https://sets.test/**', handleRoute);
        await page.goto('https://sets.test/');
        await page.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await page.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.sample')));
        const panel = page.locator('#cssholmes-designer');
        const name = panel.getByLabel('Set name', { exact: true });
        const select = panel.getByLabel('Style sets', { exact: true });
        await name.waitFor();
        if (process.env.DESIGNER_SETS_SCREENSHOT) await page.screenshot({ path: process.env.DESIGNER_SETS_SCREENSHOT });
        assert.deepEqual(await select.locator('optgroup').evaluateAll(groups => groups.map(group => group.label)), ['Current theme', 'Other themes', 'Starter palettes']);
        const field = panel.locator('[data-property="font-size"]');
        const size = () => page.locator('.sample').evaluate(el => getComputedStyle(el).fontSize);
        await field.fill('30px');
        await name.fill('My draft');
        await panel.getByRole('button', { name: 'Save as new set', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Set saved in library. Theme unchanged.');
        assert.equal(publications.length, 0);
        assert.equal(state.rules[0].declarations['font-size'], '22px');
        await select.selectOption('one');
        page.once('dialog', dialog => dialog.dismiss());
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        assert.equal(await field.inputValue(), '30px', 'Cancel preserves draft');
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '26px');
        await field.fill('broken');
        page.once('dialog', dialog => dialog.dismiss());
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        assert.equal(await field.inputValue(), 'broken', 'Cancel preserves invalid controls too');
        await field.fill('26px');
        await panel.getByLabel('Compare original', { exact: true }).check();
        assert.equal(await size(), '18px', 'Compare disables persisted overrides too');
        await panel.getByLabel('Compare original', { exact: true }).uncheck();
        assert.equal(await size(), '26px');
        // Another editor saves while this tab retains its original preview and draft.
        sets[0].revision = 2;
        setRules.set('one', [rule('34px')]);
        await panel.getByRole('button', { name: 'Like', exact: true }).click();
        await panel.getByRole('button', { name: 'Unlike', exact: true }).waitFor();
        assert.equal(sets[0].likes, 1);
        await select.selectOption('new');
        await select.selectOption('one');
        await panel.getByRole('button', { name: 'Update selected set', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: 'Set changed elsewhere' }).waitFor();
        assert.equal(posts.filter(post => post.op === 'updateSet').at(-1).setRevision, 1, 'Metadata refresh and switching selections retain the original editing revision');
        assert.equal(await field.inputValue(), '26px', 'Conflicts preserve the local draft');
        assert.equal(setRules.get('one')[0].declarations['font-size'], '34px', 'The other editor\'s saved rules survive');
        await panel.getByRole('button', { name: 'Unlike', exact: true }).click();
        await panel.getByRole('button', { name: 'Like', exact: true }).waitFor();
        assert.equal(sets[0].likes, 0);
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '34px');
        for (const size of ['35px', '36px']) {
            await field.fill(size);
            await panel.getByRole('button', { name: 'Update selected set', exact: true }).click();
            await panel.locator('[data-status]').filter({ hasText: 'Set saved in library. Theme unchanged.' }).waitFor();
            assert.equal(setRules.get('one')[0].declarations['font-size'], size, 'Preview and successful update advance the editing revision');
        }
        await select.selectOption('new');
        await field.fill('29px');
        await panel.getByRole('button', { name: 'Update selected set', exact: true }).click();
        await panel.locator('[data-status]').filter({ hasText: 'Set saved in library. Theme unchanged.' }).waitFor();
        assert.equal(setRules.get('new')[0].declarations['font-size'], '29px', 'An explicitly selected set can still be replaced without previewing it');
        await select.selectOption('one');
        const transfer = panel.locator('[data-library-transfer]');
        await transfer.locator('summary').click();
        const downloadPromise = page.waitForEvent('download');
        await panel.getByRole('button', { name: 'Export portable set', exact: true }).click();
        const stream = await (await downloadPromise).createReadStream(), chunks = [];
        for await (const chunk of stream) chunks.push(chunk);
        assert.equal(JSON.parse(Buffer.concat(chunks).toString()).format, portable.format);
        await panel.locator('.more summary').click();
        const moreDownload = page.waitForEvent('download');
        await panel.getByRole('button', { name: 'Export JSON', exact: true }).click();
        const moreStream = await (await moreDownload).createReadStream(), moreChunks = [];
        for await (const chunk of moreStream) moreChunks.push(chunk);
        assert.equal(JSON.parse(Buffer.concat(moreChunks).toString()).format, portable.format, 'Both JSON export buttons use the importable standard');
        const file = panel.getByLabel('Import set JSON', { exact: true });
        await file.setInputFiles({ name: 'huge.json', mimeType: 'application/json', buffer: Buffer.alloc(128 * 1024 + 1, 32) });
        await panel.getByRole('button', { name: 'Preview import', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: '128 KiB' }).waitFor();
        assert.equal(posts.filter(post => post.op === 'previewImport').length, 0);
        await file.setInputFiles({ name: 'bad.json', mimeType: 'application/json', buffer: Buffer.from('{broken') });
        await panel.getByRole('button', { name: 'Preview import', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: 'valid JSON' }).waitFor();
        await file.setInputFiles({ name: 'set.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify(portable)) });
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Preview import', exact: true }).click();
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '30px');
        assert.equal(posts.filter(post => post.op === 'importSet').length, 0, 'Import preview is read-only');
        await panel.getByRole('button', { name: 'Save imported set', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Set saved in library. Theme unchanged.');
        assert.equal(posts.filter(post => post.op === 'importSet').length, 1);
        await select.selectOption('other');
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Preview selected', exact: true }).click();
        await panel.locator('[data-library-warnings]').filter({ hasText: 'Skipped custom selector' }).waitFor();
        assert.equal(await panel.getByRole('button', { name: 'Update selected set', exact: true }).isDisabled(), true);
        await panel.locator('[data-library-original] summary').click();
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Preview original defaults', exact: true }).click();
        assert.equal(await size(), '18px');
        assert.equal(state.rules.length, 1, 'Original preview does not publish');
        page.once('dialog', dialog => dialog.dismiss());
        await panel.getByRole('button', { name: 'Reset theme to original', exact: true }).click();
        assert.equal(state.rules.length, 1);
        page.once('dialog', dialog => dialog.accept());
        await panel.getByRole('button', { name: 'Reset theme to original', exact: true }).click();
        await panel.locator('[data-status]').filter({ hasText: 'Theme reset to original. Library sets kept.' }).waitFor();
        assert.equal(state.rules.length, 0);
        assert.equal(sets.length, 3, 'Permanent reset keeps library sets');
        assert.equal(publications.length, 0);
        assert.ok(await panel.locator('[data-panel]').evaluate(el => el.scrollWidth <= 320));
        await select.selectOption('palette-calm');
        assert.equal(await select.locator('option[value="palette-calm"] .palette-option-swatches span').count(), 2, 'Palette choices should carry their own swatches');
        if (await page.evaluate(() => CSS.supports('appearance', 'base-select'))) {
            await select.locator('selectedcontent .palette-option-swatches span').first().waitFor();
            assert.equal(await select.locator('selectedcontent .palette-option-swatches span').count(), 2, 'The selected palette should show its swatches too');
            assert.equal(await select.locator('selectedcontent .palette-option-swatches span').first().evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(255, 255, 255)');
        }
        assert.equal(await panel.locator('.palette-swatches span').count(), 2);
        assert.match(await panel.locator('.library').textContent(), /Text: 18.8:1 · Pass/);
        initialSetId = 'other';
        canSave = false;
        const linkedPage = await browser.newPage();
        await linkedPage.route('https://sets.test/**', handleRoute);
        await linkedPage.goto('https://sets.test/');
        await linkedPage.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await linkedPage.evaluate(() => window.CssHolmesDesigner.open());
        await linkedPage.waitForFunction(() => getComputedStyle(document.querySelector('.sample')).fontSize === '26px');
        assert.equal(await linkedPage.locator('#cssholmes-designer').getByLabel('Style sets', { exact: true }).inputValue(), 'other');
        assert.equal(state.rules.length, 0, 'Set link previews without publishing');
        const linkedPanel = linkedPage.locator('#cssholmes-designer');
        await linkedPanel.getByLabel('Style sets', { exact: true }).selectOption('one');
        assert.equal(await linkedPanel.getByRole('button', { name: 'Like', exact: true }).isEnabled(), true, 'Preview-only designers can vote');
        await linkedPanel.getByRole('button', { name: 'Like', exact: true }).click();
        await linkedPanel.getByRole('button', { name: 'Unlike', exact: true }).waitFor();
        assert.equal(await linkedPanel.getByRole('button', { name: 'Save to theme', exact: true }).isDisabled(), true, 'Voting does not grant publishing');
        initialSetId = 'original';
        const originalPage = await browser.newPage();
        await originalPage.route('https://sets.test/**', handleRoute);
        await originalPage.goto('https://sets.test/');
        await originalPage.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await originalPage.evaluate(() => window.CssHolmesDesigner.open());
        await originalPage.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Original theme previewed. Use Save to theme to publish.');
        assert.equal(await originalPage.locator('.sample').evaluate(el => getComputedStyle(el).fontSize), '18px');
        assert.equal(await originalPage.locator('#cssholmes-designer').getByLabel('Set name', { exact: true }).inputValue(), 'Original theme');
        console.log('Designer library checks passed: separate saves, previews, discard cancel, original comparison/reset, conflicts, likes, portable export, bounded import and cross-theme warnings.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
