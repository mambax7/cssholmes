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
 * Configurator Class
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */

final class AccessibilityAnalyzer implements AnalyzerInterface
{
    public function name(): string
    {
        return 'accessibility';
    }

    public function label(): string
    {
        return 'Accessibility Analyzer';
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        $html = $this->resolveHtml($context);
        if ('' === $html) {
            return [];
        }

        $findings = [];
        $this->checkLang($html, $findings);
        $this->checkMainLandmark($html, $findings);
        $this->checkH1($html, $findings);
        $this->checkHeadingSkip($html, $findings);
        $this->checkEmptyLinks($html, $findings);
        $this->checkEmptyButtons($html, $findings);
        $this->checkImgAlt($html, $findings);
        $this->checkFormLabels($html, $findings);
        $this->checkSkipLink($html, $findings);
        $this->checkTabindex($html, $findings);
        $this->checkContrastTokenPairs($context, $findings);

        return $findings;
    }

    private function resolveHtml(AnalysisContext $context): string
    {
        if (str_starts_with(trim($context->themePath), '<')) {
            return trim($context->themePath);
        }

        $themePath = $context->themePath;
        $html = '';

        $rootTemplate = $themePath . '/theme.tpl';
        if (is_file($rootTemplate)) {
            $content = file_get_contents($rootTemplate);
            if (false !== $content) {
                $html .= $content . "\n";
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
                $content = file_get_contents($filePath);
                if (false !== $content) {
                    $html .= $content . "\n";
                }
            }
        }

        return $html;
    }

