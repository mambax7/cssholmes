<?php
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
 * @copyright           XOOPS Project (https://xoops.org)
 * @license             http://www.fsf.org/copyleft/gpl.html GNU public license
 * @package             Cssholmes
 * @since               2.5.x
 * @author              kris <http://www.xoofoo.org>
 **/

include __DIR__ . '/preloads/autoloader.php';

$moduleDirName = basename(__DIR__);

$modversion['version']             = 1.1;
$modversion['module_status']       = 'Final';
$modversion['release_date']        = '2017/01/04';
$modversion['name']                = _MI_CSSHOLMES_NAME;
$modversion['description']         = _MI_CSSHOLMES_DESC;
$modversion['author']              = 'Kris - http://www.xoofoo.org';
$modversion['credits']             = 'Luke Williams - http://www.red-root.com/';
$modversion['help']                = '';
$modversion['dirname']             = $moduleDirName;
$modversion['image']               = 'assets/images/logoModule.png';
$modversion['license']             = 'GNU General Public License';
$modversion['license_url']         = 'http://www.gnu.org/licenses/gpl.html';
$modversion['official']            = 0;
$modversion['author_website_url']  = 'http://labs.xoofoo.org';
$modversion['author_website_name'] = 'Laboratoire XooFoo';

$modversion['modicons16'] = 'assets/images/icons/16';
$modversion['modicons32'] = 'assets/images/icons/32';

// About
$modversion['module_release']      = '30/06/2012';
$modversion['demo_site_url']       = '';
$modversion['demo_site_name']      = '';
$modversion['module_website_url']  = 'http://labs.xoofoo.org';
$modversion['module_website_name'] = 'Labs XooFoo';

// Admin Menu
$modversion['system_menu'] = 1;
$modversion['hasAdmin']    = 1;
$modversion['adminindex']  = 'admin/index.php';
$modversion['adminmenu']   = 'admin/menu.php';

// Menu
$modversion['hasMain'] = 0;

// Templates
$i                                          = 1;
$modversion['templates'][$i]['file']        = 'admin/' . $moduleDirName . '_admin_index.html';
$modversion['templates'][$i]['description'] = _MI_CSSHOLMES_MANAGER_INDEX_DESC;
++$i;
$modversion['templates'][$i]['file']        = 'admin/' . $moduleDirName . '_admin_about.html';
$modversion['templates'][$i]['description'] = _MI_CSSHOLMES_MANAGER_ABOUT_DESC;
