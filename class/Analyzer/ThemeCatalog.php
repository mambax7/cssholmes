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
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */

final class ThemeCatalog
{
    /** @var ThemeTarget[]|null */
    private ?array $themes = null;

    /** @return ThemeTarget[] */
    public function all(): array
    {
        if (null !== $this->themes) {
            return $this->themes;
        }

        $themes = array_merge(
            $this->discoverFromRoot($this->siteThemesRoot(), 'site'),
            $this->discoverFromRoot($this->adminThemesRoot(), 'admin')
        );

        usort(
            $themes,
            static function (ThemeTarget $left, ThemeTarget $right): int {
                $scopeComparison = strcmp($left->scope, $right->scope);
                if (0 !== $scopeComparison) {
                    return $scopeComparison;
                }

                return strcmp($left->key, $right->key);
            }
        );

        $this->themes = $themes;

        return $this->themes;
    }

    /** @return ThemeTarget[] */
    public function allForScope(string $scope): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static fn (ThemeTarget $theme): bool => $theme->scope === $scope
            )
        );
    }

    public function find(string $scope, string $key): ?ThemeTarget
    {
        foreach ($this->all() as $theme) {
            if ($theme->scope === $scope && $theme->key === $key) {
                return $theme;
            }
        }

        return null;
    }

    private function siteThemesRoot(): string
    {
        if (defined('XOOPS_THEME_PATH')) {
            return rtrim(XOOPS_THEME_PATH, '/\\');
        }

        return dirname(__DIR__, 4) . '/themes';
    }

    private function adminThemesRoot(): string
    {
        if (defined('XOOPS_ROOT_PATH')) {
            return rtrim(XOOPS_ROOT_PATH, '/\\') . '/modules/system/themes';
        }

        return dirname(__DIR__, 4) . '/modules/system/themes';
    }

    /**
     * @return ThemeTarget[]
     */
    private function discoverFromRoot(string $rootPath, string $scope): array
    {
        if (!is_dir($rootPath)) {
            return [];
        }

        $themes = [];
        $normalizedRoot = str_replace('\\', '/', rtrim($rootPath, '/\\'));
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || 'theme.json' !== $fileInfo->getFilename()) {
                continue;
            }

            $themeDir = str_replace('\\', '/', rtrim($fileInfo->getPath(), '/\\'));
            $relativePath = trim(substr($themeDir, strlen($normalizedRoot)), '/');
            if ('' === $relativePath) {
                continue;
            }

            $metadata = $this->readThemeMetadata($fileInfo->getPathname());
            $label = $relativePath;
            if ('' !== $metadata['name']) {
                $label = $metadata['name'] . ' [' . $relativePath . ']';
            }

            $themes[] = new ThemeTarget(
                scope: $scope,
                key: $relativePath,
                path: rtrim($fileInfo->getPath(), '/\\'),
                label: $label,
                type: $metadata['type']
            );
        }

        return $themes;
    }

    /**
     * @return array{name: string, type: string}
     */
    private function readThemeMetadata(string $manifestPath): array
    {
        $name = '';
        $type = '';

        $json = file_get_contents($manifestPath);
        if (false !== $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $candidateName = $decoded['name'] ?? '';
                $candidateType = $decoded['type'] ?? '';
                $name = is_string($candidateName) ? trim($candidateName) : '';
                $type = is_string($candidateType) ? trim($candidateType) : '';
            }
        }

        return [
            'name' => $name,
            'type' => $type,
        ];
    }
}
