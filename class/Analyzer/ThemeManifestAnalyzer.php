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

final class ThemeManifestAnalyzer implements AnalyzerInterface
{
    public function name(): string
    {
        return 'theme-manifest';
    }

    public function label(): string
    {
        return 'Theme Manifest Validator';
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        $themePath = $context->themePath;
        $manifestPath = $themePath . '/theme.json';

        if (!is_file($manifestPath)) {
            return [
                new Finding(
                    Severity::Error,
                    'manifest/exists',
                    sprintf('theme.json not found in %s', $themePath),
                    $manifestPath,
                    suggestion: 'Create theme.json or run ThemeGenerator.'
                ),
            ];
        }

        $json = file_get_contents($manifestPath);
        if (false === $json) {
            return [
                new Finding(
                    Severity::Error,
                    'manifest/json-syntax',
                    sprintf('theme.json could not be read: %s', $manifestPath),
                    $manifestPath,
                    suggestion: 'Check file permissions and ensure the manifest is readable.'
                ),
            ];
        }

        try {
            $rawData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return [
                new Finding(
                    Severity::Error,
                    'manifest/json-syntax',
                    sprintf('theme.json is not valid JSON: %s', $exception->getMessage()),
                    $manifestPath,
                    suggestion: 'Fix the JSON syntax in theme.json.'
                ),
            ];
        }

        if (!is_array($rawData)) {
            return [
                new Finding(
                    Severity::Error,
                    'manifest/json-syntax',
                    'theme.json must decode to an object.',
                    $manifestPath,
                    suggestion: 'Update theme.json so the root value is a JSON object.'
                ),
            ];
        }

        /** @var array<string, mixed> $rawData */
        $findings = [];

        $name = $this->stringValue($rawData['name'] ?? null);
        if ('' === $name) {
            $findings[] = new Finding(
                Severity::Warning,
                'manifest/name-missing',
                "theme.json has no 'name' field",
                $manifestPath,
                suggestion: 'Add a human-readable name.'
            );
        }

        $version = $this->stringValue($rawData['version'] ?? null);
        if ('' === $version) {
            $findings[] = new Finding(
                Severity::Warning,
                'manifest/version-missing',
                "theme.json has no 'version' field",
                $manifestPath,
                suggestion: 'Add a semver version string.'
            );
        }

        $slots = $this->stringList($rawData['slots'] ?? null);
        if ([] === $slots) {
            $findings[] = new Finding(
                Severity::Warning,
                'manifest/slots-missing',
                'theme.json declares no slots',
                $manifestPath,
                suggestion: "Add a 'slots' array."
            );
        }
        $slotLookup = array_fill_keys($slots, true);

        $slotTemplates = $this->stringMap($rawData['slot_templates'] ?? null);
        foreach ($slotTemplates as $slotName => $templatePath) {
            if (!isset($slotLookup[$slotName])) {
                $findings[] = new Finding(
                    Severity::Info,
                    'manifest/orphan-slot-template',
                    sprintf("slot_templates defines '%s' which is not in slots array", $slotName),
                    $manifestPath,
                    suggestion: sprintf("Add '%s' to slots or remove from slot_templates.", $slotName)
                );
            }

            if (!$this->fileExistsInTheme($themePath, $templatePath)) {
                $findings[] = new Finding(
                    Severity::Error,
                    'manifest/template-exists',
                    sprintf("Slot template '%s' for slot '%s' not found", $templatePath, $slotName),
                    $manifestPath,
                    suggestion: 'Create the template file or fix the path.'
                );
            }
        }

        foreach ($slots as $slotName) {
            if (!isset($slotTemplates[$slotName])) {
                $findings[] = new Finding(
                    Severity::Info,
                    'manifest/slot-no-template',
                    sprintf("Slot '%s' has no template mapping", $slotName),
                    $manifestPath,
                    suggestion: sprintf("Add '%s' to slot_templates if this slot renders a template.", $slotName)
                );
            }
        }

        $assets = is_array($rawData['assets'] ?? null) ? $rawData['assets'] : [];
        foreach ($this->stringList($assets['css'] ?? null) as $cssAsset) {
            if (!$this->fileExistsInTheme($themePath, $cssAsset)) {
                $findings[] = new Finding(
                    Severity::Error,
                    'manifest/css-exists',
                    sprintf("CSS asset '%s' not found", $cssAsset),
                    $manifestPath,
                    suggestion: 'Create the file or remove it from assets.css.'
                );
            }
        }

        foreach ($this->stringList($assets['js'] ?? null) as $jsAsset) {
            if (!$this->fileExistsInTheme($themePath, $jsAsset)) {
                $findings[] = new Finding(
                    Severity::Error,
                    'manifest/js-exists',
                    sprintf("JS asset '%s' not found", $jsAsset),
                    $manifestPath,
                    suggestion: 'Create the file or remove it from assets.js.'
                );
            }
        }

        $slotGroups = $this->slotGroups($rawData['slot_groups'] ?? null);
        $groupLookup = array_fill_keys(array_keys($slotGroups), true);

        foreach ($this->stringList($rawData['render_order'] ?? null) as $entry) {
            if (str_starts_with($entry, '@')) {
                $groupName = substr($entry, 1);
                if ('' !== $groupName && !isset($groupLookup[$groupName])) {
                    $findings[] = new Finding(
                        Severity::Error,
                        'manifest/render-order-group-ref',
                        sprintf("render_order references undeclared group '@%s'", $groupName),
                        $manifestPath,
                        suggestion: sprintf("Add '%s' to slot_groups or remove it from render_order.", $groupName)
                    );
                }

                continue;
            }

            if (!isset($slotLookup[$entry])) {
                $findings[] = new Finding(
                    Severity::Error,
                    'manifest/render-order-slot-ref',
                    sprintf("render_order references undeclared slot '%s'", $entry),
                    $manifestPath,
                    suggestion: sprintf("Add '%s' to the slots array or remove it from render_order.", $entry)
                );
            }
        }

        foreach ($slotGroups as $groupName => $definition) {
            foreach ($definition['slots'] as $reference) {
                if (str_starts_with($reference, '@')) {
                    $referencedGroup = substr($reference, 1);
                    if ('' !== $referencedGroup && !isset($groupLookup[$referencedGroup])) {
                        $findings[] = new Finding(
                            Severity::Error,
                            'manifest/group-slot-ref',
                            sprintf("Group '%s' references undeclared slot/group '%s'", $groupName, $reference),
                            $manifestPath,
                            suggestion: 'Add the missing slot or group declaration.'
                        );
                    }

                    continue;
                }

                if (!isset($slotLookup[$reference])) {
                    $findings[] = new Finding(
                        Severity::Error,
                        'manifest/group-slot-ref',
                        sprintf("Group '%s' references undeclared slot/group '%s'", $groupName, $reference),
                        $manifestPath,
                        suggestion: 'Add the missing slot or group declaration.'
                    );
                }
            }
        }

        foreach ($this->detectCircularGroups($slotGroups) as $cyclePath) {
            $findings[] = new Finding(
                Severity::Error,
                'manifest/circular-groups',
                sprintf('Circular slot group reference: %s', $cyclePath),
                $manifestPath,
                suggestion: 'Break the cycle by removing one @group reference.'
            );
        }

        $parentTheme = $this->stringValue($rawData['extends'] ?? null);
        if ('' !== $parentTheme && !$this->parentThemeExists($themePath, $parentTheme)) {
            $findings[] = new Finding(
                Severity::Warning,
                'manifest/parent-exists',
                sprintf("Parent theme '%s' not found", $parentTheme),
                $manifestPath,
                suggestion: 'Install the parent theme or remove the extends field.'
            );
        }

        $preferences = is_array($rawData['preferences'] ?? null) ? $rawData['preferences'] : [];
        foreach ($preferences as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $type = strtolower($this->stringValue($definition['type'] ?? null));
            if ('' !== $type && !in_array($type, ['bool', 'boolean', 'text', 'string', 'select', 'dropdown', 'textarea'], true)) {
                $findings[] = new Finding(
                    Severity::Warning,
                    'manifest/preference-type',
                    sprintf("Unknown preference type '%s' for '%s'", $type, $key),
                    $manifestPath,
                    suggestion: 'Use one of: bool, boolean, text, string, select, dropdown, textarea.'
                );
            }

            if (in_array($type, ['select', 'dropdown'], true)
                && [] === $this->stringList($definition['choices'] ?? null)
                && [] === $this->stringList($definition['options'] ?? null)) {
                $findings[] = new Finding(
                    Severity::Warning,
                    'manifest/preference-choices',
                    sprintf("Select preference '%s' has no choices", $key),
                    $manifestPath,
                    suggestion: "Add a 'choices' array."
                );
            }
        }

        $tokens = is_array($rawData['tokens'] ?? null) ? $rawData['tokens'] : [];
        foreach ($tokens as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if ('' === trim($value)) {
                $findings[] = new Finding(
                    Severity::Warning,
                    'manifest/token-empty-value',
                    sprintf("Token '%s' has empty value", $key),
                    $manifestPath,
                    suggestion: 'Provide a CSS-valid default value.'
                );
            }
        }

        return $findings;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ('' === $candidate) {
                continue;
            }

            $items[] = $candidate;
        }

