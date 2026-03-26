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
define('_AM_CSSHOLMES_MANAGER_INDEX', 'Index');
define('_AM_CSSHOLMES_MANAGER_ABOUT', 'About');
define('_AM_CSSHOLMES_MANAGER_PREFERENCES', 'Settings');
define('_AM_CSSHOLMES_MANAGER_UPDATE', 'Update');

// Index.php
define(
    '_AM_CSSHOLMES_MANAGER_INDEX_TXT1',
    "<p>Holmes is stand-alone diagnostic CSS stylesheet that can highlight potentially invalid, inaccessible or erroneous HTML(5) markup by adding one class.</p><p>holmes.css is useful for checking the quality of your code (up to W3C HTML5 standards), nitpicking over ensuring markup is valid and semantic and accessility guidelines are met, and when you are tasked to fix up and debug an old, OLD website. It has a simple implementation and a mostly unobtrusive effect on your page. Not recommended for live enviroments.</p><p>Remember too that these are just guidelines: if something is flagged but you can't change it for a good reason, don't worry about it :) Also use a validator if you want to be 100% sure.</p><p>Inspired by <a class='tooltip' href='https://csswizardry.com/inuitcss/' title='Inuit Css' rel='external'>InuitCss</a>, <a class='tooltip' href='https://meyerweb.com/eric/tools/css/diagnostics/' title='Css diagnostics by Eric Meyer' rel='external'>Eric Meyer</a>, <a class='tooltip' href='https://www.nealgrosskopf.com/tech/thread.php?pid=17' title='NealGrossKopf thread' rel='external'>NealGrossKopf</a> and procrastination from coursework!</p>"
);

// About.php
define('_AM_CSSHOLMES_ABOUT_RELEASEDATE', 'Update date');
define('_AM_CSSHOLMES_ABOUT_AUTHOR', 'Author');
define('_AM_CSSHOLMES_ABOUT_CREDITS', 'Credits');
define('_AM_CSSHOLMES_ABOUT_LICENSE', 'License');
define('_AM_CSSHOLMES_ABOUT_MODULE_STATUS', 'Status');
define('_AM_CSSHOLMES_ABOUT_WEBSITE', 'Website');
define('_AM_CSSHOLMES_ABOUT_AUTHOR_NAME', 'Author name');
define('_AM_CSSHOLMES_ABOUT_CHANGELOG', 'Change Log');
define('_AM_CSSHOLMES_ABOUT_MODULE_INFO', 'Module Infos');
define('_AM_CSSHOLMES_ABOUT_AUTHOR_INFO', 'Author Infos');

define('_AM_CSSHOLMES_UPGRADEFAILED0', "Update failed - couldn't rename field '%s'");
define('_AM_CSSHOLMES_UPGRADEFAILED1', "Update failed - couldn't add new fields");
define('_AM_CSSHOLMES_UPGRADEFAILED2', "Update failed - couldn't rename table '%s'");
define('_AM_CSSHOLMES_ERROR_COLUMN', 'Could not create column in database : %s');
define('_AM_CSSHOLMES_ERROR_BAD_XOOPS', 'This module requires XOOPS %s+ (%s installed)');
define('_AM_CSSHOLMES_ERROR_BAD_PHP', 'This module requires PHP version %s+ (%s installed)');
define('_AM_CSSHOLMES_ERROR_TAG_REMOVAL', 'Could not remove tags from Tag Module');

define('_AM_CSSHOLMES_FOLDERS_DELETED_OK', 'Upload Folders have been deleted');

// Error Msgs
define('_AM_CSSHOLMES_ERROR_BAD_DEL_PATH', 'Could not delete %s directory');
define('_AM_CSSHOLMES_ERROR_BAD_REMOVE', 'Could not delete %s');
define('_AM_CSSHOLMES_ERROR_NO_PLUGIN', 'Could not load plugin');
