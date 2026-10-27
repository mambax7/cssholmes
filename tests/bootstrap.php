<?php

declare(strict_types=1);

// No mainfile.php: isolated unit tests must never open a site database or session.
spl_autoload_register(static function (string $class): void {
    $prefix = 'XoopsModules\\Cssholmes\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/class/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
