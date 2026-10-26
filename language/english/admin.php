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
 * @license            GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              kris <https://www.xoofoo.org>
 * @author              XOOPS Development Team, Mamba <mambax7@gmail.com> 
 **/
define('_AM_CSSHOLMES_MANAGER_INDEX', 'Index');
define('_AM_CSSHOLMES_MANAGER_ABOUT', 'About');
define('_AM_CSSHOLMES_MANAGER_PREFERENCES', 'Settings');
define('_AM_CSSHOLMES_MANAGER_UPDATE', 'Update');

// Permissions
define('_AM_CSSHOLMES_PERMISSIONS', 'Permissions');

define('_AM_CSSHOLMES_TAB_OVERLAY', 'Overlay');
define('_AM_CSSHOLMES_PERM_OVERLAY', 'Use Overlay');
define('_AM_CSSHOLMES_PERM_OVERLAY_DESC', 'Permission to see the toolbar, toggle profiles, and use inspect/measure/widget/slot tools.');

define('_AM_CSSHOLMES_TAB_DIAGNOSE', 'Diagnostics');
define('_AM_CSSHOLMES_PERM_DIAGNOSE', 'Use Diagnostics');
define('_AM_CSSHOLMES_PERM_DIAGNOSE_DESC', 'Permission to use overflow detection, z-index overlay, grid overlay, and color blindness simulation.');

define('_AM_CSSHOLMES_TAB_EDIT', 'Editing');
define('_AM_CSSHOLMES_PERM_EDIT', 'Use Editors');
define('_AM_CSSHOLMES_PERM_EDIT_DESC', 'Permission to use the token editor, typography editor, text editor, and undo. Edits are local previews only.');

define('_AM_CSSHOLMES_TAB_EXPORT', 'Export');
define('_AM_CSSHOLMES_PERM_EXPORT', 'Export Data');
define('_AM_CSSHOLMES_PERM_EXPORT_DESC', 'Permission to copy inspection JSON and export structured payloads for the admin workbench.');

define('_AM_CSSHOLMES_TAB_WORKBENCH', 'Workbench');
define('_AM_CSSHOLMES_PERM_WORKBENCH', 'Admin Workbench');
define('_AM_CSSHOLMES_PERM_WORKBENCH_DESC', 'Permission to use the admin scanner, theme/widget scans, import review, and patch drafts. Admin-only by default.');

define('_AM_CSSHOLMES_PERM_USERS_NOTICE', 'Registered Users can still use the cssHolmes overlay or diagnostics. Earlier versions granted this on install; since 3.0.0 Alpha 2 new installs grant every tool to Webmasters only, and updates leave existing permissions unchanged. Remove the Registered Users ticks under Permissions unless you want members to see the toolbar.');

//define('_AM_CSSHOLMES_MANAGER_FOOTER', "<div class='right smallsmall italic pad10'>Cssholmes is maintened by <a class='tooltip' rel='external' href='https://labs.XooFoo.org' title='VisitXooFoo Laboratory'>Labs.XooFoo.org</a></div>");

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

