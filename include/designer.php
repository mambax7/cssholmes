<?php

declare(strict_types=1);

use XoopsModules\Cssholmes\Designer\Access;
use XoopsModules\Cssholmes\Designer\ThemeConfigStore;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');
require_once dirname(__DIR__) . '/preloads/autoloader.php';

function cssholmes_designer_store(): ThemeConfigStore
{
    return new ThemeConfigStore(XOOPS_ROOT_PATH . '/themes', XOOPS_VAR_PATH . '/configs/cssholmes/designer');
}

function cssholmes_designer_access(): array
{
    $module = xoops_getHandler('module')->getByDirname('cssholmes');
    $moduleId = is_object($module) && $module->getVar('isactive') ? (int) $module->getVar('mid') : 0;
    $user = $GLOBALS['xoopsUser'] ?? null;
    return Access::capabilities($user, $moduleId, static function (string $permission) use ($user, $moduleId): bool {
        return xoops_getHandler('groupperm')->checkRight($permission, 1, $user->getGroups(), $moduleId);
    });
}

function cssholmes_designer_labels(): array
{
    xoops_loadLanguage('main', 'cssholmes');
    xoops_loadLanguage('diagnostics', 'cssholmes');
    $prefix = '_MD_CSSHOLMES_DESIGNER_';
    $labels = [];
    foreach (get_defined_constants() as $key => $value) {
        if (str_starts_with($key, $prefix) && is_string($value)) {
            $labels[strtolower(substr($key, strlen($prefix)))] = $value;
        }
    }
    return $labels;
}
