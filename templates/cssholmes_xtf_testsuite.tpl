<head>
    <title>cssHolmes - XTF/XMF Test Suite</title>
    <link rel="stylesheet" href="<{$mod_url}>/assets/css/profiles/html5.css" media="screen,projection,print,handheld" type="text/css">
    <link rel="stylesheet" href="<{$mod_url}>/assets/css/profiles/xtf-theme.css" media="screen,projection,print,handheld" type="text/css">
    <link rel="stylesheet" href="<{$mod_url}>/assets/css/profiles/xtf-widget.css" media="screen,projection,print,handheld" type="text/css">
    <link rel="stylesheet" href="<{$mod_url}>/assets/css/profiles/a11y.css" media="screen,projection,print,handheld" type="text/css">
    <link rel="stylesheet" href="<{$mod_url}>/assets/css/profiles/layout.css" media="screen,projection,print,handheld" type="text/css">
    <style>
        :root {
            --suite-bg: #e2e8f0;
            --suite-text: #0f172a;
            --suite-muted: #475569;
            --suite-muted-2: #64748b;
            --suite-surface: #ffffff;
            --suite-surface-2: #f8fafc;
            --suite-line: #dbe3ef;
            --suite-line-strong: #cbd5e1;
            --suite-brand: #2563eb;
            --suite-brand-soft: #93c5fd;
            --suite-brand-deep: #0f172a;
            --suite-hero-2: #1e293b;
            --suite-hero-3: #334155;
            --suite-shadow: 0 16px 44px rgba(15, 23, 42, 0.18);
            --suite-shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
            --suite-success-bg: #dcfce7;
            --suite-success-text: #166534;
            --suite-warn-bg: #fef3c7;
            --suite-warn-text: #92400e;
            --suite-info-bg: #dbeafe;
            --suite-info-text: #1d4ed8;
            --suite-problem-a11y: #b91c1c;
            --suite-problem-a11y-line: #fecaca;
            --suite-problem-layout: #c2410c;
            --suite-problem-layout-line: #fdba74;
            --suite-problem-form-line: #fcd34d;
            --suite-problem-form-text: #92400e;
            --suite-action-surface: #0f172a;
            --suite-action-text: #e2e8f0;
            --suite-action-line: #64748b;
            --suite-button-primary: #2563eb;
            --suite-button-danger: #ef4444;
            --suite-warning-surface: #fff7ed;
            --suite-warning-line: #fdba74;
            --suite-warning-text: #7c2d12;
            --suite-problem-soft: #fff7ed;
        }

        body.cssholmes-suite {
            margin: 0;
            font: 16px/1.5 "Segoe UI", Tahoma, sans-serif;
            background: var(--suite-bg);
            color: var(--suite-text);
        }

        .suite-shell {
            max-width: 76rem;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem;
        }

        .suite-hero {
            padding: 1.5rem;
            border-radius: 1.25rem;
            background: linear-gradient(135deg, var(--suite-brand-deep) 0%, var(--suite-hero-2) 55%, var(--suite-hero-3) 100%);
            color: #f8fafc;
            box-shadow: var(--suite-shadow);
        }

        .suite-kicker {
            margin: 0 0 0.35rem;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--suite-brand-soft);
        }

        .suite-hero h1 {
            margin: 0 0 0.75rem;
            font-size: 2rem;
            line-height: 1.1;
        }

        .suite-hero p {
            margin: 0;
            max-width: 54rem;
            color: #cbd5e1;
        }

        .suite-grid,
        .suite-main,
        .suite-card-grid,
        .suite-problem-grid {
            display: grid;
            gap: 1rem;
        }

        .suite-grid {
            grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
            margin-top: 1rem;
        }

        .suite-main {
            grid-template-columns: 2fr 1fr;
            margin-top: 1.25rem;
        }

        .suite-card-grid,
        .suite-problem-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .suite-column {
            display: grid;
            gap: 1rem;
        }

        .suite-card {
            padding: 1rem;
            border-radius: 1rem;
            background: var(--suite-surface);
            border: 1px solid var(--suite-line);
        }

        .suite-card--raised {
            padding: 1.25rem;
            box-shadow: var(--suite-shadow-soft);
        }

        .suite-card--warning {
            background: var(--suite-warning-surface);
            border-color: var(--suite-warning-line);
            color: var(--suite-warning-text);
        }

        .suite-card--action {
            background: var(--suite-action-surface);
            color: var(--suite-action-text);
            border-color: var(--suite-action-line);
        }

        .suite-card--problem-a11y {
            border-color: var(--suite-problem-a11y-line);
        }

        .suite-card--problem-layout {
            border-color: var(--suite-problem-layout-line);
        }

        .suite-card--problem-form {
            border-color: var(--suite-problem-form-line);
        }

        .suite-card h2,
        .suite-card h3,
        .suite-card h4 {
            margin: 0 0 0.5rem;
            color: inherit;
        }

        .suite-card p {
            margin: 0;
            color: inherit;
        }

        .suite-row {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 1rem;
        }

        .suite-accent {
            margin: 0 0 0.35rem;
            color: var(--xtf-color-text-sidebar-active, var(--suite-brand));
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .suite-problem-label {
            margin: 0 0 0.35rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .suite-problem-label--a11y {
            color: var(--suite-problem-a11y);
        }

        .suite-problem-label--layout {
            color: var(--suite-problem-layout);
        }

        .suite-accent-box {
            width: 4rem;
            height: 4rem;
            border-radius: 1rem;
            background: linear-gradient(135deg, #0ea5e9, var(--suite-brand));
            flex: 0 0 auto;
        }

        .suite-list {
            margin: 1rem 0 0;
            padding-left: 1.2rem;
        }

        .suite-list li {
            margin-bottom: 0.45rem;
        }

        .suite-stat-label {
            margin: 0;
            color: var(--suite-muted-2);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .suite-stat-number {
            margin: 0.25rem 0 0;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .suite-table {
            width: 100%;
            border-collapse: collapse;
        }

        .suite-table th {
            text-align: left;
            padding: 0.5rem;
            border-bottom: 1px solid var(--suite-line-strong);
        }

        .suite-table td {
            padding: 0.65rem 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .suite-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .suite-badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
        }

        .suite-badge--healthy {
            background: var(--suite-success-bg);
            color: var(--suite-success-text);
        }

        .suite-badge--review {
            background: var(--suite-warn-bg);
            color: var(--suite-warn-text);
        }

        .suite-badge--new {
            background: var(--suite-info-bg);
            color: var(--suite-info-text);
        }

        .suite-preview-image {
            width: 100%;
            height: auto;
            border-radius: 0.75rem;
            display: block;
        }

        .suite-preview-image--problem {
            margin-top: 0.75rem;
        }

        .suite-help {
            margin-top: 0.75rem;
        }

        .suite-help--warning {
            color: var(--suite-warning-text);
        }

        .suite-help--problem {
            color: var(--suite-problem-form-text);
        }

        .suite-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .suite-button {
            padding: 0.65rem 0.9rem;
            border-radius: 0.7rem;
            cursor: pointer;
            font: inherit;
        }

        .suite-button--primary {
            border: 0;
            background: var(--suite-button-primary);
            color: #fff;
        }

        .suite-button--ghost {
            border: 1px solid var(--suite-action-line);
            background: transparent;
            color: var(--suite-action-text);
        }

        .suite-form-input,
        .suite-form-select,
        .suite-form-textarea {
            display: block;
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid #94a3b8;
            border-radius: 0.7rem;
            font: inherit;
            background: var(--suite-surface);
            color: var(--suite-text);
        }

        .suite-form-input {
            margin: 0.25rem 0 0.75rem;
        }

        .suite-form-select {
            margin-top: 0.25rem;
        }

        .suite-form-input--problem {
            margin: 0 0 0.75rem;
            border-color: #f59e0b;
        }

        .suite-form-textarea {
            margin-top: 0.25rem;
            border-color: #f59e0b;
            min-height: 7rem;
        }

        .suite-problem-link {
            display: inline-block;
            margin-top: 0.75rem;
        }

        .suite-problem-stack {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .suite-problem-nesting {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--suite-problem-soft);
        }

        .suite-legend {
            padding: 1rem;
            border-radius: 1rem;
            background: var(--suite-surface);
            border: 1px solid var(--suite-line);
        }

        .suite-legend strong {
            color: var(--suite-text);
        }

        .suite-legend p {
            margin: 0.35rem 0 0;
            color: var(--suite-muted);
        }

        @media (max-width: 900px) {
            .suite-main,
            .suite-card-grid,
            .suite-problem-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="holmes-debug cssholmes-suite" data-cssholmes-profiles="html5,xtf-theme,xtf-widget,a11y,layout">
    <div class="suite-shell">
        <header class="suite-hero">
            <p class="suite-kicker">cssHolmes Modern Test Suite</p>
            <h1>XTF Themes, XMF Widgets, Slots, Tokens, and Admin-Like UI</h1>
            <p>
                Use this page with the cssHolmes toolbar to exercise token matching, slot and widget jumps, typography edits,
                layout checks, widget diagnostics, accessibility hints, and export-ready inspection context.
            </p>
        </header>

        <section class="suite-grid">
            <article class="suite-card" data-slot="hero">
                <h2>Suggested Flow</h2>
                <ol style="margin:0;padding-left:1.15rem;">
                    <li>Inspect a card or widget root.</li>
                    <li>Jump to the nearest widget or slot wrapper.</li>
                    <li>Try token or typography changes locally.</li>
                    <li>Export the useful result to the workbench.</li>
                </ol>
            </article>
            <article class="suite-card" data-slot="sidebar">
                <h2>Toolbar Targets</h2>
                <p>Cards below include token-backed accents, nested widgets, deliberate spacing differences, and common admin-style UI elements.</p>
            </article>
            <article class="suite-legend">
                <strong>Expected Behavior</strong>
                <p>Most cards are intended to be clean for `xtf-theme`. The `Known Problems` cards and `Problem Form` intentionally trigger warnings.</p>
            </article>
        </section>

        <section class="suite-main" data-slot="content-main">
            <div class="suite-column">
                <article class="suite-card suite-card--raised xtf-slot-widget xmf-widget-card" data-widget="feature-card" data-slot="content-main">
                    <div class="suite-row">
                        <div>
                            <p class="suite-accent">Feature Card</p>
                            <h2>Token-aware theme surface</h2>
                            <p>This widget uses token-like values, content spacing, and nested text so you can test inspect, token matching, typo edits, and export flow in one place.</p>
                        </div>
                        <div aria-hidden="true" class="suite-accent-box"></div>
                    </div>
                    <ul class="suite-list">
                        <li>Stable wrapper class for widget selection</li>
                        <li>Nested text for text replacement preview</li>
                        <li>Visible accent color for token inspection</li>
                    </ul>
                </article>

                <section class="suite-card-grid" data-slot="cards-grid">
                    <article class="suite-card xtf-slot-widget xmf-widget-card" data-widget="stats-card" data-slot="cards-grid">
                        <p class="suite-stat-label">Stats</p>
                        <p class="suite-stat-number">128</p>
                        <p>Published widget instances</p>
                    </article>
                    <article class="suite-card suite-card--warning xtf-slot-widget xmf-widget-card" data-widget="alert-card" data-slot="cards-grid">
                        <h3>Warning Card</h3>
                        <p>Useful for checking contrast, spacing, and message hierarchy.</p>
                    </article>
                </section>

                <article class="suite-card xtf-slot-widget xmf-widget-list" data-widget="module-list" data-slot="content-main">
                    <h2>Admin-like Module List</h2>
                    <table class="suite-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Status</th>
                                <th>Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Menus</td>
                                <td><span class="suite-badge suite-badge--healthy">Healthy</span></td>
                                <td>Core</td>
                            </tr>
                            <tr>
                                <td>BlocksAdmin</td>
                                <td><span class="suite-badge suite-badge--review">Review</span></td>
                                <td>Admin UI</td>
                            </tr>
                            <tr>
                                <td>Widgets</td>
                                <td><span class="suite-badge suite-badge--new">New</span></td>
                                <td>XMF</td>
                            </tr>
                        </tbody>
                    </table>
                </article>

                <section class="suite-problem-grid" data-slot="known-problems">
                    <article class="suite-card suite-card--problem-a11y xtf-slot-widget xmf-widget-card" data-widget="problem-card-a11y" data-slot="known-problems">
                        <p class="suite-problem-label suite-problem-label--a11y">Known Problems</p>
                        <h4>Accessibility and Content Smells</h4>
                        <button type="button" class="suite-button suite-button--primary" style="background:#ef4444;"></button>
                        <img class="suite-preview-image suite-preview-image--problem" src="https://dummyimage.com/300x120/fca5a5/7f1d1d.jpg&text=Missing+Alt">
                        <a class="suite-problem-link" href="#">Ambiguous link</a>
                        <p class="suite-help">This card intentionally mixes an empty button, missing image alt, and placeholder link target.</p>
                    </article>

                    <article class="suite-card suite-card--problem-layout xtf-slot-widget xmf-widget-card" data-widget="problem-card-layout" data-slot="known-problems">
                        <p class="suite-problem-label suite-problem-label--layout">Known Problems</p>
                        <h4>Layout and Widget Edge Cases</h4>
                        <div class="suite-problem-stack">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="1.5">
                                <path d="M4 12h16M12 4v16"></path>
                            </svg>
                            <span>SVG without width/height attributes</span>
                        </div>
                        <div class="outer">
                            <div class="inner">
                                <div class="deeper">
                                    <div class="deepest suite-problem-nesting">Deep wrapper nesting for layout inspection</div>
                                </div>
                            </div>
                        </div>
                        <p class="suite-help suite-help--warning">Useful for widget SVG checks, wrapper depth checks, and spacing inspection.</p>
                    </article>
                </section>
            </div>

            <aside class="suite-column" data-slot="content-sidebar">
                <article class="suite-card xtf-slot-widget xmf-widget-profile" data-widget="profile-card" data-slot="content-sidebar">
                    <h2>Profile Widget</h2>
                    <img class="suite-preview-image" src="https://dummyimage.com/320x180/cbd5e1/0f172a.jpg&text=Widget+Preview" alt="Widget preview image">
                    <p class="suite-help">Good for image sizing, token-based accents, and widget wrapper selection.</p>
                </article>

                <article class="suite-card suite-card--action xtf-slot-widget xmf-widget-actions" data-widget="action-panel" data-slot="content-sidebar">
                    <h2>Action Panel</h2>
                    <div class="suite-actions">
                        <button type="button" class="suite-button suite-button--primary">Primary Action</button>
                        <button type="button" class="suite-button suite-button--ghost">Secondary</button>
                    </div>
                    <p class="suite-help">Useful for focus, spacing, and button hierarchy checks.</p>
                </article>

                <article class="suite-card xtf-slot-widget xmf-widget-form" data-widget="settings-form" data-slot="content-sidebar">
                    <h2>Settings Form</h2>
                    <label for="xtf-theme-name">Theme name</label>
                    <input id="xtf-theme-name" class="suite-form-input" type="text" name="xtf-theme-name" value="front1">
                    <label for="xtf-slot-mode">Slot mode</label>
                    <select id="xtf-slot-mode" class="suite-form-select" name="xtf-slot-mode">
                        <option>Auto</option>
                        <option>Manual</option>
                    </select>
                </article>

                <article class="suite-card suite-card--problem-form xtf-slot-widget xmf-widget-form" data-widget="problem-form" data-slot="content-sidebar">
                    <p class="suite-problem-label suite-problem-label--layout">Known Problems</p>
                    <h2>Problem Form</h2>
                    <input class="suite-form-input suite-form-input--problem" type="text" value="Unnamed field">
                    <label>Detached label</label>
                    <textarea class="suite-form-textarea">Textarea without a name</textarea>
                    <p class="suite-help suite-help--problem">This widget intentionally contains weak form semantics for HTML5 and accessibility profile checks.</p>
                </article>
            </aside>
        </section>
    </div>
</body>
