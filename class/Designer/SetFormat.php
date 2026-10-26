<?php

declare(strict_types=1);

namespace XoopsModules\Cssholmes\Designer;

use InvalidArgumentException;
use JsonException;

if (!defined('XOOPS_ROOT_PATH')) {
    http_response_code(404);
    exit;
}

/** Portable semantic overrides, always revalidated by the existing CSS allowlist. */
final class SetFormat
{
    public const MAX_BYTES = 65536;
    private const TARGETS = [
        'body' => ['body'], 'link' => ['a'],
        'heading.h1' => ['h1'], 'heading.h2' => ['h2'], 'heading.h3' => ['h3'],
        'heading.h4' => ['h4'], 'heading.h5' => ['h5'], 'heading.h6' => ['h6'],
        'button' => ['button'], 'button.primary' => ['.btn-primary'],
        'input' => ['input', 'textarea', 'select'],
        'comment.title' => ['.xoops-comment-template .xoops-comment-text > h4', '.comTitle'],
        'comment.text' => ['.xoops-comment-text .message-text', '.comText'],
        'article.title' => ['.itemTitle'], 'article.text' => ['.itemText', '.itemBody'],
        'quote' => ['blockquote'], 'table' => ['.outer'], 'table.heading' => ['.head'],
        'message.error' => ['.errorMsg'], 'message.success' => ['.resultMsg'],
        'message.notice' => ['.confirmMsg'],
    ];
    private const ROLES = ['brand', 'onBrand', 'surface', 'text', 'muted', 'border', 'success', 'danger'];

    public function __construct(private readonly ThemeConfigStore $store)
    {
    }

    /** Export only authored overrides; absent properties inherit the destination theme. */
    public function fromRules(string $theme, string $name, array $rules): array
    {
        $rules = $this->store->normalizeRules($rules);
        $styles = [];
        $custom = [];
        foreach ($rules as $rule) {
            $target = null;
            foreach (self::TARGETS as $candidate => $selectors) {
                if (in_array($rule['selector'], $selectors, true)) {
                    $target = $candidate;
                    break;
                }
            }
            if ($target === null) {
                $custom[] = $rule;
                continue;
            }
            $key = implode('|', [$target, $rule['mode'], $rule['breakpoint'], $rule['state']]);
            $styles[$key] = ['target' => $target, 'mode' => $rule['mode'], 'breakpoint' => $rule['breakpoint'],
                'state' => $rule['state'], 'declarations' => array_replace($styles[$key]['declarations'] ?? [], $rule['declarations'])];
        }
        return $this->normalize(['format' => 'xoops-theme-set', 'version' => 1, 'name' => $name,
            'sourceTheme' => $theme, 'defaults' => 'inherit-target-theme', 'tokens' => [],
            'styles' => array_values($styles), 'themeRules' => $custom, 'sourceRules' => $rules]);
    }

