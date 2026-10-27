/* Portable, read-only browser diagnostics; no XOOPS bootstrap or saved state. */
(() => {
    'use strict';
    let cache = new WeakMap();
    function declarations(style, names) {
        for (const name of style || []) if (name.startsWith('--')) names.add(name);
    }
    function discover(document) {
        const sheets = [...document.styleSheets, ...(document.adoptedStyleSheets || [])];
        const previous = cache.get(document);
        if (previous && sheets.length === previous.sheets.length && sheets.every((sheet, index) => sheet === previous.sheets[index])) return previous;
        const result = { sheets, names: new Set(), incomplete: false };
        const visited = new Set();
        function rules(sheet) {
            if (visited.has(sheet)) return;
            visited.add(sheet);
            try { walk(sheet.cssRules); } catch (_) { result.incomplete = true; }
        }
        function walk(list) {
            for (const rule of list || []) {
                declarations(rule.style, result.names);
                if (rule.styleSheet) rules(rule.styleSheet);
                if (rule.cssRules) walk(rule.cssRules);
            }
        }
        sheets.forEach(rules);
        cache.set(document, result);
        return result;
    }
    function color(value) {
        const match = value.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)$/);
        if (!match) return null;
        const rgba = match.slice(1, 4).map(Number);
        rgba.push(match[4] === undefined ? 1 : Number(match[4]));
        return rgba.every(Number.isFinite) && rgba.slice(0, 3).every(value => value >= 0 && value <= 255) && rgba[3] >= 0 && rgba[3] <= 1 ? rgba : null;
    }
    function over(front, back) {
        const alpha = front[3] + back[3] * (1 - front[3]);
        return [...front.slice(0, 3).map((value, index) => alpha ? (value * front[3] + back[index] * back[3] * (1 - front[3])) / alpha : 0), alpha];
    }
    function luminance(rgb) {
        const linear = rgb.slice(0, 3).map(value => { value /= 255; return value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4; });
        return linear[0] * .2126 + linear[1] * .7152 + linear[2] * .0722;
    }
    const unknown = reason => ({ status: 'unknown', reason });
    const rgb = values => `rgb(${values.slice(0, 3).map(value => Math.round(value * 100) / 100).join(', ')})`;
    function contrast(element, computed) {
        const view = element.ownerDocument.defaultView;
        if (!element.getClientRects().length || computed.visibility !== 'visible') return unknown('hidden');
        if (!element.textContent.trim() || element.children.length || /^(INPUT|TEXTAREA|SELECT|BUTTON|IMG|VIDEO|CANVAS|SVG)$/.test(element.tagName)) return unknown('text');
        const foreground = color(computed.color);
        if (!foreground) return unknown('color');
        let background = [0, 0, 0, 0];
        const ancestors = new Set();
        for (let current = element; current; current = current.parentElement) {
            ancestors.add(current);
            const style = view.getComputedStyle(current);
            if (Number(style.opacity) !== 1 || style.filter !== 'none' || style.mixBlendMode !== 'normal'
                || (style.backdropFilter && style.backdropFilter !== 'none') || (style.maskImage && style.maskImage !== 'none')) return unknown('effects');
            if (style.textShadow !== 'none' || (style.webkitTextFillColor && style.webkitTextFillColor !== style.color)
                || parseFloat(style.webkitTextStrokeWidth || '0') !== 0) return unknown('effects');
            for (const pseudo of ['::before', '::after']) {
                const generated = view.getComputedStyle(current, pseudo);
                if (generated.display !== 'none' && !['none', 'normal'].includes(generated.content)) return unknown('layers');
            }
            if (background[3] < 1) {
                if (style.backgroundImage !== 'none' || style.backgroundClip.includes('text') || /\binset\b/.test(style.boxShadow)) return unknown('background');
                const layer = color(style.backgroundColor);
                if (!layer) return unknown('color');
                background = over(background, layer);
            }
        }
        if (background[3] !== 1) return unknown('surface');
        // shortcut: hit tests cannot prove absence of every painted overlap; keep the estimate disclaimer and reject detected foreign layers.
        const rect = element.getBoundingClientRect();
        const x = Math.max(0, Math.min(view.innerWidth - 1, rect.left + rect.width / 2));
        const y = Math.max(0, Math.min(view.innerHeight - 1, rect.top + rect.height / 2));
        if (rect.bottom > 0 && rect.top < view.innerHeight && rect.right > 0 && rect.left < view.innerWidth) {
            for (const layer of element.ownerDocument.elementsFromPoint(x, y)) {
                if (layer.id === 'cssholmes-designer') continue;
                if (!ancestors.has(layer) && !element.contains(layer)) return unknown('layers');
                if (ancestors.has(layer) && color(view.getComputedStyle(layer).backgroundColor)?.[3] === 1) break;
            }
        }
        const painted = over(foreground, background);
        const first = luminance(painted), second = luminance(background);
        return { status: 'measured', ratio: (Math.max(first, second) + .05) / (Math.min(first, second) + .05), foreground: rgb(painted), background: rgb(background) };
    }
    function inspect(element) {
        if (!element?.isConnected || !element.ownerDocument?.defaultView) return { tokens: [], discoveryIncomplete: false, contrast: unknown('selection') };
        const computed = element.ownerDocument.defaultView.getComputedStyle(element);
        const discovered = discover(element.ownerDocument);
        const names = new Set(discovered.names);
        declarations(computed, names);
        for (let current = element; current; current = current.parentElement) declarations(current.style, names);
        const tokens = [...names].sort().map(name => ({ name, value: computed.getPropertyValue(name).trim() })).filter(token => token.value);
        return { tokens, discoveryIncomplete: discovered.incomplete, contrast: contrast(element, computed) };
    }
    window.CssHolmesDiagnostics = { inspect, refresh: () => { cache = new WeakMap(); } };
})();
