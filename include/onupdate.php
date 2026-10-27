<?php declare(strict_types=1);
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author       XOOPS Development Team
 */

use XoopsModules\Cssholmes as Module;

if ((!defined('XOOPS_ROOT_PATH')) || !($GLOBALS['xoopsUser'] instanceof \XoopsUser)
    || !$GLOBALS['xoopsUser']->isAdmin()) {
    exit('Restricted access' . PHP_EOL);
}

/**
 * Prepares system prior to attempting to install module
 * @param \XoopsModule $module {@link XoopsModule}
 *
 * @return bool true if ready to install, false if not
 */
function xoops_module_pre_update_cssholmes(\XoopsModule $module)
{
    $moduleDirName = \basename(\dirname(__DIR__));
    /** @var Module\Helper $helper */
    /** @var Module\Utility $utility */
    $helper  = Module\Helper::getInstance();
    $utility = new Module\Utility();

    $xoopsSuccess = $utility::checkVerXoops($module);
    $phpSuccess   = $utility::checkVerPhp($module);

    return $xoopsSuccess && $phpSuccess;
}

/**
 * Performs tasks required during update of the module
 * @param \XoopsModule $module {@link XoopsModule}
 * @param null         $previousVersion
 *
 * @return bool true if update successful, false if not
 */
function xoops_module_update_cssholmes(\XoopsModule $module, $previousVersion = null)
{
    $moduleDirName      = \basename(\dirname(__DIR__));
    $moduleDirNameUpper = \mb_strtoupper($moduleDirName);

    /** @var Module\Helper $helper */ /** @var Module\Utility $utility */
    /** @var Module\Common\Configurator $configurator */
    $helper       = Module\Helper::getInstance();
    $utility      = new Module\Utility();
    $configurator = new Module\Common\Configurator();

    try {
        (new Module\Workbench\ImportStore(dirname(__DIR__)))->all();
        (new Module\Workbench\ScanStore(dirname(__DIR__)))->all();
    } catch (\Throwable $error) {
        $module->setErrors('Unable to migrate workbench history to private storage. Back up and reconcile any legacy and private history files before retrying.');
        return false;
    }

    // Ensure admin group always has all permissions (runs on every update)
    ensureAdminPermissions($moduleDirName, (int) $module->getVar('mid'));

    // Since 3.0.0 Alpha 2 a new install grants the overlay and diagnostics to
    // Webmasters only. Existing sites keep their permissions: an update never
    // removes rights an administrator may have chosen. It only reports the
    // Registered Users grants that came from the old defaults.
    $helper->loadLanguage('admin');
    reportRegisteredUserGrants($module, $moduleDirName);

    if ($previousVersion < 240) {
        //delete old HTML templates
        if (count($configurator->templateFolders) > 0) {
            foreach ($configurator->templateFolders as $folder) {
                $templateFolder = $GLOBALS['xoops']->path('modules/' . $moduleDirName . $folder);
                if (is_dir($templateFolder)) {
                    $templateList = array_diff(scandir($templateFolder, SCANDIR_SORT_NONE), ['..', '.']);
                    foreach ($templateList as $k => $v) {
                        $fileInfo = new \SplFileInfo($templateFolder . $v);
                        if ('html' === $fileInfo->getExtension() && 'index.html' !== $fileInfo->getFilename()) {
                            if (is_file($templateFolder . $v)) {
                                unlink($templateFolder . $v);
                            }
                        }
                    }
                }
            }
        }

        //  ---  DELETE OLD FILES ---------------
        if (count($configurator->oldFiles) > 0) {
            //    foreach (array_keys($GLOBALS['uploadFolders']) as $i) {
            foreach (array_keys($configurator->oldFiles) as $i) {
                $tempFile = $GLOBALS['xoops']->path('modules/' . $moduleDirName . $configurator->oldFiles[$i]);
                if (is_file($tempFile)) {
                    unlink($tempFile);
                }
            }
        }

        //  ---  DELETE OLD FOLDERS ---------------
        xoops_load('XoopsFile');
        if (count($configurator->oldFolders) > 0) {
            //    foreach (array_keys($GLOBALS['uploadFolders']) as $i) {
            foreach (array_keys($configurator->oldFolders) as $i) {
                $tempFolder = $GLOBALS['xoops']->path('modules/' . $moduleDirName . $configurator->oldFolders[$i]);
                /** @var XoopsObjectHandler $folderHandler */
                $folderHandler = XoopsFile::getHandler('folder', $tempFolder);
                $folderHandler->delete($tempFolder);
            }
        }

        //  ---  CREATE FOLDERS ---------------
        if (count($configurator->uploadFolders) > 0) {
            //    foreach (array_keys($GLOBALS['uploadFolders']) as $i) {
            foreach (array_keys($configurator->uploadFolders) as $i) {
                $utility::createFolder($configurator->uploadFolders[$i]);
            }
        }

        //  ---  COPY blank.png FILES ---------------
        if (count($configurator->copyBlankFiles) > 0) {
            $file = \dirname(__DIR__) . '/assets/images/blank.png';
            foreach (array_keys($configurator->copyBlankFiles) as $i) {
                $dest = $configurator->copyBlankFiles[$i] . '/blank.png';
                $utility::copyFile($file, $dest);
            }
        }

        //delete .html entries from the tpl table
        $sql = 'DELETE FROM ' . $GLOBALS['xoopsDB']->prefix('tplfile') . " WHERE `tpl_module` = '" . $module->getVar('dirname', 'n') . '\' AND `tpl_file` LIKE \'%.html%\'';
        $GLOBALS['xoopsDB']->queryF($sql);

        /** @var \XoopsGroupPermHandler $grouppermHandler */
        $grouppermHandler = xoops_getHandler('groupperm');

        return $grouppermHandler->deleteByModule($module->getVar('mid'), 'item_read');
    }

    return true;
}

