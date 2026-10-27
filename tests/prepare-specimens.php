<?php
declare(strict_types=1);
PHP_SAPI === 'cli' && isset($root, $canvas) || exit('Run designer-http.php --specimens');

$core = dirname(__DIR__, 3);
$render = proc_open([PHP_BINARY, $core . '/modules/xstyles/tests/installed-specimens.php', $core, '--json'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $renderPipes);
if (!is_resource($render)) throw new RuntimeException('Cannot render installed specimens');
fclose($renderPipes[0]);
$rendered = stream_get_contents($renderPipes[1]);
fclose($renderPipes[1]);
if (proc_close($render) !== 0) throw new RuntimeException('Installed specimen rendering failed');
$galleries = json_decode($rendered, true, 512, JSON_THROW_ON_ERROR);
$assets = [
    'xbootstrap5' => ['css/bootstrap.min.css', 'css/xoops.css', 'css/reset.css', 'style.css'],
    'mytheme6' => ['vendor/bootstrap6/css/bootstrap.min.css', 'css/theme.css', 'style.css'],
];
foreach (['assets/css/gallery.css', 'assets/js/gallery.js', 'assets/js/responsive.js'] as $asset) {
    $target = $root . '/modules/xstyles/' . $asset;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
    if (!copy($core . '/modules/xstyles/' . $asset, $target)) throw new RuntimeException('Cannot copy gallery asset');
}
foreach ($assets as $theme => $files) {
    $links = '<link rel="stylesheet" href="/modules/xstyles/assets/css/gallery.css">';
    foreach ($files as $asset) {
        $target = $root . '/themes/' . $theme . '/' . $asset;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
        if (!copy($core . '/themes/' . $theme . '/' . $asset, $target)) throw new RuntimeException('Cannot copy theme CSS');
        $links .= '<link rel="stylesheet" href="/themes/' . $theme . '/' . $asset . '">';
    }
    file_put_contents($root . '/themes/' . $theme . '/theme.tpl', 'Isolated installed-theme specimen test');
    file_put_contents($root . '/' . $theme . '-gallery.json', json_encode($galleries[$theme], JSON_THROW_ON_ERROR));
    $page = str_replace("require __DIR__ . '/mainfile.php';", "define('TEST_SPECIMEN_THEME', '$theme');\ndefine('XSTYLES_PREVIEW_FRAME', (\$_GET['preview_frame'] ?? '') === '1');\nrequire __DIR__ . '/mainfile.php';", $canvas);
    $page = str_replace('CssholmesDesignerPreload::eventCoreHeaderAddmeta();', "\$GLOBALS['xoopsConfig']['theme_set'] = '$theme';\nCssholmesDesignerPreload::eventCoreHeaderAddmeta();", $page);
    $page = str_replace('<style>.xoops-comment-text>h4{font-size:18px;color:#141414}</style>', $links, $page);
    $page = substr($page, 0, strpos($page, '<button data-cssholmes-designer>'));
    $page .= '<button data-cssholmes-designer>Open theme designer</button><?php $gallery = json_decode(file_get_contents(__DIR__ . "/' . $theme . '-gallery.json"), true); echo $gallery[XSTYLES_PREVIEW_FRAME ? "child" : "parent"]; ?>'
        . '<script defer src="/modules/xstyles/assets/js/gallery.js"></script><script defer src="/modules/xstyles/assets/js/responsive.js"></script></body></html>';
    file_put_contents($root . '/' . $theme . '.php', $page);
    mkdir($root . '/' . $theme, 0700);
    file_put_contents($root . '/' . $theme . '/index.php', '<?php require dirname(__DIR__) . "/' . $theme . '.php";');
}
