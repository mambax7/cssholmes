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
 * @license             GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since               2.3.0
 * @author              kris <https://www.xoofoo.org>
 **/

// admin menu title and link

use Xmf\Module\Admin;
use XoopsModules\Cssholmes\{
    Helper
};

require \dirname(__DIR__) . '/preloads/autoloader.php';

$moduleDirName      = \basename(\dirname(__DIR__));
$moduleDirNameUpper = \mb_strtoupper($moduleDirName);

/** @var Helper $helper */
$helper = Helper::getInstance();
$helper->loadLanguage('common');
$helper->loadLanguage('feedback');

$pathIcon32    = Admin::menuIconPath('');
$pathModIcon32 = XOOPS_URL . '/modules/' . $moduleDirName . '/assets/images/icons/32/';
if (is_object($helper->getModule()) && false !== $helper->getModule()->getInfo('modicons32')) {
    $pathModIcon32 = $helper->url($helper->getModule()->getInfo('modicons32'));
}

$adminmenu[] = [
    'title' => _MI_CSSHOLMES_MANAGER_INDEX,
    'link'  => 'admin/index.php',
    'desc'  => _MI_CSSHOLMES_MANAGER_INDEX_DESC,
    'icon'  => $pathIcon32 . '/home.png',
];

$adminmenu[] = [
    'title' => _MI_CSSHOLMES_MANAGER_ABOUT,
    'link'  => 'admin/about.php',
    'desc'  => _MI_CSSHOLMES_MANAGER_ABOUT_DESC,
    'icon'  => $pathIcon32 . '/about.png',
];
