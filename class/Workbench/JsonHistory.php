<?php
declare(strict_types=1);

namespace XoopsModules\Cssholmes\Workbench;

/** Private history with atomic writes and a lock spanning each read/modify/write. */
final class JsonHistory
{
    private string $directory;
    private string $base;
    private bool $locked = false;

    public function __construct(private readonly string $modulePath, private readonly string $file)
    {
        if (!defined('XOOPS_VAR_PATH')) {
            throw new \RuntimeException('Private workbench storage is not configured.');
        }
        $base = realpath(XOOPS_VAR_PATH);
        if ($base === false || !is_dir($base) || !in_array($file, ['imports.json', 'scans.json'], true)) {
            throw new \RuntimeException('Invalid private workbench storage configuration.');
        }
        $this->directory = $base . '/configs/cssholmes/workbench';
        $this->base = $base;
    }

    public function locked(callable $operation): mixed
    {
        if ($this->locked) {
            return $operation();
        }
        $base = $this->base;
        foreach (['configs', 'cssholmes', 'workbench'] as $part) {
            $base .= '/' . $part;
            if (is_link($base) || (file_exists($base) && !is_dir($base))) {
                throw new \RuntimeException('Unsafe workbench storage directory.');
            }
            if (!is_dir($base) && !mkdir($base, 0700) && !is_dir($base)) {
                throw new \RuntimeException('Unable to create private workbench storage.');
            }
        }
        $lock = $this->directory . '/' . $this->file . '.lock';
        if (is_link($lock)) {
            throw new \RuntimeException('Unsafe workbench lock.');
        }
        $handle = fopen($lock, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open workbench lock.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock workbench history.');
            }
            $this->locked = true;
            $legacy = $this->modulePath . '/data/workbench/' . $this->file;
            $path = $this->directory . '/' . $this->file;
            if (is_link($path) || is_link($legacy)) {
                throw new \RuntimeException('Unsafe workbench history file.');
            }
            if (is_file($legacy)) {
                $old = $this->decode($legacy);
                if (!is_file($path)) {
                    $this->write($old);
                } elseif ($this->decode($path) !== $old) {
                    throw new \RuntimeException('Both private and legacy workbench histories exist. Back up and reconcile them before removing the public copy.');
                }
                if (!unlink($legacy)) {
                    throw new \RuntimeException('Unable to remove the migrated public workbench history.');
                }
            }
            return $operation();
        } finally {
            $this->locked = false;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function read(): array
    {
        return $this->locked(function (): array {
            $path = $this->directory . '/' . $this->file;
            return is_file($path) ? $this->decode($path) : [];
        });
    }

    private function decode(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('Unable to read workbench history.');
        }
        $records = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($records) || !array_is_list($records) || array_filter($records, 'is_array') !== $records) {
            throw new \RuntimeException('Invalid workbench history.');
        }
        return $records;
    }

    public function write(array $records): void
    {
        if (!$this->locked) {
            throw new \LogicException('Workbench writes require a transaction lock.');
        }
        $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $this->directory . '/' . $this->file;
        $temporary = tempnam($this->directory, '.history-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to prepare workbench history.');
        }
        try {
            if (!chmod($temporary, 0600) || file_put_contents($temporary, $json) !== strlen($json)
                || is_link($path) || !rename($temporary, $path)) {
                throw new \RuntimeException('Unable to save workbench history.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
