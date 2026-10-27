<?php

declare(strict_types=1);

namespace XoopsModules\Cssholmes\Designer;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

if (!defined('XOOPS_ROOT_PATH')) {
    http_response_code(404);
    exit;
}

final class ThemeConfigStore
{
    private string $themesRoot;
    private string $configRoot;

    public function __construct(string $themesRoot, string $configRoot)
    {
        $this->themesRoot = $this->absolutePath($themesRoot);
        $this->configRoot = $this->absolutePath($configRoot);
        $themesPath = $this->resolvedPath($this->themesRoot);
        $configPath = $this->resolvedPath($this->configRoot);
        if ($configPath === $themesPath || str_starts_with($configPath, $themesPath . '/')) {
            throw new RuntimeException('Theme style storage must be outside theme folders.');
        }
    }

    /**
     * Read-only on public page requests, including the empty baseline.
     * @throws InvalidArgumentException Unknown theme.
     * @throws RuntimeException Invalid or inaccessible stored styles.
     */
    public function load(string $theme): array
    {
        $this->validateTheme($theme);
        $this->checkDirectory($this->configRoot, false);
        [$file, $lock] = $this->paths($theme);
        if (!$this->exists($file)) {
            return $this->state($theme, ['version' => 1, 'rules' => [], 'previous' => null]);
        }
        $this->checkFile($lock);
        $handle = $this->io(static fn() => fopen($lock, 'rb'));
        try {
            $this->io(static fn() => flock($handle, LOCK_SH));
            return $this->state($theme, $this->read($file));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Atomically replaces the full validated rule set after a revision check.
     * Labels are plain UTF-8 text (160 bytes); empty declaration sets are omitted.
     * @throws InvalidArgumentException Invalid theme, metadata, selector or declaration.
     * @throws ConflictException Stale revision.
     * @throws RuntimeException Storage failure.
     */
    public function save(string $theme, array $rules, string $revision): array
    {
        return $this->update($theme, $revision, $this->validateRules($rules));
    }

    /**
     * Consumes the single rollback slot; restore never builds another undo entry.
     * @throws InvalidArgumentException Unknown theme or no previous styles.
     * @throws ConflictException Stale revision.
     * @throws RuntimeException Storage failure.
     */
    public function restore(string $theme, string $revision): array
    {
        return $this->update($theme, $revision, null);
    }

    /** Registry consumed by the visual editor; validation also uses this single allowlist. */
    public static function properties(): array
    {
        $properties = [];
        foreach (['color', 'background-color', 'border-color'] as $property) {
            $properties[$property] = ['label' => ucwords(str_replace('-', ' ', $property)), 'type' => 'color'];
        }
        foreach (['font-size', 'letter-spacing', 'width', 'height', 'max-width', 'min-width', 'gap', 'border-width', 'border-radius'] as $property) {
            $properties[$property] = ['label' => ucwords(str_replace('-', ' ', $property)), 'type' => 'length'];
        }
        foreach (['margin', 'padding'] as $prefix) {
            foreach (['top', 'right', 'bottom', 'left', 'inline-start', 'inline-end', 'block-start', 'block-end'] as $side) {
                $property = $prefix . '-' . $side;
                $properties[$property] = ['label' => ucwords(str_replace('-', ' ', $property)), 'type' => 'length'];
            }
        }
        $properties['line-height'] = ['label' => 'Line Height', 'type' => 'number'];
        $properties['font-family'] = ['label' => 'Font Family', 'type' => 'font'];
        $enums = [
            'font-weight' => ['normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900'],
            'text-align' => ['start', 'end', 'left', 'right', 'center', 'justify'],
            'text-transform' => ['none', 'uppercase', 'lowercase', 'capitalize'],
            'text-decoration' => ['none', 'underline', 'overline', 'line-through'],
            'font-style' => ['normal', 'italic', 'oblique'],
            'border-style' => ['none', 'solid', 'dashed', 'dotted', 'double'],
            'display' => ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'none'],
            'align-items' => ['normal', 'stretch', 'start', 'end', 'center', 'flex-start', 'flex-end', 'baseline'],
            'justify-content' => ['normal', 'start', 'end', 'center', 'flex-start', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
        ];
        foreach ($enums as $property => $options) {
            $properties[$property] = ['label' => ucwords(str_replace('-', ' ', $property)), 'type' => 'enum', 'options' => $options];
        }
        return $properties;
    }

    /**
     * Emits CSS for real theme elements, without persisting or trusting raw CSS.
     * @throws InvalidArgumentException Invalid rule set.
     */
    public function compile(array $rules): string
    {
        $rules = $this->validateRules($rules);
        $breakpoints = ['all' => '', 'mobile' => '(max-width: 575px)', 'tablet' => '(min-width: 576px) and (max-width: 991px)', 'desktop' => '(min-width: 992px)'];
        $css = '';
        foreach ($breakpoints as $breakpoint => $media) {
            foreach (['all', 'light', 'dark'] as $mode) {
                foreach ($rules as $rule) {
                    if ($rule['breakpoint'] !== $breakpoint || $rule['mode'] !== $mode) {
                        continue;
                    }
                    $selector = $rule['selector'] . match ($rule['state']) {
                        'hover' => ':hover', 'focus' => ':focus-visible', default => '',
                    };
                    $declarations = '';
                    foreach ($rule['declarations'] as $property => $value) {
                        $declarations .= '  ' . $property . ': ' . $value . " !important;\n";
                    }
                    $block = $selector . " {\n" . $declarations . "}\n";
                    if ($mode !== 'all') {
                        $explicit = ':root:is([data-bs-theme="' . $mode . '"], :not([data-bs-theme="light"]):not([data-bs-theme="dark"])[data-theme="' . $mode . '"])';
                        $automatic = ':root:not([data-bs-theme="light"]):not([data-bs-theme="dark"]):not([data-theme="light"]):not([data-theme="dark"])';
                        $block = $explicit . ' :is(' . $selector . '), ' . $explicit . ':is(' . $selector . ") {\n" . $declarations . "}\n";
                        $block .= '@media (prefers-color-scheme: ' . $mode . ") {\n" . $automatic . ' :is(' . $selector . '), ' . $automatic . ':is(' . $selector . ") {\n" . $declarations . "}\n}\n";
                    }
                    $css .= $media === '' ? $block : '@media ' . $media . " {\n" . $block . "}\n";
                }
            }
        }
        return $css;
    }

    /** Shared validation for portable sets; never reads or writes theme files. */
    public function normalizeRules(array $rules): array
    {
        return $this->validateRules($rules);
    }

    private function validateRules(array $rules): array
    {
        if (!array_is_list($rules) || count($rules) > 256) {
            throw new InvalidArgumentException('Invalid designer rule list.');
        }
        $result = [];
        $seen = [];
        $registry = self::properties();
        foreach ($rules as $rule) {
            if (!is_array($rule) || array_diff(array_keys($rule), ['id', 'selector', 'label', 'mode', 'breakpoint', 'state', 'declarations']) !== []) {
                throw new InvalidArgumentException('Unknown designer rule field.');
            }
            foreach (['selector', 'label', 'mode', 'breakpoint', 'state'] as $field) {
                if (!isset($rule[$field]) || !is_string($rule[$field])) {
                    throw new InvalidArgumentException('Invalid designer rule field.');
                }
            }
            $selector = trim($rule['selector']);
            $suffix = '[.#][a-zA-Z_][a-zA-Z0-9_-]*';
            $compound = '(?:[a-zA-Z][a-zA-Z0-9-]*(?:' . $suffix . ')*|(?:' . $suffix . ')+)';
            if ($selector === '' || strlen($selector) > 240 || !preg_match('/^' . $compound . '(?:(?:\s*>\s*|\s+)' . $compound . '){0,3}$/D', $selector)
                || preg_match('/[.#](?:xs-[\w-]*|xstyles[\w-]*|cssholmes-[\w-]*|comment(?:[_-]?\d+|[_-][\w-]+))\b/i', $selector)) {
                throw new InvalidArgumentException('Unsupported theme selector.');
            }
            $selector = preg_replace('/\s*>\s*/', ' > ', preg_replace('/\s+/', ' ', $selector));
            if (strlen($rule['label']) > 160 || preg_match('/[<\x00-\x1f\x7f]/u', $rule['label']) !== 0
                || !in_array($rule['mode'], ['all', 'light', 'dark'], true)
                || !in_array($rule['breakpoint'], ['all', 'mobile', 'tablet', 'desktop'], true)
                || !in_array($rule['state'], ['normal', 'hover', 'focus'], true)
                || !isset($rule['declarations']) || !is_array($rule['declarations']) || count($rule['declarations']) > count($registry)) {
                throw new InvalidArgumentException('Invalid designer rule metadata.');
            }
            $declarations = [];
            foreach ($rule['declarations'] as $property => $value) {
                if (!isset($registry[$property]) || !is_string($value) || !$this->validValue($property, trim($value), $registry[$property])) {
                    throw new InvalidArgumentException('Unsupported style property or value.');
                }
                $declarations[$property] = trim($value);
            }
            if ($declarations === []) {
                continue;
            }
            ksort($declarations);
            $id = substr(hash('sha256', implode('|', [$selector, $rule['mode'], $rule['breakpoint'], $rule['state']])), 0, 16);
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate designer rule.');
            }
            $seen[$id] = true;
            $result[] = ['id' => $id, 'selector' => $selector, 'label' => trim($rule['label']), 'mode' => $rule['mode'],
                'breakpoint' => $rule['breakpoint'], 'state' => $rule['state'], 'declarations' => $declarations];
        }
        return $result;
    }

    private function validValue(string $property, string $value, array $definition): bool
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[;{}@!\\\\<>\x00-\x1f\x7f]/', $value)) {
            return false;
        }
        if ($definition['type'] === 'enum') {
            return in_array($value, $definition['options'], true);
        }
        if ($definition['type'] === 'font') {
            return preg_match('/^(?:[A-Za-z][A-Za-z0-9 -]*|"[A-Za-z][A-Za-z0-9 -]*"|\'[A-Za-z][A-Za-z0-9 -]*\')(?:\s*,\s*(?:[A-Za-z][A-Za-z0-9 -]*|"[A-Za-z][A-Za-z0-9 -]*"|\'[A-Za-z][A-Za-z0-9 -]*\'))*$/D', $value) === 1;
        }
        if ($definition['type'] === 'color') {
            if ($value === 'transparent' || preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/iD', $value)) {
                return true;
            }
            if (!preg_match('/^(rgb|rgba)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0(?:\.\d+)?|1(?:\.0+)?|\.\d+))?\s*\)$/D', $value, $match)) {
                return false;
            }
            return (int) $match[2] <= 255 && (int) $match[3] <= 255 && (int) $match[4] <= 255
                && (($match[1] === 'rgba') === isset($match[5]));
        }
        if ($value === 'normal' && in_array($property, ['line-height', 'letter-spacing'], true)) {
            return true;
        }
        if ($value === 'auto' && (str_starts_with($property, 'margin-') || in_array($property, ['width', 'height', 'min-width'], true))) {
            return true;
        }
        if ($value === 'none' && $property === 'max-width') {
            return true;
        }
        $negative = str_starts_with($property, 'margin-') || $property === 'letter-spacing' ? '-?' : '';
        $units = $property === 'line-height' ? '(?:px|rem|em|%|vh|vw)?' : '(?:px|rem|em|%|vh|vw)';
        return $value === '0' || preg_match('/^' . $negative . '(?:\d{1,4}(?:\.\d{1,4})?|\.\d{1,4})' . $units . '$/D', $value) === 1;
    }

    private function update(string $theme, string $revision, ?array $rules): array
    {
        $this->validateTheme($theme);
        $this->checkDirectory($this->configRoot, true);
        [$file, $lock] = $this->paths($theme);
        if ($this->exists($lock)) {
            $this->checkFile($lock);
        }
        $handle = $this->io(static fn() => fopen($lock, 'c+b'));
        try {
            $this->io(static fn() => flock($handle, LOCK_EX));
            $this->checkFile($lock);
            $document = $this->read($file);
            if (!hash_equals($this->revision($document), $revision)) {
                throw new ConflictException('These theme styles changed. Reload before saving.');
            }
            if ($rules === null && $document['previous'] === null) {
                throw new InvalidArgumentException('No previous theme styles are available.');
            }
            $next = ['version' => 1, 'rules' => $rules ?? $document['previous'], 'previous' => $rules === null ? null : $document['rules']];
            $temporary = $this->configRoot . '/.' . bin2hex(random_bytes(16)) . '.tmp';
            try {
                $json = json_encode($next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $stream = $this->io(static fn() => fopen($temporary, 'xb'));
                try {
                    $this->io(static fn() => chmod($temporary, 0600));
                    $written = $this->io(static fn() => fwrite($stream, $json));
                    if ($written !== strlen($json)) {
                        throw new RuntimeException('Unable to store theme styles.');
                    }
                    $this->io(static fn() => fflush($stream));
                } finally {
                    fclose($stream);
                }
                if ($this->exists($file)) {
                    $this->checkFile($file);
                }
                $this->io(static fn() => rename($temporary, $file));
            } finally {
                if (is_file($temporary)) {
                    $this->io(static fn() => unlink($temporary));
                }
            }
            return $this->state($theme, $next);
        } finally {
            fclose($handle);
        }
    }

    private function state(string $theme, array $document): array
    {
        return ['theme' => $theme, 'revision' => $this->revision($document), 'rules' => $document['rules'],
            'css' => $this->compile($document['rules']), 'canRestore' => $document['previous'] !== null];
    }

    private function revision(array $document): string
    {
        return hash('sha256', json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function read(string $file): array
    {
        if (!$this->exists($file)) {
            return ['version' => 1, 'rules' => [], 'previous' => null];
        }
        $this->checkFile($file);
        if (filesize($file) > 1048576) {
            throw new RuntimeException('Invalid stored theme styles.');
        }
        try {
            $data = json_decode($this->io(static fn() => file_get_contents($file)), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_keys($data) !== ['version', 'rules', 'previous'] || $data['version'] !== 1
                || !is_array($data['rules']) || ($data['previous'] !== null && !is_array($data['previous']))) {
                throw new InvalidArgumentException('Invalid stored document.');
            }
            return ['version' => 1, 'rules' => $this->validateRules($data['rules']),
                'previous' => $data['previous'] === null ? null : $this->validateRules($data['previous'])];
        } catch (JsonException | InvalidArgumentException $error) {
            throw new RuntimeException('Invalid stored theme styles.', 0, $error);
        }
    }

    private function validateTheme(string $theme): void
    {
        if ($theme !== basename($theme) || !preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $theme)) {
            throw new InvalidArgumentException('Invalid theme.');
        }
        $this->checkDirectory($this->themesRoot, false);
        $names = $this->io(fn() => scandir($this->themesRoot));
        $folder = $this->themesRoot . '/' . $theme;
        if (!in_array($theme, $names, true) || !is_dir($folder) || is_link($folder)
            || dirname((string) realpath($folder)) !== realpath($this->themesRoot)) {
            throw new InvalidArgumentException('Unknown theme.');
        }
        foreach (['theme.tpl', 'theme.html'] as $entry) {
            $file = $folder . '/' . $entry;
            if (is_file($file) && !is_link($file) && dirname((string) realpath($file)) === realpath($folder)) {
                return;
            }
        }
        throw new InvalidArgumentException('Unknown theme.');
    }

    private function paths(string $theme): array
    {
        $stem = $this->configRoot . '/' . hash('sha256', $theme);
        return [$stem . '.json', $stem . '.lock'];
    }

    private function checkFile(string $file): void
    {
        if (!is_file($file) || is_link($file) || dirname((string) realpath($file)) !== realpath($this->configRoot)) {
            throw new RuntimeException('Unable to access theme styles.');
        }
    }

    private function checkDirectory(string $directory, bool $create): void
    {
        $parent = dirname($directory);
        if ($parent !== $directory) {
            $this->checkDirectory($parent, false);
        }
        if (is_link($directory) || ($this->exists($directory) && !is_dir($directory))) {
            throw new RuntimeException('Unsafe theme style storage.');
        }
        if (!is_dir($directory) && $create) {
            $this->checkDirectory($parent, true);
            $this->io(static fn() => mkdir($directory, 0700));
        }
    }

    private function absolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (!preg_match('~^(?:[A-Za-z]:/|/)~', $path) || preg_match('~(?:^|/)\.{1,2}(?:/|$)|\x00~', $path)) {
            throw new RuntimeException('Invalid theme style storage configuration.');
        }
        return rtrim(preg_replace('~/+~', '/', $path), '/');
    }

    private function resolvedPath(string $path): string
    {
        $tail = '';
        while (!$this->exists($path) && dirname($path) !== $path) {
            $tail = '/' . basename($path) . $tail;
            $path = dirname($path);
        }
        $resolved = str_replace('\\', '/', (realpath($path) ?: $path) . $tail);
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($resolved) : $resolved;
    }

    private function exists(string $path): bool
    {
        clearstatcache(true, $path);
        return file_exists($path) || is_link($path);
    }

    private function io(callable $operation): mixed
    {
        set_error_handler(static function (): never {
            throw new RuntimeException('Unable to access theme styles.');
        });
        try {
            $result = $operation();
            if ($result === false) {
                throw new RuntimeException('Unable to access theme styles.');
            }
            return $result;
        } finally {
            restore_error_handler();
        }
    }
}
