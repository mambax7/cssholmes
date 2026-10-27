<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes\Analyzer;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 * File discovery shared by the theme analyzers.
 *
 * A theme is "XTF" when it ships a theme.json manifest and "generic" when it
 * is a normal XOOPS theme (theme.tpl or theme.html, usually with theme.ini).
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */
final class ThemeFiles
{
    /** Folders that hold third-party or generated files, never theme source. */
    private const SKIPPED_FOLDERS = ['vendor', 'node_modules', '.git', 'tests', 'test', 'dist', 'cache'];

    /** Custom property prefixes that belong to frameworks, not to the theme. */
    private const FRAMEWORK_PREFIXES = ['bs', 'tw', 'fa', 'wp', 'swiper', 'glide', 'xoops'];

    public static function hasManifest(string $themePath): bool
    {
        return is_file(rtrim($themePath, '/\\') . '/theme.json');
    }

    public static function isTheme(string $themePath): bool
    {
        $themePath = rtrim($themePath, '/\\');

        return is_file($themePath . '/theme.tpl')
            || is_file($themePath . '/theme.html')
            || is_file($themePath . '/theme.json');
    }

    /**
     * Every Smarty template of the theme, keyed by path relative to the theme.
     * Includes theme.tpl/theme.html, tpl/, xotpl/, templates/, modules/ and
     * blocks/ (recursively), skipping vendor and test folders.
     *
     * @return array<string, string>
     */
    public static function templates(string $themePath): array
    {
        $themePath = rtrim(str_replace('\\', '/', $themePath), '/');
        $templates = [];
        foreach (['theme.tpl', 'theme.html'] as $rootTemplate) {
            if (is_file($themePath . '/' . $rootTemplate)) {
                $templates[$rootTemplate] = $themePath . '/' . $rootTemplate;
            }
        }

        foreach (['tpl', 'xotpl', 'templates', 'modules', 'blocks'] as $directory) {
            foreach (self::filesIn($themePath, $directory, ['tpl', 'html']) as $relative => $fullPath) {
                if ('index.html' === basename($relative)) {
                    continue;
                }
                $templates[$relative] = $fullPath;
            }
        }

        ksort($templates);

        return $templates;
    }

    /**
     * The theme's own stylesheets (minified and vendor files excluded).
     *
     * @return array<string, string> relative path => contents
     */
    public static function stylesheets(string $themePath): array
    {
        $themePath = rtrim(str_replace('\\', '/', $themePath), '/');
        $sheets = [];
        foreach (self::filesIn($themePath, '', ['css']) as $relative => $fullPath) {
            if (str_ends_with($relative, '.min.css')) {
                continue;
            }

            $content = file_get_contents($fullPath);
            if (false !== $content) {
                $sheets[$relative] = $content;
            }
        }

        ksort($sheets);

        return $sheets;
    }

    /**
     * The theme's own scripts (minified and vendor files excluded).
     *
     * @return array<string, string> relative path => contents
     */
    public static function scripts(string $themePath): array
    {
        $themePath = rtrim(str_replace('\\', '/', $themePath), '/');
        $scripts = [];
        foreach (self::filesIn($themePath, '', ['js', 'mjs']) as $relative => $fullPath) {
            if (str_ends_with($relative, '.min.js')) {
                continue;
            }

            $content = file_get_contents($fullPath);
            if (false !== $content) {
                $scripts[$relative] = $content;
            }
        }

        return $scripts;
    }

    /**
     * The most common custom-property prefix the theme declares in its own CSS,
     * for example "mt" for --mt-color-text. Framework prefixes such as --bs-
     * are ignored. Returns null when the theme declares no custom properties.
     *
     * @param array<string, string> $stylesheets
     */
    public static function learnTokenPrefix(array $stylesheets): ?string
    {
        $counts = [];
        foreach ($stylesheets as $css) {
            $css = preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
            if (!preg_match_all('/(?<![\w-])--([a-z][a-z0-9]*)-[a-z0-9-]+\s*:/i', $css, $matches)) {
                continue;
            }

            foreach ($matches[1] as $prefix) {
                $prefix = strtolower($prefix);
                if (in_array($prefix, self::FRAMEWORK_PREFIXES, true)) {
                    continue;
                }
                $counts[$prefix] = ($counts[$prefix] ?? 0) + 1;
            }
        }

        if ([] === $counts) {
            return null;
        }

        arsort($counts);

        return (string)array_key_first($counts);
    }

    /**
     * Name from theme.ini (generic themes) or theme.json, '' when unknown.
     */
    public static function displayName(string $themePath): string
    {
        $themePath = rtrim($themePath, '/\\');
        $manifest = $themePath . '/theme.json';
        if (is_file($manifest)) {
            $decoded = json_decode((string)file_get_contents($manifest), true);
            if (is_array($decoded) && is_string($decoded['name'] ?? null) && '' !== trim($decoded['name'])) {
                return trim($decoded['name']);
            }
        }

        $ini = $themePath . '/theme.ini';
        if (is_file($ini)) {
            $parsed = @parse_ini_file($ini, true, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $section = is_array($parsed['Theme'] ?? null) ? $parsed['Theme'] : $parsed;
                $name = $section['Name'] ?? ($section['name'] ?? '');
                if (is_string($name) && '' !== trim($name, " \"'")) {
                    return trim($name, " \"'");
                }
            }
        }

        return '';
    }

    /**
     * @param string[] $extensions
     *
     * @return array<string, string> relative path => full path
     */
    private static function filesIn(string $themePath, string $directory, array $extensions): array
    {
        $root = '' === $directory ? $themePath : $themePath . '/' . $directory;
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current): bool {
                    return !$current->isDir()
                        || !in_array(strtolower($current->getFilename()), self::SKIPPED_FOLDERS, true);
                }
            )
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || !in_array(strtolower($fileInfo->getExtension()), $extensions, true)) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
            $files[ltrim(substr($fullPath, strlen($themePath)), '/')] = $fullPath;
        }

        return $files;
    }
}
