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

final class WidgetOutputAnalyzer implements AnalyzerInterface
{
    private const INLINE_HANDLERS = [
        'onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout',
        'onfocus', 'onblur', 'onsubmit', 'onchange', 'onkeydown',
        'onkeyup', 'onkeypress',
    ];

    public function name(): string
    {
        return 'widget-output';
    }

    public function label(): string
    {
        return 'Widget Output Analyzer';
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        if (null === $context->widgetName || '' === trim($context->widgetName)) {
            return [];
        }

        $html = trim($context->themePath);
        $widgetName = trim($context->widgetName);
        if ('' === $html) {
            return [
                new Finding(
                    Severity::Info,
                    'widget/empty-output',
                    "Widget '{$widgetName}' renders empty HTML with default props",
                    $widgetName,
                    suggestion: 'Ensure the widget produces visible output with defaults.'
                ),
            ];
        }

        $findings = [];
        $this->checkRootClass($html, $widgetName, $findings);
        $this->checkSvgDimensions($html, $widgetName, $findings);
        $this->checkImgAlt($html, $widgetName, $findings);
        $this->checkImgDimensions($html, $widgetName, $findings);
        $this->checkInlineHandlers($html, $widgetName, $findings);
        $this->checkJsHref($html, $widgetName, $findings);
        $this->checkEmptyAttributes($html, $widgetName, $findings);
        $this->checkFormCsrf($html, $widgetName, $findings);
        $this->checkNestedInteractive($html, $widgetName, $findings);
        $this->checkInlineStyles($html, $widgetName, $findings);

        return $findings;
    }

    /** @param Finding[] $findings */
    private function checkRootClass(string $html, string $widgetName, array &$findings): void
    {
        if (preg_match('/^<(\w+)\s[^>]*class="([^"]*)"/', $html, $match)) {
            if (str_contains((string)$match[2], 'xmf-')) {
                return;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'widget/no-root-class',
                "Widget '{$widgetName}' root element has no xmf- prefixed class",
                $widgetName,
                suggestion: 'Add class="xmf-{widgetShortName}" to the root element.'
            );
            return;
        }

        if (preg_match('/^<(\w+)[\s>]/', $html) && !preg_match('/^<(\w+)\s[^>]*class=/', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'widget/no-root-class',
                "Widget '{$widgetName}' root element has no class attribute",
                $widgetName,
                suggestion: 'Add class="xmf-{widgetShortName}" to the root element.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkSvgDimensions(string $html, string $widgetName, array &$findings): void
    {
        if (!preg_match_all('/<svg\b([^>]*)>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $attributes) {
            $hasWidth = (bool)preg_match('/\bwidth\s*=/', $attributes);
            $hasStyleWidth = (bool)preg_match('/style\s*=\s*"[^"]*width\s*:/', $attributes);
            if ($hasWidth || $hasStyleWidth) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'widget/svg-no-dimensions',
                "Widget '{$widgetName}' contains SVG without explicit dimensions",
                $widgetName,
                suggestion: 'Add width and height attributes to <svg>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkImgAlt(string $html, string $widgetName, array &$findings): void
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
                'widget/img-no-alt',
                "Widget '{$widgetName}' contains image without alt attribute",
                $widgetName,
                suggestion: 'Add alt="description" to <img>.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkImgDimensions(string $html, string $widgetName, array &$findings): void
    {
        if (!preg_match_all('/<img\b([^>]*)>/i', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $attributes) {
            $hasWidth = (bool)preg_match('/\bwidth\s*=/', $attributes);
            $hasStyleWidth = (bool)preg_match('/style\s*=\s*"[^"]*width\s*:/', $attributes);
            if ($hasWidth || $hasStyleWidth) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Warning,
                'widget/img-no-dimensions',
                "Widget '{$widgetName}' contains image without dimensions",
                $widgetName,
                suggestion: 'Add width and height attributes to prevent layout shift.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkInlineHandlers(string $html, string $widgetName, array &$findings): void
    {
        foreach (self::INLINE_HANDLERS as $handler) {
            if (!preg_match('/\b' . $handler . '\s*=/i', $html)) {
                continue;
            }

            $findings[] = new Finding(
                Severity::Error,
                'widget/inline-handler',
                "Widget '{$widgetName}' uses inline event handler '{$handler}'",
                $widgetName,
                suggestion: 'Move event handling to JavaScript.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkJsHref(string $html, string $widgetName, array &$findings): void
    {
        if (preg_match('/href\s*=\s*["\']javascript:/i', $html)) {
            $findings[] = new Finding(
                Severity::Error,
                'widget/js-href',
                "Widget '{$widgetName}' uses javascript: link href",
                $widgetName,
                suggestion: 'Use a meaningful URL or a button with an event listener.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkEmptyAttributes(string $html, string $widgetName, array &$findings): void
    {
        if (preg_match('/\bclass\s*=\s*""/', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'widget/empty-attr',
                "Widget '{$widgetName}' has empty class attribute",
                $widgetName,
                suggestion: 'Remove the empty class attribute or provide a value.'
            );
        }

        if (preg_match('/\bid\s*=\s*""/', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'widget/empty-attr',
                "Widget '{$widgetName}' has empty id attribute",
                $widgetName,
                suggestion: 'Remove the empty id attribute or provide a value.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkFormCsrf(string $html, string $widgetName, array &$findings): void
    {
        if (preg_match('/<form\b/i', $html) && !preg_match('/name\s*=\s*["\']XOOPS_TOKEN_REQUEST["\']|name\s*=\s*["\']xoops_token["\']/i', $html)) {
            $findings[] = new Finding(
                Severity::Warning,
                'widget/form-no-csrf',
                "Widget '{$widgetName}' contains form without CSRF token",
                $widgetName,
                suggestion: 'Add XOOPS security token to the form.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkNestedInteractive(string $html, string $widgetName, array &$findings): void
    {
        if (
            preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/si', $html)
            || preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<button\b/si', $html)
            || preg_match('/<button\b[^>]*>(?:(?!<\/button>).)*<a\b/si', $html)
        ) {
            $findings[] = new Finding(
                Severity::Warning,
                'widget/nested-interactive',
                "Widget '{$widgetName}' nests interactive elements",
                $widgetName,
                suggestion: 'Restructure HTML to avoid nested interactive elements.'
            );
        }
    }

    /** @param Finding[] $findings */
    private function checkInlineStyles(string $html, string $widgetName, array &$findings): void
    {
        if (!preg_match('/^<(\w+)\b([^>]*)>/', $html, $match)) {
            return;
        }

        if (str_contains((string)$match[2], 'style=')) {
            return;
        }

        $findings[] = new Finding(
            Severity::Warning,
            'widget/shortcode-no-inline-style',
            "Widget '{$widgetName}' renderHtml() has no inline styles — shortcode path may render unstyled",
            $widgetName,
            suggestion: 'Add critical layout styles inline for shortcode rendering fallback.'
        );
    }
}
