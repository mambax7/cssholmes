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

use Xmf\Module\Admin;

require_once __DIR__ . '/admin_header.php';
xoops_cp_header();

$adminObject = Admin::getInstance();
$adminObject->displayNavigation(basename(__FILE__));

$moduleDirName = basename(dirname(__DIR__));
$moduleId = $xoopsModule->getVar('mid');

// Permission tabs — each becomes a separate XoopsGroupPermForm
$tabs = [
    'overlay' => [
        'permName'  => $moduleDirName . '_overlay',
        'title'     => _AM_CSSHOLMES_TAB_OVERLAY,
        'desc'      => _AM_CSSHOLMES_PERM_OVERLAY_DESC,
        'itemLabel' => _AM_CSSHOLMES_PERM_OVERLAY,
        'anonymous' => false,
    ],
    'diagnose' => [
        'permName'  => $moduleDirName . '_diagnose',
        'title'     => _AM_CSSHOLMES_TAB_DIAGNOSE,
        'desc'      => _AM_CSSHOLMES_PERM_DIAGNOSE_DESC,
        'itemLabel' => _AM_CSSHOLMES_PERM_DIAGNOSE,
        'anonymous' => false,
    ],
    'edit' => [
        'permName'  => $moduleDirName . '_edit',
        'title'     => _AM_CSSHOLMES_TAB_EDIT,
        'desc'      => _AM_CSSHOLMES_PERM_EDIT_DESC,
        'itemLabel' => _AM_CSSHOLMES_PERM_EDIT,
        'anonymous' => false,
    ],
    'export' => [
        'permName'  => $moduleDirName . '_export',
        'title'     => _AM_CSSHOLMES_TAB_EXPORT,
        'desc'      => _AM_CSSHOLMES_PERM_EXPORT_DESC,
        'itemLabel' => _AM_CSSHOLMES_PERM_EXPORT,
        'anonymous' => false,
    ],
    'workbench' => [
        'permName'  => $moduleDirName . '_workbench',
        'title'     => _AM_CSSHOLMES_TAB_WORKBENCH,
        'desc'      => _AM_CSSHOLMES_PERM_WORKBENCH_DESC,
        'itemLabel' => _AM_CSSHOLMES_PERM_WORKBENCH,
        'anonymous' => false,
    ],
];

// Current tab from URL parameter
$permTab = \Xmf\Request::getString('tab', 'overlay', 'GET');
if (!isset($tabs[$permTab])) {
    $permTab = 'overlay';
}
$currentTab = $tabs[$permTab];

require_once XOOPS_ROOT_PATH . '/class/xoopsform/grouppermform.php';

$permForm = new \XoopsGroupPermForm(
    $currentTab['title'],
    $moduleId,
    $currentTab['permName'],
    $currentTab['desc'],
    'admin/permissions.php?tab=' . $permTab,
    $currentTab['anonymous'],
);

$permForm->addItem(1, $currentTab['itemLabel']);

// Render heading
echo '<h3>' . _AM_CSSHOLMES_PERMISSIONS . '</h3>';

// Tab navigation
echo '<div style="margin-bottom:1rem;border-bottom:2px solid #e2e8f0;">';
foreach ($tabs as $key => $tab) {
    $isActive = ($permTab === $key);
    $style = $isActive
        ? 'display:inline-block;padding:8px 16px;border-bottom:2px solid #2563eb;margin-bottom:-2px;font-weight:600;color:#2563eb;text-decoration:none;'
        : 'display:inline-block;padding:8px 16px;color:#64748b;text-decoration:none;';
    echo '<a href="permissions.php?tab=' . htmlspecialchars($key, ENT_QUOTES) . '" style="' . $style . '">'
       . htmlspecialchars($tab['title'], ENT_QUOTES) . '</a>';
}
echo '</div>';

// Description
echo '<p style="margin-bottom:1rem;color:#475569;">' . htmlspecialchars($currentTab['desc'], ENT_QUOTES) . '</p>';

// Render the permission form
$permForm->display();

require_once __DIR__ . '/admin_footer.php';
