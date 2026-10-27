<?php

declare(strict_types=1);

use XoopsModules\Cssholmes\Designer\ConflictException;
use XoopsModules\Cssholmes\Designer\ThemeConfigStore;

define('XOOPS_ROOT_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'XoopsModules\\Cssholmes\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/class/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $callback, string $exception = InvalidArgumentException::class): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        check($error instanceof $exception, 'Unexpected exception: ' . $error::class . ': ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $exception);
}

$root = sys_get_temp_dir() . '/cssholmes-store-' . bin2hex(random_bytes(8));
mkdir($root . '/themes/alpha', 0700, true);
mkdir($root . '/themes/beta', 0700, true);
file_put_contents($root . '/themes/alpha/theme.tpl', 'test');
file_put_contents($root . '/themes/beta/theme.html', 'test');
$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) as $name) {
        if ($name !== '.' && $name !== '..') {
            $cleanup($path . '/' . $name);
        }
    }
    rmdir($path);
};

try {
    check(class_exists(ThemeConfigStore::class), 'ThemeConfigStore implementation is missing');
    $store = new ThemeConfigStore($root . '/themes', $root . '/private/configs');
    $baseline = $store->load('alpha');
    check($baseline['rules'] === [] && $baseline['css'] === '' && !$baseline['canRestore'], 'Empty baseline');
    check(!file_exists($root . '/private'), 'Read-only load must not create storage directories');
    rejects(fn() => (new ThemeConfigStore($root . '/themes', $root . '/themes/alpha/config'))->save('alpha', [], $baseline['revision']), RuntimeException::class);
    check(!file_exists($root . '/themes/alpha/config'), 'Storage can never write inside theme folders');
    rejects(fn() => new ThemeConfigStore($root . '/themes', $root . '//themes/alpha/config'), RuntimeException::class);
    check($baseline['revision'] === $store->load('alpha')['revision'], 'Stable baseline revision');
    $rule = ['selector' => '#mainmenu > a.menu-link', 'label' => 'Main menu links', 'mode' => 'all',
        'breakpoint' => 'all', 'state' => 'normal', 'declarations' => ['color' => '#123abc', 'font-size' => '18px']];
    $selectorLabel = array_replace($rule, ['label' => $rule['selector']]);
    check(str_contains($store->compile([$selectorLabel]), '#mainmenu > a.menu-link'), 'Selector-derived labels must allow child combinators');
    $saved = $store->save('alpha', [$rule], $baseline['revision']);
    check(str_contains($saved['css'], '#mainmenu > a.menu-link') && str_contains($saved['css'], 'color: #123abc !important;'), 'Compiled CSS');
    check($saved['canRestore'] && $saved['rules'][0]['id'] !== '', 'Saved metadata');
    check($saved === (new ThemeConfigStore($root . '/themes', $root . '/private/configs'))->load('alpha'), 'Reload persisted state');
    check($store->load('beta')['rules'] === [], 'Theme isolation');
    rejects(fn() => $store->save('alpha', [$rule], $baseline['revision']), ConflictException::class);
    $override = array_replace($rule, ['mode' => 'dark', 'breakpoint' => 'mobile', 'state' => 'focus', 'declarations' => ['background-color' => 'rgba(10, 20, 30, 0.5)']]);
    $second = $store->save('alpha', [$override, $rule], $saved['revision']);
    check(strpos($second['css'], 'color: #123abc') < strpos($second['css'], '@media'), 'Base rules precede overrides');
    check(str_contains($second['css'], 'max-width: 575px') && str_contains($second['css'], ':focus-visible'), 'Breakpoint and state');
    check(str_contains($second['css'], '[data-bs-theme="dark"]') && str_contains($second['css'], 'prefers-color-scheme: dark'), 'Explicit and automatic dark mode');
    check(str_contains($second['css'], '[data-theme="dark"]'), 'XTF explicit mode must work without Bootstrap');
    check(str_contains($second['css'], ':not([data-bs-theme="light"]):not([data-bs-theme="dark"]):not([data-theme="light"]):not([data-theme="dark"])'), 'System fallback must not override either explicit mode');
    $restored = $store->restore('alpha', $second['revision']);
    check($restored['rules'] === $saved['rules'] && !$restored['canRestore'], 'One-step restore only restores previous rules');
    rejects(fn() => $store->restore('alpha', $second['revision']), ConflictException::class);
    rejects(fn() => $store->restore('alpha', $restored['revision']));
    check($store->compile([array_replace($rule, ['declarations' => []])]) === '', 'Empty declarations omitted');
    foreach (['../alpha', 'alpha/../beta', 'ALPHA', '.', 'alpha\\x', 'https://alpha'] as $theme) {
        rejects(fn() => $store->load($theme));
    }
    foreach (['a:hover', 'a,b', '[href]', 'body { color:red }', '.xs-block', '#xstyles', '.cssholmes-designer', '#comment_123', 'a b c d e', 'a + b', '*'] as $selector) {
        rejects(fn() => $store->compile([array_replace($rule, ['selector' => $selector])]));
    }
    foreach (['red;display:none', 'url(https://evil)', 'var(--evil)', '#fff!important', 'expression(x)', '#fff}', 'rgb(256,0,0)', 'rgba(0,0,0,2)', '\\72 ed'] as $value) {
        rejects(fn() => $store->compile([array_replace($rule, ['declarations' => ['color' => $value]])]));
    }
    foreach ([['unknown' => 'value'], ['color' => 4], ['width' => '-10px'], ['font-family' => 'A<script>'], ['display' => 'bogus']] as $declarations) {
        rejects(fn() => $store->compile([array_replace($rule, ['declarations' => $declarations])]));
    }
    rejects(fn() => $store->compile([array_replace($rule, ['label' => '<img src=x>'])]));
    rejects(fn() => $store->compile([array_replace($rule, ['unexpected' => true])]));
    rejects(fn() => $store->compile(array_fill(0, 257, $rule)));
    rejects(fn() => $store->compile([$rule, $rule]));
    rejects(fn() => $store->compile([array_replace($rule, ['selector' => str_repeat('a', 241)])]));
    rejects(fn() => $store->compile([array_replace($rule, ['label' => str_repeat('a', 161)])]));
    rejects(fn() => $store->compile([array_replace($rule, ['declarations' => ['font-family' => str_repeat('a', 129)]])]));
    rejects(fn() => $store->compile([array_replace($rule, ['mode' => 'system'])]));
    rejects(fn() => $store->compile([array_replace($rule, ['breakpoint' => 'wide'])]));
    rejects(fn() => $store->compile([array_replace($rule, ['state' => 'active'])]));
    $colors = ['#fff', '#ffff', '#aabbccdd', 'transparent', 'rgb(0, 255, 12)', 'rgba(0, 1, 255, .4)'];
    foreach ($colors as $color) {
        check(str_contains($store->compile([array_replace($rule, ['declarations' => ['color' => $color]])]), $color), 'Supported color: ' . $color);
    }
    check(str_contains($store->compile([array_replace($rule, ['selector' => 'html', 'mode' => 'light'])]), '[data-theme="light"]):is(html)'), 'Mode CSS includes root elements for both theme conventions');
    $beta = $store->save('beta', [array_replace($rule, ['declarations' => ['color' => '#fff']])], $store->load('beta')['revision']);
    check($store->load('alpha')['rules'] === $restored['rules'] && $beta['rules'] !== $restored['rules'], 'Saving another theme leaves existing styles alone');
    file_put_contents($root . '/blocked', 'file');
    rejects(fn() => (new ThemeConfigStore($root . '/themes', $root . '/blocked/configs'))->load('alpha'), RuntimeException::class);
    // Windows may deny symbolic links without Developer Mode or administrator rights.
    $linkAvailable = false;
    set_error_handler(static fn(): bool => true);
    try {
        $linkAvailable = symlink($root . '/themes/alpha', $root . '/themes/linked');
    } finally {
        restore_error_handler();
    }
    if ($linkAvailable) {
        rejects(fn() => $store->load('linked'));
        symlink($root . '/private/configs', $root . '/linked-storage');
        rejects(fn() => (new ThemeConfigStore($root . '/themes', $root . '/linked-storage'))->load('alpha'), RuntimeException::class);
    } else {
        echo "Symlink checks skipped: this account cannot create symlinks.\n";
    }
    $jsonFiles = glob($root . '/private/configs/*.json');
    check(count($jsonFiles) === 2, 'One document per theme');
    $alphaFile = $root . '/private/configs/' . hash('sha256', 'alpha') . '.json';
    file_put_contents($alphaFile, '{broken');
    rejects(fn() => $store->load('alpha'), RuntimeException::class);
    file_put_contents($alphaFile, json_encode(['version' => 1, 'rules' => [array_replace($rule, ['selector' => 'a:hover'])], 'previous' => null], JSON_THROW_ON_ERROR));
    rejects(fn() => $store->load('alpha'), RuntimeException::class);
    check(isset(ThemeConfigStore::properties()['color']['type']), 'Property registry');
    echo "Designer store checks passed.\n";
} finally {
    $cleanup($root);
}
