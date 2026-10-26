# Contributing to cssHolmes

Submit focused pull requests to [mambax7/cssholmes](https://github.com/mambax7/cssholmes).
Use PSR-12, preserve public APIs and existing request/CSRF validation, and add a
regression test beside the affected behavior. Describe the problem, behavior
change, compatibility impact and commands actually run. Include screenshots for
visible changes. Use XOOPS Conventional Commits, for example
`fix(designer): preserve preview selection`.

## Shared tools

PHP 8.4 is the module minimum. Keep Composer dependencies in the site's shared
`xoops_lib/vendor`, never under this module. Existing shared PHPUnit and PHPStan
are sufficient; do not run `composer install` in the module. Node/Playwright
also come from an existing shared installation.

From `htdocs/modules/cssholmes`:

```sh
php tests/run.php
```

For an independent checkout (PowerShell example):

```powershell
$env:XOOPS_VENDOR_PATH = 'C:/path/to/htdocs/xoops_lib/vendor'
php tests/run.php
```

The runner stops on the first failed subprocess. It runs storage, sets, access,
preview-context and HTTP endpoint regressions in temporary storage, followed by
`phpunit.xml.dist` (Analyzer, Cli and Diagnostics). Its isolated namespace
bootstrap also supports a checkout whose folder is not named `cssholmes`.
It does not load a configured site's `mainfile.php`, session or database.

To include browser regressions and the HTTP roundtrip:

```powershell
$env:PLAYWRIGHT_MODULE = 'C:/path/to/shared/node_modules/playwright-core'
$env:CHROME_PATH = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
php tests/run.php --browser
```

All independent `tests/*.cjs` run automatically. `designer-roundtrip.cjs` runs
through the temporary HTTP server. `designer-specimens.cjs` additionally needs
installed theme fixtures and is a separate opt-in command:
`php tests/designer-http.php --browser --specimens`.
The XMF/XTF package contract check is separately opt-in:
`php tests/xtf-contract.php /path/to/htdocs/xoops_lib/vendor/xoops`.

## Static analysis

`phpstan.neon` uses standard PHPStan level 5 for framework-independent
Analyzer, Cli, Diagnostics and Designer production classes. It has no baseline
or error ignores. PHPDoc types remain uncertain because manifest/token values
are validated at runtime. Core-dependent entrypoints, preloads, Workbench and
legacy Common helpers are outside this initial analysis scope; the gate does
not claim whole-module static coverage. Increase scope/level with targeted
fixes, not suppression files.

With a shared tool installation that has no automatic PHPStan extensions:

```powershell
php "$env:XOOPS_VENDOR_PATH/bin/phpstan" analyse --configuration=phpstan.neon
```

If the shared installation enables unrelated strict/deprecation extensions,
run its existing self-contained PHAR from a temporary location. This avoids
changing shared Composer settings or copying dependencies into the module:

```powershell
$analysisPhar = Join-Path ([IO.Path]::GetTempPath()) 'xoops-phpstan.phar'
Copy-Item "$env:XOOPS_VENDOR_PATH/phpstan/phpstan/phpstan.phar" $analysisPhar
try {
    php $analysisPhar analyse --configuration=phpstan.neon
    if ($LASTEXITCODE -ne 0) { throw 'PHPStan failed' }
} finally {
    Remove-Item -LiteralPath $analysisPhar
}
```

Keep the temporary PHAR location stable across runs because PHPStan caches
paths to its bundled stubs. CI installs only the required tools in the runner's
shared temporary `xoops_lib` directory and invokes them directly.

## CI, releases and limitations

CI runs syntax checks, the isolated regression suite and scoped static analysis
on PHP 8.4 and 8.5, plus Chromium regressions on PHP 8.4. It does not install or
modify a real XOOPS database. Qodana uses PHP 8.4 and requires the repository's
`QODANA_TOKEN`; missing tokens produce an explicit skip notice. No scan fixes or
PR comments are pushed automatically.

Release tags run CI, then `git archive` creates a ZIP rooted at `cssholmes/`.
`.gitattributes` excludes development tooling and tests while preserving module
assets and runtime data. Existing release assets are not overwritten. Review
the ZIP before installing it on a site. Action updates are proposed weekly by
Dependabot; auto-merge is not enabled.

The historical full `tests/Unit` tree is not the standalone suite. It contains
incomplete Common tests, obsolete `getMockForTrait()` calls, a preload test with
a filename/class mismatch, and Helper tests requiring a real XOOPS runtime.
See [the recorded results](docs/compatibility.md); do not equate the focused
suite's pass with a clean full legacy suite.