        return array_values($items);
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $key => $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $normalizedKey = trim((string)$key);
            $normalizedValue = trim($candidate);
            if ('' === $normalizedKey || '' === $normalizedValue) {
                continue;
            }

            $items[$normalizedKey] = $normalizedValue;
        }

        return $items;
    }

    /**
     * @return array<string, array{slots: string[]}>
     */
    private function slotGroups(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $groups = [];
        foreach ($value as $groupName => $definition) {
            if (!is_string($groupName) || !is_array($definition)) {
                continue;
            }

            $normalizedName = trim($groupName);
            if ('' === $normalizedName) {
                continue;
            }

            $groups[$normalizedName] = [
                'slots' => $this->stringList($definition['slots'] ?? null),
            ];
        }

        return $groups;
    }

    private function fileExistsInTheme(string $themePath, string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ('' === $relativePath) {
            return false;
        }

        return is_file($themePath . '/' . $relativePath);
    }

    private function parentThemeExists(string $themePath, string $parentTheme): bool
    {
        $parentTheme = trim($parentTheme);
        if ('' === $parentTheme) {
            return true;
        }

        $candidates = [
            dirname($themePath) . '/' . $parentTheme,
            $this->siteThemesRoot() . '/' . $parentTheme,
            $this->adminThemesRoot() . '/' . $parentTheme,
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return true;
            }
        }

        return $this->directoryExistsByBasename($this->siteThemesRoot(), $parentTheme)
            || $this->directoryExistsByBasename($this->adminThemesRoot(), $parentTheme);
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

    private function directoryExistsByBasename(string $rootPath, string $basename): bool
    {
        if (!is_dir($rootPath)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir() && $fileInfo->getBasename() === $basename) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{slots: string[]}> $slotGroups
     *
     * @return string[]
     */
    private function detectCircularGroups(array $slotGroups): array
    {
        $graph = [];
        foreach ($slotGroups as $groupName => $definition) {
            $graph[$groupName] = [];
            foreach ($definition['slots'] as $reference) {
                if (!str_starts_with($reference, '@')) {
                    continue;
                }

                $candidate = substr($reference, 1);
                if (isset($slotGroups[$candidate])) {
                    $graph[$groupName][] = $candidate;
                }
            }
        }

        $cycles = [];
        $visited = [];
        $stack = [];
        $path = [];

        $walk = function (string $groupName) use (&$walk, &$cycles, &$graph, &$visited, &$stack, &$path): void {
            if (isset($stack[$groupName])) {
                $cycleStart = array_search($groupName, $path, true);
                if (false === $cycleStart) {
                    return;
                }

                $cycle = array_slice($path, $cycleStart);
                $cycle[] = $groupName;
                $signature = implode('>', $cycle);
                $cycles[$signature] = implode(' -> ', $cycle);

                return;
            }

            if (isset($visited[$groupName])) {
                return;
            }

            $visited[$groupName] = true;
            $stack[$groupName] = true;
            $path[] = $groupName;

            foreach ($graph[$groupName] ?? [] as $targetGroup) {
                $walk($targetGroup);
            }

            unset($stack[$groupName]);
            array_pop($path);
        };

        foreach (array_keys($graph) as $groupName) {
            $walk($groupName);
        }

        return array_values($cycles);
    }
}
