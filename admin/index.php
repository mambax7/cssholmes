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

include __DIR__ . '/header.php';

require_once XOOPS_ROOT_PATH . '/modules/' . $xoopsModule->getVar('dirname') . '/class/menu.php';

$menu = new Menu();
$menu->addItem('about', _AM_CSSHOLMES_MANAGER_ABOUT, 'about.php');
$xoopsTpl->assign('cssholmes_menu', $menu->_items);

$admin = new Menu();
$admin->addItem('update', _AM_CSSHOLMES_MANAGER_UPDATE, '../../system/admin.php?fct=modulesadmin&op=update&module=cssholmes');
$admin->addItem('xoofoo', _AM_CSSHOLMES_MANAGER_PREFERENCES, 'http://www.xoofoo.org');
$xoopsTpl->assign($xoopsModule->getVar('dirname') . '_admin', $admin->_items);

$xoopsTpl->assign('module_dirname', $xoopsModule->getVar('dirname'));

$xoopsTpl->display('db:admin/' . $xoopsModule->getVar('dirname') . '_admin_index.tpl');

include __DIR__ . '/footer.php';
