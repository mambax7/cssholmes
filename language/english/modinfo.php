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
 * @author              XOOPS Development Team, Mamba <mambax7@gmail.com>
 **/

// Module Info

// The name of this module
define('_MI_CSSHOLMES_NAME', 'cssHolmes');

// A brief description of this module
define('_MI_CSSHOLMES_DESC', 'cssHolmes is an opt-in XTF theme and widget diagnostics module with profile-based frontend overlays');

// Admin menu links
define('_MI_CSSHOLMES_MANAGER_INDEX', 'Index');
define('_MI_CSSHOLMES_MANAGER_INDEX_DESC', 'Module Administration index page');
define('_MI_CSSHOLMES_MANAGER_ABOUT', 'About');
define('_MI_CSSHOLMES_MANAGER_ABOUT_DESC', 'About this module');

//Config
define('MI_CSSHOLMES_EDITOR_ADMIN', 'Editor: Admin');
define('MI_CSSHOLMES_EDITOR_ADMIN_DESC', 'Select the Editor to use by the Admin');
define('MI_CSSHOLMES_EDITOR_USER', 'Editor: User');
define('MI_CSSHOLMES_EDITOR_USER_DESC', 'Select the Editor to use by the User');
define('_MI_CSSHOLMES_QUERY_KEY', 'Overlay query key');
define('_MI_CSSHOLMES_QUERY_KEY_DESC', 'Query-string key used to activate cssHolmes overlays for administrators, for example ?holmes=html5');

//Help
define('_MI_CSSHOLMES_DIRNAME', basename(dirname(__DIR__, 2)));
define('_MI_CSSHOLMES_HELP_HEADER', __DIR__ . '/help/helpheader.tpl');
define('_MI_CSSHOLMES_BACK_2_ADMIN', 'Back to Administration of ');
define('_MI_CSSHOLMES_OVERVIEW', 'Overview');

//define('_MI_CSSHOLMES_HELP_DIR', __DIR__);

//help multipage
define('_MI_CSSHOLMES_DISCLAIMER', 'Disclaimer');
define('_MI_CSSHOLMES_LICENSE', 'License');
define('_MI_CSSHOLMES_SUPPORT', 'Support');
