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
 * @since		2.5.x
 * @author 		kris <http://www.xoofoo.org>
 * @version		$Id $
**/

defined("XOOPS_ROOT_PATH") or die("Restricted access");

class CssHolmesCorePreload extends XoopsPreloadItem{
    function eventCoreHeaderAddmeta()
	{
		if (CssHolmesCorePreload::isActive()) {
			global $xoTheme,$xoopsUser;
			$xoopsModule = XoopsModule::getByDirname("cssholmes");
			// Add scripts and Css if only User is xoopsAdmin
			if ( !is_object($xoopsUser) || !is_object($xoopsModule) || !$xoopsUser->isAdmin($xoopsModule->mid()) ) {
				$xoTheme->addStylesheet(XOOPS_URL."/modules/cssholmes/css/style.css");
			} else {
				$xoTheme->addStylesheet(XOOPS_URL."/modules/cssholmes/css/holmes.css");
				$xoTheme->addScript(XOOPS_URL."/modules/cssholmes/js/holmes.js");
			}
		} 
	}
	function isActive() {
		$xoopsModule = XoopsModule::getByDirname("cssholmes");
		return ($xoopsModule && $xoopsModule->getVar("isactive")) ? true : false;
	}
}

?>