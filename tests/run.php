<?php

declare(strict_types=1);

PHP_SAPI === 'cli' || exit('CLI only');

$vendor = getenv('XOOPS_VENDOR_PATH') ?: dirname(__DIR__, 3) . '/xoops_lib/vendor';
$phpunit = $vendor . '/bin/phpunit';
if (!is_file($phpunit)) {
    fwrite(STDERR, "Shared PHPUnit not found. Set XOOPS_VENDOR_PATH to xoops_lib/vendor.\n");
    exit(1);
}

$commands = [];
$browser = in_array('--browser', $argv, true);
foreach (['review-followup', 'review-regressions', 'designer-store', 'designer-sets', 'designer-access', 'preview-context', 'designer-http'] as $test) {
    $command = [PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/bootstrap.php', __DIR__ . '/' . $test . '.php'];
    if ($browser && $test === 'designer-http') {
        $command[] = '--browser';
    }
    $commands[] = $command;
}
$commands[] = [PHP_BINARY, $phpunit, '--configuration', dirname(__DIR__) . '/phpunit.xml.dist'];
if ($browser) {
    foreach (glob(__DIR__ . '/*.cjs') as $file) {
        if (!in_array(basename($file), ['designer-roundtrip.cjs', 'designer-specimens.cjs'], true)) {
            $commands[] = ['node', $file];
        }
    }
}
foreach ($commands as $command) {
    echo PHP_EOL . '> ' . implode(' ', $command) . PHP_EOL;
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to start test process.\n");
        exit(1);
    }
    $status = proc_close($process);
    if ($status !== 0) {
        exit($status > 0 ? $status : 1);
    }
}
