# XOOPS 4.0 / XMF 2.0 / XTF migration boundary

Reviewed against the actual packages in `400test0/htdocs/xoops_lib/vendor/xoops`
on 2026-10-25. This is migration preparation and contract verification, not a
claim that these modules have been installed as native XOOPS 4 applications.

## Preserve these boundaries

| Responsibility | Current owner | Native migration seam |
|---|---|---|
| Request validation and preview state | Pure `Gallery` methods | Reuse from an XMF route/controller |
| Core-generated samples | `Fixtures` plus `LegacyFixtureRenderer` | Keep as a legacy sample provider; add a native provider only when native form APIs exist |
| Template rendering / asset registration | `index.php` and preload hooks | Replace bootstrap/hook adapters, retain fixture data and browser assets |
| Responsive preview | Independent `responsive.js` | Same-origin URL and translated DOM attributes supplied by the new controller |
| Token/contrast diagnostics | cssHolmes `designer-diagnostics.js` | Browser computed styles, no PHP or framework dependency |
| Settings validation / compilation | cssHolmes `ThemeConfigStore` and `SetFormat` | Inject storage roots and retain the versioned data format |
| Access/CSRF/request identity | cssHolmes endpoint and `include/designer.php` adapters | Bind native authentication/request services; keep capabilities and revision checks |

`Xtf\Theme\ThemeManifest::id()` and `Xtf\Bridge\FrontEndThemeAdapter::id()`
use the directory name, matching the existing stored theme identity. Do not
rename saved sets to human-readable theme names.

`Xtf\Asset\DesignTokenRegistry` maps dot keys such as `color.primary` to
`--xtf-color-primary`. The inspector discovers these resolved variables without
requiring a PHP adapter. `Xtf\Customizer\PreviewRenderer` emits a `data-theme`
mode marker but leaves the actual switch to JavaScript. Gallery preview and
cssHolmes compiled mode CSS now understand that convention. Explicit Bootstrap
light/dark wins if both markers exist; otherwise explicit XTF mode wins; system
preference is used only when neither contains an explicit light/dark value.

`Xtf\Bridge\FrontEndThemeAdapter::wrap()` currently wraps `xos_opal_Theme`.
`FrontEndAssetManifest` describes assets; it does not register them. Do not call
imagined registration methods or migrate preload hooks simply because the
namespace exists. XMF/XTF packages inspected here require PHP 8.4; the 2.x
module keeps its PHP 8.4 floor and does not unconditionally load those packages.

## Settings ownership

cssHolmes named sets remain portable authored overrides with `defaults` set to
`inherit-target-theme`. XTF's `TokenEditor` owns separate database token overrides.
Do not mirror writes into both stores: that would create competing defaults,
reset semantics, and revisions. A future explicit token-import adapter must map
semantic roles to declared native token keys, validate values, preview the result,
and require the existing explicit publication action. Arbitrary selector rules
are not XTF token overrides and must remain separate.

## Native port work still required

1. Bind native routing, authentication/CSRF, theme catalog, translation, and asset
   registration at the existing entrypoint/preload boundaries.
2. Add a native theme-catalog adapter before supporting manifest-only themes:
   current cssHolmes discovery requires `theme.tpl` or `theme.html`.
3. Obtain native slot names through XTF layout contracts instead of assuming the
   legacy twelve-zone array. Keep current block fixtures as a legacy test mode.
4. Keep generated CSS last in the intended asset order and preserve conditional
   cache validation. Keep the storage format and existing user sets unchanged.
5. Run authenticated native save/reload/reset/import/export tests on a dedicated
   XOOPS 4 installation before declaring the native port supported.

## Evidence

The commands below require the complete source checkout, including the final QA
change. They are not available in documentation-only branches or release ZIPs;
see [Development checks](../README.md#development-checks).

The separate xStyles module's `tests/fixtures.php` checks real core rendering
without site sessions or a database; cssHolmes does not ship that script.
cssHolmes `php tests/xtf-contract.php <core>/htdocs/xoops_lib/vendor/xoops` loads
actual XMF/XTF classes without full bootstrap and checks identity, token CSS,
asset contracts, preview CSS, portable semantic roundtrip, and exact same-theme
rules. These checks cannot certify native route registration or authentication.
