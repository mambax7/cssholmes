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
    /**
     * Variables a theme prints that core does not escape.
     *
     * Core already escapes $xoops_sitename, $xoops_slogan, $xoops_requesturi
     * and the $xoops_meta_* values in class/theme.php (XOOPS 2.7.x and 2.8.x),
     * so they are deliberately not listed. $xoops_pagetitle defaults to the
     * (escaped) module name, but a title a module assigns is passed through
     * unchanged.
     */
    private const USER_DATA_VARS = [
        'xoops_pagetitle',
    ];

    /** Modifiers that make the output safe for HTML. */
    private const ESCAPING_MODIFIERS = ['escape', 'htmlspecialchars', 'htmlentities', 'strip_tags'];

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
        if (!$context->isThemeFolder()) {
            return [];
        }

        $data = $this->loadManifest($themePath . '/theme.json');
        $generic = !ThemeFiles::hasManifest($themePath);
        $templateFiles = $this->discoverTemplates($themePath, $data);

        if ([] === $templateFiles) {
            return [];
        }

        $resolver = new TemplateIncludeResolver($themePath);
        $findings = [];
        foreach ($templateFiles as $relativePath => $fullPath) {
            $content = file_get_contents($fullPath);
            if (false === $content) {
                continue;
            }

            $this->checkWrongDelimiters($content, $relativePath, $findings);
            $this->checkIncludes($content, $resolver, $relativePath, $findings);
            $this->checkUnescapedUserVars($content, $relativePath, $findings);
        }

        $this->checkRootTemplate($resolver, $generic, $findings);
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

        return array_merge($templates, ThemeFiles::templates($themePath));
    }

    /**
     * @param Finding[] $findings
     */
    private function checkWrongDelimiters(string $content, string $file, array &$findings): void
    {
        $cleaned = SmartySource::stripComments($content);
        $keepLines = static fn (array $match): string => str_repeat("\n", substr_count($match[0], "\n"));
        $cleaned = preg_replace_callback('/<script\b[^>]*>.*?<\/script>/si', $keepLines, $cleaned) ?? $cleaned;
        $cleaned = preg_replace_callback('/<style\b[^>]*>.*?<\/style>/si', $keepLines, $cleaned) ?? $cleaned;
        $cleaned = preg_replace_callback('/<\{literal\}>.*?<\{\/literal\}>/si', $keepLines, $cleaned) ?? $cleaned;
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
    private function checkIncludes(
        string $content,
        TemplateIncludeResolver $resolver,
        string $file,
        array &$findings
    ): void {
        foreach ($resolver->includesIn($content, $file) as $include) {
            if (TemplateIncludeResolver::STATUS_THEME !== $include['status'] || $include['exists']) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'tpl/include-missing',
                sprintf(
                    'Included template file=%s not found (looked for %s/%s)',
                    $include['argument'],
                    $resolver->themeName(),
                    $include['path']
                ),
                $file,
                $include['line'],
                'Create the target template or fix the path.'
            );
        }
    }

    /**
     * @param Finding[] $findings
     */
    private function checkUnescapedUserVars(string $content, string $file, array &$findings): void
    {
        $content = SmartySource::stripComments($content);
        foreach (self::USER_DATA_VARS as $variable) {
            $pattern = '/<\{\$' . preg_quote($variable, '/') . '((?:\|[^}]*)?)\s*\}>/';
            if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                if ($this->hasEscapingModifier((string)$match[1][0])) {
                    continue;
                }

                $findings[] = new Finding(
                    Severity::Warning,
                    'tpl/unescaped-user-var',
                    "Variable '\${$variable}' is printed without |escape; "
                    . 'core passes titles set by modules through unchanged',
                    $file,
                    SmartySource::lineAt($content, (int)$match[0][1]),
                    "Use <{\${$variable}|escape:'html':'UTF-8':false}> so raw text is escaped "
                    . 'and text a module already escaped is not encoded twice.'
                );
            }
        }
    }

    private function hasEscapingModifier(string $modifiers): bool
    {
        foreach (self::ESCAPING_MODIFIERS as $modifier) {
            if (preg_match('/\|\s*' . $modifier . '\b/', $modifiers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Finding[] $findings
     */
    private function checkRootTemplate(TemplateIncludeResolver $resolver, bool $generic, array &$findings): void
    {
        $content = $resolver->expandedPage();
        if ('' === $content) {
            return;
        }

        if (!$generic && !str_contains($content, 'xtf_design_tokens')) {
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
                Severity::Warning,
                'tpl/module-header',
                'theme.tpl (with its includes) never prints $xoops_module_header',
                'theme.tpl',
                suggestion: 'Add <{$xoops_module_header}> to <head>; modules add their CSS, JS and meta tags there.'
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
        $declaredSlots = is_array($data['slots'] ?? null)
            ? array_values(array_filter($data['slots'], 'is_string'))
            : [];
        if ([] === $declaredSlots) {
            return;
        }

        $allContent = '';
        foreach ($templateFiles as $fullPath) {
            $content = file_get_contents($fullPath);
            if (false !== $content) {
                $allContent .= SmartySource::stripComments($content) . "\n";
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
