<?php

declare(strict_types=1);

// Public presentation output needs paths, not a visitor session or database.
$xoopsOption['nocommon'] = true;
require_once dirname(__DIR__, 2) . '/mainfile.php';
require_once __DIR__ . '/include/designer.php';

header('Content-Type: text/css; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=0, must-revalidate');
try {
    $theme = $_GET['theme'] ?? null;
    if (!is_string($theme)) {
        throw new InvalidArgumentException('Theme is required.');
    }
    $state = cssholmes_designer_store()->load($theme);
    // Compiler upgrades can change CSS without changing the saved rule revision.
    $etag = '"' . hash('sha256', $state['css']) . '"';
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    echo $state['css'];
} catch (InvalidArgumentException $error) {
    http_response_code(404);
    echo '/* Theme not found. */';
} catch (Throwable $error) {
    http_response_code(500);
    echo '/* Theme settings could not be loaded. */';
}
