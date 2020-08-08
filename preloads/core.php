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
class CssHolmesCorePreload extends \XoopsPreloadItem
{
    // to add PSR-4 autoloader

    /**
     * @param $args
     */
    public static function eventCoreIncludeCommonEnd($args)
    {
        require_once __DIR__ . '/autoloader.php';
    }

    public static function eventCoreHeaderAddmeta()
    {
        global $xoTheme, $xoopsUser;
        $xoopsModule = XoopsModule::getByDirname('cssholmes');
        // Add scripts and Css if only User is xoopsAdmin
        if (!is_object($xoopsUser) || !is_object($xoopsModule) || !$xoopsUser->isAdmin($xoopsModule->mid())) {
            $xoTheme->addStylesheet(XOOPS_URL . '/modules/cssholmes/assets/css/style.css');
        } else {
            $xoTheme->addStylesheet(XOOPS_URL . '/modules/cssholmes/assets/css/holmes.css');
            $xoTheme->addScript(XOOPS_URL . '/modules/cssholmes/assets/js/holmes.js');
        }
    }
}
