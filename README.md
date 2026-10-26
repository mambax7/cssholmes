# cssHolmes for XOOPS

cssHolmes combines HTML/CSS diagnostics, a visual theme designer, and a library
of portable theme configuration sets. Preview changes, inspect tokens and
contrast, and publish validated overrides without rewriting theme source files.

Requires **PHP 8.4+** and an installed XOOPS site. XOOPS 2.7 and 2.8 are the
current integration targets; see the [compatibility and verification record](docs/compatibility.md)
for what has actually been tested. XMF/XTF contract checks do not establish a
native XOOPS 4 port.

- [Visual designer](docs/visual-designer.md): selection, preview and saving.
- [Theme sets](docs/theme-sets.md): library, palettes, import/export and reset.
- [Tutorial](docs/TUTORIAL-cssholmes-XTF-XMF-v2.md).
- [XMF/XTF migration boundaries](docs/xoops4-migration.md).
- [Development and testing](CONTRIBUTING.md).

Install the module as `htdocs/modules/cssholmes` and use the XOOPS module
administration screen to install or update it. Keep the site's shared dependencies
in `xoops_lib`; this module has no separate Composer installation or vendor tree.
Back up the site's files and database before an installed-module upgrade.

From an installed module directory, `php tests/run.php` runs the isolated PHP
regressions plus the analyzer, CLI and diagnostic PHPUnit tests using shared
`xoops_lib/vendor/bin/phpunit`. For a separate checkout, set `XOOPS_VENDOR_PATH`
to that shared vendor directory. `php tests/run.php --browser` also runs the
browser regressions with an existing Playwright/Chromium installation.

Source and issues: [mambax7/cssholmes](https://github.com/mambax7/cssholmes).
The repository's CI checks PHP 8.4/8.5; release tags run CI before creating a
clean module ZIP. Qodana is optional and explicitly skips its scan when
`QODANA_TOKEN` is unavailable. A skipped scan is not a quality result.

Licensed under the [GNU GPL, version 2 or later](docs/license.txt).
