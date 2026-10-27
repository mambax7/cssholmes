<?php declare(strict_types=1);

/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author       XOOPS Development Team
 */
use Xmf\Request;

require_once \dirname(__DIR__, 2) . '/mainfile.php';

$moduleDirName = basename(__DIR__);
$view = strtolower(trim(Request::getString('view', '', 'GET')));
$templateMain = match ($view) {
    'html5', 'legacy', 'testsuite' => $moduleDirName . '_testsuite.tpl',
    'xtf', 'xtf-testsuite', 'widgets' => $moduleDirName . '_xtf_testsuite.tpl',
    default => $moduleDirName . '_index.tpl',
};

$GLOBALS['xoopsOption']['template_main'] = $templateMain;
require __DIR__ . '/header.php';
require XOOPS_ROOT_PATH . '/header.php';

$GLOBALS['xoopsTpl']->assign('mod_url', $helper->url());
$GLOBALS['xoopsTpl']->assign('cssholmes_html5_testsuite_url', $helper->url('index.php?view=html5'));
$GLOBALS['xoopsTpl']->assign('cssholmes_xtf_testsuite_url', $helper->url('index.php?view=xtf'));
$GLOBALS['xoopsTpl']->assign('cssholmes_admin_url', $helper->url('admin/index.php'));

require __DIR__ . '/footer.php';
