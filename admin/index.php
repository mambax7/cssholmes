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
 * @since               2.3.0
 * @author              kris <http://www.xoofoo.org>
 **/

use XoopsModules\Cssholmes\Common;

require_once __DIR__ . '/admin_header.php';
xoops_cp_header();

$adminObject = \Xmf\Module\Admin::getInstance();
$adminObject->displayNavigation(basename(__FILE__));
$adminObject->displayIndex();

//require_once XOOPS_ROOT_PATH . '/modules/' . $xoopsModule->getVar('dirname') . '/class/menu.php';
//
//$menu = new \XoopsModules\Cssholmes\Menu();
//$menu->addItem('about', _AM_CSSHOLMES_MANAGER_ABOUT, 'about.php');
//$xoopsTpl->assign('cssholmes_menu', $menu->_items);
//
//$admin = new \XoopsModules\Cssholmes\Menu();
//$admin->addItem('update', _AM_CSSHOLMES_MANAGER_UPDATE, '../../system/admin.php?fct=modulesadmin&op=update&module=cssholmes');
//$admin->addItem('xoofoo', _AM_CSSHOLMES_MANAGER_PREFERENCES, 'http://www.xoofoo.org');
//$xoopsTpl->assign($xoopsModule->getVar('dirname') . '_admin', $admin->_items);
//
//$xoopsTpl->assign('module_dirname', $xoopsModule->getVar('dirname'));
//
//$xoopsTpl->display('db:admin/' . $xoopsModule->getVar('dirname') . '_admin_index.tpl');

require_once __DIR__ . '/admin_footer.php';
