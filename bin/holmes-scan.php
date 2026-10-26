<?php declare(strict_types=1);

/**
 * cssHolmes theme scan for the command line.
 *
 * Runs the theme analyzers on a theme folder without a XOOPS installation:
 *
 *     php modules/cssholmes/bin/holmes-scan.php themes/mytheme
 *     php modules/cssholmes/bin/holmes-scan.php --format=json --fail-on=warning themes/mytheme
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */

if ('cli' !== PHP_SAPI) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// The module's classes only; nothing from XOOPS core is loaded.
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'XoopsModules\\Cssholmes\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $file = dirname(__DIR__) . '/class/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

exit((new XoopsModules\Cssholmes\Cli\ScanCommand())->run(array_slice($argv, 1)));
