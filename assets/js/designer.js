/* Theme styles only: editor state stays in memory until an explicit save. */
(() => {
    'use strict';
    const boot = document.getElementById('cssholmes-designer-config');
    if (!boot || window.CssHolmesDesigner) return;
    let config;
    try { config = JSON.parse(boot.textContent); } catch (_) { return; }
    if (!config.theme || !config.endpoint || !config.stylesheetUrl) return;

    const english = {
        title: 'Theme designer', open: 'Design theme', close: 'Close', theme: 'Theme',
        pick: 'Pick element', cancel_pick: 'Cancel picking', pick_hint: 'Click an element, or focus it and press Enter. Escape cancels.',
        empty: 'Pick an element to edit its theme styles.', selector: 'Selector', selected: 'Selected element',
        matches: '{count} matching elements', scope_note: 'Changes apply to every matching element in this theme.',
        invalid_selector: 'Use up to four tag, class or ID selectors joined by spaces or >. Gallery controls cannot be selected.',
        no_matches: 'This selector has no editable matches on this page.', invalid_value: 'Enter a supported CSS value, or leave blank to remove the override.',
        invalid_controls: 'Correct the highlighted value before continuing.', rule_list: 'Saved and draft rules', choose_rule: 'Choose a rule',
        mode: 'Color mode', breakpoint: 'Screen size', state: 'Element state', all: 'All', light: 'Light', dark: 'Dark',
        mobile: 'Mobile (up to 575px)', tablet: 'Tablet (576–991px)', desktop: 'Desktop (992px and up)',
        normal: 'Normal', hover: 'Hover', focus: 'Keyboard focus', preview_note: 'Preview follows the page color mode, current screen size and actual hover or focus state.',
        typography: 'Typography', colors: 'Colors', spacing: 'Spacing', size: 'Size', border: 'Border', layout: 'Layout',
        color: 'Text color', background_color: 'Background color', border_color: 'Border color',
        font_size: 'Font size', line_height: 'Line height', letter_spacing: 'Letter spacing', font_weight: 'Font weight',
        font_family: 'Font family', text_align: 'Text alignment', text_transform: 'Text case', text_decoration: 'Text decoration', font_style: 'Font style',
        width: 'Width', height: 'Height', max_width: 'Maximum width', min_width: 'Minimum width',
        margin_top: 'Top margin', margin_right: 'Right margin', margin_bottom: 'Bottom margin', margin_left: 'Left margin',
        margin_inline_start: 'Start margin', margin_inline_end: 'End margin',
        margin_block_start: 'Block start margin', margin_block_end: 'Block end margin',
        padding_top: 'Top padding', padding_right: 'Right padding', padding_bottom: 'Bottom padding', padding_left: 'Left padding',
        padding_inline_start: 'Start padding', padding_inline_end: 'End padding',
        padding_block_start: 'Block start padding', padding_block_end: 'Block end padding',
        gap: 'Gap', border_width: 'Border width', border_radius: 'Corner radius', border_style: 'Border style',
        display: 'Display', align_items: 'Align items', justify_content: 'Justify content',
        default_value: 'Theme default', computed: 'Current: {value}', color_picker: '{label} picker',
        undo: 'Undo', redo: 'Redo', reset: 'Reset selected', save: 'Save to theme', discard: 'Discard draft',
        restore: 'Restore previous save', restore_confirm: 'Restore the previous saved theme configuration? This replaces the current saved styles and any draft.',
        more_actions: 'More actions', reload: 'Reload saved settings', reload_confirm: 'Discard this draft and load the latest saved theme settings?',
        export_css: 'Export CSS', export_json: 'Export JSON',
        loading: 'Loading theme styles…', ready: 'Ready. Changes preview here until you save.',
        unsaved: 'Unsaved theme changes.', saved: 'Saved to theme.', discarded: 'Draft discarded.', restored: 'Previous save restored.',
        saving: 'Saving…', restoring: 'Restoring…', request_failed: 'Could not load or save theme styles. Your draft is retained.',
        preview_only: 'Preview only. Saving requires workbench permission.', changes: '{count} style rules',
        unsupported: 'This browser cannot open the theme designer.', target_comment_titles: 'Comment titles',
        target_comment_body: 'Comment text', target_article_titles: 'Article titles', target_article_body: 'Article text',
        target_button: 'Buttons', target_input: 'Form fields', target_label: 'Form labels', target_table: 'Tables',
        target_table_header: 'Table headings', target_message: 'Messages', target_quote: 'Quotations', target_link: 'Links',
        library: 'Library', style_sets: 'Style sets', choose_set: 'Choose a set or palette', current_theme_sets: 'Current theme',
        other_theme_sets: 'Other themes', starter_palettes: 'Starter palettes', set_name: 'Set name', save_new_set: 'Save as new set',
        update_set: 'Update selected set', preview_set: 'Preview selected', like: 'Like', unlike: 'Unlike',
        set_details: '{author} · {count} likes', library_note: 'Library saves do not change the theme. Preview first, then use Save to theme to publish.',
        set_saved: 'Set saved in library. Theme unchanged.', set_previewed: 'Set previewed. Use Save to theme to publish.',
        replace_draft_confirm: 'Discard the current draft, including invalid values, and preview this selection?',
        transfer_sets: 'Import / export', import_file: 'Import set JSON', preview_import: 'Preview import', save_import: 'Save imported set',
        export_portable: 'Export portable set', import_size: 'Choose a JSON file no larger than 128 KiB.', import_invalid: 'Choose a file containing valid JSON.',
        import_previewed: 'Import previewed. Save imported set adds it to the library only.', set_name_required: 'Enter a set name.',
        original_theme: 'Original theme', compare_original: 'Compare original', preview_original: 'Preview original defaults',
        reset_original: 'Reset theme to original', reset_original_confirm: 'Remove all published designer overrides and discard this draft? This restores the untouched theme. Library sets are kept.',
        original_previewed: 'Original theme previewed. Use Save to theme to publish.', original_reset: 'Theme reset to original. Library sets kept.',
        palette_contrast: '{name}: {ratio}:1 · {result}', contrast_pass: 'Pass', contrast_fail: 'Fail',
        diagnostics: 'Tokens and contrast', diagnostics_refresh: 'Refresh diagnostics', diagnostics_search: 'Search tokens',
        diagnostics_sample: 'Inspecting the first of {count} matching elements.', diagnostics_empty: 'Select an element to inspect.',
        diagnostics_note: 'Text contrast estimate only, not a full accessibility check. Overlapping and pseudo-element paint may not be fully detected.',
        diagnostics_incomplete: 'Token discovery may be incomplete: some stylesheets cannot be read. Refresh after stylesheet changes.',
        diagnostics_tokens_empty: 'No matching tokens.', diagnostics_unknown: 'Cannot determine automatically: {reason}',
        diagnostics_ratio: '{ratio}:1 · Normal text AA (4.5:1): {normal} · Large text AA (3:1): {large}',
        diagnostics_pair: 'Rendered text {foreground} on {background}',
        diagnostics_selection: 'no connected element', diagnostics_hidden: 'the element is hidden',
        diagnostics_text: 'select a plain text element; nested text and native controls need manual review',
        diagnostics_color: 'unsupported color space', diagnostics_effects: 'opacity, filters, blending or text effects',
        diagnostics_layers: 'generated or overlapping layers', diagnostics_background: 'image, gradient or complex background',
        diagnostics_surface: 'no known opaque background',
        diagnostics_repair_color: 'Adjust text color', diagnostics_repair_background: 'Adjust background color'
    };
    const labels = { ...english, ...(config.labels || {}) };
    const t = (key, replacements = {}) => Object.entries(replacements).reduce((value, [name, item]) => value.replaceAll(`{${name}}`, String(item)), labels[key] || key);
    const groups = {
        typography: ['font-size', 'line-height', 'letter-spacing', 'font-weight', 'font-family', 'text-align', 'text-transform', 'text-decoration', 'font-style'],
        colors: ['color', 'background-color', 'border-color'],
        spacing: ['margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-inline-start', 'margin-inline-end', 'margin-block-start', 'margin-block-end', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-inline-start', 'padding-inline-end', 'padding-block-start', 'padding-block-end', 'gap'],
        size: ['width', 'height', 'max-width', 'min-width'],
        border: ['border-width', 'border-radius', 'border-style'],
        layout: ['display', 'align-items', 'justify-content']
    };
    const supported = new Set(Object.values(groups).flat());
    const propertyNames = Object.keys(config.properties || {}).filter(name => supported.has(name));
    const permitted = new Set(propertyNames.length ? propertyNames : supported);
    const enums = {
        'font-weight': ['normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900'],
        'text-align': ['start', 'end', 'left', 'right', 'center', 'justify'],
        'text-transform': ['none', 'uppercase', 'lowercase', 'capitalize'],
        'text-decoration': ['none', 'underline', 'overline', 'line-through'],
        'font-style': ['normal', 'italic', 'oblique'], 'border-style': ['none', 'solid', 'dashed', 'dotted', 'double'],
        display: ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'none'],
        'align-items': ['normal', 'stretch', 'start', 'end', 'center', 'flex-start', 'flex-end', 'baseline'],
        'justify-content': ['normal', 'start', 'end', 'center', 'flex-start', 'flex-end', 'space-between', 'space-around', 'space-evenly']
    };
    const stableTargets = [
        ['.xoops-comment-template .xoops-comment-text > h4', 'target_comment_titles'], ['.comTitle', 'target_comment_titles'],
        ['.xoops-comment-text .message-text', 'target_comment_body'], ['.comText', 'target_comment_body'],
        ['.itemTitle', 'target_article_titles'], ['.itemText', 'target_article_body'], ['.itemBody', 'target_article_body'],
        ['.errorMsg', 'target_message'], ['.confirmMsg', 'target_message'], ['.resultMsg', 'target_message'],
        ['.xoops-alert', 'target_message'], ['.head', 'target_table_header'], ['.outer', 'target_table'],
        ['.xoops-button', 'target_button'], ['button', 'target_button'], ['input', 'target_input'],
        ['textarea', 'target_input'], ['select', 'target_input'], ['label', 'target_label'], ['blockquote', 'target_quote']
    ];
    const host = document.createElement('div');
    host.id = 'cssholmes-designer';
    host.style.cssText = 'all:initial!important;font:13px/1.45 system-ui,sans-serif!important;color:#172033!important;color-scheme:light!important;position:fixed!important;inset:0 auto auto 0!important;inline-size:0!important;block-size:0!important;z-index:2147483647!important;';
    const shadow = host.attachShadow({ mode: 'open' });
    const sheet = document.createElement('link');
    sheet.rel = 'stylesheet';
    sheet.href = config.stylesheetUrl;
    shadow.append(sheet);
    document.body.append(host);
    const node = (tag, attrs = {}, text = null, parent = shadow) => {
        const item = document.createElement(tag);
        Object.entries(attrs).forEach(([name, value]) => item.setAttribute(name, String(value)));
        if (text !== null) item.textContent = text;
        parent.append(item);
        return item;
    };
    const button = (key, parent, action, attrs = {}) => {
        const item = node('button', { type: 'button', ...attrs }, t(key), parent);
        item.addEventListener('click', action);
        return item;
    };
    const launcher = button('open', shadow, () => picking ? stopPicking() : open(), { class: 'launcher', 'aria-expanded': 'false', 'aria-controls': 'designer-panel' });
    const panel = node('section', { id: 'designer-panel', 'data-panel': '', class: 'panel', hidden: '', 'aria-label': t('title') });
    const header = node('header', { class: 'panel-header' }, null, panel);
    const heading = node('div', {}, null, header);
    node('h2', {}, t('title'), heading);
    node('p', { class: 'theme-name' }, `${t('theme')}: ${config.theme}`, heading);
    button('close', header, close, { class: 'quiet' });
    const actions = node('div', { class: 'action-bar' }, null, panel);
    const pickButton = button('pick', actions, startPicking);
    const undoButton = button('undo', actions, () => travel(-1));
    const redoButton = button('redo', actions, () => travel(1));
    const scroll = node('div', { class: 'panel-content' }, null, panel);
    const status = node('p', { 'data-status': '', role: 'status', 'aria-live': 'polite', class: 'status' }, t('loading'), scroll);
    const error = node('p', { role: 'alert', hidden: '', class: 'error' }, '', scroll);
    let libraryControls = null;
    if (config.libraryEndpoint) {
        const library = node('details', { class: 'library', open: '' }, null, scroll);
        node('summary', {}, t('library'), library);
        node('p', { class: 'help' }, t('library_note'), library);
        const selectLabel = node('label', { class: 'field' }, t('style_sets'), library);
        const select = node('select', { class: 'palette-select', 'aria-label': t('style_sets') }, null, selectLabel);
        const info = node('p', { class: 'help library-info' }, '', library);
        const swatches = node('div', { class: 'palette-swatches', 'aria-hidden': 'true' }, null, library);
        const contrast = node('p', { class: 'help' }, '', library);
        const nameLabel = node('label', { class: 'field' }, t('set_name'), library);
        const name = node('input', { type: 'text', maxlength: '100', autocomplete: 'off' }, null, nameLabel);
        const row = node('div', { class: 'library-actions' }, null, library);
        const previewSet = button('preview_set', row, previewSelected);
        const like = button('like', row, () => libraryTask(async () => {
            const selected = selectedSet();
            if (selected) await libraryRequest('like', { id: selected.id, liked: !selected.liked });
        }));
        const saveRow = node('div', { class: 'library-actions' }, null, library);
        const saveNew = button('save_new_set', saveRow, () => saveSet(false));
        const update = button('update_set', saveRow, () => saveSet(true));
        const warnings = node('p', { 'data-library-warnings': '', role: 'status', class: 'notice', hidden: '' }, '', library);
        const transfer = node('details', { 'data-library-transfer': '', class: 'library-secondary' }, null, library);
        node('summary', {}, t('transfer_sets'), transfer);
        const fileLabel = node('label', { class: 'field' }, t('import_file'), transfer);
        const file = node('input', { type: 'file', accept: '.json,application/json' }, null, fileLabel);
        const transferRow = node('div', { class: 'library-actions' }, null, transfer);
        const importPreview = button('preview_import', transferRow, previewImport);
        const importSave = button('save_import', transferRow, () => libraryTask(async () => {
            if (!importDocument || !canSave) return;
            await libraryRequest('importSet', { document: importDocument, ...(name.value.trim() ? { name: name.value.trim() } : {}) });
            importDocument = null;
            status.textContent = t('set_saved');
        }));
        const exportSet = button('export_portable', transferRow, () => libraryTask(async () => {
            const data = await libraryRequest('exportCurrent', { name: name.value.trim(), rules });
            download('json', JSON.stringify(data.document || data.exported, null, 2), 'application/json');
        }));
        const original = node('details', { 'data-library-original': '', class: 'library-secondary' }, null, library);
        node('summary', {}, t('original_theme'), original);
        const compareLabel = node('label', { class: 'compare-original' }, null, library);
        const compare = node('input', { type: 'checkbox' }, null, compareLabel);
        compareLabel.append(document.createTextNode(t('compare_original')));
        compare.addEventListener('change', () => { applyPreview(); });
        const originalRow = node('div', { class: 'library-actions' }, null, original);
        const defaults = button('preview_original', originalRow, () => {
            if (!canReplaceDraft()) return;
            replaceDraft([]);
            status.textContent = t('original_previewed');
        });
        const reset = button('reset_original', originalRow, () => {
            if (!window.confirm(t('reset_original_confirm'))) return;
            libraryTask(async () => {
                accept(await libraryRequest('reset', { revision }));
                refreshPersisted();
                status.textContent = t('original_reset');
            });
        });
        libraryControls = { select, info, swatches, contrast, name, previewSet, like, saveNew, update, warnings, file, importPreview, importSave, exportSet, compare, defaults, reset };
        select.addEventListener('change', () => { showSelectedSet(true); });
        name.addEventListener('input', updateActions);
        file.addEventListener('change', () => { importDocument = null; updateActions(); });
    }
    if (!config.canSave) node('p', { class: 'notice' }, t('preview_only'), scroll);
    const ruleLabel = node('label', { class: 'field' }, t('rule_list'), scroll);
    const ruleList = node('select', {}, null, ruleLabel);
    ruleList.addEventListener('change', () => {
        if (ruleList.value === '') return;
        const rule = rules[Number(ruleList.value)];
        if (!rule || !canLeaveControls()) return;
        selection = { selector: rule.selector, label: rule.label };
        context = { mode: rule.mode, breakpoint: rule.breakpoint, state: rule.state };
        syncFields();
    });
    const selectedTitle = node('h3', { class: 'selected-title' }, t('empty'), scroll);
    const diagnostics = node('details', { class: 'diagnostics', 'data-diagnostics': '', hidden: '' }, null, scroll);
    node('summary', {}, t('diagnostics'), diagnostics);
    const diagnosticsSample = node('p', { class: 'help', 'data-diagnostics-sample': '' }, '', diagnostics);
    const contrastStatus = node('p', { 'data-contrast': '', role: 'status', 'aria-live': 'polite' }, '', diagnostics);
    const contrastPair = node('p', { class: 'help' }, '', diagnostics);
    const contrastActions = node('div', { class: 'library-actions', 'data-contrast-actions': '', hidden: '' }, null, diagnostics);
    button('diagnostics_repair_color', contrastActions, () => focusProperty('color'));
    button('diagnostics_repair_background', contrastActions, () => focusProperty('background-color'));
    node('p', { class: 'help' }, t('diagnostics_note'), diagnostics);
    button('diagnostics_refresh', diagnostics, () => { window.CssHolmesDiagnostics?.refresh(); refreshDiagnostics(); });
    const tokenLabel = node('label', { class: 'field' }, t('diagnostics_search'), diagnostics);
    const tokenSearch = node('input', { type: 'search', autocomplete: 'off' }, null, tokenLabel);
    const tokenWarning = node('p', { class: 'notice', hidden: '' }, t('diagnostics_incomplete'), diagnostics);
    const tokenList = node('dl', { class: 'token-list', 'data-token-list': '' }, null, diagnostics);
    tokenSearch.addEventListener('input', renderTokens);
    const targetArea = node('fieldset', { disabled: '', class: 'target-fields' }, null, scroll);
    node('legend', { class: 'sr-only' }, t('selected'), targetArea);
    const selectorLabel = node('label', { class: 'field' }, t('selector'), targetArea);
    const selectorInput = node('input', { type: 'text', spellcheck: 'false', autocomplete: 'off' }, null, selectorLabel);
    const count = node('p', { 'data-count': '', class: 'match-count' }, '', targetArea);
    node('p', { class: 'help' }, t('scope_note'), targetArea);
    selectorInput.addEventListener('input', () => {
        const result = validateSelector(selectorInput.value.trim());
        selectorInput.setAttribute('aria-invalid', String(!result.ok));
        selectorInput.setCustomValidity(result.ok ? '' : t('invalid_selector'));
        count.textContent = result.ok ? t('matches', { count: result.elements.length }) : t('invalid_selector');
        updateActions();
    });
    selectorInput.addEventListener('change', () => {
        const selector = selectorInput.value.trim().replace(/\s+/g, ' ').replace(/\s*>\s*/g, ' > ');
        const result = validateSelector(selector);
        if (!result.ok || !canLeaveControls()) return;
        selection = { selector, label: selector.slice(0, 160) };
        syncFields();
    });
    const contextControls = {};
    const contexts = node('div', { class: 'contexts' }, null, targetArea);
    Object.entries({ mode: ['all', 'light', 'dark'], breakpoint: ['all', 'mobile', 'tablet', 'desktop'], state: ['normal', 'hover', 'focus'] }).forEach(([name, values]) => {
        const label = node('label', { class: 'field' }, t(name), contexts);
        const select = node('select', { 'data-context': name }, null, label);
        values.forEach(value => node('option', { value }, t(value), select));
        contextControls[name] = select;
        select.addEventListener('change', () => {
            if (!canLeaveControls()) { select.value = context[name]; return; }
            context[name] = select.value;
            syncFields();
        });
    });
    node('p', { class: 'help' }, t('preview_note'), targetArea);
    const propertyFields = new Map();
    Object.entries(groups).forEach(([key, names]) => {
        const available = names.filter(name => permitted.has(name));
        if (!available.length) return;
        const section = node('details', { class: 'property-group', ...(key === 'typography' || key === 'colors' ? { open: '' } : {}) }, null, targetArea);
        node('summary', {}, t(key), section);
        const fields = node('div', { class: 'property-fields' }, null, section);
        available.forEach(name => {
            const labelText = t(name.replaceAll('-', '_'));
            const label = node('label', { class: 'field property' }, labelText, fields);
            const row = node('span', { class: 'value-row' }, null, label);
            const options = config.properties?.[name]?.options || enums[name];
            const input = node(options ? 'select' : 'input', {
                'data-property': name, 'aria-label': labelText,
                ...(options ? {} : { type: 'text', placeholder: t('default_value'), spellcheck: 'false', autocomplete: 'off' })
            }, null, row);
            if (options) {
                node('option', { value: '' }, t('default_value'), input);
                options.forEach(value => node('option', { value }, value, input));
            }
            const hint = node('small', { class: 'computed' }, '', label);
            let picker = null;
            if (key === 'colors') {
                picker = node('input', { type: 'color', class: 'color-picker', 'aria-label': t('color_picker', { label: labelText }) }, null, row);
                picker.addEventListener('input', () => { input.value = picker.value; changeProperty(name, input); });
            }
            input.addEventListener(options ? 'change' : 'input', () => changeProperty(name, input));
            propertyFields.set(name, { input, hint, picker });
        });
    });
    const resetButton = button('reset', targetArea, () => {
        if (!selection) return;
        const index = currentIndex();
        if (index !== -1) commit(rules.filter((_, position) => position !== index));
        syncFields();
    }, { class: 'reset' });
    const footer = node('footer', { class: 'panel-footer' }, null, panel);
    footer.append(error);
    const changes = node('p', { class: 'rule-count' }, '', footer);
    const saveRow = node('div', { class: 'save-row' }, null, footer);
    const saveButton = button('save', saveRow, () => persist('save'), { class: 'primary' });
    const discardButton = button('discard', saveRow, discard);
    const more = node('details', { class: 'more' }, null, footer);
    node('summary', {}, t('more_actions'), more);
    const moreActions = node('div', { class: 'more-actions' }, null, more);
    const restoreButton = button('restore', moreActions, () => {
        if (window.confirm(t('restore_confirm'))) persist('restore');
    });
    const reloadButton = button('reload', moreActions, () => {
        const invalid = !selectorInput.checkValidity() || [...propertyFields.values()].some(({ input }) => !input.checkValidity());
        if ((!dirty() && !invalid) || window.confirm(t('reload_confirm'))) load();
    });
    const exportCssButton = button('export_css', moreActions, () => download('css', compile(rules), 'text/css'));
    const exportJsonButton = button('export_json', moreActions, () => {
        if (libraryControls) libraryControls.exportSet.click();
        else download('json', JSON.stringify({ theme: config.theme, rules }, null, 2), 'application/json');
    });
    const outline = node('div', { class: 'outline', hidden: '', 'aria-hidden': 'true' });
    const pickingHint = node('p', { class: 'pick-hint', hidden: '', role: 'status' }, t('pick_hint'));
    const preview = document.createElement('style');
    preview.id = 'cssholmes-draft-style';
    let rules = [], baseline = [], history = [[]], historyIndex = 0;
    let revision = 0, token = config.token, canSave = Boolean(config.canSave), canExport = Boolean(config.canExport), canRestore = false;
    let loaded = false, busy = false, visible = false, picking = false, selection = null, hovered = null;
    let context = { mode: 'all', breakpoint: 'all', state: 'normal' };
    let loadPromise = null;
    let previousFocus = null;
    let persistedDisabled = false;
    let library = { sets: [], palettes: [] }, libraryLoaded = false, importDocument = null;
    const setRevisions = new Map();
    let diagnosticTokens = [], diagnosticsTimer = null;
    const colorPreference = window.matchMedia('(prefers-color-scheme: dark)');
    const diagnosticsObserver = new MutationObserver(records => {
        const relevant = records.filter(record => record.target !== host && !host.contains(record.target));
        if (!relevant.length) return;
        if (relevant.some(record => {
            const target = record.target.nodeType === 1 ? record.target : record.target.parentElement;
            if (target?.closest('#cssholmes-draft-style')) return false;
            return target?.matches('style, link') || [...record.addedNodes, ...record.removedNodes].some(item => item.nodeType === 1 && item.id !== 'cssholmes-draft-style' && (item.matches('style, link') || item.querySelector('style, link')));
        })) window.CssHolmesDiagnostics?.refresh();
        scheduleDiagnostics();
    });
    function scheduleDiagnostics() {
        if (!visible) return;
        clearTimeout(diagnosticsTimer);
        diagnosticsTimer = setTimeout(refreshDiagnostics, 100);
    }
    function stylesheetLoaded(event) {
        if (event.target.matches?.('link[rel=stylesheet]')) { window.CssHolmesDiagnostics?.refresh(); scheduleDiagnostics(); }
    }
    function watchDiagnostics(active) {
        diagnosticsObserver.disconnect();
        clearTimeout(diagnosticsTimer);
        window.removeEventListener('resize', scheduleDiagnostics);
        colorPreference.removeEventListener('change', scheduleDiagnostics);
        document.removeEventListener('load', stylesheetLoaded, true);
        if (!active || !window.CssHolmesDiagnostics) return;
        window.CssHolmesDiagnostics?.refresh();
        diagnosticsObserver.observe(document.documentElement, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class', 'style', 'data-theme', 'data-bs-theme', 'href', 'media', 'disabled', 'hidden'] });
        window.addEventListener('resize', scheduleDiagnostics);
        colorPreference.addEventListener('change', scheduleDiagnostics);
        document.addEventListener('load', stylesheetLoaded, true);
        scheduleDiagnostics();
    }
    function refreshDiagnostics() {
        if (!visible) return;
        const inspector = window.CssHolmesDiagnostics;
        diagnostics.hidden = !inspector;
        if (!inspector) return;
        const elements = selection ? validateSelector(selection.selector).elements : [];
        diagnosticsSample.textContent = elements.length ? t('diagnostics_sample', { count: elements.length }) : t('diagnostics_empty');
        const result = inspector.inspect(elements[0]);
        diagnosticTokens = result.tokens;
        tokenWarning.hidden = !result.discoveryIncomplete;
        const contrast = result.contrast;
        contrastStatus.textContent = contrast.status === 'measured'
            ? t('diagnostics_ratio', { ratio: contrast.ratio.toFixed(2), normal: t(contrast.ratio >= 4.5 ? 'contrast_pass' : 'contrast_fail'), large: t(contrast.ratio >= 3 ? 'contrast_pass' : 'contrast_fail') })
            : t('diagnostics_unknown', { reason: t(`diagnostics_${contrast.reason}`) });
        contrastPair.textContent = contrast.status === 'measured' ? t('diagnostics_pair', contrast) : '';
        contrastActions.hidden = contrast.status !== 'measured' || contrast.ratio >= 4.5;
        renderTokens();
    }
    function renderTokens() {
        const query = tokenSearch.value.trim().toLowerCase();
        tokenList.replaceChildren();
        const tokens = diagnosticTokens.filter(token => `${token.name} ${token.value}`.toLowerCase().includes(query));
        tokens.forEach(token => {
            node('dt', {}, token.name, tokenList);
            const value = node('dd', {}, null, tokenList);
            if (CSS.supports('color', token.value) && !/\b(?:currentcolor|inherit|initial|unset|revert|var|env)\b/i.test(token.value)) {
                const swatch = node('span', { class: 'token-swatch', 'aria-hidden': 'true' }, null, value);
                swatch.style.backgroundColor = token.value;
            }
            value.append(document.createTextNode(token.value));
        });
        if (!tokens.length) node('dd', {}, t('diagnostics_tokens_empty'), tokenList);
    }
    const clone = value => JSON.parse(JSON.stringify(value));
    const dirty = () => JSON.stringify(rules) !== JSON.stringify(baseline);
    const persisted = () => document.getElementById('cssholmes-theme-style');
    const currentIndex = () => selection ? rules.findIndex(rule => rule.selector === selection.selector && rule.mode === context.mode && rule.breakpoint === context.breakpoint && rule.state === context.state) : -1;
    function setError(message = '') { error.textContent = message; error.hidden = !message; }
    function isEditable(element) {
        if (!(element instanceof Element) || element === host || element.closest('#cssholmes-designer, #cssholmes-toolbar, #cssholmes-inspector, [data-cssholmes-designer], script, style, link, meta')) return false;
        // Gallery chrome is not a theme specimen; real pages keep their normal content scope.
        if (element.closest('#xs-gallery, .xs-gallery') && !element.closest('[data-xs-specimen]')) return false;
        return !['HTML', 'HEAD', 'BODY', 'SCRIPT', 'STYLE', 'LINK', 'META'].includes(element.tagName);
    }
    function validateSelector(selector) {
        if (!selector || selector.length > 240 || /(?:^|[.#])(?:xs-|cssholmes-|xstyles|commentxs-fixture)|#(?:comment[_-]|comment\d)/i.test(selector)) return { ok: false, elements: [] };
        const suffix = '[.#][a-zA-Z_][a-zA-Z0-9_-]*';
        const compound = `(?:[a-zA-Z][a-zA-Z0-9-]*(?:${suffix})*|(?:${suffix})+)`;
        if (!new RegExp(`^${compound}(?:\\s*>\\s*${compound}|\\s+${compound}){0,3}$`).test(selector) || /[>\s]$/.test(selector)) return { ok: false, elements: [] };
        try {
            const matches = [...document.querySelectorAll(selector)];
            return { ok: matches.every(isEditable), elements: matches };
        } catch (_) { return { ok: false, elements: [] }; }
    }
    function canLeaveControls() {
        if ([...propertyFields.values()].some(({ input }) => !input.checkValidity())) { setError(t('invalid_controls')); return false; }
        return true;
    }
    function validValue(name, value) {
        if (value === '') return true;
        if (value.length > 128 || /[;{}@!\\/<>\x00-\x1f\x7f]/.test(value) || !CSS.supports(name, value)) return false;
        const options = config.properties?.[name]?.options || enums[name];
        if (options) return options.includes(value);
        if (name === 'font-family') return value.split(',').every(family => /^(?:[A-Za-z][A-Za-z0-9 -]*|"[A-Za-z][A-Za-z0-9 -]*"|'[A-Za-z][A-Za-z0-9 -]*')$/.test(family.trim()));
        if (['color', 'background-color', 'border-color'].includes(name)) {
            if (value === 'transparent' || /^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i.test(value)) return true;
            const match = value.match(/^(rgb|rgba)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0(?:\.\d+)?|1(?:\.0+)?|\.\d+))?\s*\)$/);
            return Boolean(match && match.slice(2, 5).every(part => Number(part) <= 255) && (match[1] === 'rgba') === Boolean(match[5]));
        }
        if (value === 'normal' && ['line-height', 'letter-spacing'].includes(name)) return true;
        if (value === 'auto' && (name.startsWith('margin-') || ['width', 'height', 'min-width'].includes(name))) return true;
        if (value === 'none' && name === 'max-width') return true;
        const negative = name.startsWith('margin-') || name === 'letter-spacing' ? '-?' : '';
        const units = name === 'line-height' ? '(?:px|rem|em|%|vh|vw)?' : '(?:px|rem|em|%|vh|vw)';
        return value === '0' || new RegExp(`^${negative}(?:\\d{1,4}(?:\\.\\d{1,4})?|\\.\\d{1,4})${units}$`).test(value);
    }
    function changeProperty(name, input) {
        const value = input.value.trim();
        const valid = validValue(name, value);
        input.setCustomValidity(valid ? '' : t('invalid_value'));
        input.setAttribute('aria-invalid', String(!valid));
        if (!valid) { setError(t('invalid_value')); updateActions(); return; }
        if (!selection || !validateSelector(selection.selector).ok) return;
        const next = clone(rules);
        const index = currentIndex();
        const rule = index === -1 ? { ...selection, ...context, declarations: {} } : next[index];
        if (value === '') delete rule.declarations[name]; else rule.declarations[name] = value;
        if (index === -1 && Object.keys(rule.declarations).length) next.push(rule);
        else if (index !== -1 && !Object.keys(rule.declarations).length) next.splice(index, 1);
        commit(next);
        const picker = propertyFields.get(name).picker;
        if (picker && /^#[0-9a-f]{6}$/i.test(value)) picker.value = value;
    }
    function commit(next) {
        importDocument = null;
        if (JSON.stringify(next) !== JSON.stringify(rules)) {
            rules = next;
            history = history.slice(0, historyIndex + 1);
            history.push(clone(rules));
            if (history.length > 100) history.shift();
            historyIndex = history.length - 1;
        }
        setError();
        status.textContent = dirty() ? t('unsaved') : t('ready');
        applyPreview();
        updateActions();
        syncRuleList();
    }
    function travel(step) {
        if (busy || !loaded) return;
        const nextIndex = historyIndex + step;
        if (nextIndex < 0 || nextIndex >= history.length) return;
        importDocument = null;
        historyIndex = nextIndex;
        rules = clone(history[historyIndex]);
        setError();
        status.textContent = dirty() ? t('unsaved') : t('ready');
        applyPreview();
        syncFields();
    }
    function compile(items) {
        const breakpoints = ['all', 'mobile', 'tablet', 'desktop'];
        const modes = ['all', 'light', 'dark'];
        const media = { mobile: '(max-width: 575px)', tablet: '(min-width: 576px) and (max-width: 991px)', desktop: '(min-width: 992px)' };
        return [...items].sort((a, b) => breakpoints.indexOf(a.breakpoint) - breakpoints.indexOf(b.breakpoint) || modes.indexOf(a.mode) - modes.indexOf(b.mode)).map(rule => {
            const state = rule.state === 'hover' ? ':hover' : rule.state === 'focus' ? ':focus-visible' : '';
            const selector = rule.selector + state;
            const declarations = Object.entries(rule.declarations).filter(([name, value]) => permitted.has(name) && validValue(name, value)).map(([name, value]) => `${name}: ${value} !important;`).join(' ');
            if (!declarations) return '';
            let css = `${selector} { ${declarations} }`;
            if (rule.mode !== 'all') {
                const scope = `:root:is([data-bs-theme="${rule.mode}"], :not([data-bs-theme="light"]):not([data-bs-theme="dark"])[data-theme="${rule.mode}"])`;
                const fallback = ':root:not([data-bs-theme="light"]):not([data-bs-theme="dark"]):not([data-theme="light"]):not([data-theme="dark"])';
                css = `${scope} :is(${selector}), ${scope}:is(${selector}) { ${declarations} }\n@media (prefers-color-scheme: ${rule.mode}) { ${fallback} :is(${selector}), ${fallback}:is(${selector}) { ${declarations} } }`;
            }
            return rule.breakpoint === 'all' ? css : `@media ${media[rule.breakpoint]} { ${css} }`;
        }).join('\n');
    }
    function applyPreview() {
        if (!visible || !loaded) return;
        const link = persisted();
        if (link) link.disabled = true;
        preview.textContent = libraryControls?.compare.checked ? '' : compile(rules);
        document.head.append(preview);
        scheduleDiagnostics();
        window.dispatchEvent(new Event('cssholmes:draft-changed'));
    }
    function syncRuleList() {
        const chosen = currentIndex();
        ruleList.replaceChildren();
        node('option', { value: '' }, t('choose_rule'), ruleList);
        rules.forEach((rule, index) => node('option', { value: index }, `${rule.label || rule.selector} · ${t(rule.mode)} / ${t(rule.breakpoint)} / ${t(rule.state)}`, ruleList));
        ruleList.value = chosen === -1 ? '' : String(chosen);
    }
    function syncFields() {
        targetArea.disabled = busy || !loaded || !selection;
        selectedTitle.textContent = selection ? selection.label : t('empty');
        selectorInput.value = selection ? selection.selector : '';
        selectorInput.setCustomValidity('');
        selectorInput.setAttribute('aria-invalid', 'false');
        const result = selection ? validateSelector(selection.selector) : { elements: [] };
        count.textContent = t('matches', { count: result.elements.length });
        if (selection && !result.elements.length) count.textContent += ` · ${t('no_matches')}`;
        Object.entries(contextControls).forEach(([name, select]) => { select.value = context[name]; });
        const rule = rules[currentIndex()];
        const computed = result.elements[0] ? getComputedStyle(result.elements[0]) : null;
        propertyFields.forEach(({ input, hint, picker }, name) => {
            input.value = rule?.declarations[name] || '';
            input.setCustomValidity('');
            input.setAttribute('aria-invalid', 'false');
            const value = computed ? computed.getPropertyValue(name).trim() : '';
            hint.textContent = value ? t('computed', { value }) : '';
            if (picker) {
                const rgb = value.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                if (rgb) picker.value = '#' + rgb.slice(1).map(part => Number(part).toString(16).padStart(2, '0')).join('');
                else if (/^#[0-9a-f]{6}$/i.test(input.value)) picker.value = input.value;
            }
        });
        syncRuleList();
        updateActions();
        scheduleDiagnostics();
    }
    function updateActions() {
        const invalid = !selectorInput.checkValidity() || [...propertyFields.values()].some(({ input }) => !input.checkValidity());
        saveButton.disabled = busy || !loaded || !canSave || !dirty() || invalid;
        discardButton.disabled = busy || !loaded || (!dirty() && !invalid);
        undoButton.disabled = busy || !loaded || historyIndex === 0;
        redoButton.disabled = busy || !loaded || historyIndex >= history.length - 1;
        restoreButton.disabled = busy || !loaded || !canSave || !canRestore;
        reloadButton.disabled = busy;
        exportCssButton.disabled = exportJsonButton.disabled = busy || !loaded || !canExport || invalid;
        pickButton.disabled = busy || !loaded;
        resetButton.disabled = busy || !selection || currentIndex() === -1;
        ruleList.disabled = busy || !loaded;
        targetArea.disabled = busy || !loaded || !selection;
        changes.textContent = t('changes', { count: rules.length });
        if (libraryControls) {
            const controls = libraryControls, selected = selectedSet();
            controls.select.disabled = busy || !libraryLoaded;
            controls.name.disabled = busy;
            controls.previewSet.disabled = busy || !loaded || !controls.select.value;
            controls.saveNew.disabled = busy || !loaded || !canSave || invalid || !controls.name.value.trim();
            controls.update.disabled = busy || !loaded || !canSave || invalid || !selected?.editable || !controls.name.value.trim();
            controls.like.disabled = busy || !loaded || !selected;
            controls.importPreview.disabled = busy || !loaded || !controls.file.files.length;
            controls.importSave.disabled = busy || !loaded || !canSave || !importDocument;
            controls.exportSet.disabled = busy || !loaded || !canExport || invalid;
            controls.defaults.disabled = controls.compare.disabled = busy || !loaded;
            controls.reset.disabled = busy || !loaded || !canSave;
            controls.file.disabled = busy;
        }
    }
    async function request(op) {
        const url = new URL(config.endpoint, location.href);
        url.searchParams.set('theme', config.theme);
        const options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (op) {
            // Renew authorization without accepting server state over the local draft.
            await request();
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ theme: config.theme, op, revision, rules, token });
        }
        const response = await fetch(url, options);
        let data;
        try { data = await response.json(); } catch (_) { throw new Error(t('request_failed')); }
        if (data.token) token = data.token;
        if (!response.ok || !data.ok || !data.state || !Array.isArray(data.state.rules)) throw new Error(data.error || t('request_failed'));
        return data;
    }
    function accept(data) {
        importDocument = null;
        rules = clone(data.state.rules);
        baseline = clone(rules);
        revision = data.state.revision;
        canRestore = Boolean(data.state.canRestore);
        canSave = Boolean(data.canSave);
        canExport = Boolean(data.canExport);
        history = [clone(rules)];
        historyIndex = 0;
        loaded = true;
        applyPreview();
        syncFields();
    }
    async function load() {
        busy = true;
        updateActions();
        setError();
        status.textContent = t('loading');
        try {
            accept(await request()); status.textContent = t('ready');
            if (libraryControls) {
                setRevisions.clear();
                await libraryRequest();
                if (!libraryLoaded && config.initialSetId) {
                    if (config.initialSetId === 'original') {
                        replaceDraft([]);
                        libraryControls.name.value = t('original_theme');
                        status.textContent = t('original_previewed');
                    } else {
                        libraryControls.select.value = config.initialSetId;
                        showSelectedSet(true);
                        if (libraryControls.select.value) {
                            const data = await libraryRequest(null, { action: 'preview', id: libraryControls.select.value });
                            replaceDraft(data.preview.rules, data.preview.warnings, data.preview.set);
                            status.textContent = t('set_previewed');
                        }
                    }
                }
                libraryLoaded = true;
            }
        }
        catch (failure) { setError(failure.message); status.textContent = dirty() ? t('unsaved') : loaded ? t('ready') : ''; }
        finally { busy = false; syncFields(); loadPromise = null; }
    }
    async function persist(op) {
        if (busy || !loaded || !canSave || (op === 'save' && !canLeaveControls())) return;
        busy = true;
        setError();
        status.textContent = t(op === 'restore' ? 'restoring' : 'saving');
        updateActions();
        try {
            accept(await request(op));
            refreshPersisted();
            status.textContent = t(op === 'restore' ? 'restored' : 'saved');
        } catch (failure) { setError(failure.message); status.textContent = dirty() ? t('unsaved') : t('ready'); }
        finally { busy = false; syncFields(); }
    }
    function discard() {
        if (busy || !loaded) return;
        importDocument = null;
        rules = clone(baseline);
        history = [clone(rules)];
        historyIndex = 0;
        setError();
        status.textContent = t('discarded');
        applyPreview();
        syncFields();
    }
    function refreshPersisted() {
        let link = persisted();
        if (!link && config.styleUrl) {
            link = document.createElement('link');
            link.id = 'cssholmes-theme-style';
            link.rel = 'stylesheet';
            link.disabled = visible;
            document.head.append(link);
        }
        if (link) {
            const url = new URL(config.styleUrl || link.href, location.href);
            url.searchParams.set('revision', String(revision));
            link.href = url.href;
        }
        window.dispatchEvent(new Event('cssholmes:published'));
    }
    function selectedSet() { return library.sets.find(item => item.id === libraryControls?.select.value); }
    function showSelectedSet(rename = false) {
        const controls = libraryControls, selected = selectedSet();
        // Some Chromium builds leave selectedcontent empty after rebuilding the options.
        const selectedContent = controls.select.querySelector('selectedcontent');
        const selectedOption = controls.select.selectedOptions[0];
        if (selectedContent) selectedContent.replaceChildren(...[...(selectedOption?.childNodes || [])].map(child => child.cloneNode(true)));
        const palette = library.palettes.find(item => item.id === controls.select.value);
        controls.info.textContent = selected ? t('set_details', { author: selected.author.name, count: selected.likes }) : '';
        controls.like.textContent = t(selected?.liked ? 'unlike' : 'like');
        controls.like.setAttribute('aria-pressed', String(Boolean(selected?.liked)));
        if (rename) controls.name.value = selected?.name || palette?.name || '';
        controls.swatches.replaceChildren();
        (palette?.colors || []).forEach(color => {
            if (/^#[a-f0-9]{6}$/i.test(color)) node('span', { style: `background-color:${color}`, title: color }, null, controls.swatches);
        });
        controls.contrast.textContent = (palette?.contrast || []).map(item => t('palette_contrast', { name: item.name, ratio: item.ratio, result: t(item.pass ? 'contrast_pass' : 'contrast_fail') })).join(' · ');
        updateActions();
    }
    async function libraryRequest(op, values = {}) {
        const url = new URL(config.libraryEndpoint, location.href);
        url.searchParams.set('theme', config.theme);
        const options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (op) {
            await request();
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ theme: config.theme, op, token, ...values });
        } else Object.entries(values).forEach(([name, value]) => url.searchParams.set(name, value));
        const response = await fetch(url, options);
        let data;
        try { data = await response.json(); } catch (_) { throw new Error(t('request_failed')); }
        if (data.token) token = data.token;
        if (!response.ok || !data.ok) throw new Error(data.error || t('request_failed'));
        if (typeof data.canSave === 'boolean') canSave = data.canSave;
        if (typeof data.canExport === 'boolean') canExport = data.canExport;
        if (data.library) {
            library = data.library;
            // Likes and other metadata refreshes must not renew an older draft's revision.
            library.sets.forEach(set => { if (!setRevisions.has(set.id)) setRevisions.set(set.id, set.revision); });
            const selected = libraryControls.select.value;
            libraryControls.select.replaceChildren();
            const richOptions = CSS.supports('appearance', 'base-select');
            if (richOptions) {
                const selectedButton = node('button', { type: 'button' }, null, libraryControls.select);
                node('selectedcontent', {}, null, selectedButton);
            }
            node('option', { value: '' }, t('choose_set'), libraryControls.select);
            ['current_theme_sets', 'other_theme_sets', 'starter_palettes'].forEach(key => {
                const group = node('optgroup', { label: t(key) }, null, libraryControls.select);
                const items = key === 'starter_palettes' ? library.palettes : library.sets.filter(item => (item.theme === config.theme) === (key === 'current_theme_sets'));
                items.forEach(item => {
                    const name = key === 'other_theme_sets' ? `${item.theme} · ${item.name}` : item.name;
                    const option = node('option', { value: item.id }, null, group);
                    node('span', { class: 'palette-option-name' }, name, option);
                    if (key === 'starter_palettes') {
                        const swatches = node('span', { class: 'palette-option-swatches', 'aria-hidden': 'true' }, null, option);
                        (item.colors || []).forEach(color => {
                            if (/^#[a-f0-9]{6}$/i.test(color)) node('span', { style: `background-color:${color}` }, null, swatches);
                        });
                    }
                });
            });
            libraryControls.select.value = selected;
            showSelectedSet();
        }
        return data;
    }
    async function libraryTask(action) {
        if (busy || !loaded) return;
        busy = true; setError(); updateActions();
        try { await action(); }
        catch (failure) { setError(failure.message); }
        finally { busy = false; updateActions(); }
    }
    function canReplaceDraft() {
        const invalid = !selectorInput.checkValidity() || [...propertyFields.values()].some(({ input }) => !input.checkValidity());
        return (!dirty() && !invalid) || window.confirm(t('replace_draft_confirm'));
    }
    function replaceDraft(next, warnings = [], sourceSet = null) {
        libraryControls.compare.checked = false;
        importDocument = null;
        commit(clone(next));
        if (sourceSet) setRevisions.set(sourceSet.id, sourceSet.revision);
        syncFields();
        libraryControls.warnings.textContent = warnings.join('\n');
        libraryControls.warnings.hidden = !warnings.length;
    }
    function previewSelected() {
        if (!canReplaceDraft()) return;
        libraryTask(async () => {
            const data = await libraryRequest(null, { action: 'preview', id: libraryControls.select.value });
            replaceDraft(data.preview.rules, data.preview.warnings, data.preview.set);
            status.textContent = t('set_previewed');
        });
    }
    function saveSet(update) {
        if (!canSave || !canLeaveControls()) return;
        const name = libraryControls.name.value.trim(), selected = selectedSet();
        if (!name) { setError(t('set_name_required')); return; }
        libraryTask(async () => {
            const data = await libraryRequest(update ? 'updateSet' : 'saveSet', { name, rules, ...(update ? { id: selected.id, setRevision: setRevisions.get(selected.id) } : {}) });
            setRevisions.set(data.set.id, data.set.revision);
            status.textContent = t('set_saved');
        });
    }
    function previewImport() {
        libraryTask(async () => {
            const file = libraryControls.file.files[0];
            if (!file || file.size > 128 * 1024) throw new Error(t('import_size'));
            let document;
            try { document = JSON.parse(await file.text()); } catch (_) { throw new Error(t('import_invalid')); }
            const data = await libraryRequest('previewImport', { document });
            if (!canReplaceDraft()) return;
            replaceDraft(data.preview.rules, data.preview.warnings);
            importDocument = document;
            status.textContent = t('import_previewed');
        });
    }
    function open() {
        if (!visible) {
            previousFocus = document.activeElement;
            persistedDisabled = persisted()?.disabled || false;
        }
        visible = true;
        watchDiagnostics(true);
        window.dispatchEvent(new CustomEvent('cssholmes:designer-opened'));
        panel.hidden = false;
        launcher.hidden = true;
        launcher.setAttribute('aria-expanded', 'true');
        if (!loaded && !loadPromise) loadPromise = load();
        else applyPreview();
        return loadPromise || Promise.resolve();
    }
    function close() {
        stopPicking(false);
        visible = false;
        watchDiagnostics(false);
        panel.hidden = true;
        launcher.hidden = false;
        launcher.setAttribute('aria-expanded', 'false');
        preview.remove();
        if (persisted()) persisted().disabled = persistedDisabled;
        window.dispatchEvent(new Event('cssholmes:designer-closed'));
        if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
    }
    async function select(element, options = {}) {
        if (!isEditable(element) || !canLeaveControls()) return;
        await open();
        if (!loaded) return;
        let found = null;
        for (const [selector, label] of stableTargets) {
            const ancestor = element.closest(selector);
            if (ancestor && isEditable(ancestor) && validateSelector(selector).ok) { found = { selector, label: t(label) }; break; }
        }
        if (!found) {
            const parts = [];
            let current = element;
            for (let depth = 0; current && depth < 4 && isEditable(current); depth++, current = current.parentElement) {
                const classes = [...current.classList].filter(name => /^[A-Za-z_][A-Za-z0-9_-]*$/.test(name) && !/^(?:xs-|cssholmes-|xstyles)/i.test(name)).slice(0, 3);
                let part = current.tagName.toLowerCase() + classes.map(name => `.${name}`).join('');
                const id = current.id;
                if (/^[A-Za-z_][A-Za-z0-9_-]*$/.test(id) && !/^(?:xs-|cssholmes-|xstyles|comment[_-]|comment\d|commentxs-fixture)/i.test(id)) part = `#${id}`;
                parts.unshift(part);
                if (classes.length || part.startsWith('#')) break;
            }
            const selector = parts.join(' > ');
            if (validateSelector(selector).ok) found = { selector, label: selector.slice(0, 160) };
        }
        if (!found) { setError(t('invalid_selector')); return; }
        selection = found;
        stopPicking(false);
        setError();
        syncFields();
        if (options.diagnostics) { diagnostics.open = true; refreshDiagnostics(); }
        if (options.property && propertyFields.has(options.property)) focusProperty(options.property);
        else selectorInput.focus({ preventScroll: true });
        if (options.highlight) flashSelection(element);
        return true;
    }
    let selectionTimer;
    function flashSelection(element) {
        cancelAnimationFrame(selectionTimer);
        const until = performance.now() + 1800;
        const draw = () => {
            if (!visible || !element.isConnected || picking || performance.now() >= until) {
                if (!picking) outline.hidden = true;
                return;
            }
            highlight(element);
            selectionTimer = requestAnimationFrame(draw);
        };
        draw();
    }
    function focusProperty(property) {
        const input = propertyFields.get(property)?.input;
        if (!input || input.disabled) return;
        input.closest('details').open = true;
        input.focus();
        const element = selection && validateSelector(selection.selector).elements[0];
        if (element) flashSelection(element);
    }
    function startPicking() {
        if (!canLeaveControls()) return;
        picking = true;
        panel.hidden = true;
        launcher.hidden = false;
        launcher.textContent = t('cancel_pick');
        pickingHint.hidden = false;
        shadow.activeElement?.blur();
    }
    function stopPicking(focus = true) {
        picking = false;
        hovered = null;
        outline.hidden = true;
        pickingHint.hidden = true;
        launcher.textContent = t('open');
        if (visible) { panel.hidden = false; launcher.hidden = true; if (focus) pickButton.focus(); }
    }
    function highlight(element) {
        hovered = isEditable(element) ? element : null;
        outline.hidden = !hovered;
        if (!hovered) return;
        const rect = hovered.getBoundingClientRect();
        outline.style.transform = `translate(${rect.x}px, ${rect.y}px)`;
        outline.style.width = `${rect.width}px`;
        outline.style.height = `${rect.height}px`;
    }
    document.addEventListener('pointermove', event => { if (picking && !event.composedPath().includes(host)) highlight(event.target); }, true);
    document.addEventListener('focusin', event => { if (picking && !event.composedPath().includes(host)) highlight(event.target); }, true);
    document.addEventListener('click', event => {
        if (event.composedPath().includes(host)) return;
        const opener = event.target.closest?.('[data-cssholmes-designer]');
        if (opener) { event.preventDefault(); open(); return; }
        if (!picking) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!isEditable(event.target)) { stopPicking(); setError(t('invalid_selector')); return; }
        select(event.target);
    }, true);
    document.addEventListener('keydown', event => {
        if (!picking) return;
        if (event.key === 'Escape') { event.preventDefault(); stopPicking(); }
        if (event.key === 'Enter' && !event.composedPath().includes(host)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (isEditable(document.activeElement)) select(document.activeElement);
            else { stopPicking(); setError(t('invalid_selector')); }
        }
    }, true);
    window.addEventListener('scroll', () => { if (picking && hovered) highlight(hovered); }, { capture: true, passive: true });
    window.addEventListener('resize', () => { if (picking && hovered) highlight(hovered); });
    window.addEventListener('beforeunload', event => {
        if (!dirty()) return;
        event.preventDefault(); event.returnValue = '';
    });
    const designEvent = event => {
        if (event.currentTarget === window && event.target !== window) return;
        event.detail?.target ? select(event.detail.target, { property: event.detail.property, diagnostics: event.detail.diagnostics, highlight: true }) : open();
    };
    window.addEventListener('cssholmes:design', designEvent);
    document.addEventListener('cssholmes:design', designEvent);
    function download(extension, content, type) {
        if (!canExport) return;
        const url = URL.createObjectURL(new Blob([content], { type }));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `${config.theme}-styles.${extension}`;
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    window.CssHolmesDesigner = {
        open, close, select,
        draft: () => ({ theme: config.theme, loaded, visible, css: loaded ? compile(rules) : '', revision, dirty: dirty() })
    };
    updateActions();
    if (config.autoOpen) open();
})();
