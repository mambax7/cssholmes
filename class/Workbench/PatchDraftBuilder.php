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

final class PatchDraftBuilder
{
    /**
     * @param array<int, array<string, mixed>> $changes
     * @param array<int, array<string, mixed>> $suggestions
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(?ThemeTarget $theme, array $changes, array $suggestions): array
    {
        $drafts = [];

        foreach ($changes as $index => $change) {
            if (!is_array($change)) {
                continue;
            }

            $suggestion = is_array($suggestions[$index] ?? null) ? $suggestions[$index] : [];
            $drafts[] = $this->buildDraft($theme, $change, $suggestion);
        }

        return $drafts;
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array<string, mixed>
     */
    private function buildDraft(?ThemeTarget $theme, array $change, array $suggestion): array
    {
        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';
        $selector = is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '';
        $widget = is_string($change['widget'] ?? null) ? trim((string)$change['widget']) : '';
        $slot = is_string($change['slot'] ?? null) ? trim((string)$change['slot']) : '';
        $before = is_string($change['before'] ?? null) ? (string)$change['before'] : '';
        $after = is_string($change['after'] ?? null) ? (string)$change['after'] : '';
        $styleProperty = is_string($change['style_property'] ?? null) ? trim((string)$change['style_property']) : '';
        $tokenKey = is_string($change['token_key'] ?? null) ? trim((string)$change['token_key']) : '';
        $tokenProperties = is_array($change['token_properties'] ?? null)
            ? array_values(array_filter($change['token_properties'], 'is_string'))
            : [];
        $targets = array_values(array_filter(
            is_array($suggestion['targets'] ?? null) ? $suggestion['targets'] : [],
            'is_string'
        ));
        $target = (string)($targets[0] ?? '');

        return match ($kind) {
            'text' => [
                'title' => 'Text replacement draft',
                'target' => $target,
                'format' => 'search-replace',
                'content' => $this->textDraft($theme, $target, $selector, $widget, $slot, $before, $after),
            ],
            'inspect' => [
                'title' => 'Inspection review draft',
                'target' => $target,
                'format' => 'review-note',
                'content' => $this->inspectDraft($theme, $target, $change),
            ],
            'token', 'color' => [
                'title' => 'Theme token draft',
                'target' => $target !== '' ? $target : 'theme.json',
                'format' => 'json-fragment',
                'content' => $this->tokenDraft($theme, $target, $selector, $before, $after, $tokenKey, $tokenProperties),
            ],
            default => [
                'title' => 'Theme patch draft',
                'target' => $target,
                'format' => 'css-template',
                'content' => $this->styleDraft($selector, $widget, $slot, $styleProperty, $before, $after),
            ],
        };
    }

    private function textDraft(
        ?ThemeTarget $theme,
        string $target,
        string $selector,
        string $widget,
        string $slot,
        string $before,
        string $after
    ): string {
        $lines = [];
        $lines[] = '# cssHolmes draft';
        if (null !== $theme) {
            $lines[] = '# Theme: ' . $theme->key . ' [' . $theme->scope . ']';
        }
        if ('' !== $target) {
            $lines[] = '# Target: ' . $target;
        }
        if ('' !== $selector) {
            $lines[] = '# Selector: ' . $selector;
        }
        if ('' !== $widget) {
            $lines[] = '# Widget: ' . $widget;
        }
        if ('' !== $slot) {
            $lines[] = '# Slot: ' . $slot;
        }
        $lines[] = '';
        $lines[] = 'Search for:';
        $lines[] = $before;
        $lines[] = '';
        $lines[] = 'Replace with:';
        $lines[] = $after;

        return implode("\n", $lines);
    }

