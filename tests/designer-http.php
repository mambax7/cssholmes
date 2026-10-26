<?php

declare(strict_types=1);

PHP_SAPI === 'cli' || exit('CLI only');

// Exercise production endpoints in a temporary core with explicit test-only users.
// No production session, database, credentials or theme settings are opened.
$root = sys_get_temp_dir() . '/cssholmes-http-' . bin2hex(random_bytes(8));
mkdir($root . '/modules/cssholmes', 0700, true);
mkdir($root . '/themes/demo', 0700, true);
mkdir($root . '/themes/demo2', 0700, true);
mkdir($root . '/private', 0700, true);
file_put_contents($root . '/themes/demo/theme.tpl', 'Test theme');
file_put_contents($root . '/themes/demo2/theme.tpl', 'Second test theme');
$module = dirname(__DIR__);
$copy = static function (string $relative) use ($root, $module): void {
    $destination = $root . '/modules/cssholmes/' . $relative;
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0700, true);
    }
    if (!copy($module . '/' . $relative, $destination)) {
        throw new RuntimeException('Unable to prepare endpoint test.');
    }
};
foreach (['designer.php', 'sets.php', 'theme-style.php', 'include/designer.php', 'preloads/autoloader.php', 'preloads/designer.php', 'assets/js/designer.js', 'assets/css/designer.css', 'language/english/main.php'] as $file) {
    $copy($file);
}
foreach (glob($module . '/class/Designer/*.php') as $file) {
    $copy('class/Designer/' . basename($file));
}
foreach (['assets/js/theme-sets-admin.js', 'assets/css/theme-sets-admin.css', 'language/english/admin.php'] as $file) {
    $copy($file);
}
foreach (['assets/js/designer-diagnostics.js', 'language/english/diagnostics.php'] as $file) {
    $copy($file);
}
$bootstrap = <<<'PHP'
<?php
namespace Xmf {
    final class Request {
        public static function getString($key, $default = '', $source = 'GET') {
            $value = $_GET[$key] ?? $default;
            return is_string($value) ? $value : $default;
        }
    }
}
namespace {
    define('XOOPS_ROOT_PATH', __DIR__);
    define('XOOPS_VAR_PATH', __DIR__ . '/private');
    define('XOOPS_URL', 'http://' . $_SERVER['HTTP_HOST']);
    class XoopsPreloadItem {}
    function xoops_loadLanguage($file, $module) { require_once __DIR__ . '/modules/cssholmes/language/english/' . $file . '.php'; }
    final class TestUser {
        public function isAdmin($id) { return ($_SERVER['HTTP_X_TEST_ROLE'] ?? '') === 'admin'; }
        public function getGroups() { return [$_SERVER['HTTP_X_TEST_ROLE'] ?? 'reader']; }
        public function getVar($key, $format = '') { return $key === 'uid' ? (($_SERVER['HTTP_X_TEST_ROLE'] ?? '') === 'writer2' ? 2 : 1) : 'Test designer'; }
    }
    final class TestModule {
        public function getVar($key) { return $key === 'isactive' ? 1 : 42; }
    }
    final class TestHandler {
        public function getByDirname($name) { return new TestModule(); }
        public function checkRight($name, $item, $groups, $module) {
            return in_array('writer', $groups, true) || in_array('writer2', $groups, true)
                || (in_array('preview', $groups, true) && in_array($name, ['cssholmes_edit', 'cssholmes_overlay'], true));
        }
    }
    final class TestSecurity {
        public function check($clear, $token, $name) { return $token === 'test-token' && $name === 'CSSHOLMES_DESIGNER'; }
        public function createToken($timeout, $name) { return 'test-token'; }
    }
    function xoops_getHandler($name) { return new TestHandler(); }
    $GLOBALS['xoopsUser'] = isset($_SERVER['HTTP_X_TEST_ROLE']) ? new TestUser() : null;
    $GLOBALS['xoopsSecurity'] = new TestSecurity();
    $GLOBALS['xoopsConfig'] = ['theme_set' => 'demo'];
}
PHP;
file_put_contents($root . '/mainfile.php', $bootstrap);
$canvas = <<<'PHP'
<?php
require __DIR__ . '/mainfile.php';
require __DIR__ . '/modules/cssholmes/preloads/designer.php';
final class TestTemplate { public function assign($key, $value) {} }
final class TestTheme {
    public string $folderName = TEST_SPECIMEN_THEME;
    public array $htmlHeadStrings = [];
    public TestTemplate $template;
    public function __construct() { $this->template = new TestTemplate(); }
    public function addStylesheet($url, $attributes) { $this->htmlHeadStrings[] = '<link rel="stylesheet" id="' . $attributes['id'] . '" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'; }
    public function addScript($url, $attributes) { $this->htmlHeadStrings[] = '<script defer src="' . htmlspecialchars($url, ENT_QUOTES) . '"></script>'; }
}
$GLOBALS['xoTheme'] = new TestTheme();
CssholmesDesignerPreload::eventCoreHeaderAddmeta();
?><!doctype html><html><head><style>.xoops-comment-text>h4{font-size:18px;color:#141414}</style><?php echo implode("\n", $GLOBALS['xoTheme']->htmlHeadStrings); ?></head><body>
<button data-cssholmes-designer>Open theme designer</button>
<div class="xoops-comment-template"><div class="xoops-comment-text"><h4>A thoughtful response</h4><p class="message-text">Comment text</p></div></div>
<div class="xoops-comment-template"><div class="xoops-comment-text"><h4>Another response</h4></div></div>
<section class="fixture-parent"><span><strong>Nested specimen</strong></span></section>
</body></html>
PHP;
file_put_contents($root . '/canvas.php', str_replace("require __DIR__ . '/mainfile.php';", "define('TEST_SPECIMEN_THEME', 'demo');\nrequire __DIR__ . '/mainfile.php';", $canvas));
$adminCanvas = <<<'PHP'
<?php
require __DIR__ . '/mainfile.php';
require __DIR__ . '/modules/cssholmes/language/english/admin.php';
$labels = [];
foreach (get_defined_constants() as $key => $value) {
    if (str_starts_with($key, '_AM_CSSHOLMES_SETS_')) {
        $labels[strtolower(substr($key, strlen('_AM_CSSHOLMES_SETS_')))] = $value;
    }
}
$config = ['libraryEndpoint' => '/modules/cssholmes/sets.php', 'previewUrl' => '/canvas.php', 'labels' => $labels];
?><!doctype html><html><head><link rel="stylesheet" href="/modules/cssholmes/assets/css/theme-sets-admin.css"></head><body>
<div id="cssholmes-theme-sets"></div>
<script type="application/json" id="cssholmes-theme-sets-config"><?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="/modules/cssholmes/assets/js/theme-sets-admin.js" defer></script>
</body></html>
PHP;
file_put_contents($root . '/library.php', $adminCanvas);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException('Unable to allocate test port.'); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, '-t', $root], [0 => ['pipe', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Unable to start endpoint test server.'); }
fclose($pipes[0]);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) { throw new RuntimeException($message); }
};
$request = static function (string $path, ?array $body = null, string $role = '', array $extraHeaders = []) use ($address): array {
    $headers = ['Content-Type: application/json'];
    if ($role !== '') { $headers[] = 'X-Test-Role: ' . $role; }
    $headers = array_merge($headers, $extraHeaders);
    $context = stream_context_create(['http' => [
        'method' => $body === null ? 'GET' : 'POST', 'ignore_errors' => true, 'timeout' => 5,
        'header' => implode("\r\n", $headers), 'content' => $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
    ]]);
    $text = file_get_contents('http://' . $address . '/modules/cssholmes/' . $path, false, $context);
    if ($text === false) { throw new RuntimeException('Endpoint request failed.'); }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], $text, $http_response_header];
};
try {
    $ready = false;
    for ($attempt = 0; $attempt < 50; ++$attempt) {
        set_error_handler(static fn() => true);
        try {
            $connection = stream_socket_client('tcp://' . $address, $errno, $error, .1);
        } finally {
            restore_error_handler();
        }
        if (is_resource($connection)) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    $check($ready, 'Endpoint server did not become ready');
    [$status] = $request('designer.php?theme=demo');
    $check($status === 403, 'Anonymous config reads must be denied');
    [$status, $text] = $request('designer.php?theme=demo', null, 'preview');
    $preview = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check($status === 200 && $preview['canSave'] === false, 'Preview role must remain read-only');
    $payload = ['op' => 'save', 'theme' => 'demo', 'revision' => $preview['state']['revision'], 'token' => 'test-token', 'rules' => [[
        'selector' => '.comTitle', 'label' => 'Comment titles', 'mode' => 'all', 'breakpoint' => 'all', 'state' => 'normal',
        'declarations' => ['color' => '#123456', 'font-size' => '24px'],
    ]]];
    [$status] = $request('designer.php', $payload, 'preview');
    $check($status === 403, 'Preview role must never save');
    [$status, $text] = $request('designer.php', array_replace($payload, ['token' => 'wrong']), 'writer');
    $check($status === 403, 'Invalid CSRF must fail before saving');
    $renewed = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check(($renewed['code'] ?? '') === 'csrf_expired' && ($renewed['token'] ?? '') === 'test-token', 'An open editor must recover its token without reloading the draft');
    $check(!is_dir($root . '/private/configs'), 'Reads and rejected writes must not create settings');
    [$status, $text] = $request('designer.php', $payload, 'writer');
    $saved = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check($status === 200 && $saved['ok'], 'Valid style save failed');
    [$status, $text, $headers] = $request('theme-style.php?theme=demo');
    $check($status === 200 && str_contains($text, 'color: #123456 !important;'), 'Public styles must apply without editor access');
    $etag = '';
    foreach ($headers as $header) { if (str_starts_with(strtolower($header), 'etag: ')) { $etag = substr($header, 6); } }
    $check($etag === '"' . hash('sha256', $text) . '"', 'CSS ETag must describe rendered output, including compiler changes');
    [$status] = $request('theme-style.php?theme=demo', null, '', ['If-None-Match: ' . $etag]);
    $check($etag !== '' && $status === 304, 'CSS cache revalidation failed');
    [$status] = $request('designer.php', $payload, 'writer');
    $check($status === 409, 'Stale revision must not overwrite settings');
    $invalid = $payload;
    $invalid['revision'] = $saved['state']['revision'];
    $invalid['rules'][0]['declarations']['color'] = 'red;body{display:none}';
    [$status] = $request('designer.php', $invalid, 'writer');
    $check($status === 422, 'Injected CSS must be rejected');
    [$status] = $request('theme-style.php?theme=..%2F..%2Fprivate');
    $check($status === 404, 'Public stylesheet traversal must be rejected');
    [$status, $text] = $request('designer.php', ['op' => 'restore', 'theme' => 'demo', 'revision' => $saved['state']['revision'], 'token' => 'test-token'], 'admin');
    $restored = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check($status === 200 && $restored['state']['rules'] === [], 'Restore must reinstate the previous save');
    [$status, $text] = $request('theme-style.php?theme=demo');
    $check($status === 200 && trim($text) === '', 'Public CSS must reflect restore');
    [$status, $text] = $request('sets.php?theme=demo');
    $check($status === 403, 'Anonymous users must not read the set library');
    $signedOut = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check(($signedOut['code'] ?? '') === 'auth_required' && !isset($signedOut['token']), 'Signed-out users must be asked to log in and must not receive a new token');
    [$status, $text] = $request('sets.php?theme=demo', null, 'writer');
    $listing = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $listing['library']['sets'] === [] && count($listing['library']['palettes']) >= 4, 'Library must start empty and supply starter palettes');
    $newSet = ['theme' => 'demo', 'token' => 'test-token', 'op' => 'saveSet', 'name' => 'Ocean café', 'rules' => [
        ['selector' => '.comTitle', 'label' => 'Comment title', 'mode' => 'all', 'breakpoint' => 'all', 'state' => 'normal', 'declarations' => ['color' => '#123456']],
        ['selector' => '.brand-widget', 'label' => 'Source widget', 'mode' => 'all', 'breakpoint' => 'all', 'state' => 'normal', 'declarations' => ['font-size' => '25px']],
    ]];
    [$status, $text] = $request('sets.php', array_replace($newSet, ['token' => 'wrong']), 'writer');
    $check($status === 403, 'Library writes require a valid CSRF token');
    $renewed = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check(($renewed['code'] ?? '') === 'csrf_expired' && ($renewed['token'] ?? '') === 'test-token', 'An open library must recover its token without accepting an expired write');
    [$status] = $request('sets.php', $newSet, 'preview');
    $check($status === 403, 'Read-only designers cannot create library sets');
    [$status, $text] = $request('sets.php', $newSet, 'writer');
    $created = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $created['set']['author']['id'] === 1 && $created['set']['name'] === 'Ocean café', 'Save as must use the authenticated author and preserve Unicode names');
    $check(!str_contains($text, 'likedBy'), 'Private voter identities must not be exposed');
    $set = $created['set'];
    [$status, $text] = $request('theme-style.php?theme=demo');
    $check($status === 200 && trim($text) === '', 'Saving a library set must not publish it');
    [$status, $text] = $request('sets.php?theme=demo2&action=preview&id=' . $set['id'], null, 'writer');
    $adapted = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $adapted['preview']['warnings'] !== [] && !str_contains(json_encode($adapted['preview']['rules']), 'brand-widget'), 'Cross-theme preview must report skipped source-only selectors');
    $check(str_contains(json_encode($adapted['preview']['rules']), 'comTitle'), 'Semantic component styling must survive adaptation');
    [$status, $text] = $request('sets.php?theme=demo&action=export&id=' . $set['id'], null, 'writer');
    $exported = json_decode($text, true, 32, JSON_THROW_ON_ERROR)['exported'];
    $check($status === 200 && $exported['defaults'] === 'inherit-target-theme' && $exported['version'] === 1 && !isset($exported['author']), 'Portable exports must specify defaults and omit local identities');
    $update = array_replace($newSet, ['op' => 'updateSet', 'id' => $set['id'], 'setRevision' => $set['revision'], 'name' => 'Changed name']);
    [$status] = $request('sets.php', $update, 'writer2');
    $check($status === 403, 'Another designer cannot overwrite a set');
    $vote = ['theme' => 'demo', 'op' => 'like', 'id' => $set['id'], 'liked' => true, 'token' => 'test-token'];
    [$status, $text] = $request('sets.php', $vote, 'preview');
    $liked = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $liked['set']['likes'] === 1 && $liked['set']['liked'], 'Preview designers can like sets');
    [$status, $text] = $request('sets.php', $vote, 'preview');
    $check($status === 200 && json_decode($text, true)['set']['likes'] === 1, 'Repeated votes must be idempotent');
    [$status, $text] = $request('sets.php', $update, 'writer');
    $updated = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $updated['set']['created'] === $set['created'], 'Liking must not invalidate design revisions; updating preserves creation date');
    [$status] = $request('sets.php', $update, 'writer');
    $check($status === 409, 'Stale set revisions must not overwrite newer content');
    $activate = ['theme' => 'demo', 'op' => 'activate', 'id' => $set['id'], 'revision' => $restored['state']['revision'], 'token' => 'test-token'];
    [$status, $text] = $request('sets.php', $activate, 'writer');
    $active = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $active['state']['rules'] !== [], 'Activation must publish the selected set');
    [$status, $text] = $request('sets.php', ['theme' => 'demo', 'op' => 'reset', 'revision' => $active['state']['revision'], 'token' => 'test-token'], 'writer');
    $reset = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $reset['state']['rules'] === [] && count($reset['library']['sets']) === 1, 'Original reset must clear published overrides and retain named sets');
    $badDocument = $exported;
    $badDocument['version'] = 999;
    [$status] = $request('sets.php', ['theme' => 'demo2', 'op' => 'importSet', 'document' => $badDocument, 'token' => 'test-token'], 'writer');
    $check($status === 422, 'Unknown portable versions must be rejected');
    [$status, $text] = $request('sets.php', ['theme' => 'demo2', 'op' => 'importSet', 'document' => $exported, 'token' => 'test-token'], 'writer2');
    $imported = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    $check($status === 200 && $imported['set']['theme'] === 'demo2' && $imported['set']['author']['id'] === 2, 'Imports must create a local author-owned copy in the chosen theme');
    [$status] = $request('sets.php', ['theme' => 'demo', 'op' => 'deleteSet', 'id' => $set['id'], 'setRevision' => $updated['set']['revision'], 'token' => 'test-token'], 'writer2');
    $check($status === 403, 'Another author cannot delete a set');
    [$status, $text] = $request('sets.php', ['theme' => 'demo', 'token' => 'test-token', 'op' => 'exportCurrent', 'name' => '', 'rules' => []], 'preview');
    $check($status === 200 && json_decode($text, true)['document']['format'] === 'xoops-theme-set', 'Portable export works without a set name or publishing permission');
    [$status, $text] = $request('sets.php', ['theme' => 'demo2', 'token' => 'test-token', 'op' => 'previewImport', 'document' => ['theme' => 'demo', 'rules' => $newSet['rules']]], 'preview');
    $check($status === 200 && json_decode($text, true)['preview']['document']['version'] === 1, 'Legacy theme/rules exports must convert safely to the portable format');
    [$status, $text] = $request('sets.php?theme=demo&action=preview&id=original', null, 'writer');
    $original = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check($status === 200 && $original['preview']['rules'] === [], 'The original preview must always be an empty override set');
    $clone = ['theme' => 'demo', 'token' => 'test-token', 'op' => 'cloneSet', 'id' => $set['id'], 'name' => 'Copied design', 'author' => ['id' => 999, 'name' => 'Admin'], 'likes' => 999, 'created' => '1900-01-01'];
    [$status] = $request('sets.php', $clone, 'preview');
    $check($status === 403, 'Read-only designers cannot clone sets');
    [$status] = $request('sets.php', array_replace($clone, ['token' => 'wrong']), 'writer2');
    $check($status === 403, 'Cloning requires CSRF validation');
    [$status, $text] = $request('sets.php', $clone, 'writer2');
    $copied = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
    $check($status === 200 && $copied['set']['id'] !== $set['id'] && $copied['set']['name'] === 'Copied design', 'Clone must create a distinct named set');
    $check($copied['set']['author']['id'] === 2 && $copied['set']['likes'] === 0 && $copied['set']['created'] !== $set['created'] && $copied['set']['created'] === $copied['set']['modified'], 'Clones use the current author, real dates and zero likes, ignoring fabricated metadata');
    [$status, $text] = $request('sets.php', array_replace($clone, ['id' => 'original', 'name' => 'Fresh defaults']), 'writer');
    $check($status === 200 && json_decode($text, true)['set']['name'] === 'Fresh defaults', 'A displayed original sample can be cloned into a real set');
    [$status, $text] = $request('theme-style.php?theme=demo');
    $check($status === 200 && trim($text) === '', 'Cloning must never activate a set');
    if (in_array('--browser', $argv, true)) {
        $browser = proc_open(['node', __DIR__ . '/designer-roundtrip.cjs', 'http://' . $address], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $browserPipes);
        if (!is_resource($browser)) { throw new RuntimeException('Unable to start browser integration check.'); }
        fclose($browserPipes[0]);
        $check(proc_close($browser) === 0, 'Browser roundtrip failed');
    }
    if (in_array('--specimens', $argv, true)) {
        require __DIR__ . '/prepare-specimens.php';
        $browser = proc_open(['node', __DIR__ . '/designer-specimens.cjs', 'http://' . $address], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $browserPipes);
        if (!is_resource($browser)) { throw new RuntimeException('Unable to start specimen workflow check.'); }
        fclose($browserPipes[0]);
        $check(proc_close($browser) === 0, 'Specimen designer workflow failed');
    }
    echo "Designer endpoints: {$checks} checks passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    $resolved = realpath($root);
    $temporary = realpath(sys_get_temp_dir());
    if ($resolved !== false && str_starts_with($resolved, $temporary . DIRECTORY_SEPARATOR . 'cssholmes-http-')) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($resolved);
    }
}
