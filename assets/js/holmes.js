(() => {
    const PROFILE_ORDER = ['html5', 'xtf-theme', 'xtf-widget', 'a11y', 'layout'];
    const XTF_ONLY_PROFILES = ['xtf-theme', 'xtf-widget'];
    const PROFILE_ALIASES = { theme: 'xtf-theme', widget: 'xtf-widget', accessibility: 'a11y', html: 'html5' };
    const TOOLBAR_ID = 'cssholmes-toolbar';
    const INSPECTOR_ID = 'cssholmes-inspector';
    const COUNTER_COLORS = {
        error: ['rgb(239, 68, 68)', 'rgb(220, 38, 38)', 'rgb(231, 76, 60)'],
        warning: ['rgb(245, 158, 11)', 'rgb(217, 70, 239)', 'rgb(243, 156, 18)', 'rgb(241, 196, 15)', 'rgb(155, 89, 182)'],
    };

    const script = document.currentScript || Array.from(document.scripts).find((item) => (item.src || '').includes('/assets/js/holmes.js')) || null;
    const scriptUrl = script ? new URL(script.src, window.location.href) : new URL(window.location.href);
    const moduleUrl = scriptUrl.searchParams.get('moduleUrl') || '';
    const queryKey = scriptUrl.searchParams.get('queryKey') || 'holmes';
    const pageScope = scriptUrl.searchParams.get('scope') || 'site';
    const themeKey = scriptUrl.searchParams.get('themeKey') || '';
    const themeManifestUrl = scriptUrl.searchParams.get('themeManifestUrl') || '';
    const genericMode = themeManifestUrl === '';
    const storageKey = `cssholmes.activeProfiles.${pageScope}`;
    const root = document.documentElement;

    let activeProfiles = [];
    let inspectEnabled = false;
    let measureEnabled = false;
    let hovered = null;
    let selected = null;
    let pointerX = 0;
    let pointerY = 0;
    let toolbar = null;
    let details = null;
    let copyTextButton = null;
    let copyJsonButton = null;
    let themeTokens = [];
    let changeLog = [];
    let tokenEditorOpen = false;
    let typographyEditorOpen = false;

    function normalizeProfiles(value) {
        const raw = String(value || '').trim();
        if (raw === '') {
            return [];
        }
        const normalized = raw.toLowerCase();
        if (['0', 'false', 'no', 'off'].includes(normalized)) {
            return [];
        }
        if (['1', 'true', 'yes', 'on', 'html'].includes(normalized)) {
            return ['html5'];
        }
        if (normalized === 'all') {
            return [...PROFILE_ORDER];
        }
        const profiles = [];
        raw.split(/[\s,]+/).forEach((candidate) => {
            const profile = PROFILE_ALIASES[candidate.toLowerCase().trim()] || candidate.toLowerCase().trim();
            if (PROFILE_ORDER.includes(profile) && !profiles.includes(profile)) {
                profiles.push(profile);
            }
        });
        return profiles;
    }

    function supportedProfiles(profiles) {
        return genericMode ? profiles.filter((profile) => !XTF_ONLY_PROFILES.includes(profile)) : profiles;
    }

    function resolveProfiles() {
        const pageUrl = new URL(window.location.href);
        if (pageUrl.searchParams.has(queryKey)) {
            const requested = supportedProfiles(normalizeProfiles(pageUrl.searchParams.get(queryKey) || ''));
            persistProfiles(requested);
            return requested;
        }
        try {
            const stored = supportedProfiles(normalizeProfiles(window.localStorage.getItem(storageKey) || ''));
            if (stored.length > 0) {
                return stored;
            }
        } catch (error) {}
        return supportedProfiles(normalizeProfiles(scriptUrl.searchParams.get('initialProfiles') || ''));
    }

    function persistProfiles(profiles) {
        const supported = supportedProfiles(profiles);
        try {
            if (supported.length === 0) {
                window.localStorage.removeItem(storageKey);
            } else {
                window.localStorage.setItem(storageKey, supported.join(','));
            }
        } catch (error) {}
    }

    function syncRootState() {
        root.setAttribute('data-cssholmes-scope', pageScope);
        root.setAttribute('data-cssholmes-mode', genericMode ? 'generic' : 'xtf');
        root.toggleAttribute('data-cssholmes-inspect', inspectEnabled);
        root.toggleAttribute('data-cssholmes-measure', measureEnabled);
        if (activeProfiles.length > 0) {
            root.classList.add('holmes-debug');
            root.setAttribute('data-cssholmes-profiles', activeProfiles.join(','));
        } else {
            root.classList.remove('holmes-debug');
            root.removeAttribute('data-cssholmes-profiles');
        }
    }

    function ensureStylesheets() {
        PROFILE_ORDER.forEach((profile) => {
            const id = `cssholmes-profile-${profile}`;
            const existing = document.getElementById(id);
            const enabled = activeProfiles.includes(profile);
            if (enabled && !existing) {
                const link = document.createElement('link');
                link.id = id;
                link.rel = 'stylesheet';
                link.href = `${moduleUrl}/assets/css/profiles/${profile}.css`;
                document.head.appendChild(link);
            }
            if (!enabled && existing) {
                existing.remove();
            }
        });
    }

    function updateUrl() {
        const pageUrl = new URL(window.location.href);
        if (activeProfiles.length > 0) {
            pageUrl.searchParams.set(queryKey, activeProfiles.join(','));
        } else {
            pageUrl.searchParams.delete(queryKey);
        }
        window.history.replaceState({}, document.title, pageUrl.toString());
    }

    function countFindings() {
        let errors = 0;
        let warnings = 0;
        document.querySelectorAll('body *').forEach((element) => {
            const style = window.getComputedStyle(element);
            if ((style.outlineStyle || 'none') === 'none') {
                return;
            }
            if (COUNTER_COLORS.error.includes(style.outlineColor || '')) {
                errors++;
            } else if (COUNTER_COLORS.warning.includes(style.outlineColor || '')) {
                warnings++;
            }
        });
        return { errors, warnings };
    }

    function currentTarget() {
        if (selected instanceof HTMLElement && document.contains(selected)) {
            return selected;
        }
        if (inspectEnabled && hovered instanceof HTMLElement && document.contains(hovered)) {
            return hovered;
        }
        return null;
    }

    function shortSelector(element) {
        if (!(element instanceof HTMLElement)) {
            return '';
        }
        if (element.id) {
            return `${element.tagName.toLowerCase()}#${element.id}`;
        }
        const classes = Array.from(element.classList).slice(0, 2);
        return `${element.tagName.toLowerCase()}${classes.length ? '.' + classes.join('.') : ''}`;
    }

    function selectorPath(element) {
        if (!(element instanceof HTMLElement)) {
            return '';
        }
        if (element.id) {
            return `#${element.id}`;
        }
        const parts = [];
        let current = element;
        while (current instanceof HTMLElement && current !== document.body && parts.length < 4) {
            let part = current.tagName.toLowerCase();
            const classes = Array.from(current.classList).slice(0, 2);
            if (classes.length > 0) {
                part += `.${classes.join('.')}`;
            }
            parts.unshift(part);
            current = current.parentElement;
        }
        return parts.join(' > ');
    }

    function widgetTargetFor(element) {
        let current = element instanceof HTMLElement ? element : null;
        while (current instanceof HTMLElement && current !== document.body) {
            if (
                current.matches('.xtf-slot-widget,[data-widget],[data-widget-name],.xtf-mkt-card,.xmf-widget-lazy,.xmf-widget-loaded,.xmf-widget-loading')
                || Array.from(current.classList).some((className) => className.startsWith('xmf-') && !className.includes('__'))
            ) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    }

    function slotTargetFor(element) {
        return element instanceof HTMLElement ? element.closest('[data-slot]') : null;
    }

    function editableTextTarget(element) {
        if (!(element instanceof HTMLElement)) {
            return null;
        }
        if (['SCRIPT', 'STYLE'].includes(element.tagName) || element.children.length > 0) {
            return null;
        }
        return element.innerText.trim() !== '' ? element : null;
    }

    function normalizeColor(value) {
        const probe = document.createElement('span');
        probe.style.color = '';
        probe.style.color = value;
        if (!probe.style.color) {
            return '';
        }
        document.body.appendChild(probe);
        const normalized = window.getComputedStyle(probe).color;
        probe.remove();
        return normalized;
    }

    function tokenMatchesFor(style) {
        if (themeTokens.length === 0) {
            return [];
        }

        const matches = [];
        const currentColors = [style.color, style.backgroundColor].filter(Boolean);
        themeTokens.forEach((token) => {
            if (token.normalized && currentColors.includes(token.normalized)) {
                matches.push(token);
            }
        });

        return matches.slice(0, 4);
    }

    function tokenBindingsFor(target = currentTarget()) {
        if (!(target instanceof HTMLElement)) {
            return [];
        }

        const style = window.getComputedStyle(target);

        return tokenMatchesFor(style)
            .map((token) => {
                const properties = [];
                if (style.color === token.normalized) {
                    properties.push('color');
                }
                if (style.backgroundColor === token.normalized) {
                    properties.push('backgroundColor');
                }

                return {
                    ...token,
                    properties,
                };
            })
            .filter((binding) => binding.properties.length > 0);
    }

    function summarizeText(value) {
        const normalized = String(value).replace(/\s+/g, ' ').trim();
        return normalized.length > 48 ? `${normalized.slice(0, 45)}...` : normalized;
    }

    function typographyBindingsFor(target = currentTarget()) {
        if (!(target instanceof HTMLElement)) {
            return [];
        }

        const style = window.getComputedStyle(target);

        return [
            { key: 'fontSize', cssProperty: 'font-size', label: 'Size', value: style.fontSize, input: 'text' },
            { key: 'lineHeight', cssProperty: 'line-height', label: 'Line Height', value: style.lineHeight, input: 'text' },
            { key: 'letterSpacing', cssProperty: 'letter-spacing', label: 'Letter Spacing', value: style.letterSpacing, input: 'text' },
            { key: 'fontWeight', cssProperty: 'font-weight', label: 'Weight', value: style.fontWeight, input: 'select', options: ['300', '400', '500', '600', '700', '800'] },
            { key: 'textAlign', cssProperty: 'text-align', label: 'Align', value: style.textAlign, input: 'select', options: ['left', 'center', 'right', 'justify'] },
        ];
    }

    function inspectionSnapshot(target = currentTarget()) {
        if (!(target instanceof HTMLElement)) {
            return null;
        }

        const style = window.getComputedStyle(target);
        const rect = target.getBoundingClientRect();
        const widgetTarget = widgetTargetFor(target);
        const slotTarget = slotTargetFor(target);
        const tokens = tokenMatchesFor(style).map((token) => token.key);

        return {
            shortSelector: shortSelector(target),
            selector: selectorPath(target),
            size: `${Math.round(rect.width)} x ${Math.round(rect.height)}`,
            position: `${Math.round(rect.left)} / ${Math.round(rect.top)}`,
            margin: `${style.marginTop} ${style.marginRight} ${style.marginBottom} ${style.marginLeft}`,
            padding: `${style.paddingTop} ${style.paddingRight} ${style.paddingBottom} ${style.paddingLeft}`,
            font: `${style.fontSize} / ${style.lineHeight}`,
            color: style.color,
            theme: themeKey || pageScope,
            mode: genericMode ? 'generic-xoops' : 'xtf',
            widget: widgetTarget ? (widgetTarget.dataset.widget || shortSelector(widgetTarget)) : '',
            slot: slotTarget ? (slotTarget.dataset.slot || shortSelector(slotTarget)) : '',
            tokens,
        };
    }

    function inspectionSnapshotText(target = currentTarget()) {
        const snapshot = inspectionSnapshot(target);
        if (!snapshot) {
            return '';
        }

        const lines = [
            `Element: ${snapshot.shortSelector}`,
            `Selector: ${snapshot.selector}`,
            `Size: ${snapshot.size}`,
            `Pos: ${snapshot.position}`,
            `Margin: ${snapshot.margin}`,
            `Padding: ${snapshot.padding}`,
            `Font: ${snapshot.font}`,
            `Color: ${snapshot.color}`,
            `Mode: ${snapshot.mode}`,
            `Theme: ${snapshot.theme}`,
        ];

        if (snapshot.widget) {
            lines.push(`Widget: ${snapshot.widget}`);
        }
        if (snapshot.slot) {
            lines.push(`Slot: ${snapshot.slot}`);
        }
        if (snapshot.tokens.length > 0) {
            lines.push(`Tokens: ${snapshot.tokens.join(', ')}`);
        }

        return lines.join('\n');
    }

    function inspectionSnapshotJson(target = currentTarget()) {
        const snapshot = inspectionSnapshot(target);
        if (!snapshot) {
            return '';
        }

        return JSON.stringify(snapshot, null, 2);
    }

    function selectedExportPayload(target = currentTarget()) {
        const snapshot = inspectionSnapshot(target);
        if (!snapshot) {
            return '';
        }

        return JSON.stringify({
            scope: pageScope,
            theme: themeKey || null,
            generated_at: new Date().toISOString(),
            changes: [
                {
                    kind: 'inspect',
                    selector: snapshot.selector,
                    widget: snapshot.widget || '',
                    slot: snapshot.slot || '',
                    before: '',
                    after: '',
                    summary: `Inspect ${snapshot.shortSelector} (${snapshot.size})`,
                    inspection: snapshot,
                },
            ],
        }, null, 2);
    }

    function writeClipboard(text) {
        if (!text) {
            return Promise.reject(new Error('No text to copy'));
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise((resolve, reject) => {
            const field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.top = '-9999px';
            field.style.left = '-9999px';
            document.body.appendChild(field);
            field.focus();
            field.select();

            try {
                if (!document.execCommand('copy')) {
                    throw new Error('Copy command was rejected');
                }
                resolve();
            } catch (error) {
                reject(error);
            } finally {
                field.remove();
            }
        });
    }

    function flashButton(button, successText, failureText = 'copy failed') {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const label = button.textContent;

        return {
            success() {
                button.textContent = successText;
                window.setTimeout(() => {
                    button.textContent = label;
                }, 900);
            },
            failure() {
                button.textContent = failureText;
                window.setTimeout(() => {
                    button.textContent = label;
                }, 1200);
            },
        };
    }

    function exportPayload() {
        return JSON.stringify({
            scope: pageScope,
            theme: themeKey || null,
            generated_at: new Date().toISOString(),
            changes: changeLog.map((change) => ({
                kind: change.kind,
                selector: change.selector,
                widget: change.widget || '',
                slot: change.slot || '',
                before: change.before,
                after: change.after,
                summary: change.summary,
                style_property: change.styleProperty || '',
                token_key: change.tokenKey || '',
                token_properties: change.tokenProperties || [],
                inspection: change.inspection || null,
            })),
        }, null, 2);
    }

    function isInternal(element) {
        return !!(element instanceof HTMLElement && element.closest(`#${TOOLBAR_ID}, #${INSPECTOR_ID}`));
    }

    function inspectableAt(x, y) {
        return document.elementsFromPoint(x, y).find((element) => element instanceof HTMLElement && !isInternal(element)) || null;
    }

    function setNode(node, visible, styles = {}, text = null) {
        if (!(node instanceof HTMLElement)) {
            return;
        }
        node.style.display = visible ? 'block' : 'none';
        if (!visible) {
            return;
        }
        Object.assign(node.style, styles);
        if (text !== null) {
            node.textContent = text;
        }
    }

    function draw() {
        const layer = document.getElementById(INSPECTOR_ID);
        if (!(layer instanceof HTMLElement)) {
            return;
        }

        const hoverBox = layer.querySelector('[data-role="hover-box"]');
        const selectBox = layer.querySelector('[data-role="selected-box"]');
        const label = layer.querySelector('[data-role="focus-label"]');
        const lines = Array.from(layer.querySelectorAll('[data-role^="line-"]'));
        const badges = Array.from(layer.querySelectorAll('[data-role^="badge-"]'));
        const target = currentTarget();

        const drawBox = (element, node, border) => {
            if (!(element instanceof HTMLElement) || !document.contains(element)) {
                setNode(node, false);
                return;
            }
            const rect = element.getBoundingClientRect();
            setNode(node, rect.width > 0 && rect.height > 0, {
                left: `${rect.left + window.scrollX}px`,
                top: `${rect.top + window.scrollY}px`,
                width: `${rect.width}px`,
                height: `${rect.height}px`,
                border,
            });
        };

        drawBox(inspectEnabled ? hovered : null, hoverBox, '1px dashed #38bdf8');
        drawBox(selected, selectBox, '2px solid #f59e0b');

        if (target instanceof HTMLElement) {
            const rect = target.getBoundingClientRect();
            setNode(label, true, {
                left: `${Math.max(6, rect.left + window.scrollX)}px`,
                top: `${Math.max(6, rect.top + window.scrollY - 28)}px`,
            }, `${shortSelector(target)} ${Math.round(rect.width)} x ${Math.round(rect.height)}`);
            if (label.getBoundingClientRect().top < 6) {
                label.style.top = `${rect.bottom + window.scrollY + 6}px`;
            }
        } else {
            setNode(label, false);
        }

        if (measureEnabled && target instanceof HTMLElement) {
            const rect = target.getBoundingClientRect();
            const cx = rect.left + rect.width / 2 + window.scrollX;
            const cy = rect.top + rect.height / 2 + window.scrollY;
            const values = [
                { node: lines[0], style: { left: `${cx}px`, top: `${window.scrollY}px`, width: '1px', height: `${Math.max(0, rect.top)}px` }, badge: badges[0], bx: cx + 8, by: window.scrollY + Math.max(6, rect.top / 2), text: `${Math.round(rect.top)}px` },
                { node: lines[1], style: { left: `${rect.right + window.scrollX}px`, top: `${cy}px`, width: `${Math.max(0, window.innerWidth - rect.right)}px`, height: '1px' }, badge: badges[1], bx: rect.right + window.scrollX + 8, by: cy + 8, text: `${Math.round(window.innerWidth - rect.right)}px` },
                { node: lines[2], style: { left: `${cx}px`, top: `${rect.bottom + window.scrollY}px`, width: '1px', height: `${Math.max(0, window.innerHeight - rect.bottom)}px` }, badge: badges[2], bx: cx + 8, by: rect.bottom + window.scrollY + 8, text: `${Math.round(window.innerHeight - rect.bottom)}px` },
                { node: lines[3], style: { left: `${window.scrollX}px`, top: `${cy}px`, width: `${Math.max(0, rect.left)}px`, height: '1px' }, badge: badges[3], bx: window.scrollX + 8, by: cy + 8, text: `${Math.round(rect.left)}px` },
            ];
            values.forEach((entry) => {
                setNode(entry.node, true, entry.style);
                setNode(entry.badge, true, { left: `${entry.bx}px`, top: `${entry.by}px` }, entry.text);
            });
        } else {
            lines.forEach((node) => setNode(node, false));
            badges.forEach((node) => setNode(node, false));
        }

        if (details instanceof HTMLElement) {
            if (!(target instanceof HTMLElement)) {
                details.innerHTML = 'Inspect: <code>I</code>. Measure: <code>M</code>. Click an element to lock it.';
                if (genericMode) {
                    details.innerHTML += '<br><span style="color:#fbbf24;">Generic XOOPS mode: token-aware XTF tools are limited because no theme.json manifest was detected.</span>';
                }
                if (copyTextButton instanceof HTMLButtonElement) {
                    copyTextButton.disabled = true;
                }
                if (copyJsonButton instanceof HTMLButtonElement) {
                    copyJsonButton.disabled = true;
                }
            } else {
                const style = window.getComputedStyle(target);
                const rect = target.getBoundingClientRect();
                const tokenMatches = tokenMatchesFor(style);
                const widgetTarget = widgetTargetFor(target);
                const slotTarget = slotTargetFor(target);
                details.innerHTML = `
                    <div class="cssholmes-detail-name">${shortSelector(target)}</div>
                    <div class="cssholmes-detail-path"><code>${selectorPath(target)}</code></div>
                    <div class="cssholmes-detail-grid">
                        <span>Size</span><strong>${Math.round(rect.width)} x ${Math.round(rect.height)}</strong>
                        <span>Pos</span><strong>${Math.round(rect.left)} / ${Math.round(rect.top)}</strong>
                        <span>Margin</span><strong>${style.marginTop} ${style.marginRight} ${style.marginBottom} ${style.marginLeft}</strong>
                        <span>Padding</span><strong>${style.paddingTop} ${style.paddingRight} ${style.paddingBottom} ${style.paddingLeft}</strong>
                        <span>Font</span><strong>${style.fontSize} / ${style.lineHeight}</strong>
                        <span>Color</span><strong>${style.color}</strong>
                        <span>Mode</span><strong>${genericMode ? 'generic-xoops' : 'xtf'}</strong>
                        <span>Theme</span><strong>${themeKey || pageScope}</strong>
                        ${widgetTarget ? `<span>Widget</span><strong>${widgetTarget.dataset.widget || shortSelector(widgetTarget)}</strong>` : ''}
                        ${slotTarget ? `<span>Slot</span><strong>${slotTarget.dataset.slot || shortSelector(slotTarget)}</strong>` : ''}
                    </div>
                    ${tokenMatches.length > 0 ? `<div class="cssholmes-token-list"><span>Tokens</span>${tokenMatches.map((token) => `<code>${token.key}</code>`).join('')}</div>` : ''}`;
                if (copyTextButton instanceof HTMLButtonElement) {
                    copyTextButton.disabled = false;
                }
                if (copyJsonButton instanceof HTMLButtonElement) {
                    copyJsonButton.disabled = false;
                }
            }
        }

        refreshToolbar();
    }

    function refreshToolbar() {
        toolbar = document.getElementById(TOOLBAR_ID);
        if (!(toolbar instanceof HTMLElement)) {
            return;
        }
        const counts = countFindings();
        const errorNode = toolbar.querySelector('[data-role="errors"]');
        const warningNode = toolbar.querySelector('[data-role="warnings"]');
        if (errorNode) {
            errorNode.textContent = String(counts.errors);
        }
        if (warningNode) {
            warningNode.textContent = String(counts.warnings);
        }
        PROFILE_ORDER.forEach((profile) => {
            const button = toolbar.querySelector(`[data-profile="${profile}"]`);
            if (button instanceof HTMLButtonElement) {
                button.className = activeProfiles.includes(profile) ? 'cssholmes-btn cssholmes-btn--active' : 'cssholmes-btn';
                button.disabled = genericMode && XTF_ONLY_PROFILES.includes(profile);
                button.title = genericMode && XTF_ONLY_PROFILES.includes(profile) ? 'This profile requires an XTF theme.json manifest.' : '';
            }
        });
        ['inspect', 'measure'].forEach((action) => {
            const button = toolbar.querySelector(`[data-action="${action}"]`);
            const enabled = action === 'inspect' ? inspectEnabled : measureEnabled;
            if (button instanceof HTMLElement) {
                button.className = enabled ? 'cssholmes-btn cssholmes-btn--active' : 'cssholmes-btn';
            }
        });
        const target = currentTarget();
        const editable = editableTextTarget(target);
        const widgetTarget = widgetTargetFor(target);
        const slotTarget = slotTargetFor(target);
        const tokenBindings = tokenBindingsFor(target);
        const typographyBindings = typographyBindingsFor(target);
        const tokenButton = toolbar.querySelector('[data-action="edit-token"]');
        const typographyButton = toolbar.querySelector('[data-action="edit-typography"]');
        const textButton = toolbar.querySelector('[data-action="edit-text"]');
        const widgetButton = toolbar.querySelector('[data-action="select-widget"]');
        const slotButton = toolbar.querySelector('[data-action="select-slot"]');
        const exportSelectedButton = toolbar.querySelector('[data-action="export-selected"]');
        if (tokenButton instanceof HTMLButtonElement) {
            tokenButton.disabled = genericMode || tokenBindings.length === 0;
            tokenButton.className = tokenEditorOpen ? 'cssholmes-btn cssholmes-btn--active' : 'cssholmes-btn cssholmes-btn--ghost';
            tokenButton.title = genericMode ? 'Token editor requires a detected XTF theme.json manifest.' : '';
        }
        if (typographyButton instanceof HTMLButtonElement) {
            typographyButton.disabled = !(target instanceof HTMLElement);
            typographyButton.className = typographyEditorOpen ? 'cssholmes-btn cssholmes-btn--active' : 'cssholmes-btn cssholmes-btn--ghost';
        }
        if (textButton instanceof HTMLButtonElement) {
            textButton.disabled = !(editable instanceof HTMLElement);
        }
        if (widgetButton instanceof HTMLButtonElement) {
            widgetButton.disabled = !(widgetTarget instanceof HTMLElement);
        }
        if (slotButton instanceof HTMLButtonElement) {
            slotButton.disabled = !(slotTarget instanceof HTMLElement);
        }
        if (exportSelectedButton instanceof HTMLButtonElement) {
            exportSelectedButton.disabled = !(target instanceof HTMLElement);
        }
        const undoButton = toolbar.querySelector('[data-action="undo"]');
        if (undoButton instanceof HTMLButtonElement) {
            undoButton.disabled = changeLog.length === 0;
        }
        const editorNode = toolbar.querySelector('[data-role="editor"]');
        if (editorNode instanceof HTMLElement) {
            if (tokenEditorOpen && tokenBindings.length > 0) {
                editorNode.style.display = 'block';
                editorNode.innerHTML = `
                    <div class="cssholmes-editor-head">
                        <strong>Token Editor</strong>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="close-token-editor">close</button>
                    </div>
                    <div class="cssholmes-token-editor-list">
                        ${tokenBindings.map((binding) => `
                            <div class="cssholmes-token-editor-row">
                                <div class="cssholmes-token-editor-meta">
                                    <code>${binding.key}</code>
                                    <span>${binding.properties.join(' + ')}</span>
                                </div>
                                <div class="cssholmes-token-editor-controls">
                                    <input type="color" value="${binding.normalized}" data-role="token-color" data-token-key="${binding.key}">
                                    <input type="text" value="${binding.value}" data-role="token-value" data-token-key="${binding.key}">
                                    <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="apply-token" data-token-key="${binding.key}">apply</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>`;
            } else if (typographyEditorOpen && typographyBindings.length > 0) {
                editorNode.style.display = 'block';
                editorNode.innerHTML = `
                    <div class="cssholmes-editor-head">
                        <strong>Typography Editor</strong>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="close-typography-editor">close</button>
                    </div>
                    <div class="cssholmes-typography-editor-list">
                        ${typographyBindings.map((binding) => `
                            <div class="cssholmes-typography-editor-row">
                                <label class="cssholmes-typography-editor-meta" for="cssholmes-typo-${binding.key}">
                                    <span>${binding.label}</span>
                                    <code>${binding.cssProperty}</code>
                                </label>
                                <div class="cssholmes-typography-editor-controls">
                                    ${binding.input === 'select'
                                        ? `<select id="cssholmes-typo-${binding.key}" data-role="typography-value" data-style-key="${binding.key}">${binding.options.map((option) => `<option value="${option}"${option === binding.value ? ' selected' : ''}>${option}</option>`).join('')}</select>`
                                        : `<input id="cssholmes-typo-${binding.key}" type="text" value="${binding.value}" data-role="typography-value" data-style-key="${binding.key}">`}
                                    <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="apply-typography" data-style-key="${binding.key}">apply</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>`;
            } else {
                editorNode.innerHTML = '';
                editorNode.style.display = 'none';
            }
        }
        const changesNode = toolbar.querySelector('[data-role="changes"]');
        if (changesNode instanceof HTMLElement) {
            if (changeLog.length === 0) {
                changesNode.innerHTML = '<div class="cssholmes-changes-empty">No local edits yet.</div>';
            } else {
                changesNode.innerHTML = `
                    <div class="cssholmes-changes-head">
                        <strong>Local Changes</strong>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="copy-export">export</button>
                    </div>
                    <div class="cssholmes-change-list">
                        ${changeLog
                            .slice(0, 5)
                            .map((change, index) => `<div class="cssholmes-change-row"><span>#${index + 1}</span><strong>${change.kind}</strong><code>${change.selector}</code><em>${change.summary}</em></div>`)
                            .join('')}
                    </div>
                    <pre class="cssholmes-export">${exportPayload()}</pre>`;
            }
        }
    }

    function toggleProfile(profile) {
        if (genericMode && XTF_ONLY_PROFILES.includes(profile)) {
            return;
        }
        activeProfiles = activeProfiles.includes(profile)
            ? activeProfiles.filter((candidate) => candidate !== profile)
            : [...activeProfiles, profile];
        persistProfiles(activeProfiles);
        syncRootState();
        ensureStylesheets();
        updateUrl();
        window.setTimeout(refreshToolbar, 50);
    }

    function clearInspector() {
        inspectEnabled = false;
        measureEnabled = false;
        hovered = null;
        selected = null;
        tokenEditorOpen = false;
        typographyEditorOpen = false;
        syncRootState();
        draw();
    }

    function copySelector() {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const feedback = flashButton(copyTextButton, 'copied');
        writeClipboard(inspectionSnapshotText(target))
            .then(() => feedback?.success())
            .catch(() => feedback?.failure());
    }

    function copyInspectionJson() {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const feedback = flashButton(copyJsonButton, 'json copied');
        writeClipboard(inspectionSnapshotJson(target))
            .then(() => feedback?.success())
            .catch(() => feedback?.failure());
    }

    function exportSelected() {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const button = toolbar?.querySelector('[data-action="export-selected"]');
        const feedback = flashButton(button, 'exported');
        writeClipboard(selectedExportPayload(target))
            .then(() => feedback?.success())
            .catch(() => feedback?.failure());
    }

    function editToken() {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const bindings = tokenBindingsFor(target);
        if (bindings.length === 0) {
            return;
        }
        typographyEditorOpen = false;
        tokenEditorOpen = !tokenEditorOpen;
        refreshToolbar();
    }

    function editTypography() {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        tokenEditorOpen = false;
        typographyEditorOpen = !typographyEditorOpen;
        refreshToolbar();
    }

    function applyTokenEdit(tokenKey, nextValue) {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const binding = tokenBindingsFor(target).find((candidate) => candidate.key === tokenKey);
        if (!binding) {
            return;
        }

        const trimmedValue = String(nextValue || '').trim();
        const normalizedValue = normalizeColor(trimmedValue);
        if (trimmedValue === '' || normalizedValue === '') {
            return;
        }

        const tokenIndex = themeTokens.findIndex((token) => token.key === binding.key);
        if (tokenIndex < 0) {
            return;
        }

        const previousToken = {
            value: themeTokens[tokenIndex].value,
            normalized: themeTokens[tokenIndex].normalized,
        };
        const previousInlineStyles = {};
        binding.properties.forEach((property) => {
            previousInlineStyles[property] = target.style[property] || '';
            target.style[property] = trimmedValue;
        });

        themeTokens[tokenIndex] = {
            ...themeTokens[tokenIndex],
            value: trimmedValue,
            normalized: normalizedValue,
        };

        recordChange({
            kind: 'token',
            element: target,
            selector: selectorPath(target),
            widget: widgetTargetFor(target)?.dataset.widget || shortSelector(widgetTargetFor(target)),
            slot: slotTargetFor(target)?.dataset.slot || shortSelector(slotTargetFor(target)),
            before: previousToken.value,
            after: trimmedValue,
            summary: `${binding.key}: ${previousToken.value} -> ${trimmedValue}`,
            tokenKey: binding.key,
            tokenProperties: binding.properties,
            previousToken,
            previousInlineStyles,
            inspection: inspectionSnapshot(target),
        });
        draw();
    }

    function applyTypographyEdit(styleKey, nextValue) {
        const target = currentTarget();
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const binding = typographyBindingsFor(target).find((candidate) => candidate.key === styleKey);
        if (!binding) {
            return;
        }

        const trimmedValue = String(nextValue || '').trim();
        if (trimmedValue === '') {
            return;
        }

        const previousInlineStyle = target.style.getPropertyValue(binding.cssProperty);
        target.style.setProperty(binding.cssProperty, trimmedValue);

        recordChange({
            kind: 'style',
            element: target,
            selector: selectorPath(target),
            widget: widgetTargetFor(target)?.dataset.widget || shortSelector(widgetTargetFor(target)),
            slot: slotTargetFor(target)?.dataset.slot || shortSelector(slotTargetFor(target)),
            before: binding.value,
            after: trimmedValue,
            summary: `${binding.label}: ${binding.value} -> ${trimmedValue}`,
            styleProperty: binding.cssProperty,
            previousInlineStyle,
            inspection: inspectionSnapshot(target),
        });
        draw();
    }

    function recordChange(change) {
        changeLog.unshift(change);
        if (changeLog.length > 20) {
            changeLog = changeLog.slice(0, 20);
        }
        refreshToolbar();
    }

    function undoLastChange() {
        const change = changeLog.shift();
        if (!change || !(change.element instanceof HTMLElement) || !document.contains(change.element)) {
            refreshToolbar();
            return;
        }

        if (change.kind === 'text') {
            change.element.innerText = change.before;
            selected = change.element;
            draw();
            return;
        }

        if (change.kind === 'token') {
            if (change.previousInlineStyles && typeof change.previousInlineStyles === 'object') {
                Object.entries(change.previousInlineStyles).forEach(([property, value]) => {
                    change.element.style[property] = String(value);
                });
            }

            if (change.tokenKey && change.previousToken && typeof change.previousToken === 'object') {
                const tokenIndex = themeTokens.findIndex((token) => token.key === change.tokenKey);
                if (tokenIndex >= 0) {
                    themeTokens[tokenIndex] = {
                        ...themeTokens[tokenIndex],
                        value: String(change.previousToken.value || ''),
                        normalized: String(change.previousToken.normalized || ''),
                    };
                }
            }

            selected = change.element;
            draw();
            return;
        }

        if (change.kind === 'style') {
            const styleProperty = String(change.styleProperty || '');
            if (styleProperty !== '') {
                if (String(change.previousInlineStyle || '') === '') {
                    change.element.style.removeProperty(styleProperty);
                } else {
                    change.element.style.setProperty(styleProperty, String(change.previousInlineStyle));
                }
            }

            selected = change.element;
            draw();
            return;
        }

        refreshToolbar();
    }

    function copyExport() {
        if (changeLog.length === 0) {
            return;
        }

        writeClipboard(exportPayload()).then(() => {
            const button = toolbar?.querySelector('[data-action="copy-export"]');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const label = button.textContent;
            button.textContent = 'exported';
            window.setTimeout(() => {
                button.textContent = label;
            }, 900);
        }).catch(() => {
            const button = toolbar?.querySelector('[data-action="copy-export"]');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const label = button.textContent;
            button.textContent = 'export failed';
            window.setTimeout(() => {
                button.textContent = label;
            }, 1200);
        });
    }

    function editText() {
        const target = editableTextTarget(currentTarget());
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const beforeText = target.innerText;
        const nextText = window.prompt('Edit text content', beforeText);
        if (nextText === null) {
            return;
        }
        target.innerText = nextText;
        recordChange({
            kind: 'text',
            element: target,
            selector: selectorPath(target),
            widget: widgetTargetFor(target)?.dataset.widget || shortSelector(widgetTargetFor(target)),
            slot: slotTargetFor(target)?.dataset.slot || shortSelector(slotTargetFor(target)),
            before: beforeText,
            after: nextText,
            summary: `${summarizeText(beforeText)} -> ${summarizeText(nextText)}`,
        });
        draw();
    }

    function attachListeners() {
        document.addEventListener('mousemove', (event) => {
            pointerX = event.clientX;
            pointerY = event.clientY;
            if (inspectEnabled) {
                // Freeze hover when an editor panel is open — moving the mouse
                // to interact with the color picker or typography controls must
                // not re-inspect the element under the cursor.
                if (tokenEditorOpen || typographyEditorOpen) {
                    return;
                }
                hovered = inspectableAt(pointerX, pointerY);
                draw();
            }
        }, true);

        document.addEventListener('click', (event) => {
            if (!inspectEnabled) {
                return;
            }
            if (isInternal(event.target)) {
                return;
            }
            const target = inspectableAt(event.clientX, event.clientY);
            if (!(target instanceof HTMLElement)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            selected = target;
            draw();
        }, true);

        document.addEventListener('keydown', (event) => {
            const activeTag = document.activeElement ? document.activeElement.tagName : '';
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag) || document.activeElement?.isContentEditable) {
                return;
            }
            if (event.key === 'i' || event.key === 'I') {
                event.preventDefault();
                inspectEnabled = !inspectEnabled;
                if (inspectEnabled) {
                    hovered = inspectableAt(pointerX, pointerY);
                } else {
                    hovered = null;
                }
                syncRootState();
                draw();
            } else if (event.key === 'm' || event.key === 'M') {
                event.preventDefault();
                measureEnabled = !measureEnabled;
                syncRootState();
                draw();
            } else if (event.key === 'u' || event.key === 'U') {
                event.preventDefault();
                undoLastChange();
            } else if (event.key === 't' || event.key === 'T') {
                event.preventDefault();
                editToken();
            } else if (event.key === 'p' || event.key === 'P') {
                event.preventDefault();
                editTypography();
            } else if (event.key === 'Escape') {
                clearInspector();
            }
        }, true);

        window.addEventListener('resize', draw);
        window.addEventListener('scroll', draw, true);
    }

    function loadThemeTokens() {
        if (!themeManifestUrl || !window.fetch) {
            return;
        }

        window.fetch(themeManifestUrl, { credentials: 'same-origin' })
            .then((response) => response.ok ? response.json() : null)
            .then((manifest) => {
                if (!manifest || typeof manifest !== 'object' || !manifest.tokens || typeof manifest.tokens !== 'object') {
                    return;
                }

                themeTokens = Object.entries(manifest.tokens)
                    .filter((entry) => typeof entry[0] === 'string' && typeof entry[1] === 'string')
                    .map(([key, value]) => ({
                        key,
                        value,
                        normalized: normalizeColor(value),
                    }))
                    .filter((token) => token.normalized !== '');

                draw();
            })
            .catch(() => {});
    }

    function renderToolbar() {
        if (!document.body || document.getElementById(TOOLBAR_ID)) {
            return;
        }

        const layer = document.createElement('div');
        layer.id = INSPECTOR_ID;
        layer.innerHTML = `
            <div class="cssholmes-box" data-role="hover-box"></div>
            <div class="cssholmes-box" data-role="selected-box"></div>
            <div class="cssholmes-label" data-role="focus-label"></div>
            <div class="cssholmes-line" data-role="line-top"></div>
            <div class="cssholmes-line" data-role="line-right"></div>
            <div class="cssholmes-line" data-role="line-bottom"></div>
            <div class="cssholmes-line" data-role="line-left"></div>
            <div class="cssholmes-badge" data-role="badge-top"></div>
            <div class="cssholmes-badge" data-role="badge-right"></div>
            <div class="cssholmes-badge" data-role="badge-bottom"></div>
            <div class="cssholmes-badge" data-role="badge-left"></div>`;
        document.body.appendChild(layer);

        const style = document.createElement('style');
        style.textContent = `
            #${TOOLBAR_ID}, #${INSPECTOR_ID} { font-family:system-ui,-apple-system,sans-serif; }
            #${TOOLBAR_ID} * , #${INSPECTOR_ID} * { box-sizing:border-box; }
            #${TOOLBAR_ID}{position:fixed;right:1rem;bottom:1rem;z-index:2147483647;max-width:min(34rem,calc(100vw - 2rem));padding:.55rem .7rem;border-radius:10px;background:#111827;color:#f9fafb;box-shadow:0 10px 30px rgba(0,0,0,.3);font-size:13px;text-align:left}
            #${TOOLBAR_ID} .cssholmes-shell{display:flex;flex-direction:column;gap:.45rem}
            #${TOOLBAR_ID} .cssholmes-row,#${TOOLBAR_ID} .cssholmes-profiles,#${TOOLBAR_ID} .cssholmes-tools,#${TOOLBAR_ID} .cssholmes-counts{display:flex;gap:.35rem;align-items:center;flex-wrap:wrap}
            #${TOOLBAR_ID} .cssholmes-logo{font-weight:700;color:#60a5fa}
            #${TOOLBAR_ID} .cssholmes-scope,#${TOOLBAR_ID} .cssholmes-mode{padding:.1rem .45rem;border-radius:999px;background:#1e293b;color:#94a3b8;font-size:11px;text-transform:uppercase}
            #${TOOLBAR_ID} .cssholmes-mode{background:${genericMode ? '#7c2d12' : '#14532d'};color:${genericMode ? '#fed7aa' : '#bbf7d0'}}
            #${TOOLBAR_ID} .cssholmes-btn{border:1px solid #475569;background:#334155;color:#cbd5e1;border-radius:999px;padding:.2rem .55rem;cursor:pointer;font:inherit;line-height:1.2}
            #${TOOLBAR_ID} .cssholmes-btn:hover{background:#475569;color:#fff}
            #${TOOLBAR_ID} .cssholmes-btn:disabled{opacity:.45;cursor:default}
            #${TOOLBAR_ID} .cssholmes-btn--active{background:#2563eb;border-color:#3b82f6;color:#fff}
            #${TOOLBAR_ID} .cssholmes-btn--ghost{background:transparent}
            #${TOOLBAR_ID} .cssholmes-count{padding:.1rem .35rem;border-radius:999px;background:#1f2937}
            #${TOOLBAR_ID} .cssholmes-count--error{color:#f87171}
            #${TOOLBAR_ID} .cssholmes-count--warning{color:#fbbf24}
            #${TOOLBAR_ID} .cssholmes-detail{min-height:3.25rem;padding:.5rem .6rem;border:1px solid #334155;border-radius:.75rem;background:#0f172a;color:#94a3b8;line-height:1.4;text-align:left}
            #${TOOLBAR_ID} .cssholmes-detail-name{font-weight:700;color:#e2e8f0;margin-bottom:.2rem}
            #${TOOLBAR_ID} .cssholmes-detail-path{margin-bottom:.35rem;color:#93c5fd;overflow-wrap:anywhere}
            #${TOOLBAR_ID} .cssholmes-detail-grid{display:grid;grid-template-columns:auto 1fr;gap:.2rem .55rem}
            #${TOOLBAR_ID} .cssholmes-detail-grid span{color:#94a3b8}
            #${TOOLBAR_ID} .cssholmes-detail-grid strong{font-size:12px;color:#e2e8f0;overflow-wrap:anywhere}
            #${TOOLBAR_ID} .cssholmes-token-list{margin-top:.45rem;display:flex;gap:.35rem;align-items:center;flex-wrap:wrap}
            #${TOOLBAR_ID} .cssholmes-token-list span{color:#94a3b8}
            #${TOOLBAR_ID} .cssholmes-token-list code{padding:.12rem .35rem;border-radius:.4rem;background:#1e293b;color:#86efac}
            #${TOOLBAR_ID} .cssholmes-editor{padding:.45rem .6rem;border:1px solid #334155;border-radius:.75rem;background:#0b1220}
            #${TOOLBAR_ID} .cssholmes-editor-head{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.45rem}
            #${TOOLBAR_ID} .cssholmes-editor-head strong{color:#e2e8f0;font-size:12px}
            #${TOOLBAR_ID} .cssholmes-token-editor-list{display:flex;flex-direction:column;gap:.5rem}
            #${TOOLBAR_ID} .cssholmes-token-editor-row{padding:.45rem;border-radius:.65rem;background:#0f172a;border:1px solid #1e293b}
            #${TOOLBAR_ID} .cssholmes-token-editor-meta{display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem}
            #${TOOLBAR_ID} .cssholmes-token-editor-meta code{color:#86efac}
            #${TOOLBAR_ID} .cssholmes-token-editor-meta span{color:#94a3b8;font-size:11px}
            #${TOOLBAR_ID} .cssholmes-token-editor-controls{display:grid;grid-template-columns:3rem minmax(0,1fr) auto;gap:.45rem;align-items:center}
            #${TOOLBAR_ID} .cssholmes-token-editor-controls input[type="color"]{width:100%;height:2rem;padding:0;border:1px solid #475569;border-radius:.45rem;background:#111827}
            #${TOOLBAR_ID} .cssholmes-token-editor-controls input[type="text"]{width:100%;padding:.35rem .45rem;border:1px solid #475569;border-radius:.45rem;background:#111827;color:#e2e8f0;font:inherit}
            #${TOOLBAR_ID} .cssholmes-typography-editor-list{display:flex;flex-direction:column;gap:.5rem}
            #${TOOLBAR_ID} .cssholmes-typography-editor-row{padding:.45rem;border-radius:.65rem;background:#0f172a;border:1px solid #1e293b}
            #${TOOLBAR_ID} .cssholmes-typography-editor-meta{display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem}
            #${TOOLBAR_ID} .cssholmes-typography-editor-meta span{color:#e2e8f0;font-size:12px}
            #${TOOLBAR_ID} .cssholmes-typography-editor-meta code{color:#93c5fd}
            #${TOOLBAR_ID} .cssholmes-typography-editor-controls{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem;align-items:center}
            #${TOOLBAR_ID} .cssholmes-typography-editor-controls input[type="text"],#${TOOLBAR_ID} .cssholmes-typography-editor-controls select{width:100%;padding:.35rem .45rem;border:1px solid #475569;border-radius:.45rem;background:#111827;color:#e2e8f0;font:inherit}
            #${TOOLBAR_ID} .cssholmes-changes{padding:.45rem .6rem;border:1px solid #334155;border-radius:.75rem;background:#0b1220}
            #${TOOLBAR_ID} .cssholmes-changes-empty{color:#64748b}
            #${TOOLBAR_ID} .cssholmes-changes-head{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.35rem}
            #${TOOLBAR_ID} .cssholmes-changes-head strong{color:#e2e8f0;font-size:12px}
            #${TOOLBAR_ID} .cssholmes-change-list{display:flex;flex-direction:column;gap:.1rem}
            #${TOOLBAR_ID} .cssholmes-change-row{display:grid;grid-template-columns:auto auto 1fr 1fr;gap:.35rem;align-items:start;font-size:12px;padding:.15rem 0}
            #${TOOLBAR_ID} .cssholmes-change-row span{color:#64748b}
            #${TOOLBAR_ID} .cssholmes-change-row strong{color:#f8fafc;font-weight:600;text-transform:uppercase;font-size:11px}
            #${TOOLBAR_ID} .cssholmes-change-row code{color:#93c5fd;overflow-wrap:anywhere}
            #${TOOLBAR_ID} .cssholmes-change-row em{color:#cbd5e1;font-style:normal;overflow-wrap:anywhere}
            #${TOOLBAR_ID} .cssholmes-export{margin:.45rem 0 0;padding:.45rem;border-radius:.55rem;background:#020617;color:#cbd5e1;font-size:11px;line-height:1.35;max-height:9rem;overflow:auto;white-space:pre-wrap}
            #${INSPECTOR_ID}{position:absolute;inset:0;pointer-events:none;z-index:2147483646}
            #${INSPECTOR_ID} .cssholmes-box,#${INSPECTOR_ID} .cssholmes-label,#${INSPECTOR_ID} .cssholmes-line,#${INSPECTOR_ID} .cssholmes-badge{position:absolute;display:none}
            #${INSPECTOR_ID} .cssholmes-box{background:rgba(56,189,248,.08)}
            #${INSPECTOR_ID} [data-role="selected-box"]{background:rgba(245,158,11,.08)}
            #${INSPECTOR_ID} .cssholmes-label,#${INSPECTOR_ID} .cssholmes-badge{padding:.2rem .45rem;border-radius:.45rem;font:600 12px/1.2 system-ui,-apple-system,sans-serif;color:#fff;white-space:nowrap}
            #${INSPECTOR_ID} .cssholmes-label{background:#0284c7}
            #${INSPECTOR_ID} .cssholmes-line{background:#34d399}
            #${INSPECTOR_ID} .cssholmes-badge{background:#065f46;color:#d1fae5}`;
        document.head.appendChild(style);

        const element = document.createElement('div');
        element.id = TOOLBAR_ID;
        element.innerHTML = `
            <div class="cssholmes-shell">
                <div class="cssholmes-row">
                    <span class="cssholmes-logo">cssHolmes</span>
                    <span class="cssholmes-scope">${pageScope}</span>
                    <span class="cssholmes-mode">${genericMode ? 'generic' : 'xtf'}</span>
                    <span class="cssholmes-profiles">${PROFILE_ORDER.map((profile) => `<button type="button" class="cssholmes-btn${activeProfiles.includes(profile) ? ' cssholmes-btn--active' : ''}" data-profile="${profile}">${profile.replace('xtf-', '')}</button>`).join('')}</span>
                </div>
                <div class="cssholmes-row">
                    <span class="cssholmes-tools">
                        <button type="button" class="cssholmes-btn" data-action="inspect">inspect</button>
                        <button type="button" class="cssholmes-btn" data-action="measure">measure</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="edit-token">token</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="edit-typography">typo</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="edit-text">text</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="undo">undo</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="select-widget">widget</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="select-slot">slot</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="copy-selector">copy text</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="copy-json">copy json</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="export-selected">export sel</button>
                        <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="reset-inspector">reset</button>
                    </span>
                    <span class="cssholmes-counts">
                        <span class="cssholmes-count cssholmes-count--error">E:<span data-role="errors">0</span></span>
                        <span class="cssholmes-count cssholmes-count--warning">W:<span data-role="warnings">0</span></span>
                    </span>
                    <button type="button" class="cssholmes-btn cssholmes-btn--ghost" data-action="clear">clear</button>
                </div>
                <div class="cssholmes-detail" data-role="details"></div>
                <div class="cssholmes-editor" data-role="editor" style="display:none;"></div>
                <div class="cssholmes-changes" data-role="changes"></div>
            </div>`;

        element.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const profile = target.getAttribute('data-profile');
            if (profile) {
                toggleProfile(profile);
                return;
            }
            switch (target.getAttribute('data-action')) {
                case 'clear':
                    activeProfiles = [];
                    persistProfiles(activeProfiles);
                    syncRootState();
                    ensureStylesheets();
                    updateUrl();
                    refreshToolbar();
                    break;
                case 'inspect':
                    inspectEnabled = !inspectEnabled;
                    hovered = inspectEnabled ? inspectableAt(pointerX, pointerY) : null;
                    syncRootState();
                    draw();
                    break;
                case 'measure':
                    measureEnabled = !measureEnabled;
                    syncRootState();
                    draw();
                    break;
                case 'copy-selector':
                    copySelector();
                    break;
                case 'copy-json':
                    copyInspectionJson();
                    break;
                case 'export-selected':
                    exportSelected();
                    break;
                case 'edit-token':
                    editToken();
                    break;
                case 'edit-typography':
                    editTypography();
                    break;
                case 'close-token-editor':
                    tokenEditorOpen = false;
                    refreshToolbar();
                    break;
                case 'close-typography-editor':
                    typographyEditorOpen = false;
                    refreshToolbar();
                    break;
                case 'apply-token': {
                    const tokenKey = target.getAttribute('data-token-key') || '';
                    const input = toolbar?.querySelector(`[data-role="token-value"][data-token-key="${CSS.escape(tokenKey)}"]`);
                    if (input instanceof HTMLInputElement) {
                        applyTokenEdit(tokenKey, input.value);
                    }
                    break;
                }
                case 'apply-typography': {
                    const styleKey = target.getAttribute('data-style-key') || '';
                    const input = toolbar?.querySelector(`[data-role="typography-value"][data-style-key="${CSS.escape(styleKey)}"]`);
                    if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
                        applyTypographyEdit(styleKey, input.value);
                    }
                    break;
                }
                case 'edit-text':
                    editText();
                    break;
                case 'undo':
                    undoLastChange();
                    break;
                case 'copy-export':
                    copyExport();
                    break;
                case 'select-widget':
                    selected = widgetTargetFor(currentTarget());
                    if (selected) {
                        draw();
                    }
                    break;
                case 'select-slot':
                    selected = slotTargetFor(currentTarget());
                    if (selected) {
                        draw();
                    }
                    break;
                case 'reset-inspector':
                    clearInspector();
                    break;
                default:
                    break;
            }
        });

        document.body.appendChild(element);
        toolbar = element;
        details = element.querySelector('[data-role="details"]');
        copyTextButton = element.querySelector('[data-action="copy-selector"]');
        copyJsonButton = element.querySelector('[data-action="copy-json"]');
        element.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }
            if ('token-color' === target.getAttribute('data-role')) {
                const tokenKey = target.getAttribute('data-token-key') || '';
                const valueInput = toolbar?.querySelector(`[data-role="token-value"][data-token-key="${CSS.escape(tokenKey)}"]`);
                if (valueInput instanceof HTMLInputElement) {
                    valueInput.value = target.value;
                }
            }
        });
        attachListeners();
        loadThemeTokens();
        draw();
        window.setTimeout(refreshToolbar, 120);
    }

    activeProfiles = supportedProfiles(resolveProfiles());
    syncRootState();
    ensureStylesheets();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderToolbar, { once: true });
    } else {
        renderToolbar();
    }
})();
