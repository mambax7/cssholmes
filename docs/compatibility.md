# Compatibility and verification

The module manifest requires PHP **8.4+**. The older `min_xoops = 2.5.12`
metadata is retained for compatibility with the installer; it is not evidence
that every legacy core/PHP combination has been tested.

| Environment | Evidence and boundary |
| --- | --- |
| PHP 8.4 | Isolated storage, sets, permissions, preview and HTTP regressions; Analyzer/Cli/Diagnostics PHPUnit suite. |
| PHP 8.5 | The same isolated PHP checks passed locally on PHP 8.5.12; also a CI matrix target. |
| XOOPS 2.7.4-RC2 | Clean ZIP installation and 3.0.0-to-3.3.0 upgrade tested in a disposable installed site on PHP 8.4.27; normal login, designer save/reload, visitor CSS, permissions, CSRF and restore passed with the default theme. |
| XOOPS 2.8 Alpha | Current local integration target. Synthetic test-core endpoint results are distinct from an installed-site smoke test. |
| XOOPS 4 / XMF 2 / XTF | Opt-in package contract checks only; native routes, authentication and module installation remain unverified. |

## Local tooling verification, 2026-10-26

`XOOPS_VENDOR_PATH=<shared xoops_lib/vendor> php tests/run.php` passed on
PHP 8.4.27 with shared PHPUnit 13.4.3: storage and set checks, 7 access checks,
preview-context checks, 48 HTTP endpoint checks, and **35 PHPUnit tests / 115
assertions**. The tests use temporary files and synthetic HTTP identities;
production settings, credentials and sessions are not accessed. Symlink tests
were skipped because the Windows account could not create symlinks.

The same runner passed on PHP 8.5.12 (35 PHPUnit tests / 115 assertions and 48
HTTP checks). The first run reported the HTTP test harness's deprecated
`$http_response_header`; the production endpoints did not report that warning.

Standard PHPStan 2.3.2 passed the level-5 scope in `phpstan.neon` without error
suppressions. Shared optional strict rules are not part of this initial gate.
CI uses PHPUnit 11.5 and PHPStan 2.x installed outside the module checkout.
Local success with PHPUnit 13 does not substitute for an actual CI run on 11.5.

## Installed-site release smoke test, 2026-10-26

A clean `git archive` ZIP was installed using the real XOOPS 2.7.4-RC2 module
installer in a separate loopback-only site and database. No production tables,
users or settings were copied. Normal administrator login and the xStyles
designer UI saved a heading override, which survived reload and reached an
anonymous visitor. Anonymous endpoint access and an invalid CSRF token were
rejected. A second save and restoration of the previous save passed.

Fresh-install editor/diagnostic permissions were restricted to administrators.
The old 3.0.0 package was then installed and upgraded to 3.3.0: its custom
query-key preference and existing Registered Users grant were preserved;
administrator grants were complete. Saved designer settings were byte-identical
before and after the upgrade. These checks used PHP 8.4.27, Chrome and the
default frontend theme; they do not establish every theme/core combination.

An explicit broader check,
`php <shared-vendor>/bin/phpunit -c phpunit.xml.dist tests/Unit`, produced
58 tests, 115 assertions, 14 errors, 1 runner warning and 9 incomplete tests on
PHPUnit 13.4.3. Eleven errors come from removed `getMockForTrait()` APIs; three
Helper errors need `xoops_getHandler()` from an installed core. The warning is
the `coreTest.php` / `CssholmesCorePreloadTest` filename/class mismatch. These
legacy tests remain outside the focused standalone configuration, not silently
skipped by it.

Browser and installed-core results should name the browser, core, theme and
operation tested. No hosted CI, Qodana or release execution is implied by the
presence of workflow files. A missing Qodana token explicitly skips analysis.
All three workflow files passed actionlint 1.7.12, and `qodana.yaml` validated
against the [SchemaStore Qodana schema](https://www.schemastore.org/qodana-1.0.json).
The pinned Playwright 1.60.0 release was verified against the npm registry.