    /**
     * @param string[] $tokenProperties
     */
    private function tokenDraft(
        ?ThemeTarget $theme,
        string $target,
        string $selector,
        string $before,
        string $after,
        string $tokenKey,
        array $tokenProperties
    ): string
    {
        $lines = [];
        $lines[] = '{';
        $note = '' !== $tokenKey
            ? 'cssHolmes draft: review and apply this token value in theme.json.'
            : 'cssHolmes draft: map this change to the correct token name before applying.';
        $lines[] = '  "_note": "' . $this->jsonEscape($note) . '",';
        if (null !== $theme) {
            $lines[] = '  "_theme": "' . $this->jsonEscape($theme->key) . '",';
        }
        if ('' !== $target) {
            $lines[] = '  "_target": "' . $this->jsonEscape($target) . '",';
        }
        if ('' !== $selector) {
            $lines[] = '  "_selector": "' . $this->jsonEscape($selector) . '",';
        }
        if ('' !== $tokenKey) {
            $lines[] = '  "_token_key": "' . $this->jsonEscape($tokenKey) . '",';
        }
        if ([] !== $tokenProperties) {
            $lines[] = '  "_token_properties": ["' . implode('", "', array_map([$this, 'jsonEscape'], $tokenProperties)) . '"],';
        }
        $lines[] = '  "_before": "' . $this->jsonEscape($before) . '",';
        $lines[] = '  "_after": "' . $this->jsonEscape($after) . '",';
        $lines[] = '  "tokens": {';
        $lines[] = '    "' . $this->jsonEscape('' !== $tokenKey ? $tokenKey : 'TODO_TOKEN_NAME') . '": "' . $this->jsonEscape('' !== $after ? $after : 'TODO_NEW_VALUE') . '"';
        $lines[] = '  }';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function styleDraft(string $selector, string $widget, string $slot, string $styleProperty, string $before, string $after): string
    {
        $lines = [];
        $lines[] = '/* cssHolmes draft */';
        if ('' !== $widget) {
            $lines[] = '/* Widget: ' . $widget . ' */';
        }
        if ('' !== $slot) {
            $lines[] = '/* Slot: ' . $slot . ' */';
        }
        $lines[] = '' !== $selector ? $selector . ' {' : '/* Add the most specific selector here */';
        if ('' !== $selector) {
            if ('' !== $styleProperty) {
                $lines[] = '    /* Before: ' . $before . ' */';
                $lines[] = '    ' . $styleProperty . ': ' . ('' !== $after ? $after : 'TODO_NEW_VALUE') . ';';
            } else {
                $lines[] = '    /* TODO: apply the style/layout change observed in the toolbar */';
            }
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $change
     */
    private function inspectDraft(?ThemeTarget $theme, string $target, array $change): string
    {
        $inspection = is_array($change['inspection'] ?? null) ? $change['inspection'] : [];
        $lines = [];
        $lines[] = '# cssHolmes inspection draft';
        if (null !== $theme) {
            $lines[] = '# Theme: ' . $theme->key . ' [' . $theme->scope . ']';
        }
        if ('' !== $target) {
            $lines[] = '# Review target: ' . $target;
        }
        if (is_string($change['selector'] ?? null) && '' !== trim((string)$change['selector'])) {
            $lines[] = '# Selector: ' . trim((string)$change['selector']);
        }
        if (is_string($change['widget'] ?? null) && '' !== trim((string)$change['widget'])) {
            $lines[] = '# Widget: ' . trim((string)$change['widget']);
        }
        if (is_string($change['slot'] ?? null) && '' !== trim((string)$change['slot'])) {
            $lines[] = '# Slot: ' . trim((string)$change['slot']);
        }
        if (is_string($change['summary'] ?? null) && '' !== trim((string)$change['summary'])) {
            $lines[] = '# Summary: ' . trim((string)$change['summary']);
        }
        $lines[] = '';
        foreach (['size', 'position', 'margin', 'padding', 'font', 'color', 'theme'] as $key) {
            if (is_string($inspection[$key] ?? null) && '' !== trim((string)$inspection[$key])) {
                $lines[] = ucfirst($key) . ': ' . trim((string)$inspection[$key]);
            }
        }
        if (is_array($inspection['tokens'] ?? null) && [] !== $inspection['tokens']) {
            $lines[] = 'Tokens: ' . implode(', ', array_map('strval', $inspection['tokens']));
        }
        $lines[] = '';
        $lines[] = 'Review this snapshot and convert it into a token, CSS, or template patch as needed.';

        return implode("\n", $lines);
    }

    private function jsonEscape(string $value): string
    {
        return addcslashes($value, "\\\"\n\r\t/");
    }
}
