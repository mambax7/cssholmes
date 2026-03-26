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

final class DesignTokenAnalyzer implements AnalyzerInterface
{
    private const CSS_VAR_PATTERN = '/var\(\s*(--xtf-[a-z0-9-]+)\s*(?:,\s*([^)]+))?\)/i';

    public function name(): string
    {
        return 'design-token';
    }

    public function label(): string
    {
        return 'Design Token Analyzer';
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        $themePath = $context->themePath;
        $manifestPath = $themePath . '/theme.json';

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

        if (!is_array($data)) {
            return [];
        }

        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : [];
        if ([] === $tokens) {
            return [];
        }

        $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];
        $cssFiles = $this->loadCssFiles($themePath, is_array($assets['css'] ?? null) ? $assets['css'] : []);

        $findings = [];
        $this->checkUndefinedRefs($tokens, $cssFiles, $findings);
        $this->checkUnusedTokens($tokens, $cssFiles, $findings);
        $this->checkHardcodedMatches($tokens, $cssFiles, $findings);
        $this->checkMissingFallbacks($cssFiles, $findings);
        $this->checkColorFormat($tokens, $manifestPath, $findings);

        return $findings;
    }

    /**
     * @param mixed[] $cssFiles
     *
     * @return array<string, string>
     */
    private function loadCssFiles(string $themePath, array $cssFiles): array
    {
        $contents = [];

        foreach ($cssFiles as $cssPath) {
            if (!is_string($cssPath) || '' === trim($cssPath)) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', trim($cssPath)), '/');
            $fullPath = $themePath . '/' . $relativePath;
            if (!is_file($fullPath)) {
                continue;
            }

            $content = file_get_contents($fullPath);
            if (false !== $content) {
                $contents[$relativePath] = $content;
            }
        }

        return $contents;
    }

    private function tokenToCssVar(string $tokenKey): string
    {
        return '--xtf-' . str_replace('.', '-', $tokenKey);
    }

    /**
     * @param array<string, string> $cssContents
     *
     * @return array<string, array{file: string, line: int, fallback: string|null}>
     */
    private function extractCssVarRefs(array $cssContents): array
    {
        $references = [];

        foreach ($cssContents as $file => $content) {
            $lines = preg_split('/\R/', $content) ?: [];
            foreach ($lines as $lineNumber => $line) {
                if (!preg_match_all(self::CSS_VAR_PATTERN, $line, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $fallback = isset($match[2]) && '' !== trim((string)$match[2]) ? trim((string)$match[2]) : null;
                    $references[(string)$match[1]] = [
                        'file' => $file,
                        'line' => $lineNumber + 1,
                        'fallback' => $fallback,
                    ];
                }
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, string> $cssContents
     * @param Finding[] $findings
     */
    private function checkUndefinedRefs(array $tokens, array $cssContents, array &$findings): void
    {
        $declaredVars = [];
        foreach (array_keys($tokens) as $key) {
            if (is_string($key) && '' !== trim($key)) {
                $declaredVars[$this->tokenToCssVar($key)] = $key;
            }
        }

        foreach ($this->extractCssVarRefs($cssContents) as $variable => $reference) {
            if (!str_starts_with($variable, '--xtf-') || isset($declaredVars[$variable])) {
                continue;
            }

            $dotKey = str_replace('-', '.', substr($variable, 6));
            $findings[] = new Finding(
                Severity::Error,
                'token/undefined-ref',
                "CSS references undefined token '{$variable}' in {$reference['file']}:{$reference['line']}",
                $reference['file'],
                $reference['line'],
                "Add '{$dotKey}' to theme.json tokens or remove the reference."
            );
        }
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, string> $cssContents
     * @param Finding[] $findings
     */
    private function checkUnusedTokens(array $tokens, array $cssContents, array &$findings): void
    {
        $allCss = implode("\n", $cssContents);

        foreach ($tokens as $key => $value) {
            if (!is_string($key) || '' === trim($key)) {
                continue;
            }

            $cssVar = $this->tokenToCssVar($key);
            if (false !== stripos($allCss, $cssVar)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'token/unused',
                "Token '{$key}' declared but never referenced in CSS",
                'theme.json',
                suggestion: "Remove unused token or reference it via var({$cssVar})."
            );
        }
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, string> $cssContents
     * @param Finding[] $findings
     */
    private function checkHardcodedMatches(array $tokens, array $cssContents, array &$findings): void
    {
        foreach ($cssContents as $file => $content) {
            $lines = preg_split('/\R/', $content) ?: [];
            foreach ($lines as $lineNumber => $line) {
                foreach ($tokens as $key => $value) {
                    if (!is_string($key) || !is_string($value) || strlen(trim($value)) < 3) {
                        continue;
                    }

                    if (false === stripos($line, $value) || false !== stripos($line, 'var(')) {
                        continue;
                    }

                    $findings[] = new Finding(
                        Severity::Info,
                        'token/hardcoded-match',
                        "Hardcoded value '{$value}' in {$file}:" . ($lineNumber + 1) . " matches token '{$key}'",
                        $file,
                        $lineNumber + 1,
                        'Replace it with ' . 'var(' . $this->tokenToCssVar($key) . ').'
                    );
                }
            }
        }
    }

    /**
     * @param array<string, string> $cssContents
     * @param Finding[] $findings
     */
    private function checkMissingFallbacks(array $cssContents, array &$findings): void
    {
        foreach ($this->extractCssVarRefs($cssContents) as $variable => $reference) {
            if (!str_starts_with($variable, '--xtf-') || null !== $reference['fallback']) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Info,
                'token/no-fallback',
                "Token reference '{$variable}' in {$reference['file']}:{$reference['line']} has no fallback",
                $reference['file'],
                $reference['line'],
                "Add fallback: var({$variable}, <default>)."
            );
        }
    }

    /**
     * @param array<string, mixed> $tokens
     * @param Finding[] $findings
     */
    private function checkColorFormat(array $tokens, string $manifestPath, array &$findings): void
    {
        $colorPattern = '/^(#([0-9a-f]{3,8})|rgb[a]?\s*\(|hsl[a]?\s*\(|transparent|currentcolor|inherit)/i';

        foreach ($tokens as $key => $value) {
            if (!is_string($key) || !is_string($value) || !str_contains($key, 'color')) {
                continue;
            }

            $candidate = trim($value);
            if ('' === $candidate || preg_match($colorPattern, $candidate)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Info,
                'token/color-format',
                "Token '{$key}' value '{$value}' may not be a valid CSS color",
                $manifestPath,
                suggestion: 'Use hex, rgb(), hsl(), or a named CSS color.'
            );
        }
    }
}
