<?php

declare(strict_types=1);

namespace XoopsModules\Cssholmes\Designer;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Shared policy for the editor loader and its JSON endpoint. */
final class Access
{
    public static function capabilities(mixed $user, int $moduleId, callable $permission): array
    {
        if ($moduleId < 1 || !is_object($user)) {
            return ['edit' => false, 'save' => false];
        }
        $admin = $user->isAdmin($moduleId);
        $edit = $admin || ($permission('cssholmes_overlay') && $permission('cssholmes_edit'));
        return ['edit' => $edit, 'save' => $edit && ($admin || $permission('cssholmes_workbench'))];
    }
}
