# Theme configuration sets

## Design and implementation

This extends the existing designer with a shared library of named configurations.
The library uses private JSON storage; it does not require a database migration.
The existing per-theme store remains the sole source of published CSS, so visitors
do not load the library or receive authors' voting records.

Work reaches the format validator and schema, library storage, JSON endpoint,
designer sidebar, admin library, language labels, preload configuration, exports,
and regression tests. Existing saved theme rules and their one-step restore keep
working. No theme source files or native theme configuration are overwritten.

1. Add a bounded, locked library with immutable authorship/creation time,
   modification time, revision checks and one like per registered designer.
2. Define a versioned export standard with semantic component targets, typed
   colour tokens and explicit inheritance. Keep unsupported source-theme rules
   out of cross-theme imports and show a compatibility report.
3. Connect save-as, preview, update, portable export/import and original reset to
   the sidebar. Separate library storage from publishing to the site's theme.
4. Add the admin table grouped by installed front-end themes, search/sort/likes,
   author/date metadata, import/export and explicit activate/reset actions.
5. Supply coordinated light/dark palette starters and tested contrast pairs.
6. Verify format/ownership/likes/concurrency/CSRF, full browser workflows, and
   existing designer regressions; back up the live module before applying files.

## Saving and publishing

**Save as new set** stores a reusable named configuration without changing the
live theme. **Update selected set** replaces that library entry while retaining
its author and creation date. Only its author or a cssHolmes administrator can
update or delete it. An adapted set for another theme is saved as a new set.

**Preview selected** loads a set into the current editing draft. Applying it to
the theme is a separate explicit action. **Save to theme** publishes the draft;
the admin library also offers **Activate**. The saved CSS continues to apply to
visitors while cssHolmes is active.

**Reset theme to original** removes every published cssHolmes override for that
theme. The original is the theme's current native appearance, including its own
native settings; library entries are retained. Theme upgrades can change native
defaults. Resetting does not roll back changes made outside cssHolmes.

## Portable format

The version 1 `xoops-theme-set` format is documented by
[theme-set.schema.json](theme-set.schema.json). It contains:

- A format identifier/version, display name and source theme.
- `defaults: "inherit-target-theme"`, meaning unchanged values remain native.
- Semantic component styles such as `comment.title`, instead of fixture IDs.
- Optional colour tokens using the Design Tokens Community Group's typed sRGB
  colour shape, with explicit light/dark modes.
- Validated selectors for source-theme-specific overrides. These are omitted,
  with a warning, when adapting the set to another theme.
- An optional `sourceRules` snapshot of authored overrides preserves exact
  selector differences when loading into the original theme. Semantic roles
  provide the adaptation for another theme; they do not replace the original
  selectors during a same-theme roundtrip.

An export is a set of overrides, not a dump of every computed CSS value. This
preserves responsive rules, hover states, inheritance and future theme updates.
It also keeps the original default available. The envelope is XOOPS-specific;
using the DTCG colour shape does not make the complete document a generic DTCG
token file or guarantee third-party editor compatibility.

Imports accept JSON only, enforce bounds and validate every CSS declaration.
They never fetch external URLs, execute PHP/JavaScript or accept raw stylesheet
text. Imported sets are attributed to the local user who saves the copy. Likes,
voter IDs, internal record IDs and local account identity are not portable.
Unsupported versions and malformed or unsafe documents are rejected.
Legacy designer exports containing exactly `theme` and `rules` are validated
and converted to version 1 during import, preserving previously exported drafts.

## Library and palettes

The admin page groups configurations beneath discovered, supported front-end
themes, including those with no saved sets. Discovery requires a theme directory
containing `theme.tpl` or `theme.html`; manifest-only XTF themes and symlinked
theme directories are not listed. It supports theme/name filtering, name/date/
popularity sorting, likes, author and creation/modification dates. A like records
one vote per registered designer and can be withdrawn; repeated requests do not
add extra votes. Liking a set does not modify its design revision.

The table uses the admin theme's standard `outer` table styles and compact rows.
**Edit** opens a set in a separate designer tab; **Clone** creates an independent
copy under the current account, with actual new timestamps and zero likes.
**Delete** removes a library entry after confirmation. **Reset to original**
stays at theme level and affects published overrides, not the named library.

**Show sample rows** fills empty categories with an illustrative original-default
row: author Admin, example dates and a sample like count. These rows are marked
Sample, are never saved or published automatically, and cannot be voted on or
deleted. Edit starts an original-default draft; Clone creates a real empty set.
Real sets retain their actual authors, dates and votes.

Starter palettes provide coordinated surface, text, brand and status colours in
light and dark modes. Displayed contrast ratios cover specified text/background
pairs only; they do not certify an entire theme's accessibility. Preview actual
content and interactions before publishing, especially when a theme uses its
own hard-coded colours or mode switcher.

The Style sets dropdown shows each starter palette's swatches inline and in
the selected value on browsers supporting customizable native selects. Keyboard
selection remains native. Other browsers keep the text dropdown and display the
selected palette's swatches below it.

Settings live in `XOOPS_VAR_PATH/configs/cssholmes/designer/`. Include this folder
in private site backups. A library is limited to 200 sets, 20 per author and
4 MiB total; exported documents have a smaller per-file validation bound.

## Research behind these choices

- [DTCG Format 2025.10](https://www.designtokens.org/tr/2025.10/format/): typed
  JSON values provide an interoperable vocabulary for colours.
- [WordPress global settings and styles](https://developer.wordpress.org/themes/core-concepts/global-settings-and-styles/):
  layered defaults and user styles keep changes separate from the base theme.
- [W3C contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum):
  test foreground/background pairs, rather than treating attractive swatches as
  evidence that text is readable.

Useful future extensions are screenshot previews, tag/search collections,
palette contrast repair, and a documented theme adapter manifest for additional
component targets. These should build on the versioned format and explicit
preview/apply workflow.
