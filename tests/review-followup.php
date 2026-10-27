<?php
declare(strict_types=1);

PHP_SAPI === 'cli' || exit('CLI only');
$root = sys_get_temp_dir() . '/cssholmes-followup-' . bin2hex(random_bytes(8));
mkdir($root . '/theme', 0700, true);
mkdir($root . '/private', 0700, true);
define('XOOPS_VAR_PATH', $root . '/private');
require_once __DIR__ . '/bootstrap.php';
register_shutdown_function(static function () use ($root): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
});
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) { throw new RuntimeException($message); }
};
use XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer;
use XoopsModules\Cssholmes\Analyzer\WidgetOutputAnalyzer;
use XoopsModules\Cssholmes\Analyzer\AnalysisContext;
use XoopsModules\Cssholmes\Analyzer\ThemeTarget;
use XoopsModules\Cssholmes\Workbench\PatchApplier;
use XoopsModules\Cssholmes\Workbench\ImportStore;
use XoopsModules\Cssholmes\Workbench\JsonHistory;

$color = new ReflectionMethod(DesignTokenAnalyzer::class, 'checkColorFormat');
foreach (['red', 'RebeccaPurple', 'aliceblue', '#abc', '#abcd', '#abcdef', '#abcdef99', 'rgb(1 2 3)', 'transparent'] as $value) {
    $findings = [];
    $color->invokeArgs(new DesignTokenAnalyzer(), [['--xtf-color-test' => $value], 'theme.json', &$findings]);
    $check($findings === [], 'Valid color rejected: ' . $value);
}
foreach (['red nonsense', 'transparent-invalid', '#12345', '#abcdefjunk', 'rgb('] as $value) {
    $findings = [];
    $color->invokeArgs(new DesignTokenAnalyzer(), [['--xtf-color-test' => $value], 'theme.json', &$findings]);
    $check(count($findings) === 1, 'Invalid color accepted: ' . $value);
}
foreach (['svg', 'img'] as $tag) {
    foreach (['width="24"' => false, 'height="24"' => false, 'data-width="24" height="24"' => false,
        'width="24" height="24"' => true, "style='width: 24px; height: 24px'" => true,
        'width="24" style="height:24px"' => true, 'style="max-width:24px; height:24px"' => false] as $attrs => $valid) {
        $findings = (new WidgetOutputAnalyzer())->analyze(new AnalysisContext('<' . $tag . ' ' . $attrs . '></' . $tag . '>', widgetName: 'sample'));
        $dimensions = array_filter($findings, static fn($finding) => $finding->ruleId === 'widget/' . $tag . '-no-dimensions');
        $check(($dimensions === []) === $valid, 'Check both dimensions: ' . $tag . ' ' . $attrs);
    }
}
$theme = new ThemeTarget('frontend', 'demo', $root . '/theme', 'Demo');
$patch = new PatchApplier();
$change = ['kind' => 'token', 'token_key' => '--xtf-color-primary', 'after' => 'blue'];
file_put_contents($root . '/theme/theme.json', '{"tokens":{"--xtf-color-primary":"red"}}');
$applied = $patch->apply($theme, $change, []);
$check($applied['success'] && $patch->rollback($theme, $change, $applied['rollback'])['success'], 'Normal manifest edits and rollback work');
unlink($root . '/theme/theme.json');
file_put_contents($root . '/outside.json', '{"tokens":{"--xtf-color-primary":"red"}}');
set_error_handler(static fn(): bool => true);
try { $linked = symlink($root . '/outside.json', $root . '/theme/theme.json'); } finally { restore_error_handler(); }
if ($linked) {
    $before = file_get_contents($root . '/outside.json');
    $check(!$patch->preview($theme, $change, [])['can_apply'], 'Preview must reject an external manifest');
    $check(!$patch->apply($theme, $change, [])['success'], 'Apply must reject an external manifest');
    $check(!$patch->rollback($theme, $change, $applied['rollback'])['success'], 'Rollback must reject an external manifest');
    $check(file_get_contents($root . '/outside.json') === $before, 'External manifest remains unchanged');
} else {
    if (PHP_OS_FAMILY !== 'Windows') { throw new RuntimeException('Symlink regression could not run.'); }
    echo "SKIP: Windows does not permit symlink creation; Linux CI covers the escape.\n";
}
$imports = new ImportStore($root . '/module');
$saved = $imports->saveImport([], '{}');
try { $imports->saveImport([], str_repeat('x', 1048577)); $check(false, 'Oversized payload accepted'); } catch (InvalidArgumentException $expected) {}
$check(count($imports->all()) === 1, 'Rejected payload must preserve history');
$history = new JsonHistory($root . '/module', 'imports.json');
$full = array_fill(0, 1000, $saved);
$history->locked(static fn() => $history->write($full));
try { $imports->saveImport([], '{}'); $check(false, 'Full history accepted another import'); } catch (RuntimeException $expected) {
    $check(str_contains($expected->getMessage(), 'history is full'), 'History limit must explain the refusal');
}
$check(count($imports->all()) === 1000, 'History must not silently evict rollback records');
try { $history->locked(static fn() => $history->write([['raw' => str_repeat('x', 16777216)]])); $check(false, 'Oversized history accepted'); } catch (RuntimeException $expected) {
    $check(str_contains($expected->getMessage(), '16 MiB'), 'History byte limit must be reported');
}
$check(count($imports->all()) === 1000, 'Failed history writes remain atomic');
file_put_contents($root . '/private/configs/cssholmes/workbench/imports.json', str_repeat(' ', 16777217));
try { $imports->all(); $check(false, 'Oversized history read accepted'); } catch (RuntimeException $expected) {
    $check(str_contains($expected->getMessage(), '16 MiB'), 'Oversized history reads must stop before decoding');
}
echo "Review follow-up: {$checks} checks passed.\n";