/**
 * Ensure the admin group has all cssHolmes permissions.
 *
 * Runs on every update so admin permissions are never accidentally lost,
 * even if the permissions table was cleared or a new permission was added.
 */
function ensureAdminPermissions(string $moduleDirName, int $moduleId): void
{
    /** @var \XoopsGroupPermHandler $grouppermHandler */
    $grouppermHandler = xoops_getHandler('groupperm');

    $permissionNames = [
        $moduleDirName . '_overlay',
        $moduleDirName . '_diagnose',
        $moduleDirName . '_edit',
        $moduleDirName . '_export',
        $moduleDirName . '_workbench',
        $moduleDirName . '_view',
    ];

    foreach ($permissionNames as $permName) {
        if (!$grouppermHandler->checkRight($permName, 1, [XOOPS_GROUP_ADMIN], $moduleId)) {
            $grouppermHandler->addRight($permName, 1, XOOPS_GROUP_ADMIN, $moduleId);
        }
    }
}

/**
 * Add a note to the update log when Registered Users still hold the overlay or
 * diagnostics permission (the defaults before 3.0.0 Alpha 2). Nothing is
 * changed; the administrator decides in Admin > cssHolmes > Permissions.
 */
function reportRegisteredUserGrants(\XoopsModule $module, string $moduleDirName): void
{
    $grants = registeredUserGrants($moduleDirName, (int) $module->getVar('mid'));
    if ([] === $grants) {
        return;
    }

    $message = \defined('_AM_CSSHOLMES_PERM_USERS_NOTICE')
        ? \constant('_AM_CSSHOLMES_PERM_USERS_NOTICE')
        : 'Registered Users can still use the cssHolmes overlay or diagnostics (the defaults before 3.0.0 Alpha 2). '
          . 'New installs grant them to Webmasters only. Review Admin > cssHolmes > Permissions.';
    $module->setMessage($message . ' (' . implode(', ', $grants) . ')');
}

/**
 * The overlay/diagnostics permissions Registered Users hold.
 *
 * @return string[] permission names
 */
function registeredUserGrants(string $moduleDirName, int $moduleId): array
{
    /** @var \XoopsGroupPermHandler $grouppermHandler */
    $grouppermHandler = xoops_getHandler('groupperm');
    $grants = [];
    foreach (['_overlay', '_diagnose'] as $suffix) {
        $criteria = new \CriteriaCompo(new \Criteria('gperm_modid', $moduleId));
        $criteria->add(new \Criteria('gperm_name', $moduleDirName . $suffix));
        $criteria->add(new \Criteria('gperm_groupid', XOOPS_GROUP_USERS));
        $criteria->add(new \Criteria('gperm_itemid', 1));
        if ($grouppermHandler->getCount($criteria) > 0) {
            $grants[] = $moduleDirName . $suffix;
        }
    }

    return $grants;
}
