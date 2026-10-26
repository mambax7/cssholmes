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

    /**
     * Page-level checks (lang, main landmark, h1, heading order, skip link)
     * run on theme.tpl with its includes inlined. Element checks (empty links
     * and buttons, img alt, form labels, tabindex) run on every template file
     * so findings carry a file and line. Smarty tags are neutralised first.
     *
     * @return Finding[]
     */
    public function analyze(AnalysisContext $context): array
    {
        $findings = [];

        if (str_starts_with(trim($context->themePath), '<')) {
            $html = SmartySource::htmlView(trim($context->themePath));
            $this->runPageChecks($html, 'html', $findings);
            $this->runElementChecks($html, 'html', $findings);

            return $findings;
        }

        if (!$context->isThemeFolder()) {
            return [];
        }

        $resolver = new TemplateIncludeResolver($context->themePath);
        $page = $resolver->expandedPage();
        if ('' !== $page) {
            $pageTemplate = $this->pageTemplateName($context->themePath);
            $this->runPageChecks(SmartySource::htmlView($page), $pageTemplate, $findings);
        }

        foreach (ThemeFiles::templates($context->themePath) as $relativePath => $fullPath) {
            $content = file_get_contents($fullPath);
            if (false === $content) {
                continue;
            }

            $this->runElementChecks(SmartySource::htmlView($content), $relativePath, $findings);
        }

        $this->checkContrastTokenPairs($context, $findings);

        return $findings;
    }

    private function pageTemplateName(string $themePath): string
    {
        return is_file($themePath . '/theme.tpl') ? 'theme.tpl' : 'theme.html';
    }

    /** @param Finding[] $findings */
    private function runPageChecks(string $html, string $target, array &$findings): void
    {
        $this->checkLang($html, $target, $findings);
        $this->checkMainLandmark($html, $target, $findings);
        $this->checkH1($html, $target, $findings);
        $this->checkHeadingSkip($html, $target, $findings);
        $this->checkSkipLink($html, $target, $findings);
    }

    /** @param Finding[] $findings */
    private function runElementChecks(string $html, string $target, array &$findings): void
    {
        $this->checkEmptyElements($html, 'a', 'Empty link with no accessible name', $target, $findings);
        $this->checkEmptyElements($html, 'button', 'Empty button with no accessible name', $target, $findings);
        $this->checkImgAlt($html, $target, $findings);
        $this->checkFormLabels($html, $target, $findings);
        $this->checkTabindex($html, $target, $findings);
    }

    /** @param Finding[] $findings */
    private function checkLang(string $html, string $target, array &$findings): void
    {
        if (preg_match('/<html\b/i', $html) && !preg_match('/<html\b[^>]*\blang\s*=/i', $html)) {
            $findings[] = new Finding(
                Severity::Error,
                'a11y/no-lang',
                'Page has no lang attribute on <html>',
                $target,
                suggestion: 'Add lang="<{$xoops_langcode}>" to <html>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkMainLandmark(string $html, string $target, array &$findings): void
    {
        if (!preg_match('/<main\b/i', $html) && !preg_match('/role\s*=\s*["\']main["\']/i', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'a11y/no-main-landmark',
                'Page has no <main> landmark',
                $target,
                suggestion: 'Wrap <{$xoops_contents}> in <main>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkH1(string $html, string $target, array &$findings): void
    {
        if (!preg_match('/<h1[\s>]/i', $html)) {
            $findings[] = new Finding(
                Severity::Info,
                'a11y/no-h1',
                'The page template has no <h1>; each page then depends on module content for its main heading',
                $target,
                suggestion: 'Check that module pages print an <h1>, or give the theme one.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkHeadingSkip(string $html, string $target, array &$findings): void
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
                    $target,
                    suggestion: 'Add intermediate heading levels.'
                );
                break;
            }
            $previous = $level;
        }
    }

    /** @param Finding[] $findings */
    private function checkEmptyElements(
        string $html,
        string $tag,
        string $message,
        string $target,
        array &$findings
    ): void {
        $ruleId = 'a' === $tag ? 'a11y/empty-link' : 'a11y/empty-' . $tag;
        $pattern = '/<' . $tag . '\b([^>]*)>\s*<\/' . $tag . '>/i';
        if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $attributes = (string)$match[1][0];
            if ('a' === $tag && !preg_match('/\bhref\s*=/i', $attributes)) {
                // <a id="top"></a> is a fragment target, not a link.
                continue;
            }

            $hasLabel = (bool)preg_match('/aria-label\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasLabelledBy = (bool)preg_match('/aria-labelledby\s*=/', $attributes);
            $hasTitle = (bool)preg_match('/\btitle\s*=\s*["\'][^"\']+["\']/', $attributes);
            if ($hasLabel || $hasLabelledBy || $hasTitle || SmartySource::hasDynamicAttributes($attributes)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                $ruleId,
                $message,
                $target,
                SmartySource::lineAt($html, (int)$match[0][1]),
                'Add text content or aria-label.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkImgAlt(string $html, string $target, array &$findings): void
    {
        if (!preg_match_all('/<img\b([^>]*)>/i', $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $attributes = (string)$match[1][0];
            if (preg_match('/\balt\s*=/i', $attributes) || SmartySource::hasDynamicAttributes($attributes)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'a11y/img-no-alt',
                'Image without alt attribute',
                $target,
                SmartySource::lineAt($html, (int)$match[0][1]),
                'Add alt text or alt="" for decorative images.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkFormLabels(string $html, string $target, array &$findings): void
    {
        $pattern = '/<(input|select|textarea)\b([^>]*)>/i';
        if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $tag = strtolower((string)$match[1][0]);
            $attributes = (string)$match[2][0];
            $unlabelledType = '/type\s*=\s*["\'](hidden|submit|button|reset|image)["\']/i';
            if ('input' === $tag && preg_match($unlabelledType, $attributes)) {
                continue;
            }

            $hasId = (bool)preg_match('/\bid\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasAriaLabel = (bool)preg_match('/aria-label\s*=\s*["\'][^"\']+["\']/', $attributes);
            $hasAriaLabelledBy = (bool)preg_match('/aria-labelledby\s*=/', $attributes);
            if (
                $hasId || $hasAriaLabel || $hasAriaLabelledBy || SmartySource::hasDynamicAttributes($attributes)
                || $this->insideLabel($html, (int)$match[0][1])
            ) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'a11y/form-label',
                "Form <{$tag}> without label: no id, aria-label, or aria-labelledby",
                $target,
                SmartySource::lineAt($html, (int)$match[0][1]),
                'Add a <label for="id"> or aria-label.'
            );
        }
    }

    /**
     * Whether the offset sits inside an open <label> element.
     */
    private function insideLabel(string $html, int $offset): bool
    {
        $before = strtolower(substr($html, 0, $offset));
        $open = strrpos($before, '<label');
        if (false === $open) {
            return false;
        }

        $close = strrpos($before, '</label');

        return false === $close || $close < $open;
    }

    /** @param Finding[] $findings */
    private function checkSkipLink(string $html, string $target, array &$findings): void
    {
        if (!preg_match('/<body\b[^>]*>(.*?)<(main|div|header|nav)\b/si', $html, $match)) {
            return;
        }

        $targets = ['main', 'content', 'main-content', 'maincontent', 'skip'];
        if (preg_match('/<main\b[^>]*\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $mainMatch)) {
            $targets[] = $mainMatch[1];
        }

        $quoted = implode('|', array_map(static fn (string $id): string => preg_quote($id, '/'), $targets));
        if (preg_match('/<a\b[^>]*href\s*=\s*["\']#(' . $quoted . ')["\']/i', (string)$match[1])) {
            return;
        }

        $findings[] = new Finding(
            Severity::Info,
            'a11y/skip-link',
            'Page has no skip-to-content link',
            $target,
            suggestion: 'Add a visually-hidden skip link as the first body child.'
        );
    }

    /** @param Finding[] $findings */
    private function checkTabindex(string $html, string $target, array &$findings): void
    {
        $pattern = '/tabindex\s*=\s*["\'](\d+)["\']/i';
        if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $value = (string)$match[1][0];
            if ((int)$value <= 0) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'a11y/tabindex-positive',
                "Element has tabindex=\"{$value}\" — positive tabindex disrupts tab order",
                $target,
                SmartySource::lineAt($html, (int)$match[0][1]),
                'Use tabindex="0" or tabindex="-1" instead.'
            );
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
