<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes\Workbench;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */

use XoopsModules\Cssholmes\Analyzer\ThemeTarget;

final class PatchSuggester
{
    /**
     * @param array<int, array<string, mixed>> $changes
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggest(?ThemeTarget $theme, array $changes): array
    {
        $suggestions = [];

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $suggestions[] = $this->suggestForChange($theme, $change);
        }

        return $suggestions;
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array<string, mixed>
     */
    private function suggestForChange(?ThemeTarget $theme, array $change): array
    {
        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';
        $selector = is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '';
        $widget = is_string($change['widget'] ?? null) ? trim((string)$change['widget']) : '';
        $slot = is_string($change['slot'] ?? null) ? trim((string)$change['slot']) : '';
        $before = is_string($change['before'] ?? null) ? (string)$change['before'] : '';
        $after = is_string($change['after'] ?? null) ? (string)$change['after'] : '';
        $tokenKey = is_string($change['token_key'] ?? null) ? trim((string)$change['token_key']) : '';

        if (null === $theme) {
            return [
                'title' => $this->titleForKind($kind),
                'detail' => 'No matching XTF theme was resolved for this import, so cssHolmes cannot suggest concrete file targets yet.',
                'targets' => [],
            ];
        }

        return match ($kind) {
            'text' => $this->textSuggestion($theme, $selector, $widget, $slot, $before, $after),
            'token', 'color' => $this->tokenSuggestion($theme, $selector, $widget, $slot, $tokenKey),
            'style', 'layout', 'measure' => $this->styleSuggestion($theme, $selector, $widget, $slot),
            default => $this->genericSuggestion($theme, $kind, $selector, $widget, $slot),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function textSuggestion(
        ThemeTarget $theme,
        string $selector,
        string $widget,
        string $slot,
        string $before,
        string $after
    ): array {
        $targets = $this->searchThemeFiles(
            $theme->path,
            ['tpl', 'html', 'php'],
            array_filter([$before, $after, $widget, $slot], static fn ($value): bool => '' !== trim((string)$value))
        );

        if ([] === $targets) {
            $targets = $this->fallbackFiles($theme->path, ['tpl', 'html', 'php']);
        }

        return [
            'title' => 'Template text patch',
            'detail' => 'Update the rendered text for selector '
                . $this->renderContext($selector, $widget, $slot)
                . ' from "' . $before . '" to "' . $after . '".',
            'targets' => $targets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenSuggestion(ThemeTarget $theme, string $selector, string $widget, string $slot, string $tokenKey): array
    {
        $targets = [];
        $manifestPath = $theme->path . '/theme.json';
        if (is_file($manifestPath)) {
            $targets[] = $this->relativePath($theme->path, $manifestPath);
        }

        foreach ($this->fallbackFiles($theme->path, ['css']) as $target) {
            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return [
            'title' => 'Theme token update',
            'detail' => 'Review '
                . ('' !== $tokenKey ? 'token ' . $tokenKey . ' for ' : 'token-backed color or style values for ')
                . $this->renderContext($selector, $widget, $slot)
                . ' in the manifest first, then in any theme-specific CSS overrides.',
            'targets' => array_slice($targets, 0, 6),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function styleSuggestion(ThemeTarget $theme, string $selector, string $widget, string $slot): array
    {
        return [
            'title' => 'Theme style patch',
            'detail' => 'Apply the style/layout change for '
                . $this->renderContext($selector, $widget, $slot)
                . ' in the most specific theme stylesheet or template wrapper.',
            'targets' => array_slice(
                array_merge(
                    $this->fallbackFiles($theme->path, ['css']),
                    $this->fallbackFiles($theme->path, ['tpl', 'html'])
                ),
                0,
                6
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericSuggestion(ThemeTarget $theme, string $kind, string $selector, string $widget, string $slot): array
    {
        return [
            'title' => $this->titleForKind($kind),
            'detail' => 'Review the imported ' . $kind . ' change for '
                . $this->renderContext($selector, $widget, $slot)
                . ' in the nearest matching template or theme asset.',
            'targets' => array_slice(
                array_merge(
                    $this->fallbackFiles($theme->path, ['tpl', 'html', 'php']),
                    $this->fallbackFiles($theme->path, ['css'])
                ),
                0,
                6
            ),
        ];
    }

    private function titleForKind(string $kind): string
    {
        return match ($kind) {
            'text' => 'Template text patch',
            'token', 'color' => 'Theme token update',
            'style', 'layout', 'measure' => 'Theme style patch',
            'inspect' => 'Inspection snapshot review',
            default => 'Imported change review',
        };
    }

    private function renderContext(string $selector, string $widget, string $slot): string
    {
        $parts = [];
        if ('' !== $selector) {
            $parts[] = 'selector ' . $selector;
        }
        if ('' !== $widget) {
            $parts[] = 'widget ' . $widget;
        }
        if ('' !== $slot) {
            $parts[] = 'slot ' . $slot;
        }

        return [] === $parts ? 'the affected output' : implode(', ', $parts);
    }

    /**
     * @param string[] $extensions
     * @param string[] $terms
     *
     * @return string[]
     */
    private function searchThemeFiles(string $themePath, array $extensions, array $terms): array
    {
        $candidates = [];
        foreach ($this->collectFiles($themePath, $extensions) as $filePath) {
            $score = $this->scoreFile($filePath, $terms);
            if ($score <= 0) {
                continue;
            }

            $candidates[$filePath] = $score;
        }

        arsort($candidates);

        return array_map(
            fn (string $filePath): string => $this->relativePath($themePath, $filePath),
            array_slice(array_keys($candidates), 0, 6)
        );
    }

    /**
     * @param string[] $extensions
     *
     * @return string[]
     */
    private function fallbackFiles(string $themePath, array $extensions): array
    {
        $files = $this->collectFiles($themePath, $extensions);
        $preferred = [];

        foreach ($files as $filePath) {
            $relative = $this->relativePath($themePath, $filePath);
            $score = 0;
            if (str_contains($relative, 'theme.tpl')) {
                $score += 30;
            }
            if (str_contains($relative, 'theme.json')) {
                $score += 25;
            }
            if (str_contains($relative, '/xotpl/')) {
                $score += 20;
            }
            if (str_contains($relative, '/tpl/')) {
                $score += 15;
            }
            if (str_contains($relative, '/css/')) {
                $score += 10;
            }
            if (str_contains($relative, '/templates/')) {
                $score += 10;
            }
            if (preg_match('/\.(tpl|html|css)$/', $relative)) {
                $score += 5;
            }

            $preferred[$relative] = $score;
        }

        arsort($preferred);

        return array_slice(array_keys($preferred), 0, 6);
    }

    /**
     * @param string[] $extensions
     *
     * @return string[]
     */
    private function collectFiles(string $themePath, array $extensions): array
    {
        if (!is_dir($themePath)) {
            return [];
        }

        $allowed = array_map('strtolower', $extensions);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themePath, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (!in_array($extension, $allowed, true)) {
                continue;
            }

            $files[] = str_replace('\\', '/', $fileInfo->getPathname());
        }

        return $files;
    }

    /**
     * @param string[] $terms
     */
    private function scoreFile(string $filePath, array $terms): int
    {
        $contents = file_get_contents($filePath);
        if (false === $contents || '' === $contents) {
            return 0;
        }

        $score = 0;
        foreach ($terms as $term) {
            $needle = trim((string)$term);
            if ('' === $needle || mb_strlen($needle) < 3) {
                continue;
            }

            if (false !== stripos($contents, $needle)) {
                $score += 10;
            }
        }

        return $score;
    }

    private function relativePath(string $themePath, string $filePath): string
    {
        $normalizedTheme = str_replace('\\', '/', rtrim($themePath, '/\\')) . '/';
        $normalizedFile = str_replace('\\', '/', $filePath);

        if (str_starts_with($normalizedFile, $normalizedTheme)) {
            return substr($normalizedFile, strlen($normalizedTheme));
        }

        return $normalizedFile;
    }
}
