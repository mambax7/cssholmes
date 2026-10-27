<?php
declare(strict_types=1);

PHP_SAPI === 'cli' || exit('CLI only');
if (($argv[1] ?? '') === 'history-worker') {
    define('XOOPS_VAR_PATH', $argv[2] . '/private');
    require_once __DIR__ . '/bootstrap.php';
    $history = new XoopsModules\Cssholmes\Workbench\ImportStore($argv[2] . '/module');
    for ($i = 0; $i < 12; ++$i) {
        $history->saveImport([], '{}');
    }
    exit;
}
$root = sys_get_temp_dir() . '/cssholmes-review-' . bin2hex(random_bytes(8));
mkdir($root . '/themes/demo', 0700, true);
mkdir($root . '/private', 0700, true);
define('XOOPS_ROOT_PATH', $root);
define('XOOPS_VAR_PATH', $root . '/private');
require __DIR__ . '/bootstrap.php';
$failures = [];
function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}
register_shutdown_function(static function () use ($root): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
});

use XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer;
use XoopsModules\Cssholmes\Analyzer\ThemeTarget;
use XoopsModules\Cssholmes\Workbench\ImportStore;
use XoopsModules\Cssholmes\Workbench\PatchApplier;
use XoopsModules\Cssholmes\Workbench\ScanStore;

