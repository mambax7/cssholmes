/* Named sets remain independent of a theme's active styles until explicitly activated. */
(() => {
    'use strict';
    const root = document.getElementById('cssholmes-theme-sets');
    const boot = document.getElementById('cssholmes-theme-sets-config');
    if (!root || !boot) return;
    let config;
    try { config = JSON.parse(boot.textContent); } catch (_) { return; }
    if (!config.libraryEndpoint || !config.previewUrl) return;
    const t = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`{${name}}`, String(value)), config.labels?.[key] || key);
    const el = (tag, text, attrs = {}) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);
        return node;
    };
    const button = (text, action) => {
        const node = el('button', text, { type: 'button' });
        node.addEventListener('click', () => run(action));
        return node;
    };
    const field = (label, node) => {
        const wrap = el('label', undefined, { class: 'chs-field' });
        wrap.append(el('span', label), node);
        return wrap;
    };
    let library = { themes: [], sets: [], palettes: [] };
    let canSave = false;
    let busy = false;
    let pendingImport = null;
    let importGeneration = 0;
    const status = el('p', t('loading'), { role: 'status', 'aria-live': 'polite', class: 'chs-status' });
    const controls = el('div', undefined, { class: 'chs-controls' });
    const themeFilter = el('select', undefined, { 'aria-label': t('theme') });
    const nameFilter = el('input', undefined, { type: 'search', placeholder: t('filter_hint') });
    const sort = el('select', undefined, { 'aria-label': t('sort') });
    for (const [value, key] of [['name', 'sort_name'], ['modified', 'sort_modified'], ['likes', 'sort_likes']]) sort.append(el('option', t(key), { value }));
    const table = el('table', undefined, { class: 'outer' });
    const showSamples = el('input', undefined, { type: 'checkbox' });
    showSamples.checked = true;
    const sampleLabel = el('label', undefined, { class: 'chs-sample-toggle' });
    sampleLabel.append(showSamples, document.createTextNode(t('show_samples')));
    const sampleNote = el('p', t('sample_note'), { class: 'chs-sample-note' });
    const head = el('thead');
    const header = el('tr');
    for (const key of ['name', 'author', 'created', 'modified', 'likes', 'actions']) header.append(el('th', t(key), { scope: 'col' }));
    head.append(header);
    table.append(el('caption', t('table_caption')), head);
    const tableWrap = el('div', undefined, { class: 'chs-table-wrap', tabindex: '0', role: 'region', 'aria-label': t('table_caption') });
    tableWrap.append(table);
    const palettes = el('section', undefined, { class: 'chs-palettes', 'aria-label': t('palettes') });
    const paletteTheme = el('select', undefined, { 'aria-label': t('palette_theme') });
    const paletteList = el('div', undefined, { class: 'chs-palette-list' });
    palettes.append(el('h2', t('palettes')), el('p', t('palette_note')), field(t('palette_theme'), paletteTheme), paletteList);
    const importBox = el('section', undefined, { class: 'chs-import', 'aria-label': t('import_title') });
    const importTheme = el('select');
    const importName = el('input', undefined, { type: 'text', maxlength: '80' });
    const file = el('input', undefined, { type: 'file', accept: '.json,application/json' });
    const review = el('div', undefined, { class: 'chs-import-review' });
    const importConfirm = button(t('import_confirm'), async () => {
        if (!pendingImport || pendingImport.theme !== importTheme.value) return;
        await mutate(importTheme.value, 'importSet', { document: pendingImport.document, name: importName.value.trim() });
        clearImport();
        file.value = '';
        await reload();
        status.textContent = t('imported');
    });
    importConfirm.hidden = true;
    importBox.append(el('h2', t('import_title')), el('p', t('import_note')), field(t('import_theme'), importTheme), field(t('import_name'), importName), field(t('json_file'), file), button(t('review_import'), async () => {
        clearImport();
        const selected = file.files[0];
        if (!selected || !importTheme.value) throw new Error(t('choose_import'));
        if (selected.size > 131072) throw new Error(t('too_large'));
        const theme = importTheme.value;
        const generation = importGeneration;
        let document;
        try { document = JSON.parse(await selected.text()); } catch (_) { throw new Error(t('invalid_json')); }
        if (generation !== importGeneration) return;
        const response = await mutate(theme, 'previewImport', { document });
        if (generation !== importGeneration) return;
        const preview = response.preview;
        if (!preview?.document || !Array.isArray(preview.rules)) throw new Error(t('request_failed'));
        pendingImport = { theme, document: preview.document };
        review.append(el('p', t('rule_count', { count: preview.rules.length })));
        if (preview.warnings?.length) {
            const warnings = el('ul');
            for (const warning of preview.warnings) warnings.append(el('li', warning));
            review.append(warnings);
        }
        importConfirm.hidden = false;
        status.textContent = t('review_ready');
    }), review, importConfirm);
    controls.append(field(t('theme'), themeFilter), field(t('filter'), nameFilter), field(t('sort'), sort), button(t('reload'), async () => { await reload(); status.textContent = t('ready'); }), sampleLabel);
    root.replaceChildren(el('h1', t('title')), el('p', t('intro')), status, controls, sampleNote, tableWrap, palettes, importBox);

    function clearImport() {
        importGeneration += 1;
        pendingImport = null;
        review.replaceChildren();
        importConfirm.hidden = true;
    }
    file.addEventListener('change', clearImport);
    importTheme.addEventListener('change', clearImport);
    for (const control of [themeFilter, sort]) control.addEventListener('change', renderTable);
    nameFilter.addEventListener('input', renderTable);
    showSamples.addEventListener('change', () => { sampleNote.hidden = !showSamples.checked; renderTable(); });
    paletteTheme.addEventListener('change', renderPalettes);

    async function request(query = {}, data = null) {
        const url = new URL(config.libraryEndpoint, window.location.href);
        for (const [key, value] of Object.entries(query)) url.searchParams.set(key, value);
        const response = await fetch(url, {
            method: data ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: data ? { 'Content-Type': 'application/json', Accept: 'application/json' } : { Accept: 'application/json' },
            body: data ? JSON.stringify(data) : undefined
        });
        let result;
        try { result = await response.json(); } catch (_) { throw new Error(t('request_failed')); }
        if (response.status === 409) throw new Error(t('conflict'));
        if (!response.ok || !result.ok) throw new Error(result.error || t('request_failed'));
        return result;
    }
    async function mutate(theme, op, data, confirmKey) {
        const fresh = await request({ theme });
        if (confirmKey && !window.confirm(t(confirmKey, { theme, name: data.name || '' }))) return null;
        return request({}, { ...data, theme, op, token: fresh.token, ...(fresh.state ? { revision: fresh.state.revision } : {}) });
    }
    async function run(action) {
        if (busy) return;
        busy = true;
        root.setAttribute('aria-busy', 'true');
        status.textContent = t('loading');
        try { await action(); } catch (error) { status.textContent = error.message || t('request_failed'); }
        finally { busy = false; root.setAttribute('aria-busy', 'false'); }
    }
    async function reload() {
        const response = await request();
        library = response.library;
        if (!Array.isArray(library?.themes) || !Array.isArray(library.sets)) throw new Error(t('request_failed'));
        canSave = response.canSave === true;
        for (const [select, includeAll] of [[themeFilter, true], [paletteTheme, false], [importTheme, false]]) {
            const previous = select.value;
            select.replaceChildren();
            if (includeAll) select.append(el('option', t('all_themes'), { value: '' }));
            for (const theme of library.themes) select.append(el('option', theme, { value: theme }));
            if ([...select.options].some(option => option.value === previous)) select.value = previous;
        }
        importBox.hidden = !canSave;
        renderTable();
        renderPalettes();
    }
    function previewLink(theme, id, label = 'preview') {
        const url = new URL(config.previewUrl, window.location.href);
        for (const [key, value] of Object.entries({ section: 'all', designer: '1', xoops_theme_select: theme, theme_set: id })) url.searchParams.set(key, value);
        return el('a', t(label), { href: url.href, target: '_blank', rel: 'noopener', title: t(label === 'edit' ? 'edit_new_tab' : 'preview_new_tab') });
    }
    function timestamp(value) {
        return new Date(typeof value === 'number' ? value * 1000 : value).getTime();
    }
    function date(value) {
        const parsed = new Date(timestamp(value));
        if (!Number.isFinite(parsed.getTime())) return '—';
        return parsed.toLocaleString(document.documentElement.lang || undefined);
    }
    function renderTable() {
        for (const body of [...table.tBodies]) body.remove();
        const filter = nameFilter.value.toLocaleLowerCase();
        const themes = library.themes.filter(theme => !themeFilter.value || themeFilter.value === theme);
        for (const theme of themes) {
            const body = el('tbody');
            const category = el('tr', undefined, { class: 'chs-category' });
            const categoryCell = el('th', undefined, { scope: 'rowgroup', colspan: '6' });
            categoryCell.append(el('span', theme));
            if (canSave) categoryCell.append(button(t('reset'), async () => {
                const response = await mutate(theme, 'reset', {}, 'reset_confirm');
                if (!response) { status.textContent = t('cancelled'); return; }
                await reload(); status.textContent = t('reset_done');
            }));
            category.append(categoryCell);
            body.append(category);
            const sets = library.sets.filter(set => set.theme === theme && set.name.toLocaleLowerCase().includes(filter));
            if (showSamples.checked && !library.sets.some(set => set.theme === theme) && t('sample_name').toLocaleLowerCase().includes(filter)) {
                sets.push({ id: 'original', theme, name: t('sample_name'), author: { name: 'Admin' }, created: '2026-10-01T12:00:00Z', modified: '2026-10-20T15:30:00Z', likes: 5 + [...theme].reduce((sum, letter) => sum + letter.codePointAt(0), 0) % 80, liked: false, sample: true });
            }
            sets.sort((a, b) => {
                if (sort.value === 'likes') return b.likes - a.likes || a.name.localeCompare(b.name);
                if (sort.value === 'modified') return timestamp(b.modified) - timestamp(a.modified) || a.name.localeCompare(b.name);
                return a.name.localeCompare(b.name);
            });
            for (const set of sets) {
                const row = el('tr', undefined, { class: body.querySelectorAll('.chs-set-row').length % 2 ? 'chs-set-row even' : 'chs-set-row odd', ...(set.sample ? { 'data-sample': '' } : {}) });
                const name = el('th', set.name, { scope: 'row' });
                if (set.sample) name.append(el('small', t('sample'), { class: 'chs-sample-badge' }));
                row.append(name, el('td', set.author?.name || '—'), el('td', date(set.created), { class: 'chs-date' }), el('td', date(set.modified), { class: 'chs-date' }));
                const likes = el('td');
                const like = button(t(set.liked ? 'unlike' : 'like'), async () => {
                    await mutate(theme, 'like', { id: set.id, liked: !set.liked });
                    await reload(); status.textContent = t('liked');
                });
                like.setAttribute('aria-pressed', String(set.liked === true));
                if (set.sample) { like.disabled = true; like.title = t('sample_readonly'); }
                likes.append(el('span', String(set.likes), { class: 'chs-like-count' }), like);
                const actionsCell = el('td');
                const actions = el('div', undefined, { class: 'chs-actions' });
                if (canSave && (set.editable || set.sample)) actions.append(previewLink(theme, set.id, 'edit'));
                actions.append(previewLink(theme, set.id), button(t('export'), async () => {
                    const response = await request({ theme, action: 'export', id: set.id });
                    const url = URL.createObjectURL(new Blob([JSON.stringify(response.exported, null, 2)], { type: 'application/json' }));
                    const link = el('a', undefined, { href: url, download: `${theme}-${set.id}.json` });
                    root.append(link); link.click(); link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    status.textContent = t('exported');
                }));
                if (canSave) actions.append(button(t('clone'), async () => {
                    const name = window.prompt(t('clone_prompt'), t('copy_name', { name: [...set.name].slice(0, 70).join('') }));
                    if (name === null) { status.textContent = t('cancelled'); return; }
                    if (!name.trim()) throw new Error(t('name_required'));
                    await mutate(theme, 'cloneSet', { id: set.id, name: name.trim() });
                    await reload(); status.textContent = t('cloned');
                }));
                if (canSave && !set.sample) actions.append(button(t('activate'), async () => {
                    const response = await mutate(theme, 'activate', { id: set.id, name: set.name }, 'activate_confirm');
                    if (!response) { status.textContent = t('cancelled'); return; }
                    await reload(); status.textContent = t('activated');
                }));
                if (canSave && (set.editable || set.sample)) {
                    const remove = button(t('delete'), async () => {
                        if (set.sample) return;
                        const response = await mutate(theme, 'deleteSet', { id: set.id, setRevision: set.revision, name: set.name }, 'delete_confirm');
                        if (!response) { status.textContent = t('cancelled'); return; }
                        await reload(); status.textContent = t('deleted');
                    });
                    remove.disabled = set.sample === true;
                    if (set.sample) remove.title = t('sample_readonly');
                    actions.append(remove);
                }
                actionsCell.append(actions);
                row.append(likes, actionsCell);
                body.append(row);
            }
            if (!sets.length) {
                const row = el('tr');
                row.append(el('td', t(filter ? 'no_matches' : 'empty'), { colspan: '6' }));
                body.append(row);
            }
            table.append(body);
        }
        if (!themes.length) {
            const body = el('tbody');
            const row = el('tr');
            row.append(el('td', t('no_themes'), { colspan: '6' }));
            body.append(row); table.append(body);
        }
    }
    function renderPalettes() {
        paletteList.replaceChildren();
        for (const palette of library.palettes || []) {
            const article = el('article', undefined, { class: 'chs-palette' });
            article.append(el('h3', palette.name));
            const swatches = el('div', undefined, { class: 'chs-swatches' });
            for (const color of palette.colors || []) {
                if (!/^#[\da-f]{6}$/i.test(color)) continue;
                const swatch = el('span', color);
                swatch.style.setProperty('--chs-swatch', color);
                swatches.append(swatch);
            }
            article.append(swatches);
            const pairs = el('ul');
            for (const pair of palette.contrast || []) pairs.append(el('li', `${pair.name}: ${Number(pair.ratio).toFixed(2)}:1 — ${t(pair.pass ? 'pair_pass' : 'pair_fail')}`));
            article.append(pairs);
            if (paletteTheme.value) article.append(previewLink(paletteTheme.value, palette.id));
            paletteList.append(article);
        }
    }
    run(async () => { await reload(); status.textContent = t('ready'); });
})();