// Theme set library
define('_AM_CSSHOLMES_SETS_TITLE', 'Theme sets');
define('_AM_CSSHOLMES_SETS_INTRO', 'Browse named style sets by installed theme. Preview a set before activating it for site visitors.');
define('_AM_CSSHOLMES_SETS_JAVASCRIPT', 'Enable JavaScript to browse the theme set library.');
define('_AM_CSSHOLMES_SETS_LOADING', 'Loading…');
define('_AM_CSSHOLMES_SETS_READY', 'Library ready. Preview and import do not activate theme styles.');
define('_AM_CSSHOLMES_SETS_THEME', 'Theme');
define('_AM_CSSHOLMES_SETS_ALL_THEMES', 'All themes');
define('_AM_CSSHOLMES_SETS_FILTER', 'Find a set');
define('_AM_CSSHOLMES_SETS_FILTER_HINT', 'Filter by set name');
define('_AM_CSSHOLMES_SETS_SORT', 'Sort sets');
define('_AM_CSSHOLMES_SETS_SORT_NAME', 'Name, A–Z');
define('_AM_CSSHOLMES_SETS_SORT_MODIFIED', 'Recently modified');
define('_AM_CSSHOLMES_SETS_SORT_LIKES', 'Most liked');
define('_AM_CSSHOLMES_SETS_RELOAD', 'Reload library');
define('_AM_CSSHOLMES_SETS_TABLE_CAPTION', 'Named sets by theme');
define('_AM_CSSHOLMES_SETS_NAME', 'Set name');
define('_AM_CSSHOLMES_SETS_AUTHOR', 'Author');
define('_AM_CSSHOLMES_SETS_CREATED', 'Date created');
define('_AM_CSSHOLMES_SETS_MODIFIED', 'Date modified');
define('_AM_CSSHOLMES_SETS_LIKES', 'Likes');
define('_AM_CSSHOLMES_SETS_ACTIONS', 'Actions');
define('_AM_CSSHOLMES_SETS_PREVIEW', 'Preview');
define('_AM_CSSHOLMES_SETS_PREVIEW_NEW_TAB', 'Preview in a new tab');
define('_AM_CSSHOLMES_SETS_EXPORT', 'Export JSON');
define('_AM_CSSHOLMES_SETS_EXPORTED', 'Set exported.');
define('_AM_CSSHOLMES_SETS_ACTIVATE', 'Activate');
define('_AM_CSSHOLMES_SETS_ACTIVATE_CONFIRM', 'Activate {name} for {theme}? This replaces the active style overrides shown to site visitors.');
define('_AM_CSSHOLMES_SETS_ACTIVATED', 'Set activated.');
define('_AM_CSSHOLMES_SETS_DELETE', 'Delete');
define('_AM_CSSHOLMES_SETS_DELETE_CONFIRM', 'Delete the named set {name}? This cannot be undone. Active theme styles are kept.');
define('_AM_CSSHOLMES_SETS_DELETED', 'Named set deleted.');
define('_AM_CSSHOLMES_SETS_LIKE', 'Like');
define('_AM_CSSHOLMES_SETS_UNLIKE', 'Unlike');
define('_AM_CSSHOLMES_SETS_LIKED', 'Like updated.');
define('_AM_CSSHOLMES_SETS_RESET', 'Reset to original');
define('_AM_CSSHOLMES_SETS_RESET_CONFIRM', 'Reset {theme} to its original theme styles? Active style overrides will be removed. Named sets are kept.');
define('_AM_CSSHOLMES_SETS_RESET_DONE', 'Original theme styles restored. Named sets are kept.');
define('_AM_CSSHOLMES_SETS_CANCELLED', 'Action cancelled.');
define('_AM_CSSHOLMES_SETS_EMPTY', 'No named sets for this theme.');
define('_AM_CSSHOLMES_SETS_NO_MATCHES', 'No sets match this name.');
define('_AM_CSSHOLMES_SETS_NO_THEMES', 'No installed front-end themes are available.');
define('_AM_CSSHOLMES_SETS_PALETTES', 'Starter palettes');
define('_AM_CSSHOLMES_SETS_PALETTE_NOTE', 'These built-in color pairs are a starting point. Contrast results describe only the listed pairs, not whole-theme WCAG conformance. Preview on your target theme and check its content before saving.');
define('_AM_CSSHOLMES_SETS_PALETTE_THEME', 'Preview palette on theme');
define('_AM_CSSHOLMES_SETS_PAIR_PASS', 'passes 4.5:1 for normal text');
define('_AM_CSSHOLMES_SETS_PAIR_FAIL', 'below 4.5:1 for normal text');
define('_AM_CSSHOLMES_SETS_IMPORT_TITLE', 'Import a named set');
define('_AM_CSSHOLMES_SETS_IMPORT_NOTE', 'Choose a target theme and review the validated import. Importing creates a named set without activating it. Maximum JSON file size: 128 KiB.');
define('_AM_CSSHOLMES_SETS_IMPORT_THEME', 'Import into theme');
define('_AM_CSSHOLMES_SETS_IMPORT_NAME', 'Set name (optional)');
define('_AM_CSSHOLMES_SETS_JSON_FILE', 'JSON file');
define('_AM_CSSHOLMES_SETS_REVIEW_IMPORT', 'Review import');
define('_AM_CSSHOLMES_SETS_IMPORT_CONFIRM', 'Import named set');
define('_AM_CSSHOLMES_SETS_RULE_COUNT', '{count} validated style rules.');
define('_AM_CSSHOLMES_SETS_REVIEW_READY', 'Review the rules and warnings, then import the named set.');
define('_AM_CSSHOLMES_SETS_IMPORTED', 'Named set imported. It has not been activated.');
define('_AM_CSSHOLMES_SETS_CHOOSE_IMPORT', 'Choose a JSON file and a target theme.');
define('_AM_CSSHOLMES_SETS_TOO_LARGE', 'Choose a JSON file no larger than 128 KiB.');
define('_AM_CSSHOLMES_SETS_INVALID_JSON', 'The selected file is not valid JSON.');
define('_AM_CSSHOLMES_SETS_REQUEST_FAILED', 'Could not load or save theme sets. Reload the library and try again.');
define('_AM_CSSHOLMES_SETS_CONFLICT', 'Settings changed elsewhere. Reload and review before trying again.');
define('_AM_CSSHOLMES_SETS_SHOW_SAMPLES', 'Show sample rows');
define('_AM_CSSHOLMES_SETS_SAMPLE_NOTE', 'Sample rows for empty themes use Admin and illustrative dates/likes. They are not saved or published. Edit starts a draft; Clone creates a real set under your account.');
define('_AM_CSSHOLMES_SETS_SAMPLE_NAME', 'Original defaults');
define('_AM_CSSHOLMES_SETS_SAMPLE', 'Sample');
define('_AM_CSSHOLMES_SETS_SAMPLE_READONLY', 'This is an unsaved sample. Clone it to create a real set.');
define('_AM_CSSHOLMES_SETS_EDIT', 'Edit');
define('_AM_CSSHOLMES_SETS_EDIT_NEW_TAB', 'Edit in the designer without publishing changes');
define('_AM_CSSHOLMES_SETS_CLONE', 'Clone');
define('_AM_CSSHOLMES_SETS_CLONE_PROMPT', 'Name for the cloned set');
define('_AM_CSSHOLMES_SETS_COPY_NAME', '{name} copy');
define('_AM_CSSHOLMES_SETS_CLONED', 'Set cloned. It has not been activated.');
define('_AM_CSSHOLMES_SETS_NAME_REQUIRED', 'Enter a name for the new set.');
