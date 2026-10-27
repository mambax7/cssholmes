# Visual theme designer

Start with the [illustrated cssHolmes + xStyles tutorial](TUTORIAL-xStyles.md)
for a short inspect, edit, compare and publish walkthrough.

The designer uses a guided property sidebar: select an element, inspect its
scope, preview styles, then explicitly save to the active theme. xStyles is
the repeatable fixture canvas; cssHolmes owns editing and theme persistence.
Interaction references: Stylebot (https://stylebot.dev/), Webflow's Style panel,
and VisBug's element selection. No extension or external service is required.

## Implementation plan

1. Implement bounded selectors/properties and a private per-theme JSON store.
   Use atomic replacement, file locking, optimistic revisions and a previous
   save for restore. Never rewrite vendor stylesheets or executable PHP config.
2. Add authenticated JSON read/save/restore endpoints with existing cssHolmes
   overlay/edit/workbench permissions and XOOPS CSRF validation.
3. Load generated theme CSS for every visitor while cssHolmes is active.
   Keep editing assets and configuration restricted to authorized editors.
4. Add the accessible Shadow DOM property sidebar, stable component selection,
   live changes, undo/redo, reset/discard, export and conflict feedback.
5. Connect the existing cssHolmes inspector and the xStyles gallery to the same
   designer. Save selectors that apply to real pages rather than fixture IDs.
6. Verify validation, persistence, conflicts, restore, authorization/CSRF,
   public CSS and browser selection/edit/save/reload/reset flows. Preserve the
   original module copies and apply only reviewed files to the requested site.

## Initial supported editing

Colours, typography, spacing, dimensions, borders and common layout properties.
Rules can target all/light/dark modes, normal/hover/focus states, and all/mobile/
tablet/desktop viewports (575px and 991px boundaries). This edits presentation;
content, image replacement, DOM rearrangement and arbitrary JavaScript are not
theme settings and are outside this implementation.

The first saved settings use a dedicated cssHolmes theme configuration, not a
theme's unrelated legacy PHP options or its vendor framework files. JSON/CSS
export supports moving settings to another environment. Runtime CSS requires
the cssHolmes module to remain active.

## Using it

1. Log in as a cssHolmes module administrator. Other groups need both Overlay
   and Edit permission to preview; Workbench permission also allows saving.
2. Open xStyles and choose **Open theme designer**, or use **Design theme** on
   any front-end page. The existing cssHolmes toolbar also opens the designer.
3. Choose **Pick element**, then click a specimen such as a comment title.
   The panel shows the selector and number of matching elements. Comment
   titles use a reusable selector, so the change also reaches actual comments.
4. Enter a text colour and font size, for example `#2255aa` and `28px`.
   Blank fields remove overrides. The current computed value appears beneath
   each field. Choose a colour mode, screen size or interaction state if needed.
5. Use **Save to theme** to publish the changes for that theme. Undo/Redo only
   changes the current draft; **Discard draft** returns to the last save.
   **Reset selected** removes that rule's overrides; save to publish the reset.
6. **More actions** contains CSS/JSON exports and **Restore previous save**.
   Restore replaces the saved configuration and consumes the one previous-save
   slot. A conflicting save from another editor is rejected rather than lost.

The picker cancels link navigation while selecting. Escape cancels picking;
keyboard users can focus a page element and press Enter while picking.
Changes apply to every element matching the displayed selector. Refine the
selector before saving if a generic button or heading matches too much.

Editing has no separate time limit. Theme and library writes refresh the
security token automatically without reloading the draft or changing its
conflict-detection revision. The site's normal login-session policy still
applies; if sign-in expires, sign in using another tab and retry while keeping
the editor tab open. An older open editor that reports a renewed token can
retry the action once without reloading or discarding its draft.

Mode and viewport scopes follow the actual page mode and viewport: resize the
page or use xStyles' mode switcher to see those rules. Hover and focus rules
follow actual pointer hover and keyboard focus. Mode rules use root
`data-bs-theme` or XTF's `data-theme`. Explicit Bootstrap light/dark takes
precedence, then explicit XTF light/dark, then the system preference when neither
is explicit. Other conventions still need an adapter. Existing settings remain
unchanged; the public stylesheet ETag now tracks generated CSS so compiler
updates reach visitors without requiring a settings resave.

## Tokens and contrast

Select an element and expand **Tokens and contrast**. Search resolved custom
properties (including XTF `--xtf-*` tokens), inspect swatches and read the measured
foreground/background pair. Only the first matching element is measured. Plain
text with supported solid sRGB backgrounds is supported; nested content, native
controls, images/gradients, uncertain layers and compositing effects report
**Cannot determine automatically**. A measured ratio is an estimate, not a theme
accessibility certificate. Normal-text AA uses 4.5:1; large-text AA uses 3:1.

The panel refreshes after previews, undo/redo and mode changes. Refresh diagnostics
after stylesheet changes not observed by the browser. Inspection never publishes
or adds history entries. Observers stop when the designer closes.

When a measured ratio is below 4.5:1, **Adjust text color** and **Adjust background
color** focus the corresponding control and highlight the selected element.
Markup problems, such as a missing label or alternative text, still require a
template change; changing CSS cannot repair them.

## Inspect, edit and compare in xStyles

1. Open the frontend gallery with Overlay and Edit permissions. Each example
   offers **Inspect example** and **Edit styles**. These select its first relevant
   element; use **Pick element** for another target and check the selector before editing.
2. Expand **Preview your changes** and choose **Published vs draft**. Both panes
   use the same theme, example and viewport width.
3. Choose **Draft at three screen sizes** to check 360, 768 and 1280 CSS pixels.
   Wider panes scroll horizontally. Changes, undo, redo and discard update the
   draft panes without saving.
4. Use **Save to theme** only when ready to publish. Publishing refreshes the
   panes. Closing the preview panel removes its frames; closing the designer
   retains the draft in the current page. Navigating away loses unsaved changes.

Draft CSS stays in memory in this tab and its owned, matching-theme frames. It
is not included in URLs or sent to a public endpoint. The ordinary xStyles
theme comparison and responsive panels continue to show published CSS only.
Embedded previews suppress editing/overlay assets. Admin-theme editing is not
supported. See [XOOPS 4 migration notes](xoops4-migration.md).

## Storage and upgrades

Settings live in `XOOPS_VAR_PATH/configs/cssholmes/designer/`, outside the theme
directories. Back up that directory with the site's other private settings.
The web server needs permission to create/write it when an editor first saves.
Theme installation and upgrades leave these settings alone. The module update
operation can refresh the displayed version; there is no database migration.
No files are created by a public visit or a preview without saving.

CSS export is portable: include it after the theme styles if cssHolmes is later
disabled. JSON export uses the Library's versioned portable-set format and can
be imported again; see [theme-sets.md](theme-sets.md). Earlier draft exports
containing only `theme` and `rules` are validated and converted during import. Do not edit
the stored JSON by hand while editors are saving.

## Verification

Use the complete source checkout described in [Development checks](../README.md#development-checks).
These scripts arrive with the final QA change; documentation-only branches and
release ZIPs do not contain the test suite.

Run `php tests/designer-access.php`, `php tests/designer-store.php`, and
`php tests/designer-http.php`. The HTTP check builds an isolated temporary test
site, never a production authentication bypass. For the full browser roundtrip,
set `PLAYWRIGHT_MODULE` to an existing Playwright installation and run
`php tests/designer-http.php --browser`. `node tests/designer-client.cjs` checks
the panel interactions with a mocked endpoint. `CHROME_PATH` can select Chrome.
