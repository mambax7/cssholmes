# cssHolmes 3.3.0 Alpha 1

An alpha prerelease for testing theme inspection, visual editing and reusable style sets on XOOPS. Requires **PHP 8.4 or newer**. Try it on a development or staging site first.

## Highlights

- Inspect styles, resolved tokens and estimated text contrast; edit colors, typography, spacing and supported layout properties with live preview.
- Use undo/redo, scoped rules and explicit **Save to theme** publication. Overrides are stored separately from theme source files.
- Keep alternatives in a named-set library, with validated import/export and protection against concurrent updates.
- With the corresponding xStyles integration update, inspect or edit individual examples, compare **Published vs draft**, and preview draft CSS at 360, 768 and 1280 pixels.
- Run theme and template diagnostics, including the standalone CLI scanner.

## Install or upgrade

1. Back up the site, database and existing private cssHolmes settings.
2. Extract the release ZIP so its `cssholmes` directory is under `htdocs/modules/`.
3. Install or update cssHolmes through XOOPS module administration. Existing permissions are preserved on update; review legacy Registered Users overlay/diagnostics grants.
   Workbench history migrates from `data/workbench/*.json` to `XOOPS_VAR_PATH/configs/cssholmes/workbench/`. Keep these private files in backups. If both locations contain different histories, the update stops for manual reconciliation rather than overwriting either copy. Legacy text patches without a saved revision must be restored manually.
4. For the new gallery controls, also install or update [xStyles 1.5.0 Beta 3](https://github.com/XoopsModules27x/xstyles) or a later compatible version. cssHolmes works independently without xStyles.
5. Read `docs/TUTORIAL-xStyles.md` in the package for the illustrated workflow.

No module-local Composer or vendor installation is needed. Saving a named set does not publish changes; **Save to theme** does and may affect visitors.

## Compatibility and limitations

PHP 8.4/8.5 are the QA targets. Clean installation and upgrade from 3.0.0 passed on a disposable XOOPS 2.7.4-RC2 site with PHP 8.4.27, including authenticated save/reload, anonymous published styles, permission checks and restoration. See `docs/compatibility.md` for precise test evidence. The retained XOOPS 2.5.12 installer minimum is not a claim that all older core/PHP combinations were tested.

This alpha does not publish admin-theme changes or provide a native XOOPS 4 port. Contrast readings are conservative estimates, not an accessibility certificate. Legacy Common PHPUnit tests still need modernization; they are outside the documented standalone suite. Qodana is optional and requires a repository token.

Source, issues and future releases: [XoopsModules27x/cssholmes](https://github.com/XoopsModules27x/cssholmes).
