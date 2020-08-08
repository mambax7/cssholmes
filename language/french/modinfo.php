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

// Module Info

// The name of this module
define('_MI_CSSHOLMES_NAME', 'Css Holmes');

// A brief description of this module
define('_MI_CSSHOLMES_DESC', 'Css Holmes est une feuille de style CSS autonome de diagnostic qui peut mettre en évidence des élements du code HTML potentiellement incorrects ou erronés.');

// Admin menu links
define('_MI_CSSHOLMES_MANAGER_INDEX', 'Index');
define('_MI_CSSHOLMES_MANAGER_INDEX_DESC', "Page d'index de l'administration du module");
define('_MI_CSSHOLMES_MANAGER_ABOUT', 'Au Sujet');
define('_MI_CSSHOLMES_MANAGER_ABOUT_DESC', 'A propos de ce module');

//Config
define('MI_CSSHOLMES_EDITOR_ADMIN', 'Editor: Admin');
define('MI_CSSHOLMES_EDITOR_ADMIN_DESC', 'Select the Editor to use by the Admin');
define('MI_CSSHOLMES_EDITOR_USER', 'Editor: User');
define('MI_CSSHOLMES_EDITOR_USER_DESC', 'Select the Editor to use by the User');

//Help
define('_MI_CSSHOLMES_DIRNAME', basename(dirname(dirname(__DIR__))));
define('_MI_CSSHOLMES_HELP_HEADER', __DIR__ . '/help/helpheader.tpl');
define('_MI_CSSHOLMES_BACK_2_ADMIN', 'Back to Administration of ');
define('_MI_CSSHOLMES_OVERVIEW', 'Overview');

//define('_MI_CSSHOLMES_HELP_DIR', __DIR__);

//help multi-page
define('_MI_CSSHOLMES_DISCLAIMER', 'Disclaimer');
define('_MI_CSSHOLMES_LICENSE', 'License');
define('_MI_CSSHOLMES_SUPPORT', 'Support');
