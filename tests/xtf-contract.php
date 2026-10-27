<?php

declare(strict_types=1);

use Xmf\Request;
use XoopsModules\Cssholmes\Designer\SetFormat;
use XoopsModules\Cssholmes\Designer\ThemeConfigStore;
use Xtf\Asset\DesignTokenRegistry;
use Xtf\Bridge\FrontEndAssetManifest;
use Xtf\Bridge\FrontEndThemeAdapter;
use Xtf\Customizer\PreviewRenderer;
use Xtf\Theme\ThemeManifest;

PHP_SAPI === 'cli' || exit('CLI only');
if (PHP_VERSION_ID < 80400) {
    fwrite(STDERR, "XMF 2 / XTF require PHP 8.4 or newer.\n");
    exit(1);
}
$packages = isset($argv[1]) ? realpath($argv[1]) : false;
if ($packages === false || !is_file($packages . '/xtf/src/Theme/ThemeManifest.php')
    || !is_file($packages . '/xmf/src/Request.php')) {
    fwrite(STDERR, "Usage: php -n tests/xtf-contract.php /path/to/htdocs/xoops_lib/vendor/xoops\n");
    exit(1);
}

// Load only these namespaces: the site's Composer files/bootstrap must not start a database or session.
spl_autoload_register(static function (string $class) use ($packages): void {
    foreach (['Xtf\\' => $packages . '/xtf/src/', 'Xmf\\' => $packages . '/xmf/src/',
        'XoopsModules\\Cssholmes\\' => dirname(__DIR__) . '/class/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
            return;
        }
    }
});
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = str_replace('\\', '/', sys_get_temp_dir()) . '/cssholmes-xtf-' . bin2hex(random_bytes(8));
$themePath = $root . '/themes/contract-theme';
mkdir($themePath, 0700, true);
define('XOOPS_ROOT_PATH', $root);
try {
    $manifestData = ['name' => 'Display name differs from identity', 'version' => '1.0.0',
        'tokens' => ['color.primary' => '#123456', 'radius.sm' => '4px'],
        'assets' => ['css' => ['assets/theme.css'], 'js' => ['assets/theme.js']]];
    file_put_contents($themePath . '/theme.json', json_encode($manifestData, JSON_THROW_ON_ERROR));
    file_put_contents($themePath . '/theme.tpl', '<main>Contract fixture</main>');
    $manifestBefore = hash_file('sha256', $themePath . '/theme.json');

    foreach (['getVar', 'getString', 'getInt', 'getArray', 'hasVar'] as $method) {
        check(method_exists(Request::class, $method), 'Installed XMF Request API missing: ' . $method);
    }
    $manifest = ThemeManifest::load($themePath);
    check($manifest->id() === 'contract-theme', 'Theme identity must use the directory, not display name');
    check($manifest->name() === $manifestData['name'], 'Theme display name changed');
    $tokens = DesignTokenRegistry::fromManifest($manifest);
    check($tokens->get('color.primary') === '#123456', 'XTF manifest token defaults missing');
    check(str_contains($tokens->toCssString(), '--xtf-color-primary: #123456;'), 'XTF dot token mapping changed');
    $tokens->override('color.primary', '#abcdef');
    check($tokens->get('color.primary') === '#abcdef' && $tokens->get('radius.sm') === '4px', 'Token override lost defaults');
    $preview = (new PreviewRenderer())->render($tokens, ['dark_mode' => true, 'layout' => 'wide']);
    check(str_contains($preview, '--xtf-color-primary: #abcdef;'), 'Preview ignored token override');
    check(str_contains($preview, '/* [data-theme="dark"] */'), 'XTF dark-mode marker changed');
    check(str_contains($preview, 'body.layout-wide'), 'XTF layout preview changed');
    check(str_contains((new PreviewRenderer())->toStyleTag($tokens), 'id="xtf-customizer-preview"'), 'Preview style target changed');

    $assets = new FrontEndAssetManifest($manifest->css(), $manifest->js(), $manifest->version());
    check($assets->css() === ['assets/theme.css'] && $assets->js() === ['assets/theme.js']
        && $assets->version() === '1.0.0', 'XTF asset manifest contract changed');
    $wrap = new ReflectionMethod(FrontEndThemeAdapter::class, 'wrap');
    check((string) $wrap->getParameters()[0]->getType() === 'xos_opal_Theme', 'Frontend bridge boundary changed');

    $store = new ThemeConfigStore($root . '/themes', $root . '/private/config');
    $format = new SetFormat($store);
    $base = ['selector' => 'body', 'label' => 'Body', 'mode' => 'dark', 'breakpoint' => 'all',
        'state' => 'normal', 'declarations' => ['color' => '#123456']];
    $rules = [$base,
        array_replace($base, ['selector' => 'input', 'label' => 'Input', 'declarations' => ['color' => '#ff0000']]),
        array_replace($base, ['selector' => 'textarea', 'label' => 'Textarea', 'declarations' => ['color' => '#0000ff']]),
        array_replace($base, ['selector' => '.contract-only', 'label' => 'Theme detail'])];
    $document = $format->fromRules($manifest->id(), 'XTF migration fixture', $rules);
    $roundtrip = json_decode(json_encode($document, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    check($format->normalize($roundtrip) === $document, 'Semantic set JSON roundtrip changed');
    $sameTheme = $format->resolve($roundtrip, $manifest->id());
    check($sameTheme['rules'] === $store->normalizeRules($rules) && $sameTheme['warnings'] === [],
        'Same-theme restore must preserve distinct input/textarea rules and custom selectors exactly');
    $crossTheme = $format->resolve($roundtrip, 'another-theme');
    check(!in_array('.contract-only', array_column($crossTheme['rules'], 'selector'), true)
        && count($crossTheme['warnings']) === 1, 'Cross-theme custom-selector boundary changed');
    check(in_array('body', array_column($crossTheme['rules'], 'selector'), true), 'Portable semantic body style lost');
    check(str_contains($store->compile($sameTheme['rules']), '[data-theme="dark"]'),
        'cssHolmes CSS must recognize the XTF data-theme convention');
    check($store->load($manifest->id())['rules'] === [], 'Read-only baseline must remain empty');
    check(!is_dir($root . '/private'), 'Contract checks must not create persistent style storage');
    check(hash_file('sha256', $themePath . '/theme.json') === $manifestBefore, 'Preview mutated the theme manifest');
    check(!isset($GLOBALS['xoopsDB']) && !class_exists('XoopsDatabase', false)
        && session_status() === PHP_SESSION_NONE, 'Contract check unexpectedly bootstrapped XOOPS');
    echo "XMF 2 / XTF contract checks passed: manifest identity, token CSS, preview mode, assets, and semantic set restoration.\n";
    echo "Package-level compatibility only; this does not certify an installed XOOPS 4 module or bridge rendering.\n";
} finally {
    foreach (['theme.json', 'theme.tpl'] as $file) {
        if (is_file($themePath . '/' . $file)) {
            unlink($themePath . '/' . $file);
        }
    }
    rmdir($themePath);
    rmdir($root . '/themes');
    rmdir($root);
}
