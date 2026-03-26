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

final class TemplateAnalyzer implements AnalyzerInterface
{
    private const USER_DATA_VARS = [
        'xoops_pagetitle',
        'xoops_sitename',
        'xoops_slogan',
    ];

    private const WRONG_DELIMITER_PATTERN = '/(?<![<])\{(if |foreach |include |assign |\$[a-z])/';

    public function name(): string
    {
        return 'template';
    }

    public function label(): string
    {
        return 'Template Analyzer';
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        $themePath = $context->themePath;
        $manifestPath = $themePath . '/theme.json';
        $data = $this->loadManifest($manifestPath);
        $templateFiles = $this->discoverTemplates($themePath, $data);

        if ([] === $templateFiles) {
            return [];
        }

        $findings = [];
        foreach ($templateFiles as $relativePath => $fullPath) {
            $content = file_get_contents($fullPath);
            if (false === $content) {
                continue;
            }

            $this->checkWrongDelimiters($content, $relativePath, $findings);
            $this->checkIncludes($content, $themePath, $relativePath, $findings);
            $this->checkUnescapedUserVars($content, $relativePath, $findings);
        }

        $this->checkRootTemplate($themePath, $findings);
        $this->checkSlotVarRefs($data, $templateFiles, $findings);

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        if (!is_file($manifestPath)) {
            return [];
        }

        $raw = file_get_contents($manifestPath);
        if (false === $raw) {
            return [];
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function discoverTemplates(string $themePath, array $data): array
    {
        $templates = [];

        $slotTemplates = is_array($data['slot_templates'] ?? null) ? $data['slot_templates'] : [];
        foreach ($slotTemplates as $path) {
            if (!is_string($path) || '' === trim($path)) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', trim($path)), '/');
            $fullPath = $themePath . '/' . $relativePath;
            if (is_file($fullPath)) {
                $templates[$relativePath] = $fullPath;
            }
        }

        foreach (['tpl', 'xotpl', 'templates'] as $directory) {
            $directoryPath = $themePath . '/' . $directory;
            if (!is_dir($directoryPath)) {
                continue;
            }

            $matches = glob($directoryPath . '/*.tpl');
            if (false === $matches) {
                continue;
            }

            foreach ($matches as $filePath) {
                $templates[$directory . '/' . basename($filePath)] = $filePath;
            }
        }

        if (is_file($themePath . '/theme.tpl')) {
            $templates['theme.tpl'] = $themePath . '/theme.tpl';
        }

        return $templates;
    }

    /**
     * @param Finding[] $findings
     */
    private function checkWrongDelimiters(string $content, string $file, array &$findings): void
    {
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $content) ?? $content;
        $cleaned = preg_replace('/<\{literal\}>.*?<\{\/literal\}>/si', '', $cleaned) ?? $cleaned;
        $lines = preg_split('/\R/', $cleaned) ?: [];

        foreach ($lines as $lineNumber => $line) {
            if (!preg_match(self::WRONG_DELIMITER_PATTERN, $line)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'tpl/wrong-delimiters',
                'Standard Smarty delimiters found; XOOPS requires <{...}>.',
                $file,
                $lineNumber + 1,
                'Replace {$var} with <{$var}>.'
            );
        }
    }

    /**
     * @param Finding[] $findings
     */
    private function checkIncludes(string $content, string $themePath, string $file, array &$findings): void
    {
        if (!preg_match_all('/<\{include\s+file=["\']([^"\'$]+)["\']\s*\}>/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[1] as $match) {
            $includePath = (string)$match[0];
            if (str_starts_with($includePath, 'db:') || str_contains($includePath, '$')) {
                continue;
            }

            $lineNumber = substr_count(substr($content, 0, (int)$match[1]), "\n") + 1;
            $fullPath = $themePath . '/' . ltrim(str_replace('\\', '/', $includePath), '/');
            if (is_file($fullPath)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'tpl/include-missing',
                "Template include target '{$includePath}' not found",
                $file,
                $lineNumber,
                'Create the target template or fix the path.'
            );
        }
    }

    /**
     * @param Finding[] $findings
     */
    private function checkUnescapedUserVars(string $content, string $file, array &$findings): void
    {
        foreach (self::USER_DATA_VARS as $variable) {
            $pattern = '/<\{\$' . preg_quote($variable, '/') . '\s*\}>/';
            if (!preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            if (preg_match('/<\{\$' . preg_quote($variable, '/') . '\|escape/', $content)) {
                continue;
            }

            $lineNumber = substr_count(substr($content, 0, (int)$match[0][1]), "\n") + 1;
            $findings[] = new Finding(
                Severity::Warning,
                'tpl/unescaped-user-var',
                "User data variable '\${$variable}' output without |escape",
                $file,
                $lineNumber,
                "Add |escape:'html' filter."
            );
        }
    }

    /**
     * @param Finding[] $findings
     */
    private function checkRootTemplate(string $themePath, array &$findings): void
    {
        $rootTemplate = $themePath . '/theme.tpl';
        if (!is_file($rootTemplate)) {
            return;
        }

        $content = file_get_contents($rootTemplate);
        if (false === $content) {
            return;
        }

        if (!str_contains($content, 'xtf_design_tokens')) {
            $findings[] = new Finding(
                Severity::Info,
                'tpl/design-token-style-tag',
                'theme.tpl does not inject design tokens',
                'theme.tpl',
                suggestion: 'Add <{if isset($xtf_design_tokens)}><{$xtf_design_tokens}><{/if}> to <head>.'
            );
        }

        if (!str_contains($content, 'xoops_module_header')) {
            $findings[] = new Finding(
                Severity::Info,
                'tpl/module-header',
                'theme.tpl does not inject module header',
                'theme.tpl',
                suggestion: 'Add <{if isset($xoops_module_header)}><{$xoops_module_header}><{/if}> to <head>.'
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $templateFiles
     * @param Finding[] $findings
     */
    private function checkSlotVarRefs(array $data, array $templateFiles, array &$findings): void
    {
        $declaredSlots = is_array($data['slots'] ?? null) ? array_values(array_filter($data['slots'], 'is_string')) : [];
        if ([] === $declaredSlots) {
            return;
        }

        $allContent = '';
        foreach ($templateFiles as $fullPath) {
            $content = file_get_contents($fullPath);
            if (false !== $content) {
                $allContent .= $content . "\n";
            }
        }

        if (!preg_match_all('/\$slot_([a-z0-9_]+)_html/', $allContent, $matches)) {
            return;
        }

        foreach (array_unique($matches[1]) as $slotName) {
            if (in_array($slotName, $declaredSlots, true)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'tpl/orphan-slot-var',
                "Template references slot variable '{$slotName}' not declared in theme.json",
                'templates',
                suggestion: "Add '{$slotName}' to theme.json slots or remove the variable reference."
            );
        }
    }
}
