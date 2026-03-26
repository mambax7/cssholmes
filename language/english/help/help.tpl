<div id="help-template" class="outer">
    <{include file=$smarty.const._MI_CSSHOLMES_HELP_HEADER}>

    <h4 class="odd">DESCRIPTION</h4> <br>

    <div class="even">
        <p><strong>cssHolmes</strong> is now a XOOPS design and diagnostics workbench, not just a wrapper around <code>holmes.css</code>.</p>
        <p>It combines two complementary tools:</p>
        <ul>
            <li>a live in-browser overlay for inspection, measurement, quick local edits, and export</li>
            <li>an admin-side workbench for theme scans, widget scans, import review, patch drafts, preview/apply, and rollback</li>
        </ul>
        <p>It is especially useful for:</p>
        <ul>
            <li>creating new XTF themes</li>
            <li>editing or repairing existing XTF themes</li>
            <li>building and validating XMF widgets</li>
            <li>debugging admin-theme UI, spacing, semantics, and accessibility</li>
        </ul>
        <p>On plain XOOPS installs without XTF manifests, cssHolmes still works in a reduced generic XOOPS mode for HTML5, accessibility, layout, inspect, measure, copy, export, and widget HTML diagnostics.</p>
    </div>

    <h4 class="odd">INSTALL/UNINSTALL</h4>

    <p class="even">
        No special measures necessary, follow the standard installation process –
        extract the module folder into the ../modules directory. Install the
        module through Admin -> System Module -> Modules.<br> <br>
        Detailed instructions on installing modules are available in the
        <a href="https://xoops.gitbook.io/xoops-operations-guide/" target="_blank">Chapter 2.12 of our XOOPS Operations Manual</a></p>


    <h4 class="odd">OPERATING INSTRUCTIONS</h4><br>
    <div class="even">
        <p><strong>1. Enable the overlay</strong></p>
        <p>The overlay is opt-in and is activated by query string for admin users. Typical examples:</p>
        <ul>
            <li><code>?holmes=html5</code></li>
            <li><code>?holmes=xtf-theme,a11y,layout</code></li>
            <li><code>?holmes=xtf-theme,xtf-widget</code></li>
            <li><code>?holmes=all</code></li>
        </ul>
        <p>Available profiles:</p>
        <ul>
            <li><code>html5</code></li>
            <li><code>xtf-theme</code></li>
            <li><code>xtf-widget</code></li>
            <li><code>a11y</code></li>
            <li><code>layout</code></li>
        </ul>

        <p><strong>2. Use the live toolbar</strong></p>
        <p>The toolbar supports the main day-to-day visual workflow:</p>
        <ul>
            <li><code>inspect</code> and <code>measure</code></li>
            <li><code>token</code> editing for token-aware color preview when an XTF <code>theme.json</code> manifest is available</li>
            <li><code>typo</code> editing for font size, line height, letter spacing, weight, and alignment</li>
            <li><code>text</code> for quick local text experiments</li>
            <li><code>widget</code> and <code>slot</code> jumps to the nearest wrapper</li>
            <li><code>copy text</code>, <code>copy json</code>, and <code>export sel</code></li>
            <li><code>undo</code> for local changes</li>
        </ul>
        <p>Keyboard shortcuts include <code>I</code>, <code>M</code>, <code>T</code>, <code>P</code>, <code>U</code>, and <code>Esc</code>.</p>

        <p><strong>3. Review results in the admin workbench</strong></p>
        <p>The admin side supports:</p>
        <ul>
            <li>Phase 2 theme scans for XTF themes with <code>theme.json</code></li>
            <li>widget HTML scans</li>
            <li>importing toolbar JSON exports</li>
            <li>reviewing imported changes and marking them accepted or rejected</li>
            <li>file suggestions, patch drafts, preview/apply, and rollback</li>
            <li>saved workbench history</li>
        </ul>

        <p><strong>4. Workflows where cssHolmes is most useful</strong></p>
        <ul>
            <li>finding which token is driving a rendered color in a XTF theme</li>
            <li>jumping from a nested DOM node to the real widget or slot wrapper</li>
            <li>testing typography and spacing ideas before touching templates or CSS</li>
            <li>reviewing widget HTML for accessibility and wrapper issues</li>
            <li>turning designer experiments into structured developer handoff data</li>
        </ul>

        <p>Detailed instructions on configuring the access rights for user groups are available in the
            <a href="https://xoops.gitbook.io/xoops-operations-guide/" target="_blank">Chapter 2.8 of our XOOPS Operations Manual</a>.</p>
    </div>

    <h4 class="odd">TUTORIAL</h4> <br>

    <div class="even">
        <p>The current local tutorial for designers and developers is:</p>
        <p><code>docs/TUTORIAL-cssholmes-XTF-XMF.md</code></p>
        <p>It focuses on XTF themes, XMF widgets, admin themes, inspection/export workflow, and the cases where cssHolmes becomes indispensable.</p>
        <p>Additional strategic planning material is also available locally in:</p>
        <p><code>docs/PROPOSAL-cssholmes-v2.md</code></p>
    </div>


    <h4 class="odd">TRANSLATIONS</h4> <br>
    <p class="even">
        Translations are on <a href="https://www.transifex.com/xoops/" target="_blank">Transifex</a> and in our <a href="https://github.com/XoopsLanguages/" target="_blank">XOOPS Languages Repository on GitHub</a>.</p>

    <h4 class="odd">SUPPORT</h4> <br>
    <p class="even">
        If you have questions about this module and need help, you can visit our <a href="https://xoops.org/modules/newbb/viewforum.php?forum=28/" target="_blank">Support Forums on XOOPS Website</a></p>

    <h4 class="odd">DEVELOPMENT</h4> <br>
    <p class="even">
        This module is Open Source and we would love your help in making it better. You can fork this module on <a href="https://github.com/XoopsModules25x/cssholmes" target="_blank">GitHub</a><br><br>
        But there is more happening on GitHub:<br><br>
        - <a href="https://github.com/xoops" target="_blank">XOOPS Core</a> <br>
        - <a href="https://github.com/XoopsModules25x" target="_blank">XOOPS Modules</a><br>
        - <a href="https://github.com/XoopsThemes" target="_blank">XOOPS Themes</a><br><br>
        cssHolmes is particularly interested in contributions around XTF theme analysis, widget diagnostics, admin-theme coverage, documentation, and tests.<br><br>
        Go check it out, and <strong>GET INVOLVED</strong>

    </p>

</div>