    /** Validate uploaded documents, including fields that will not apply to this theme. */
    public function normalize(array $document): array
    {
        $this->checkSize($document);
        $this->fields($document, ['format', 'version', 'name', 'sourceTheme', 'defaults', 'tokens', 'styles', 'themeRules'], ['sourceRules']);
        if ($document['format'] !== 'xoops-theme-set' || $document['version'] !== 1
            || $document['defaults'] !== 'inherit-target-theme') {
            throw new InvalidArgumentException('Unsupported theme set format or defaults.');
        }
        $name = $this->name($document['name']);
        $theme = $document['sourceTheme'];
        if (!is_string($theme) || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $theme) !== 1) {
            throw new InvalidArgumentException('Invalid source theme.');
        }
        if (!is_array($document['tokens']) || array_diff(array_keys($document['tokens']), ['all', 'light', 'dark']) !== []) {
            throw new InvalidArgumentException('Invalid token modes.');
        }
        $tokens = [];
        foreach ($document['tokens'] as $mode => $roles) {
            if (!is_array($roles) || array_diff(array_keys($roles), self::ROLES) !== []) {
                throw new InvalidArgumentException('Invalid semantic color role.');
            }
            $tokens[$mode] = [];
            foreach ($roles as $role => $token) {
                if (!is_array($token)) {
                    throw new InvalidArgumentException('Invalid color token.');
                }
                $this->fields($token, ['$type', '$value']);
                $value = $token['$value'];
                if ($token['$type'] !== 'color' || !is_array($value)) {
                    throw new InvalidArgumentException('Only DTCG color tokens are supported.');
                }
                $this->fields($value, ['colorSpace', 'components', 'alpha']);
                if ($value['colorSpace'] !== 'srgb' || !in_array($value['alpha'], [1, 1.0], true)
                    || !is_array($value['components']) || !array_is_list($value['components']) || count($value['components']) !== 3) {
                    throw new InvalidArgumentException('Only opaque sRGB colors are supported.');
                }
                foreach ($value['components'] as $component) {
                    if ((!is_int($component) && !is_float($component)) || !is_finite((float) $component) || $component < 0 || $component > 1) {
                        throw new InvalidArgumentException('Invalid sRGB color component.');
                    }
                }
                $tokens[$mode][$role] = ['$type' => 'color', '$value' => ['colorSpace' => 'srgb',
                    'components' => $value['components'], 'alpha' => 1]];
            }
        }
        if (!is_array($document['styles']) || !array_is_list($document['styles']) || count($document['styles']) > 256
            || !is_array($document['themeRules'])) {
            throw new InvalidArgumentException('Invalid portable style list.');
        }
        $styles = [];
        foreach ($document['styles'] as $style) {
            if (!is_array($style)) {
                throw new InvalidArgumentException('Invalid semantic style.');
            }
            $this->fields($style, ['target', 'mode', 'breakpoint', 'state', 'declarations']);
            if (!is_string($style['target']) || !isset(self::TARGETS[$style['target']])) {
                throw new InvalidArgumentException('Unknown semantic target.');
            }
            $validated = $this->store->normalizeRules([['selector' => self::TARGETS[$style['target']][0], 'label' => $style['target'],
                'mode' => $style['mode'], 'breakpoint' => $style['breakpoint'], 'state' => $style['state'], 'declarations' => $style['declarations']]]);
            if ($validated !== []) {
                $key = implode('|', [$style['target'], $style['mode'], $style['breakpoint'], $style['state']]);
                $style['declarations'] = array_replace($styles[$key]['declarations'] ?? [], $validated[0]['declarations']);
                $styles[$key] = $style;
            }
        }
        $normalized = ['format' => 'xoops-theme-set', 'version' => 1, 'name' => $name, 'sourceTheme' => $theme,
            'defaults' => 'inherit-target-theme', 'tokens' => $tokens, 'styles' => array_values($styles),
            'themeRules' => $this->store->normalizeRules($document['themeRules'])];
        if (array_key_exists('sourceRules', $document)) {
            if (!is_array($document['sourceRules'])) {
                throw new InvalidArgumentException('Invalid source rule snapshot.');
            }
            $normalized['sourceRules'] = $this->store->normalizeRules($document['sourceRules']);
        }
        $this->checkSize($normalized);
        return $normalized;
    }

    /** Custom selectors remain local to their originating theme; semantic targets travel. */
    public function resolve(array $document, string $targetTheme): array
    {
        $document = $this->normalize($document);
        if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $targetTheme) !== 1) {
            throw new InvalidArgumentException('Invalid destination theme.');
        }
        $rules = [];
        foreach ($document['tokens'] as $mode => $tokens) {
            $colors = [];
            foreach ($tokens as $role => $token) {
                $colors[$role] = $this->hex($token);
            }
            foreach ($this->tokenStyles($colors) as $target => $declarations) {
                foreach (self::TARGETS[$target] as $selector) {
                    $this->merge($rules, ['selector' => $selector, 'label' => $target, 'mode' => $mode,
                        'breakpoint' => 'all', 'state' => 'normal', 'declarations' => $declarations]);
                }
            }
        }
        $exactSource = $document['sourceTheme'] === $targetTheme && array_key_exists('sourceRules', $document);
        foreach ($exactSource ? [] : $document['styles'] as $style) {
            foreach (self::TARGETS[$style['target']] as $selector) {
                $this->merge($rules, ['selector' => $selector, 'label' => $style['target'], 'mode' => $style['mode'],
                    'breakpoint' => $style['breakpoint'], 'state' => $style['state'], 'declarations' => $style['declarations']]);
            }
        }
        $warnings = [];
        if ($document['sourceTheme'] === $targetTheme) {
            foreach ($document['sourceRules'] ?? $document['themeRules'] as $rule) {
                $this->merge($rules, $rule);
            }
        } else {
            $semanticSelectors = array_merge(...array_values(self::TARGETS));
            $customSnapshot = array_filter($document['sourceRules'] ?? [], static fn(array $rule): bool => !in_array($rule['selector'], $semanticSelectors, true));
            if ($document['themeRules'] !== [] || $customSnapshot !== []) {
                $warnings[] = 'Theme-specific selectors were omitted because this set comes from another theme.';
            }
        }
        return ['rules' => $this->store->normalizeRules(array_values($rules)), 'warnings' => $warnings];
    }

    /** Curated color pairs, with measured contrast for these pairs only. */
    public function builtins(): array
    {
        $palettes = [
            ['ocean', 'Ocean', '#075985', '#7dd3fc', '#f0f9ff', '#082f49'],
            ['forest', 'Forest', '#166534', '#86efac', '#f0fdf4', '#052e16'],
            ['plum', 'Plum', '#6b21a8', '#d8b4fe', '#faf5ff', '#3b0764'],
            ['ember', 'Ember', '#9a3412', '#fdba74', '#fff7ed', '#431407'],
            ['rose', 'Rose', '#9f1239', '#fda4af', '#fff1f2', '#4c0519'],
            ['slate', 'Slate', '#334155', '#cbd5e1', '#f8fafc', '#0f172a'],
        ];
        $result = [];
        foreach ($palettes as [$id, $name, $brand, $darkBrand, $surface, $text]) {
            $modes = [
                'light' => ['brand' => $brand, 'onBrand' => '#ffffff', 'surface' => $surface, 'text' => $text,
                    'muted' => '#475569', 'border' => '#64748b', 'success' => '#166534', 'danger' => '#991b1b'],
                'dark' => ['brand' => $darkBrand, 'onBrand' => '#0f172a', 'surface' => '#0f172a', 'text' => '#f8fafc',
                    'muted' => '#cbd5e1', 'border' => '#94a3b8', 'success' => '#86efac', 'danger' => '#fca5a5'],
            ];
            $tokens = [];
            $contrast = [];
            foreach ($modes as $mode => $roles) {
                foreach ($roles as $role => $hex) {
                    $tokens[$mode][$role] = $this->token($hex);
                }
                foreach ([['text', 'surface'], ['onBrand', 'brand'], ['muted', 'surface'], ['success', 'surface'], ['danger', 'surface']] as [$foreground, $background]) {
                    $ratio = $this->contrast($roles[$foreground], $roles[$background]);
                    $contrast[] = ['name' => $mode . ' ' . $foreground . '/' . $background, 'ratio' => round($ratio, 2), 'pass' => $ratio >= 4.5];
                }
            }
            $payload = $this->fromRules('portable', $name, []);
            $payload['tokens'] = $tokens;
            $result[] = ['id' => 'palette-' . $id, 'name' => $name, 'payload' => $this->normalize($payload),
                'colors' => [$brand, $darkBrand, $surface, $text], 'contrast' => $contrast];
        }
        return $result;
    }

    private function tokenStyles(array $colors): array
    {
        $styles = [];
        foreach (['body', 'input', 'comment.text', 'article.text', 'quote', 'table', 'message.notice'] as $target) {
            foreach (['surface' => 'background-color', 'text' => 'color', 'border' => 'border-color'] as $role => $property) {
                if (isset($colors[$role])) {
                    $styles[$target][$property] = $colors[$role];
                }
            }
        }
        foreach (['link', 'article.title', 'comment.title'] as $target) {
            if (isset($colors['brand'])) {
                $styles[$target]['color'] = $colors['brand'];
            }
        }
        foreach (['heading.h1', 'heading.h2', 'heading.h3', 'heading.h4', 'heading.h5', 'heading.h6'] as $target) {
            if (isset($colors['text'])) {
                $styles[$target]['color'] = $colors['text'];
            }
        }
        foreach (['button', 'button.primary', 'table.heading'] as $target) {
            foreach (['brand' => 'background-color', 'onBrand' => 'color'] as $role => $property) {
                if (isset($colors[$role])) {
                    $styles[$target][$property] = $colors[$role];
                }
            }
        }
        foreach (['message.success' => 'success', 'message.error' => 'danger'] as $target => $role) {
            if (isset($colors[$role])) {
                $styles[$target]['color'] = $colors[$role];
            }
            if (isset($colors['surface'])) {
                $styles[$target]['background-color'] = $colors['surface'];
            }
        }
        if (isset($colors['muted'])) {
            $styles['quote']['color'] = $colors['muted'];
        }
        return $styles;
    }

    private function merge(array &$rules, array $rule): void
    {
        $key = implode('|', [$rule['selector'], $rule['mode'], $rule['breakpoint'], $rule['state']]);
        $rule['declarations'] = array_replace($rules[$key]['declarations'] ?? [], $rule['declarations']);
        $rules[$key] = $rule;
    }

    private function fields(array $value, array $fields, array $optional = []): void
    {
        if (array_diff(array_keys($value), array_merge($fields, $optional)) !== [] || array_diff($fields, array_keys($value)) !== []) {
            throw new InvalidArgumentException('Missing or unknown theme set field.');
        }
    }

    private function checkSize(array $document): void
    {
        try {
            $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, 16);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid theme set document.', 0, $error);
        }
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Theme set exceeds 64 KiB.');
        }
    }

    private function name(mixed $name): string
    {
        if (!is_string($name) || preg_match('/^[^<>\p{C}]{1,80}$/uD', $name) !== 1 || trim($name) === '') {
            throw new InvalidArgumentException('Set names must contain 1–80 plain Unicode characters.');
        }
        return trim($name);
    }

    private function token(string $hex): array
    {
        return ['$type' => 'color', '$value' => ['colorSpace' => 'srgb',
            'components' => array_map(static fn(int $offset): float => hexdec(substr($hex, $offset, 2)) / 255, [1, 3, 5]), 'alpha' => 1]];
    }

    private function hex(array $token): string
    {
        return '#' . implode('', array_map(static fn(int|float $component): string => sprintf('%02x', (int) round($component * 255)), $token['$value']['components']));
    }

    private function contrast(string $first, string $second): float
    {
        $luminance = static function (string $hex): float {
            $channels = [];
            foreach ([1, 3, 5] as $offset) {
                $channel = hexdec(substr($hex, $offset, 2)) / 255;
                $channels[] = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }
            return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        };
        $a = $luminance($first);
        $b = $luminance($second);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }
}
