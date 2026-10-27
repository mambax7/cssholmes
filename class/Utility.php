<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes;

/*
 Utility Class Definition

 You may not change or alter any portion of this comment or credits of
 supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit
 authors.

 This program is distributed in the hope that it will be useful, but
 WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Module:  xSitemap
 *
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2001-2017 &copy; XOOPS Project
 * @author       ZySpec <zyspec@yahoo.com>
 * @author       Mamba <mambax7@gmail.com>
 * @since        File available since version 1.54
 */

/**
 * Class Utility
 */
class Utility extends Common\SysUtility
{
    //--------------- Custom module methods -----------------------------

    /**
     * Overlay and diagnostics permissions that Registered Users hold. These
     * were the install defaults before 3.0.0 Alpha 2; updates leave them in
     * place and the admin pages show a notice while they exist.
     *
     * @return string[] permission names
     */
    public static function registeredUserToolGrants(string $moduleDirName, int $moduleId): array
    {
        /** @var \XoopsGroupPermHandler $grouppermHandler */
        $grouppermHandler = \xoops_getHandler('groupperm');
        $grants = [];
        foreach (['_overlay', '_diagnose'] as $suffix) {
            if ($grouppermHandler->checkRight($moduleDirName . $suffix, 1, [\XOOPS_GROUP_USERS], $moduleId, false)) {
                $grants[] = $moduleDirName . $suffix;
            }
        }

        return $grants;
    }

    /**
     * HTML notice for the admin pages when Registered Users hold overlay or
     * diagnostics rights; '' when they do not.
     */
    public static function registeredUserToolNotice(string $moduleDirName, int $moduleId): string
    {
        if ([] === self::registeredUserToolGrants($moduleDirName, $moduleId)) {
            return '';
        }

        $text = \defined('_AM_CSSHOLMES_PERM_USERS_NOTICE')
            ? (string)\constant('_AM_CSSHOLMES_PERM_USERS_NOTICE')
            : 'Registered Users can still use the cssHolmes overlay or diagnostics. Review the permissions.';

        return '<div class="warningMsg" style="margin:0 0 1rem 0;">' . htmlspecialchars($text, ENT_QUOTES) . '</div>';
    }
}
