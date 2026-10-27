/* Exercise expiring editor tokens without waiting or changing the site's login policy. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        page.setDefaultTimeout(5000);
        let authenticated = true, tokenValid = false, tokenNumber = 0, currentToken = '';
        let state = { theme: 'demo', revision: 'original', rules: [], canRestore: false };
        const posts = [];
        const response = () => {
            currentToken = `token-${++tokenNumber}`;
            tokenValid = true;
            return { ok: true, token: currentToken, canSave: true, canExport: true, state, library: { themes: ['demo'], sets: [], palettes: [] } };
        };
        await page.route('https://session.test/**', async route => {
            const request = route.request(), url = new URL(request.url());
            if (url.pathname === '/api' || url.pathname === '/sets') {
                if (!authenticated) return route.fulfill({ status: 403, json: { ok: false, error: 'Sign in again. Your draft is kept.' } });
                if (request.method() === 'POST') {
                    const body = request.postDataJSON();
                    posts.push(body);
                    if (!tokenValid || body.token !== currentToken) return route.fulfill({ status: 403, json: { ok: false, error: 'The editor session expired.' } });
                    if (url.pathname === '/api') {
                        if (body.revision !== state.revision) return route.fulfill({ status: 409, json: { ok: false, error: 'This theme changed elsewhere.' } });
                        state = { ...state, revision: `saved-${posts.length}`, rules: body.rules };
                    }
                    if (body.op === 'saveSet') return route.fulfill({ json: { ...response(), set: { id: 'new', revision: 'created' } } });
                }
                return route.fulfill({ json: response() });
            }
            if (url.pathname === '/designer.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'assets/css/designer.css'), 'utf8') });
            if (url.pathname === '/saved.css') return route.fulfill({ contentType: 'text/css', body: '' });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body><h4 class="comTitle">Comment title</h4><script type="application/json" id="cssholmes-designer-config">${JSON.stringify({ theme: 'demo', endpoint: '/api', libraryEndpoint: '/sets', stylesheetUrl: '/designer.css', styleUrl: '/saved.css', canSave: true, canExport: true })}</script></body></html>` });
        });
        await page.goto('https://session.test/');
        await page.addScriptTag({ path: path.join(root, 'assets/js/designer.js') });
        await page.evaluate(() => window.CssHolmesDesigner.select(document.querySelector('.comTitle')));
        const panel = page.locator('#cssholmes-designer');
        const size = panel.getByLabel('Font size', { exact: true });
        await size.fill('31px');
        tokenValid = false;
        const firstToken = tokenNumber;
        await panel.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Saved to theme.', null, { timeout: 3000 });
        assert.ok(tokenNumber > firstToken, 'An expired editor token must be renewed before saving');
        assert.equal(posts.length, 1, 'Saving must not submit and retry an expired write');
        assert.equal(posts[0].rules[0].declarations['font-size'], '31px', 'Refreshing must preserve the local draft');
        assert.equal(posts[0].revision, 'original', 'Refreshing must preserve the draft baseline revision');

        await size.fill('32px');
        await panel.getByLabel('Set name', { exact: true }).fill('Long editing session');
        tokenValid = false;
        await panel.getByRole('button', { name: 'Save as new set', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('#cssholmes-designer').shadowRoot.querySelector('[data-status]').textContent === 'Set saved in library. Theme unchanged.');
        assert.equal(posts.at(-1).op, 'saveSet');
        assert.equal(posts.at(-1).rules[0].declarations['font-size'], '32px');
        assert.equal(state.rules[0].declarations['font-size'], '31px', 'Renewing library access must not publish a draft');

        const baselineRevision = state.revision;
        state = { ...state, revision: 'another-editor' };
        tokenValid = false;
        await panel.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: 'changed elsewhere' }).waitFor();
        assert.equal(posts.at(-1).revision, baselineRevision, 'Fresh tokens must never bypass concurrent-save protection');
        assert.equal(await size.inputValue(), '32px', 'Conflicts preserve the draft');

        await size.fill('33px');
        authenticated = false;
        const beforeLogoutSave = posts.length;
        await panel.getByRole('button', { name: 'Save to theme', exact: true }).click();
        await panel.getByRole('alert').filter({ hasText: 'Sign in again' }).waitFor();
        assert.equal(posts.length, beforeLogoutSave, 'A logged-out user must never submit a write');
        assert.equal(await size.inputValue(), '33px', 'Login expiry preserves the draft');
        console.log('Designer sessions: expired tokens refresh before theme/library saves; drafts, revisions and login checks remain intact.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
