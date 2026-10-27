/* Uses the installed browser runner; no module server or PHP bootstrap required. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<!doctype html><style>
            :root { --xtf-color-primary: #2563eb; --font: system-ui; --gap: 12px; }
            body { background: white; color: black; }
            .parent { --local: #123456; background: transparent; }
            [data-theme=dark], [data-bs-theme=dark] { --xtf-color-primary: #abcdef; }
            #sample { background: transparent; }
        </style><div class="parent"><p id="sample">Text sample</p></div>`);
        await page.addScriptTag({ path: path.join(__dirname, '../assets/js/designer-diagnostics.js') });
        const inspect = () => page.evaluate(() => CssHolmesDiagnostics.inspect(document.querySelector('#sample')));
        const style = value => page.evaluate(value => document.querySelector('#sample').style.cssText = value, value);
        let result = await inspect();
        assert.equal(result.contrast.status, 'measured');
        assert.equal(result.contrast.ratio, 21);
        assert.ok(result.tokens.some(token => token.name === '--local' && token.value === '#123456'));
        assert.ok(result.tokens.some(token => token.name === '--xtf-color-primary' && token.value === '#2563eb'));
        await style('color: white');
        assert.equal((await inspect()).contrast.ratio, 1);
        await style('color: #767676');
        assert.ok((await inspect()).contrast.ratio >= 4.5);
        await style('color: #777777');
        assert.ok((await inspect()).contrast.ratio < 4.5);
        await style('color: rgb(115,120,116)');
        assert.ok((await inspect()).contrast.ratio < 4.5);
        assert.equal((await inspect()).contrast.ratio.toFixed(2), '4.50');
        await style('color: rgba(0,0,0,.5)');
        assert.ok(Math.abs((await inspect()).contrast.ratio - 3.9766530249) < 1e-8);
        await style('color: black; background: rgba(0,0,0,.5)');
        assert.ok(Math.abs((await inspect()).contrast.ratio - 5.2808228096) < 1e-8);
        for (const value of ['background: linear-gradient(white,black)', 'opacity: .5', 'filter: brightness(.8)', 'mix-blend-mode: multiply', 'color: color(display-p3 1 0 0)', 'background-clip: text', 'box-shadow: inset 0 0 10px black', 'text-shadow: 1px 1px black']) {
            await style(value);
            assert.equal((await inspect()).contrast.status, 'unknown', value);
        }
        await style('background: white');
        await page.evaluate(() => document.querySelector('.parent').style.opacity = '.5');
        assert.equal((await inspect()).contrast.status, 'unknown', 'Ancestor effects matter even behind an opaque child');
        await page.evaluate(() => document.querySelector('.parent').style.opacity = '1');
        await style('');
        await page.evaluate(() => document.body.style.background = 'transparent');
        assert.equal((await inspect()).contrast.status, 'unknown', 'No assumed white canvas');
        await page.evaluate(() => { document.body.style.background = 'white'; document.documentElement.dataset.theme = 'dark'; });
        assert.ok((await inspect()).tokens.some(token => token.name === '--xtf-color-primary' && token.value === '#abcdef'));
        await page.evaluate(() => {
            window.sheetReads = 0;
            const sheet = document.styleSheets[0], rules = sheet.cssRules;
            Object.defineProperty(sheet, 'cssRules', { configurable: true, get() { window.sheetReads++; return rules; } });
            CssHolmesDiagnostics.refresh();
        });
        await inspect();
        await style('color: #123456');
        await inspect();
        assert.equal(await page.evaluate(() => window.sheetReads), 1, 'Property previews reuse discovered names');
        await page.evaluate(() => {
            const sheet = document.styleSheets[0];
            Object.defineProperty(sheet, 'cssRules', { configurable: true, get() { throw new DOMException('Blocked', 'SecurityError'); } });
            CssHolmesDiagnostics.refresh();
        });
        assert.equal((await inspect()).discoveryIncomplete, true, 'Blocked CSS is reported, never fetched');
        await page.evaluate(() => {
            const overlay = document.createElement('div');
            overlay.id = 'overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5)';
            document.body.append(overlay);
        });
        assert.equal((await inspect()).contrast.status, 'unknown', 'Detected overlapping paint needs manual review');
        await page.evaluate(() => document.querySelector('#overlay').remove());
        await page.evaluate(() => document.querySelector('#sample').append(document.createElement('span')));
        assert.equal((await inspect()).contrast.status, 'unknown', 'Even empty child elements can paint over text');
        await page.evaluate(() => document.querySelector('#sample span').remove());
        await page.evaluate(() => {
            delete document.styleSheets[0].cssRules;
            document.styleSheets[0].insertRule('#sample::before { content: ""; position: absolute; inset: 0; background: red; }');
            CssHolmesDiagnostics.refresh();
        });
        assert.equal((await inspect()).contrast.status, 'unknown', 'Generated layers are not silently ignored');
        assert.deepEqual(errors, []);
        console.log('Diagnostics passed: numeric contrast, alpha, inherited/XTF tokens, mode, inaccessible sheets and conservative unknown cases.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
