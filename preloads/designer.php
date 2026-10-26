<?php

declare(strict_types=1);

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Saved CSS applies to visitors; the editor itself remains permission-gated. */
final class CssholmesDesignerPreload extends \XoopsPreloadItem
{
    public static function eventCoreHeaderAddmeta(): void
    {
        $theme = $GLOBALS['xoTheme'] ?? null;
        if (!is_object($theme)) {
            return;
        }
        require_once dirname(__DIR__) . '/include/designer.php';
        $moduleUrl = XOOPS_URL . '/modules/cssholmes';
        $active = (string) $theme->folderName;
        try {
            $store = cssholmes_designer_store();
            $state = $store->load($active);
            $styleUrl = $moduleUrl . '/theme-style.php?' . http_build_query(['theme' => $active]);
            if ($state['rules'] !== []) {
                $theme->addStylesheet($styleUrl . '&v=' . rawurlencode($state['revision']), ['id' => 'cssholmes-theme-style']);
            }
            if (defined('XSTYLES_PREVIEW_FRAME') && XSTYLES_PREVIEW_FRAME === true) {
                $theme->template->assign('cssholmes_designer_available', false);
                return;
            }
            $access = cssholmes_designer_access();
            $theme->template->assign('cssholmes_designer_available', $access['edit']);
            if (!$access['edit']) {
                return;
            }
            $config = [
                'theme' => $active, 'endpoint' => $moduleUrl . '/designer.php',
                'styleUrl' => $styleUrl, 'stylesheetUrl' => $moduleUrl . '/assets/css/designer.css',
                'token' => '', 'canSave' => $access['save'], 'labels' => cssholmes_designer_labels(),
                'autoOpen' => \Xmf\Request::getString('designer', '0', 'GET') === '1',
                'properties' => $store::properties(),
                'libraryEndpoint' => $moduleUrl . '/sets.php',
                'initialSetId' => \Xmf\Request::getString('theme_set', '', 'GET'),
            ];
            // addScript wraps inline content in JavaScript comments, which are invalid JSON.
            $theme->htmlHeadStrings[] = '<script type="application/json" id="cssholmes-designer-config">'
                . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)
                . '</script>';
            $theme->addScript($moduleUrl . '/assets/js/designer-diagnostics.js', ['defer' => 'defer']);
            $theme->addScript($moduleUrl . '/assets/js/designer.js', ['defer' => 'defer']);
            $theme->addScript($moduleUrl . '/assets/js/gallery-designer.js', ['defer' => 'defer']);
            $theme->addStylesheet($moduleUrl . '/assets/css/gallery-designer.css', ['id' => 'cssholmes-gallery-style']);
        } catch (\Throwable $error) {
            // A bad configuration must not take the public site offline.
            $GLOBALS['xoopsLogger']->addExtra('Theme Designer', 'Saved theme configuration could not be loaded.');
        }
    }
}
