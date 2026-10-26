<?php

declare(strict_types=1);

PHP_SAPI === 'cli' || exit('CLI only');
define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
require dirname(__DIR__) . '/class/Designer/Access.php';

use XoopsModules\Cssholmes\Designer\Access;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$user = new class {
    public bool $admin = false;
    public function isAdmin(int $id): bool { return $this->admin; }
};
$allow = static fn(string $permission): bool => true;
$deny = static fn(string $permission): bool => false;
$check(Access::capabilities(null, 1, $allow) === ['edit' => false, 'save' => false], 'Anonymous users cannot edit');
$check(Access::capabilities($user, 0, $allow) === ['edit' => false, 'save' => false], 'Inactive module cannot authorize');
$check(Access::capabilities($user, 1, $deny) === ['edit' => false, 'save' => false], 'No implicit member access');
$previewOnly = static fn(string $permission): bool => in_array($permission, ['cssholmes_overlay', 'cssholmes_edit'], true);
$check(Access::capabilities($user, 1, $previewOnly) === ['edit' => true, 'save' => false], 'Edit permission alone never grants persistent writes');
$workbenchOnly = static fn(string $permission): bool => $permission === 'cssholmes_workbench';
$check(Access::capabilities($user, 1, $workbenchOnly) === ['edit' => false, 'save' => false], 'Workbench alone cannot open designer');
$check(Access::capabilities($user, 1, $allow) === ['edit' => true, 'save' => true], 'Combined permissions permit save');
$user->admin = true;
$check(Access::capabilities($user, 1, $deny) === ['edit' => true, 'save' => true], 'Module admins retain access');
echo "Designer access: 7 checks passed.\n";