    /** @param Finding[] $findings */
    private function checkLang(string $html, array &$findings): void
    {
        if (preg_match('/<html\b/i', $html) && !preg_match('/<html\b[^>]*\blang\s*=/i', $html)) {
            $findings[] = new Finding(
                Severity::Error,
                'a11y/no-lang',
                'Page has no lang attribute on <html>',
                'theme.tpl',
                suggestion: 'Add a lang attribute to <html>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkMainLandmark(string $html, array &$findings): void
    {
        if (!preg_match('/<main\b/i', $html) && !preg_match('/role\s*=\s*["\']main["\']/i', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'a11y/no-main-landmark',
                'Page has no <main> landmark',
                'theme.tpl',
                suggestion: 'Wrap primary content in <main>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkH1(string $html, array &$findings): void
    {
        if (!preg_match('/<h1[\s>]/i', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'a11y/no-h1',
                'Page has no <h1> heading',
                'theme.tpl',
                suggestion: 'Add a primary heading.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkHeadingSkip(string $html, array &$findings): void
    {
        if (!preg_match_all('/<h([1-6])[\s>]/i', $html, $matches)) {
            return;
        }

        $levels = array_map('intval', $matches[1]);
        $previous = 0;
        foreach ($levels as $level) {
            if ($previous > 0 && $level > $previous + 1) {
                $findings[] = new Finding(
                    Severity::Warning,
                    'a11y/heading-skip',
                    "Heading level skips from h{$previous} to h{$level}",
                    'templates',
                    suggestion: 'Add intermediate heading levels.'
                );
                break;
            }
            $previous = $level;
        }
    }

    /** @param Finding[] $findings */
    private function checkEmptyLinks(string $html, array &$findings): void
    {
        if (!preg_match_all('/<a\b([^>]*)>\s*<\/a>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $attributes) {
            $hasLabel = (bool)preg_match('/aria-label\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasLabelledBy = (bool)preg_match('/aria-labelledby\s*=/', $attributes);
            if ($hasLabel || $hasLabelledBy) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'a11y/empty-link',
                'Empty link with no accessible name',
                'templates',
                suggestion: 'Add text content or aria-label.'
            );
            break;
        }
    }

    /** @param Finding[] $findings */
    private function checkEmptyButtons(string $html, array &$findings): void
    {
        if (!preg_match_all('/<button\b([^>]*)>\s*<\/button>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $attributes) {
            $hasLabel = (bool)preg_match('/aria-label\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasLabelledBy = (bool)preg_match('/aria-labelledby\s*=/', $attributes);
            if ($hasLabel || $hasLabelledBy) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'a11y/empty-button',
                'Empty button with no accessible name',
                'templates',
                suggestion: 'Add text content or aria-label.'
            );
            break;
        }
    }

    /** @param Finding[] $findings */
    private function checkImgAlt(string $html, array &$findings): void
    {
        if (!preg_match_all('/<img\b([^>]*)>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $attributes) {
            if (preg_match('/\balt\s*=/', $attributes)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'a11y/img-no-alt',
                'Image without alt attribute',
                'templates',
                suggestion: 'Add alt text or alt="" for decorative images.'
            );
            break;
        }
    }

    /** @param Finding[] $findings */
    private function checkFormLabels(string $html, array &$findings): void
    {
        if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[2] as $index => $attributes) {
            $tag = strtolower((string)$matches[1][$index]);
            if ('input' === $tag && preg_match('/type\s*=\s*["\'](hidden|submit|button|reset|image)["\']/i', $attributes)) {
                continue;
            }

            $hasId = (bool)preg_match('/\bid\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasAriaLabel = (bool)preg_match('/aria-label\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasAriaLabelledBy = (bool)preg_match('/aria-labelledby\s*=/', $attributes);
            if ($hasId || $hasAriaLabel || $hasAriaLabelledBy) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'a11y/form-label',
                "Form <{$tag}> without label: no id, aria-label, or aria-labelledby",
                'templates',
                suggestion: 'Add a <label for="id"> or aria-label.'
            );
            break;
        }
    }

    /** @param Finding[] $findings */
    private function checkSkipLink(string $html, array &$findings): void
    {
        if (!preg_match('/<body\b[^>]*>(.*?)<(main|div|header|nav)\b/si', $html, $match)) {
            return;
        }

        if (preg_match('/<a\b[^>]*href\s*=\s*["\']#(main|content|skip)["\']/', (string)$match[1])) {
            return;
        }

        $findings[] = new Finding(
            Severity::Info,
            'a11y/skip-link',
            'Page has no skip-to-content link',
            'theme.tpl',
            suggestion: 'Add a visually-hidden skip link as the first body child.'
        );
    }

    /** @param Finding[] $findings */
    private function checkTabindex(string $html, array &$findings): void
    {
        if (!preg_match_all('/tabindex\s*=\s*["\'](\d+)["\']/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $value) {
            if ((int)$value <= 0) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'a11y/tabindex-positive',
                "Element has tabindex=\"{$value}\" — positive tabindex disrupts tab order",
                'templates',
                suggestion: 'Use tabindex="0" or tabindex="-1" instead.'
            );
            break;
        }
    }

    /** @param Finding[] $findings */
    private function checkContrastTokenPairs(AnalysisContext $context, array &$findings): void
    {
        $manifestPath = $context->themePath . '/theme.json';
        if (!is_file($manifestPath)) {
            return;
        }

        $raw = file_get_contents($manifestPath);
        if (false === $raw) {
            return;
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : [];
        foreach ($this->findContrastPairs($tokens) as [$foregroundKey, $backgroundKey, $foregroundValue, $backgroundValue]) {
            $foregroundRgb = $this->hexToRgb($foregroundValue);
            $backgroundRgb = $this->hexToRgb($backgroundValue);
            if (null === $foregroundRgb || null === $backgroundRgb) {
                continue;
            }

            $ratio = $this->contrastRatio($foregroundRgb, $backgroundRgb);
            if ($ratio >= 4.5) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'a11y/contrast-token-pair',
                sprintf(
                    "Token pair '%s' / '%s' has contrast ratio %.1f:1 (minimum 4.5:1)",
                    $foregroundKey,
                    $backgroundKey,
                    $ratio
                ),
                'theme.json',
                suggestion: 'Adjust colors to meet WCAG AA.'
            );
        }
    }

    /**
     * @param array<string, mixed> $tokens
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function findContrastPairs(array $tokens): array
    {
        $pairs = [];
        $foregroundPatterns = ['color.text', 'color.link', 'color.heading', 'color.text-secondary'];
        $backgroundPatterns = ['color.bg', 'color.bg-secondary', 'color.surface'];

        foreach ($foregroundPatterns as $foregroundPattern) {
            if (!isset($tokens[$foregroundPattern]) || !is_string($tokens[$foregroundPattern])) {
                continue;
            }
            foreach ($backgroundPatterns as $backgroundPattern) {
                if (!isset($tokens[$backgroundPattern]) || !is_string($tokens[$backgroundPattern])) {
                    continue;
                }
                $pairs[] = [$foregroundPattern, $backgroundPattern, $tokens[$foregroundPattern], $tokens[$backgroundPattern]];
            }
        }

        return $pairs;
    }

    /** @return array{r: float, g: float, b: float}|null */
    private function hexToRgb(string $hex): ?array
    {
        $normalized = ltrim(trim($hex), '#');
        if (3 === strlen($normalized)) {
            $normalized = $normalized[0] . $normalized[0] . $normalized[1] . $normalized[1] . $normalized[2] . $normalized[2];
        }

        if (6 !== strlen($normalized) || !ctype_xdigit($normalized)) {
            return null;
        }

        return [
            'r' => hexdec(substr($normalized, 0, 2)) / 255.0,
            'g' => hexdec(substr($normalized, 2, 2)) / 255.0,
            'b' => hexdec(substr($normalized, 4, 2)) / 255.0,
        ];
    }

    /**
     * @param array{r: float, g: float, b: float} $foreground
     * @param array{r: float, g: float, b: float} $background
     */
    private function contrastRatio(array $foreground, array $background): float
    {
        $lighter = max($this->relativeLuminance($foreground), $this->relativeLuminance($background));
        $darker = min($this->relativeLuminance($foreground), $this->relativeLuminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** @param array{r: float, g: float, b: float} $color */
    private function relativeLuminance(array $color): float
    {
        $linearize = static function (float $channel): float {
            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linearize($color['r'])
            + 0.7152 * $linearize($color['g'])
            + 0.0722 * $linearize($color['b']);
    }
}
