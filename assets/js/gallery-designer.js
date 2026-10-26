/* Private gallery previews: CSS stays in this tab and its owned same-origin frames. */
(() => {
    'use strict';
    const gallery = document.querySelector('#xs-gallery[data-xs-workspace]');
    const designer = window.CssHolmesDesigner;
    if (!gallery || typeof designer?.draft !== 'function' || /\/admin\//.test(location.pathname)) return;
    let config, base;
    try {
        config = JSON.parse(document.getElementById('cssholmes-designer-config').textContent);
        base = new URL(gallery.dataset.xsPreviewUrl, location.href);
    } catch { return; }
    if (base.origin !== location.origin || !/\/xstyles\/index\.php$/.test(base.pathname)
        || base.searchParams.get('preview_frame') !== '1' || gallery.dataset.xsTheme !== config.theme) return;
    const defaults = {
        gallery_title: 'Preview your changes', gallery_note: 'Draft previews stay in this tab. Only Save to theme publishes changes for visitors.',
        gallery_view: 'Preview', gallery_compare: 'Published vs draft', gallery_responsive: 'Draft at three screen sizes',
        gallery_width: 'Viewport width', gallery_refresh: 'Refresh previews', gallery_published: 'Published', gallery_draft: 'Unsaved draft',
        gallery_ready: 'Preview ready', gallery_loading: 'Loading preview…', gallery_failed: 'Preview unavailable. Refresh to try again.',
        gallery_no_target: 'Choose an element with Pick element in the designer.', gallery_open: 'Open theme designer',
        gallery_current: 'Current draft', gallery_wait: 'Open the designer to load your draft.'
    };
    const text = key => config.labels?.[key] || defaults[key];
    const node = (tag, parent, value, attributes = {}) => {
        const item = document.createElement(tag);
        if (value) item.textContent = value;
        Object.entries(attributes).forEach(([name, value]) => item.setAttribute(name, value));
        parent.append(item);
        return item;
    };
    const panel = document.createElement('details');
    panel.className = 'xs-controls';
    panel.dataset.cssholmesPreview = '';
    node('summary', panel, text('gallery_title'));
    node('p', panel, text('gallery_note'));
    const toolbar = node('div', panel, '', { class: 'cssholmes-preview-toolbar' });
    const viewLabel = node('label', toolbar, text('gallery_view'));
    const view = node('select', viewLabel, '', { 'data-draft-view': '' });
    node('option', view, text('gallery_compare'), { value: 'compare' });
    node('option', view, text('gallery_responsive'), { value: 'responsive' });
    const widthLabel = node('label', toolbar, text('gallery_width'));
    const width = node('select', widthLabel, '', { 'data-draft-width': '' });
    [360, 768, 1280].forEach(pixels => node('option', width, `${pixels} px`, { value: pixels }));
    width.value = '768';
    const refresh = node('button', toolbar, text('gallery_refresh'), { type: 'button' });
    const open = node('button', toolbar, text('gallery_open'), { type: 'button' });
    const status = node('p', panel, '', { role: 'status', 'aria-live': 'polite', 'data-draft-status': '' });
    const tray = node('div', panel, '', { class: 'cssholmes-preview-panes' });
    const launcher = gallery.querySelector('[data-cssholmes-designer]');
    if (!launcher) return;
    launcher.closest('p').after(panel);
    gallery.classList.toggle('cssholmes-editor-visible', designer.draft().visible);
    const frames = new Map(), timers = new Set();
    let generation = 0, targetId = '', pending = false;
    function clear() {
        generation++;
        timers.forEach(clearTimeout);
        timers.clear();
        frames.clear();
        tray.replaceChildren();
    }
    function apply(frame, record) {
        const state = designer.draft();
        const child = frame.contentDocument;
        if (!state.loaded || state.theme !== config.theme || !frame.isConnected
            || child?.getElementById('xs-gallery')?.dataset.xsTheme !== state.theme
            || child.querySelector('[data-xs-workspace], #cssholmes-designer-config')) return false;
        if (record.draft) {
            const published = child.getElementById('cssholmes-theme-style');
            if (published) published.disabled = true;
            let style = child.getElementById('cssholmes-frame-draft');
            if (!style) { style = child.createElement('style'); style.id = 'cssholmes-frame-draft'; child.head.append(style); }
            style.textContent = state.css;
            record.label.textContent = `${text(state.dirty ? 'gallery_draft' : 'gallery_current')} · ${record.pixels} px`;
        }
        return true;
    }
    function update() {
        if (pending) return;
        pending = true;
        requestAnimationFrame(() => {
            pending = false;
            frames.forEach((record, frame) => { if (record.ready) apply(frame, record); });
        });
    }
    async function render() {
        clear();
        if (!panel.open) return;
        const current = generation;
        widthLabel.hidden = view.value === 'responsive';
        if (!designer.draft().loaded) await designer.open();
        if (current !== generation || !panel.open) return;
        if (!designer.draft().loaded) { status.textContent = text('gallery_wait'); return; }
        status.textContent = `${text('gallery_ready')} · ${config.theme}`;
        const example = gallery.querySelector('.xs-example:not([hidden])');
        if (example) targetId = example.id;
        const url = new URL(base);
        url.searchParams.set('xs_theme', config.theme);
        if (example?.dataset.xsSection) url.searchParams.set('section', example.dataset.xsSection);
        url.hash = targetId;
        const panes = view.value === 'responsive'
            ? [360, 768, 1280].map(pixels => ({ pixels, draft: true }))
            : [{ pixels: Number(width.value), draft: false }, { pixels: Number(width.value), draft: true }];
        tray.dataset.layout = view.value;
        panes.forEach(record => {
            if (![360, 768, 1280].includes(record.pixels)) return;
            const figure = node('figure', tray);
            record.label = node('figcaption', figure, `${text(record.draft ? 'gallery_draft' : 'gallery_published')} · ${record.pixels} px`);
            const state = node('p', figure, text('gallery_loading'), { role: 'status', class: 'cssholmes-frame-status' });
            const scroll = node('div', figure, '', { class: 'cssholmes-preview-scroll' });
            const frame = node('iframe', scroll, '', { title: record.label.textContent, width: record.pixels, height: 460 });
            frame.style.width = `${record.pixels}px`;
            frame.style.minWidth = `${record.pixels}px`;
            frames.set(frame, record);
            const timeout = setTimeout(() => { if (current === generation) state.textContent = text('gallery_failed'); }, 15000);
            timers.add(timeout);
            frame.addEventListener('load', () => {
                if (current !== generation) return;
                clearTimeout(timeout); timers.delete(timeout);
                try {
                    if (frame.contentWindow.location.origin !== location.origin) throw new Error('Origin mismatch');
                    record.ready = apply(frame, record);
                    state.textContent = text(record.ready ? 'gallery_ready' : 'gallery_failed');
                    if (record.ready) {
                        const element = frame.contentDocument.getElementById(targetId);
                        if (element) frame.contentWindow.scrollTo(0, frame.contentWindow.scrollY + element.getBoundingClientRect().top - 20);
                    }
                } catch { record.ready = false; state.textContent = text('gallery_failed'); }
            });
            frame.src = url.href;
        });
    }
    panel.addEventListener('toggle', render);
    view.addEventListener('change', render);
    width.addEventListener('change', render);
    refresh.addEventListener('click', render);
    open.addEventListener('click', () => designer.open());
    window.addEventListener('cssholmes:draft-changed', update);
    window.addEventListener('cssholmes:published', render);
    window.addEventListener('cssholmes:designer-opened', () => gallery.classList.add('cssholmes-editor-visible'));
    window.addEventListener('cssholmes:designer-closed', () => gallery.classList.remove('cssholmes-editor-visible'));
    window.addEventListener('pagehide', clear);
    window.addEventListener('pageshow', event => { if (event.persisted) render(); });
    gallery.querySelectorAll('.xs-designer-actions').forEach(item => { item.hidden = false; });
    gallery.addEventListener('click', async event => {
        const action = event.target.closest('[data-xs-edit], [data-xs-inspect]');
        if (!action) return;
        const example = action.closest('.xs-example');
        const sample = example?.querySelector('[data-xs-specimen]');
        const target = sample?.querySelector('h1,h2,h3,h4,h5,h6,p,button,input:not([type=hidden]),textarea,select,table,blockquote,a,img') || sample?.firstElementChild;
        targetId = example?.id || '';
        if (!target || !await designer.select(target, { diagnostics: action.hasAttribute('data-xs-inspect'), highlight: true })) {
            status.textContent = text('gallery_no_target');
            await designer.open();
        }
        if (panel.open) render();
    });
    gallery.addEventListener('click', event => {
        if (panel.open && event.target.closest('[data-xs-example-link]')) setTimeout(render, 0);
    });
})();