$extract = new ReflectionMethod(DesignTokenAnalyzer::class, 'extractCssVarRefs');
$refs = $extract->invoke(new DesignTokenAnalyzer(), ['a.css' => 'a{color:var(--xtf-ink)}', 'b.css' => 'b{color:var(--xtf-ink, red)}']);
check($refs['--xtf-ink']['file'] === 'a.css' && $refs['--xtf-ink']['fallback'] === null, 'Keep first reference and missing fallback');
$theme = new ThemeTarget('frontend', 'demo', $root . '/themes/demo', 'Demo');
$resolver = new XoopsModules\Cssholmes\Analyzer\TemplateIncludeResolver($theme->path);
check($resolver->resolve('"$theme_name/../shared/header.tpl"')['status'] === $resolver::STATUS_EXTERNAL, 'Theme-prefixed external includes are not missing files');
$template = $theme->path . '/theme.tpl';
file_put_contents($template, '<h1>Before</h1>');
file_put_contents($theme->path . '/style.css', 'body { color: black; }');
$patch = new PatchApplier();
$change = ['kind' => 'text', 'before' => 'Before', 'after' => 'After'];
$result = $patch->apply($theme, $change, ['targets' => ['theme.tpl']]);
check($result['success'], 'Text patch applies');
file_put_contents($template, '<h1>After</h1><p>Later edit</p>');
$rollback = $patch->rollback($theme, $change, $result['rollback']);
check(!$rollback['success'] || str_contains(file_get_contents($template), 'Later edit'), 'Rollback preserves later edits');
file_put_contents($template, 'Before');
file_put_contents($theme->path . '/theme.php', 'Before');
check(!$patch->apply($theme, array_replace($change, ['after' => '<?php evil(); ?>']), ['targets' => ['theme.php']])['success'], 'PHP targets cannot be patched');
check(file_get_contents($theme->path . '/theme.php') === 'Before', 'Executable target remains unchanged');
$clean = $patch->apply($theme, $change, ['targets' => ['theme.tpl']]);
check($patch->rollback($theme, $change, $clean['rollback'])['success'] && file_get_contents($template) === 'Before', 'Unchanged template rolls back');
$style = ['kind' => 'style', 'selector' => 'h1', 'style_property' => 'content', 'after' => '"\\201C $1"'];
$first = $patch->apply($theme, $style, ['targets' => ['style.css']]);
$second = $patch->apply($theme, array_replace($style, ['after' => '"\\201D $2"']), ['targets' => ['style.css']]);
check(str_contains(file_get_contents($theme->path . '/style.css'), '"\\201D $2"'), 'CSS replacement preserves literal escapes');
$patch->rollback($theme, $style, $second['rollback']);
check(str_contains(file_get_contents($theme->path . '/style.css'), '"\\201C $1"'), 'CSS rollback preserves literal escapes');
foreach (['red; } body { display:none', 'red/*', "red\n}"] as $value) {
    $unsafe = array_replace($style, ['after' => $value]);
    check(!$patch->preview($theme, $unsafe, [])['can_apply'] && !$patch->apply($theme, $unsafe, [])['success'], 'Reject CSS rule injection');
}
$module = $root . '/module';
mkdir($module . '/data/workbench', 0700, true);
file_put_contents($module . '/data/workbench/imports.json', '[{"id":"legacy","changes":[]}]');
$imports = new ImportStore($module);
check($imports->find('legacy') !== null, 'Legacy import history is migrated');
$record = $imports->saveImport(['changes' => [['kind' => 'text']]], '{}');
check($imports->updateChangeStatus($record['id'], 0, 'accepted'), 'Update imported change');
check($imports->find($record['id'])['changes'][0]['status'] === 'accepted', 'Persist imported change');
check(!file_exists($module . '/data/workbench/imports.json'), 'Import history is removed from public tree');
$scans = new ScanStore($module);
$scans->saveScan($theme, [], []);
check(!file_exists($module . '/data/workbench/scans.json') && count($scans->all()) === 1, 'Scan history stays private');
$workers = [];
for ($i = 0; $i < 3; ++$i) {
    $workers[] = proc_open([PHP_BINARY, __FILE__, 'history-worker', $root], [STDIN, STDOUT, STDERR], $pipes);
}
foreach ($workers as $worker) {
    check(is_resource($worker) && proc_close($worker) === 0, 'Concurrent history writer completes');
}
check(count($imports->all()) === 38, 'Concurrent writers preserve every record');
$historyPath = XOOPS_VAR_PATH . '/configs/cssholmes/workbench/imports.json';
$beforeHash = hash_file('sha256', $historyPath);
try {
    $imports->saveImport([], "\xff");
    check(false, 'Invalid UTF-8 must report a write failure');
} catch (JsonException $expected) {
    check(hash_file('sha256', $historyPath) === $beforeHash, 'Encoding failure preserves existing history');
}
$output = fopen('php://memory', 'w+');
$errors = fopen('php://memory', 'w+');
file_put_contents($template, '<{include file="missing-' . "\xff" . '.tpl"}>');
(new XoopsModules\Cssholmes\Cli\ScanCommand($output, $errors))->run(['--format=json', $theme->path]);
rewind($output);
check(is_array(json_decode(stream_get_contents($output), true, 512, JSON_THROW_ON_ERROR)), 'CLI emits JSON for legacy encoded theme text');
fclose($output);
fclose($errors);
class ReviewResult extends mysqli_result
{
    public function __construct() {}
}
class XoopsMySQLDatabase
{
    public string $insert = '';
    public int $rows = 1;
    public bool $failInsert = false;
    public function prefix($name): string { return 'test_' . $name; }
    public function quote($value): string { return "'" . str_replace("'", "''", $value) . "'"; }
    public function query($sql): ReviewResult { return new ReviewResult(); }
    public function queryF($sql, $limit = 0, $start = 0): ReviewResult|bool
    {
        if (str_starts_with($sql, 'SHOW')) { return new ReviewResult(); }
        $this->insert = $sql;
        return !$this->failInsert;
    }
    public function isResultSet($result): bool { return $result instanceof mysqli_result; }
    public function fetchArray($result): array { return ['id' => 1, 'title' => "O'Brien", 'optional' => null]; }
    public function getInsertId(): int { return 2; }
    public function getRowsNum($result): int { return $this->rows; }
}
$GLOBALS['xoopsDB'] = $db = new XoopsMySQLDatabase();
$GLOBALS['xoopsLogger'] = new class { public function addDeprecated($message): void {} };
$utility = XoopsModules\Cssholmes\Common\SysUtility::class;
check($utility::cloneRecord('items', 'id', 1) === 2 && str_contains($db->insert, "'O''Brien', NULL"), 'Clone quotes values, preserves NULL and accepts INSERT success');
$db->failInsert = true;
try {
    $utility::cloneRecord('items', 'id', 1);
    check(false, 'Failed INSERT must be reported');
} catch (RuntimeException $expected) {}
set_error_handler(static fn(int $severity): bool => $severity === E_USER_NOTICE);
try {
    check($utility::tableExists('items'), 'Existing table is reported');
    $db->rows = 0;
    check(!$utility::tableExists('items'), 'Missing table is reported');
} finally { restore_error_handler(); }
file_put_contents($root . '/blocked', 'file');
set_error_handler(static fn(): bool => true);
try {
    $utility::recurseCopy($theme->path, $root . '/blocked/target');
    check(false, 'Copy must stop on destination failure');
} catch (RuntimeException $expected) {
} finally { restore_error_handler(); }
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Review regressions passed.\n";

