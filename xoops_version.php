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
 * @since               2.5.x
 * @author              kris <https://www.xoofoo.org>
 **/
require_once __DIR__ . '/preloads/autoloader.php';

$moduleDirName = basename(__DIR__);

$modversion['version']             = '3.0.0';
$modversion['module_status']       = 'Alpha 1';
$modversion['release_date']        = '2026/03/26';
$modversion['name']                = _MI_CSSHOLMES_NAME;
$modversion['description']         = _MI_CSSHOLMES_DESC;
$modversion['author']              = 'Kris - https://www.xoofoo.org, Mamba: XOOPS Project';
$modversion['credits']             = 'Luke Williams - https://www.red-root.com/';
$modversion['help']                = '';
$modversion['dirname']             = $moduleDirName;
$modversion['image']               = 'assets/images/logoModule.png';
$modversion['license']             = 'GNU General Public License';
$modversion['license_url']         = 'https://www.gnu.org/licenses/gpl.html';
$modversion['official']            = 0;
$modversion['modicons16'] = 'assets/images/icons/16';
$modversion['modicons32'] = 'assets/images/icons/32';
$modversion['module_website_url']  = 'https://xoops.org';
$modversion['module_website_name'] = 'XOOPS';
$modversion['min_php']             = '8.4';
$modversion['min_xoops']           = '2.5.12';
$modversion['min_admin']           = '1.9';
$modversion['min_db']              = ['mysql' => '5.7'];

// Admin Menu
$modversion['system_menu'] = 1;
$modversion['hasAdmin']    = 1;
$modversion['adminindex']  = 'admin/index.php';
$modversion['adminmenu']   = 'admin/menu.php';
$modversion['onInstall']   = 'include/oninstall.php';
$modversion['onUpdate']    = 'include/onupdate.php';
$modversion['onUninstall'] = 'include/onuninstall.php';

// Menu
$modversion['hasMain'] = 1;

// ------------------- Help files ------------------- //
$modversion['help']        = 'page=help';
$modversion['helpsection'] = [
    ['name' => _MI_CSSHOLMES_OVERVIEW, 'link' => 'page=help'],
    ['name' => _MI_CSSHOLMES_DISCLAIMER, 'link' => 'page=disclaimer'],
    ['name' => _MI_CSSHOLMES_LICENSE, 'link' => 'page=license'],
    ['name' => _MI_CSSHOLMES_SUPPORT, 'link' => 'page=support'],
];

// ------------------- Templates ------------------- //
$modversion['templates'] = [
    ['file' => $moduleDirName . '_index.tpl', 'description' => ''],
    ['file' => $moduleDirName . '_testsuite.tpl', 'description' => ''],
    ['file' => $moduleDirName . '_xtf_testsuite.tpl', 'description' => ''],
];

$modversion['config'][] = [
    'name'        => 'holmes_query_key',
    'title'       => '_MI_CSSHOLMES_QUERY_KEY',
    'description' => '_MI_CSSHOLMES_QUERY_KEY_DESC',
    'formtype'    => 'textbox',
    'valuetype'   => 'text',
    'default'     => 'holmes',
];
