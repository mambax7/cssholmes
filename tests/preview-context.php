<?php
declare(strict_types=1);
PHP_SAPI === 'cli' || exit('CLI only');
$root = sys_get_temp_dir() . '/cssholmes-preview-' . bin2hex(random_bytes(8));
mkdir($root . '/themes/demo', 0700, true);
file_put_contents($root . '/themes/demo/theme.tpl', 'Preview');
define('XOOPS_ROOT_PATH', $root);
define('XOOPS_VAR_PATH', $root . '/private');
define('XOOPS_URL', 'https://preview.invalid');
define('XSTYLES_PREVIEW_FRAME', true);
class XoopsPreloadItem {}
class PreviewTemplate {
    public array $values = [];
    public function assign($name, $value) { $this->values[$name] = $value; }
}
class PreviewTheme {
    public string $folderName = 'demo';
    public array $styles = [];
    public array $scripts = [];
    public PreviewTemplate $template;
    public function __construct() { $this->template = new PreviewTemplate(); }
    public function addStylesheet($url, $attributes) { $this->styles[] = $url; }
    public function addScript($url, $attributes) { $this->scripts[] = $url; }
}
require dirname(__DIR__) . '/include/designer.php';
require dirname(__DIR__) . '/preloads/designer.php';
require dirname(__DIR__) . '/preloads/core.php';
try {
    $store = cssholmes_designer_store();
    $state = $store->load('demo');
    $store->save('demo', [['id' => '', 'selector' => 'h1', 'label' => 'Heading', 'mode' => 'dark', 'breakpoint' => 'all', 'state' => 'normal', 'declarations' => ['color' => '#123456']]], $state['revision']);
    $GLOBALS['xoTheme'] = new PreviewTheme();
    CssholmesDesignerPreload::eventCoreHeaderAddmeta();
    CssholmesCorePreload::eventCoreHeaderAddmeta();
    if (count($GLOBALS['xoTheme']->styles) !== 1 || $GLOBALS['xoTheme']->scripts !== []
        || $GLOBALS['xoTheme']->template->values['cssholmes_designer_available'] !== false) {
        throw new RuntimeException('Frame must retain published CSS and suppress editor assets');
    }
    echo "Preview context: published CSS retained, editor/overlay assets suppressed, no authentication lookup.\n";
} finally {
    $resolved = realpath($root);
    if ($resolved !== false && str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'cssholmes-preview-')) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($resolved);
    }
}
