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
 * @copyright	The XOOPS Project http://sourceforge.net/projects/xoops/
 * @license             http://www.fsf.org/copyleft/gpl.html GNU public license
 * @package	Cssholmes
 * @since		2.3.0
 * @author 	kris <http://www.xoofoo.org>
 * @version	$Id: menu.php 794 2012-08-06 21:08:50Z kris_fr $
**/

// admin menu title and link

$adminmenu = array();

$i = 1;
$adminmenu[$i]["title"] = _MI_CSSHOLMES_MANAGER_INDEX;
$adminmenu[$i]["link"]  = "admin/index.php";
$adminmenu[$i]["desc"] = _MI_CSSHOLMES_MANAGER_INDEX_DESC;
$adminmenu[$i]["icon"] = "images/icons/index.png";
$i++;
$adminmenu[$i]["title"] = _MI_CSSHOLMES_MANAGER_ABOUT;
$adminmenu[$i]["link"]  = "admin/about.php";
$adminmenu[$i]["desc"] = _MI_CSSHOLMES_MANAGER_ABOUT_DESC;
$adminmenu[$i]["icon"] = "images/icons/about.png";

?>