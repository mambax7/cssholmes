<?php

declare(strict_types=1);

use Xmf\Module\Admin;

require_once __DIR__ . '/admin_header.php';
require_once dirname(__DIR__) . '/include/designer.php';
xoops_cp_header();
Admin::getInstance()->displayNavigation(basename(__FILE__));

$labels = [];
$prefix = '_AM_CSSHOLMES_SETS_';
foreach (get_defined_constants() as $key => $value) {
    if (str_starts_with($key, $prefix) && is_string($value)) {
        $labels[strtolower(substr($key, strlen($prefix)))] = $value;
    }
}
$baseUrl = XOOPS_URL . '/modules/cssholmes';
$config = [
    'libraryEndpoint' => $baseUrl . '/sets.php',
    'designerEndpoint' => $baseUrl . '/designer.php',
    'previewUrl' => XOOPS_URL . '/modules/xstyles/',
    'token' => '',
    'labels' => $labels,
];
echo '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl . '/assets/css/theme-sets-admin.css', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div id="cssholmes-theme-sets"><noscript>' . htmlspecialchars(_AM_CSSHOLMES_SETS_JAVASCRIPT, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</noscript></div>';
echo '<script type="application/json" id="cssholmes-theme-sets-config">'
    . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)
    . '</script>';
echo '<script src="' . htmlspecialchars($baseUrl . '/assets/js/theme-sets-admin.js', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" defer></script>';

require_once __DIR__ . '/admin_footer.php';
