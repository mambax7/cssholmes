<?php

declare(strict_types=1);

use XoopsModules\Cssholmes\Designer\ConflictException;
use XoopsModules\Cssholmes\Designer\SetFormat;
use XoopsModules\Cssholmes\Designer\SetLibrary;
use XoopsModules\Cssholmes\Designer\ThemeConfigStore;

spl_autoload_register(static function (string $class): void {
    $prefix = 'XoopsModules\\Cssholmes\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/class/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejects(callable $callback, string $type = InvalidArgumentException::class): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        check($error instanceof $type, 'Unexpected rejection: ' . $error::class . ': ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $type);
}
$root = sys_get_temp_dir() . '/cssholmes-sets-' . bin2hex(random_bytes(8));
mkdir($root . '/themes/alpha', 0700, true);
mkdir($root . '/themes/beta', 0700, true);
file_put_contents($root . '/themes/alpha/theme.tpl', 'test');
file_put_contents($root . '/themes/beta/theme.tpl', 'test');
define('XOOPS_ROOT_PATH', $root);
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
    $store = new ThemeConfigStore($root . '/themes', $root . '/private/config');
    $format = new SetFormat($store);
    $library = new SetLibrary($root . '/private/config');
    rejects(fn() => new SetLibrary($root . '/themes/alpha/config'), RuntimeException::class);
    check($library->all() === [] && !is_dir($root . '/private'), 'Reads never create files');
    $rule = ['selector' => 'body', 'label' => 'Body', 'mode' => 'all', 'breakpoint' => 'all', 'state' => 'normal', 'declarations' => ['color' => '#123456']];
    $custom = array_replace($rule, ['selector' => '.alpha-only']);
    $doc = $format->fromRules('alpha', 'Ocean', [$rule, $custom]);
    check(count($doc['styles']) === 1 && count($doc['themeRules']) === 1, 'Semantic and custom selectors split');
    $cross = $format->resolve($doc, 'beta');
    check(count($cross['rules']) === 1 && count($cross['warnings']) === 1, 'Cross-theme skips custom selectors with warning');
    check(count($format->resolve($doc, 'alpha')['rules']) === 2, 'Same theme retains custom rules');
    $differentAliases = [$rule, array_replace($rule, ['selector' => 'input', 'declarations' => ['color' => '#ff0000']]),
        array_replace($rule, ['selector' => 'textarea', 'declarations' => ['color' => '#0000ff']])];
    $aliasDocument = $format->fromRules('alpha', 'Different inputs', $differentAliases);
    check($format->resolve($aliasDocument, 'alpha')['rules'] === $store->normalizeRules($differentAliases), 'Source snapshot preserves distinct input and textarea, never adds select');
    check($format->resolve($format->fromRules('alpha', 'Original', []), 'beta')['rules'] === [], 'Defaults stay inherited');
    foreach (['.comTitle', '.xoops-comment-template .xoops-comment-text > h4'] as $selector) {
        $mapped = $format->fromRules('alpha', 'Comments', [array_replace($rule, ['selector' => $selector])]);
        check($mapped['styles'][0]['target'] === 'comment.title', 'Comment semantic mapping');
        check(count($format->resolve($mapped, 'beta')['rules']) === 2, 'Both comment markup families resolved');
        check(count($format->resolve($mapped, 'alpha')['rules']) === 1 && $format->resolve($mapped, 'alpha')['rules'][0]['selector'] === $selector, 'Source comment selector stays exact');
    }
    $both = $format->fromRules('alpha', 'Both', [array_replace($rule, ['selector' => '.comTitle']), array_replace($rule, ['selector' => '.xoops-comment-template .xoops-comment-text > h4'])]);
    check(count($format->resolve($both, 'beta')['rules']) === 2, 'Duplicate semantic alternatives merge');
    foreach ([array_replace($doc, ['version' => 2]), array_replace($doc, ['extra' => true]), array_replace($doc, ['name' => '<b>Bad</b>'])] as $bad) {
        rejects(fn() => $format->normalize($bad));
    }
    $bad = $doc;
    $bad['styles'][0]['declarations']['color'] = 'url(https://evil.test)';
    rejects(fn() => $format->normalize($bad));
    $bad = $doc;
    $bad['styles'][0]['target'] = 'body { display:none }';
    rejects(fn() => $format->normalize($bad));
    $bad = $format->builtins()[0]['payload'];
    $bad['tokens']['light']['brand']['$value']['alpha'] = 0.5;
    rejects(fn() => $format->normalize($bad));
    $bad['tokens']['light']['brand']['$value']['alpha'] = 1;
    $bad['tokens']['light']['brand']['$value']['components'][0] = 1.01;
    rejects(fn() => $format->normalize($bad));
    $bad = $doc;
    $bad['themeRules'] = array_fill(0, 257, $custom);
    rejects(fn() => $format->normalize($bad));
    $bad['themeRules'] = ['oversize' => str_repeat('x', 65537)];
    rejects(fn() => $format->normalize($bad));
    $owner = ['id' => 7, 'name' => 'Alice'];
    $other = ['id' => 8, 'name' => 'Bob'];
    $set = $library->create('alpha', 'Ocean', $doc, $owner);
    $imported = $library->create('beta', 'Imported', $doc, $other);
    check($imported['payload']['sourceTheme'] === 'alpha' && count($format->resolve($imported['payload'], 'beta')['rules']) === 1, 'Imported source provenance survives library storage');
    $library->delete($imported['id'], $other, false, $imported['revision']);
    check(preg_match('/^[a-f0-9]{32}$/D', $set['id']) === 1 && $set['author'] === $owner, 'Server identity and opaque ID');
    check($library->get($set['id']) === $set && count($library->all()) === 1, 'CRUD persistence');
    rejects(fn() => $library->update($set['id'], 'Stolen', $doc, $other, false, $set['revision']));
    rejects(fn() => $library->delete($set['id'], $other, false, $set['revision']));
    rejects(fn() => $library->get('../sets-library'));
    rejects(fn() => $library->create('alpha', '<b>Bad</b>', $doc, $owner));
    rejects(fn() => $library->create('../alpha', 'Bad theme', $doc, $owner));
    rejects(fn() => $library->create('alpha', str_repeat('é', 81), $doc, $owner));
    $unicode = $library->create('alpha', str_repeat('é', 80), $doc, $other);
    $library->delete($unicode['id'], $other, false, $unicode['revision']);
    rejects(fn() => $library->create('alpha', 'Anonymous', $doc, ['id' => 0, 'name' => 'Guest']));
    $liked = $library->like($set['id'], 8, true);
    check($liked['likedBy'] === [8] && $liked['revision'] === $set['revision'], 'Likes do not conflict with revisions');
    check($library->like($set['id'], 8, true)['likedBy'] === [8], 'Likes are idempotent');
    check($library->like($set['id'], 8, false)['likedBy'] === [], 'Unlike');
    rejects(fn() => $library->like($set['id'], 0, true));
    $updated = $library->update($set['id'], 'New name', $doc, $owner, false, $set['revision']);
    check($updated['revision'] !== $set['revision'] && $updated['author'] === $owner, 'Edits update revision, retain owner');
    rejects(fn() => $library->update($set['id'], 'Stale', $doc, $owner, false, $set['revision']), ConflictException::class);
    $adminEdit = $library->update($set['id'], 'Admin edit', $doc, $other, true, $updated['revision']);
    check($adminEdit['author'] === $owner, 'Admin edit does not claim authorship');
    $library->delete($set['id'], $other, true, $adminEdit['revision']);
    check($library->all() === [], 'Admin delete');
    for ($i = 0; $i < 20; ++$i) {
        $library->create('alpha', 'Set ' . $i, $doc, $owner);
    }
    rejects(fn() => $library->create('alpha', 'Over quota', $doc, $owner));
    for ($uid = 20; $uid < 29; ++$uid) {
        for ($i = 0; $i < 20; ++$i) {
            $library->create('alpha', 'Set ' . $i, $doc, ['id' => $uid, 'name' => 'Author']);
        }
    }
    check(count($library->all()) === 200, 'Total library quota');
    rejects(fn() => $library->create('alpha', 'Full library', $doc, $other));
    check(count($format->builtins()) >= 6, 'Six coordinated palettes');
    foreach ($format->builtins() as $palette) {
        check(count($palette['colors']) >= 4 && count($format->resolve($palette['payload'], 'alpha')['rules']) > 0, 'Palette produces concrete rules');
        foreach ($palette['contrast'] as $contrast) {
            check($contrast['ratio'] >= 4.5 && $contrast['pass'], 'Palette contrast: ' . $palette['name'] . ' ' . $contrast['name']);
        }
        foreach ($palette['payload']['tokens'] as $mode => $roles) {
            $luminance = static function (array $token): float {
                $sum = 0.0;
                foreach ($token['$value']['components'] as $index => $component) {
                    $sum += [0.2126, 0.7152, 0.0722][$index] * ($component <= 0.04045 ? $component / 12.92 : pow(($component + 0.055) / 1.055, 2.4));
                }
                return $sum;
            };
            foreach ([['text', 'surface'], ['onBrand', 'brand']] as [$fg, $bg]) {
                $a = $luminance($roles[$fg]);
                $b = $luminance($roles[$bg]);
                check((max($a, $b) + 0.05) / (min($a, $b) + 0.05) >= 4.5, 'Independent palette pair contrast: ' . $mode);
            }
        }
    }
    $schema = json_decode(file_get_contents(dirname(__DIR__) . '/docs/theme-set.schema.json'), true, 32, JSON_THROW_ON_ERROR);
    check($schema['properties']['version']['const'] === 1, 'Schema is parseable and versioned');
    $file = $root . '/private/config/sets-library.json';
    $validLibrary = file_get_contents($file);
    file_put_contents($file, str_repeat(' ', 4194305));
    rejects(fn() => $library->all(), RuntimeException::class);
    file_put_contents($file, '{broken');
    rejects(fn() => $library->create('alpha', 'No overwrite', $doc, $other), RuntimeException::class);
    check(file_get_contents($file) === '{broken', 'Corrupt storage is never overwritten');
    set_error_handler(static fn(): bool => true);
    try {
        $linked = symlink($root . '/private', $root . '/linked');
    } finally {
        restore_error_handler();
    }
    if ($linked) {
        file_put_contents($file, $validLibrary);
        check(count((new SetLibrary($root . '/linked/config'))->all()) === count($library->all()), 'Trusted linked parent is supported');
        unlink($file);
        file_put_contents($root . '/outside.json', 'outside sentinel');
        check(symlink($root . '/outside.json', $file), 'File symlink fixture');
        rejects(fn() => $library->create('alpha', 'Unsafe path', $doc, $other), RuntimeException::class);
        check(file_get_contents($root . '/outside.json') === 'outside sentinel', 'Symlink target remains untouched');
    } else {
        echo "Set symlink checks skipped: this account cannot create symlinks.\n";
    }
    echo "Designer set tests passed.\n";
} finally {
    $cleanup($root);
}
