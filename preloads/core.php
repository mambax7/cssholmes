<?php declare(strict_types=1);

/**
 * Cssholmes module
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright           2000-2026 XOOPS Project (https://xoops.org)
 * @license            GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since               2.5.x
 * @author              kris <https://www.xoofoo.org>
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 **/
class CssholmesCorePreload extends \XoopsPreloadItem
{
    // to add PSR-4 autoloader
    /**
     * @param array $args
     */
    public static function eventCoreIncludeCommonEnd(array $args): void
    {
        require_once __DIR__ . '/autoloader.php';
    }

    public static function eventCoreHeaderAddmeta(): void
    {
        self::injectAssets('site');
    }

    public static function eventSystemClassGuiHeader(mixed $args): void
    {
        self::injectAssets('admin');
    }

    private static function injectAssets(string $scope): void
    {
        global $xoTheme, $xoopsUser;

        if (!is_object($xoTheme)) {
            return;
        }

        static $injectedScopes = [];
        if (isset($injectedScopes[$scope])) {
            return;
        }

        $moduleDirName = basename(\dirname(__DIR__));
        $moduleId = null;
        if (\function_exists('xoops_getHandler')) {
            $moduleHandler = xoops_getHandler('module');
            if (is_object($moduleHandler) && method_exists($moduleHandler, 'getByDirname')) {
                $xoopsModule = $moduleHandler->getByDirname($moduleDirName);
                if (is_object($xoopsModule) && method_exists($xoopsModule, 'getVar')) {
                    $moduleId = (int)$xoopsModule->getVar('mid');
                }
            }
        }

        $isModuleAdmin = is_object($xoopsUser)
            && (
                (null !== $moduleId && $xoopsUser->isAdmin($moduleId))
                || (null === $moduleId && $xoopsUser->isAdmin(-1))
            );
        if (!$isModuleAdmin) {
            return;
        }

        $helper   = \XoopsModules\Cssholmes\Helper::getInstance();
        $queryKey = (string)$helper->getConfig('holmes_query_key');
        if ('' === trim($queryKey)) {
            $queryKey = 'holmes';
        }

        $moduleUrl = XOOPS_URL . '/modules/' . $moduleDirName;
        $themeKey = '';
        $themeManifestPath = '';
        $themeManifestUrl = '';
        if ('admin' === $scope && isset($xoTheme->folderName) && is_string($xoTheme->folderName) && '' !== trim($xoTheme->folderName)) {
            $themeKey = trim($xoTheme->folderName);
            $themeManifestPath = XOOPS_ADMINTHEME_PATH . '/' . $themeKey . '/theme.json';
            $themeManifestUrl = XOOPS_ADMINTHEME_URL . '/' . $themeKey . '/theme.json';
        } elseif ('site' === $scope) {
            $themeKey = trim((string)($GLOBALS['xoopsConfig']['theme_set'] ?? ($xoTheme->folderName ?? '')));
            if ('' !== $themeKey) {
                $themeManifestPath = XOOPS_THEME_PATH . '/' . $themeKey . '/theme.json';
                $themeManifestUrl = XOOPS_THEME_URL . '/' . $themeKey . '/theme.json';
            }
        }
        $hasThemeManifest = '' !== $themeManifestPath && is_file($themeManifestPath);
        if (!$hasThemeManifest) {
            $themeManifestUrl = '';
        }

        $overlay   = \XoopsModules\Cssholmes\Diagnostics\OverlayConfig::fromRequest(
            \Xmf\Request::getString($queryKey, '', 'GET')
        );
        $initialProfiles = $overlay->profiles();
        if (!$hasThemeManifest) {
            $initialProfiles = array_values(array_filter(
                $initialProfiles,
                static fn (string $profile): bool => !in_array($profile, ['xtf-theme', 'xtf-widget'], true)
            ));
        }

        if ([] !== $initialProfiles) {
            foreach ($initialProfiles as $profile) {
                $stylesheetPath = 'assets/css/profiles/' . $profile . '.css';
                $xoTheme->addStylesheet($moduleUrl . '/' . $stylesheetPath);
            }
        }

        $xoTheme->addScript(
            $moduleUrl
            . '/assets/js/holmes.js?queryKey=' . rawurlencode($queryKey)
            . '&initialProfiles=' . rawurlencode(implode(',', $initialProfiles))
            . '&moduleUrl=' . rawurlencode($moduleUrl)
            . '&scope=' . rawurlencode($scope)
            . '&themeKey=' . rawurlencode($themeKey)
            . '&themeManifestUrl=' . rawurlencode($themeManifestUrl)
        );

        $injectedScopes[$scope] = true;
    }
}
