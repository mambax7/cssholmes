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

final class PatchApplier
{
    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{supported: bool, can_apply: bool, message: string, target: string, current: string, proposed: string}
     */
    public function preview(?ThemeTarget $theme, array $change, array $suggestion): array
    {
        if (null === $theme) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'cssHolmes could not resolve the target theme for this change.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';

        return match ($kind) {
            'text' => $this->previewTextChange($theme, $change, $suggestion),
            'token', 'color' => $this->previewTokenChange($theme, $change),
            'style', 'layout', 'measure' => $this->previewStyleChange($theme, $change, $suggestion),
            default => [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Preview is currently available only for text, token, and style changes.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ],
        };
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{success: bool, message: string, target: string, rollback: array<string, mixed>}
     */
    public function apply(?ThemeTarget $theme, array $change, array $suggestion): array
    {
        if (null === $theme) {
            return [
                'success' => false,
                'message' => 'cssHolmes could not resolve the target theme for this change.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';

        return match ($kind) {
            'text' => $this->applyTextChange($theme, $change, $suggestion),
            'token', 'color' => $this->applyTokenChange($theme, $change),
            'style', 'layout', 'measure' => $this->applyStyleChange($theme, $change, $suggestion),
            default => [
                'success' => false,
                'message' => 'Direct apply is currently supported only for text, token, and style changes.',
                'target' => '',
                'rollback' => [],
            ],
        };
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $appliedMeta
     *
     * @return array{success: bool, message: string, target: string}
     */
    public function rollback(?ThemeTarget $theme, array $change, array $appliedMeta): array
    {
        if (null === $theme) {
            return [
                'success' => false,
                'message' => 'cssHolmes could not resolve the target theme for this rollback.',
                'target' => '',
            ];
        }

        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';

        return match ($kind) {
            'text' => $this->rollbackTextChange($theme, $appliedMeta),
            'token', 'color' => $this->rollbackTokenChange($theme, $appliedMeta),
            'style', 'layout', 'measure' => $this->rollbackStyleChange($theme, $change, $appliedMeta),
            default => [
                'success' => false,
                'message' => 'Rollback is currently supported only for text, token, and style changes.',
                'target' => '',
            ],
        };
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{success: bool, message: string, target: string, rollback: array<string, mixed>}
     */
    private function applyTextChange(ThemeTarget $theme, array $change, array $suggestion): array
    {
        $before = is_string($change['before'] ?? null) ? (string)$change['before'] : '';
        $after = is_string($change['after'] ?? null) ? (string)$change['after'] : '';

        $target = $this->resolveTextTarget($theme, $suggestion);
        if ('' === $target) {
            return [
                'success' => false,
                'message' => 'cssHolmes could not find a template target for this text change.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $target);
        if ('' === $absoluteTarget || !is_file($absoluteTarget)) {
            return [
                'success' => false,
                'message' => 'The target template file could not be found.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $existing = file_get_contents($absoluteTarget);
        if (false === $existing) {
            return [
                'success' => false,
                'message' => 'Unable to read the target template file.',
                'target' => '',
                'rollback' => [],
            ];
        }

        if ('' === $before || '' === $after) {
            return [
                'success' => false,
                'message' => 'Text apply requires both before and after text values.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $occurrences = substr_count($existing, $before);
        if (1 !== $occurrences) {
            return [
                'success' => false,
                'message' => 'Text apply requires exactly one match in the target file. Found ' . $occurrences . '.',
                'target' => $target,
                'rollback' => [],
            ];
        }

        $updated = str_replace($before, $after, $existing);
        if (false === file_put_contents($absoluteTarget, $updated, LOCK_EX)) {
            return [
                'success' => false,
                'message' => 'Unable to write the target template file.',
                'target' => '',
                'rollback' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Applied text change to ' . $target . '.',
            'target' => $target,
            'rollback' => [
                'kind' => 'text',
                'target' => $target,
                'original_content' => $existing,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array{success: bool, message: string, target: string, rollback: array<string, mixed>}
     */
    private function applyTokenChange(ThemeTarget $theme, array $change): array
    {
        $tokenKey = is_string($change['token_key'] ?? null) ? trim((string)$change['token_key']) : '';
        $after = is_string($change['after'] ?? null) ? trim((string)$change['after']) : '';

        if ('' === $tokenKey || '' === $after) {
            return [
                'success' => false,
                'message' => 'Token apply requires a token key and a non-empty replacement value.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $manifestPath = $this->manifestPath($theme);
        if (!is_file($manifestPath)) {
            return [
                'success' => false,
                'message' => 'theme.json was not found for the selected theme.',
                'target' => '',
                'rollback' => [],
            ];
        }

        try {
            $json = file_get_contents($manifestPath);
            if (false === $json) {
                throw new \RuntimeException('Unable to read theme.json.');
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('theme.json did not decode to an object.');
            }

            $decoded['tokens'] ??= [];
            if (!is_array($decoded['tokens'])) {
                $decoded['tokens'] = [];
            }
            $previousExists = array_key_exists($tokenKey, $decoded['tokens']);
            $previousValue = $previousExists ? (string)$decoded['tokens'][$tokenKey] : '';

            $decoded['tokens'][$tokenKey] = $after;

            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (false === file_put_contents($manifestPath, $encoded . PHP_EOL, LOCK_EX)) {
                throw new \RuntimeException('Unable to write theme.json.');
            }

            return [
                'success' => true,
                'message' => 'Applied token change to theme.json.',
                'target' => 'theme.json',
                'rollback' => [
                    'kind' => 'token',
                    'token_key' => $tokenKey,
                    'previous_exists' => $previousExists,
                    'previous_value' => $previousValue,
                ],
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Token apply failed: ' . $throwable->getMessage(),
                'target' => '',
                'rollback' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{success: bool, message: string, target: string, rollback: array<string, mixed>}
     */
    private function applyStyleChange(ThemeTarget $theme, array $change, array $suggestion): array
    {
        $selector = is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '';
        $styleProperty = is_string($change['style_property'] ?? null) ? trim((string)$change['style_property']) : '';
        $after = is_string($change['after'] ?? null) ? trim((string)$change['after']) : '';

        if ('' === $selector || '' === $styleProperty || '' === $after) {
            return [
                'success' => false,
                'message' => 'Style apply requires a selector, CSS property, and non-empty value.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $relativeTarget = $this->resolveStylesheetTarget($theme, $suggestion);
        if ('' === $relativeTarget) {
            return [
                'success' => false,
                'message' => 'cssHolmes could not find a stylesheet target for this style change.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $relativeTarget);
        if ('' === $absoluteTarget) {
            return [
                'success' => false,
                'message' => 'The suggested stylesheet target is outside the theme root.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $existing = is_file($absoluteTarget) ? file_get_contents($absoluteTarget) : '';
        if (false === $existing) {
            return [
                'success' => false,
                'message' => 'Unable to read the stylesheet target.',
                'target' => '',
                'rollback' => [],
            ];
        }

        $blockId = substr(sha1($selector . '|' . $styleProperty), 0, 12);
        $block = $this->styleBlock($blockId, $change);
        $pattern = '/\/\* cssHolmes:begin ' . preg_quote($blockId, '/') . ' \*\/.*?\/\* cssHolmes:end ' . preg_quote($blockId, '/') . ' \*\/\s*/s';
        $previousBlock = preg_match($pattern, (string)$existing, $matches) === 1
            ? trim((string)($matches[0] ?? ''))
            : '';
        $updated = preg_match($pattern, (string)$existing) === 1
            ? (string)preg_replace($pattern, $block . PHP_EOL, (string)$existing, 1)
            : rtrim((string)$existing) . PHP_EOL . PHP_EOL . $block . PHP_EOL;

        $directory = dirname($absoluteTarget);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return [
                'success' => false,
                'message' => 'Unable to create the stylesheet directory.',
                'target' => '',
                'rollback' => [],
            ];
        }

        if (false === file_put_contents($absoluteTarget, $updated, LOCK_EX)) {
            return [
                'success' => false,
                'message' => 'Unable to write the stylesheet target.',
                'target' => '',
                'rollback' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Applied style change to ' . $relativeTarget . '.',
            'target' => $relativeTarget,
            'rollback' => [
                'kind' => 'style',
                'target' => $relativeTarget,
                'block_id' => $blockId,
                'previous_block' => $previousBlock,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $appliedMeta
     *
     * @return array{success: bool, message: string, target: string}
     */
    private function rollbackTokenChange(ThemeTarget $theme, array $appliedMeta): array
    {
        $tokenKey = is_string($appliedMeta['token_key'] ?? null) ? trim((string)$appliedMeta['token_key']) : '';
        $previousExists = (bool)($appliedMeta['previous_exists'] ?? false);
        $previousValue = is_string($appliedMeta['previous_value'] ?? null) ? (string)$appliedMeta['previous_value'] : '';

        if ('' === $tokenKey) {
            return [
                'success' => false,
                'message' => 'Rollback metadata is missing the token key.',
                'target' => '',
            ];
        }

        $manifestPath = $this->manifestPath($theme);
        if (!is_file($manifestPath)) {
            return [
                'success' => false,
                'message' => 'theme.json was not found for rollback.',
                'target' => '',
            ];
        }

        try {
            $json = file_get_contents($manifestPath);
            if (false === $json) {
                throw new \RuntimeException('Unable to read theme.json.');
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('theme.json did not decode to an object.');
            }

            $decoded['tokens'] ??= [];
            if (!is_array($decoded['tokens'])) {
                $decoded['tokens'] = [];
            }

            if ($previousExists) {
                $decoded['tokens'][$tokenKey] = $previousValue;
            } else {
                unset($decoded['tokens'][$tokenKey]);
            }

            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (false === file_put_contents($manifestPath, $encoded . PHP_EOL, LOCK_EX)) {
                throw new \RuntimeException('Unable to write theme.json.');
            }

            return [
                'success' => true,
                'message' => 'Rolled back token change in theme.json.',
                'target' => 'theme.json',
            ];
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Token rollback failed: ' . $throwable->getMessage(),
                'target' => '',
            ];
        }
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $appliedMeta
     *
     * @return array{success: bool, message: string, target: string}
     */
    private function rollbackStyleChange(ThemeTarget $theme, array $change, array $appliedMeta): array
    {
        $selector = is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '';
        $styleProperty = is_string($change['style_property'] ?? null) ? trim((string)$change['style_property']) : '';
        $relativeTarget = is_string($appliedMeta['target'] ?? null) ? trim((string)$appliedMeta['target']) : '';
        $blockId = is_string($appliedMeta['block_id'] ?? null) ? trim((string)$appliedMeta['block_id']) : '';
        $previousBlock = is_string($appliedMeta['previous_block'] ?? null) ? trim((string)$appliedMeta['previous_block']) : '';

        if ('' === $relativeTarget) {
            return [
                'success' => false,
                'message' => 'Rollback metadata is missing the stylesheet target.',
                'target' => '',
            ];
        }

        if ('' === $blockId && '' !== $selector && '' !== $styleProperty) {
            $blockId = substr(sha1($selector . '|' . $styleProperty), 0, 12);
        }

        if ('' === $blockId) {
            return [
                'success' => false,
                'message' => 'Rollback metadata is missing the managed style block id.',
                'target' => '',
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $relativeTarget);
        if ('' === $absoluteTarget || !is_file($absoluteTarget)) {
            return [
                'success' => false,
                'message' => 'The stylesheet target for rollback could not be found.',
                'target' => '',
            ];
        }

        $existing = file_get_contents($absoluteTarget);
        if (false === $existing) {
            return [
                'success' => false,
                'message' => 'Unable to read the stylesheet target for rollback.',
                'target' => '',
            ];
        }

        $pattern = '/\/\* cssHolmes:begin ' . preg_quote($blockId, '/') . ' \*\/.*?\/\* cssHolmes:end ' . preg_quote($blockId, '/') . ' \*\/\s*/s';
        $updated = '' !== $previousBlock
            ? (string)preg_replace($pattern, $previousBlock . PHP_EOL, (string)$existing, 1)
            : (string)preg_replace($pattern, '', (string)$existing, 1);

        if ($updated === (string)$existing) {
            return [
                'success' => false,
                'message' => 'No matching cssHolmes-managed style block was found to roll back.',
                'target' => '',
            ];
        }

        if (false === file_put_contents($absoluteTarget, rtrim($updated) . PHP_EOL, LOCK_EX)) {
            return [
                'success' => false,
                'message' => 'Unable to write the stylesheet rollback.',
                'target' => '',
            ];
        }

        return [
            'success' => true,
            'message' => 'Rolled back style change in ' . $relativeTarget . '.',
            'target' => $relativeTarget,
        ];
    }

    /**
     * @param array<string, mixed> $appliedMeta
     *
     * @return array{success: bool, message: string, target: string}
     */
    private function rollbackTextChange(ThemeTarget $theme, array $appliedMeta): array
    {
        $target = is_string($appliedMeta['target'] ?? null) ? trim((string)$appliedMeta['target']) : '';
        $originalContent = is_string($appliedMeta['original_content'] ?? null) ? (string)$appliedMeta['original_content'] : '';

        if ('' === $target) {
            return [
                'success' => false,
                'message' => 'Rollback metadata is missing the template target.',
                'target' => '',
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $target);
        if ('' === $absoluteTarget) {
            return [
                'success' => false,
                'message' => 'The rollback template target is outside the theme root.',
                'target' => '',
            ];
        }

        if (false === file_put_contents($absoluteTarget, $originalContent, LOCK_EX)) {
            return [
                'success' => false,
                'message' => 'Unable to restore the original template file.',
                'target' => '',
            ];
        }

        return [
            'success' => true,
            'message' => 'Rolled back text change in ' . $target . '.',
            'target' => $target,
        ];
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array{supported: bool, can_apply: bool, message: string, target: string, current: string, proposed: string}
     */
    private function previewTokenChange(ThemeTarget $theme, array $change): array
    {
        $tokenKey = is_string($change['token_key'] ?? null) ? trim((string)$change['token_key']) : '';
        $after = is_string($change['after'] ?? null) ? trim((string)$change['after']) : '';

        if ('' === $tokenKey || '' === $after) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Token preview requires a token key and a non-empty replacement value.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $manifestPath = $this->manifestPath($theme);
        if (!is_file($manifestPath)) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'theme.json was not found for the selected theme.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        try {
            $json = file_get_contents($manifestPath);
            if (false === $json) {
                throw new \RuntimeException('Unable to read theme.json.');
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('theme.json did not decode to an object.');
            }

            $tokens = is_array($decoded['tokens'] ?? null) ? $decoded['tokens'] : [];
            $current = array_key_exists($tokenKey, $tokens)
                ? '"' . $tokenKey . '": "' . (string)$tokens[$tokenKey] . '"'
                : '"' . $tokenKey . '": <not currently set>';
            $proposed = '"' . $tokenKey . '": "' . $after . '"';

            return [
                'supported' => true,
                'can_apply' => true,
                'message' => 'Preview of the token change that will be written to theme.json.',
                'target' => 'theme.json',
                'current' => $current,
                'proposed' => $proposed,
            ];
        } catch (\Throwable $throwable) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Token preview failed: ' . $throwable->getMessage(),
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{supported: bool, can_apply: bool, message: string, target: string, current: string, proposed: string}
     */
    private function previewTextChange(ThemeTarget $theme, array $change, array $suggestion): array
    {
        $before = is_string($change['before'] ?? null) ? (string)$change['before'] : '';
        $after = is_string($change['after'] ?? null) ? (string)$change['after'] : '';
        $target = $this->resolveTextTarget($theme, $suggestion);

        if ('' === $target) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'cssHolmes could not find a template target for this text change.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $target);
        if ('' === $absoluteTarget || !is_file($absoluteTarget)) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'The target template file could not be found.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $existing = file_get_contents($absoluteTarget);
        if (false === $existing) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Unable to read the target template file.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        if ('' === $before || '' === $after) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Text preview requires both before and after text values.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $occurrences = substr_count($existing, $before);
        $message = 1 === $occurrences
            ? 'Preview of the template text replacement that will be written.'
            : 'Text apply is blocked until the target file has exactly one match. Current matches: ' . $occurrences . '.';

        return [
            'supported' => true,
            'can_apply' => 1 === $occurrences,
            'message' => $message,
            'target' => $target,
            'current' => $before,
            'proposed' => $after,
        ];
    }

    /**
     * @param array<string, mixed> $change
     * @param array<string, mixed> $suggestion
     *
     * @return array{supported: bool, can_apply: bool, message: string, target: string, current: string, proposed: string}
     */
    private function previewStyleChange(ThemeTarget $theme, array $change, array $suggestion): array
    {
        $selector = is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '';
        $styleProperty = is_string($change['style_property'] ?? null) ? trim((string)$change['style_property']) : '';
        $after = is_string($change['after'] ?? null) ? trim((string)$change['after']) : '';

        if ('' === $selector || '' === $styleProperty || '' === $after) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Style preview requires a selector, CSS property, and non-empty value.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $relativeTarget = $this->resolveStylesheetTarget($theme, $suggestion);
        if ('' === $relativeTarget) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'cssHolmes could not find a stylesheet target for this style change.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $absoluteTarget = $this->absoluteThemePath($theme, $relativeTarget);
        if ('' === $absoluteTarget) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'The suggested stylesheet target is outside the theme root.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $existing = is_file($absoluteTarget) ? file_get_contents($absoluteTarget) : '';
        if (false === $existing) {
            return [
                'supported' => false,
                'can_apply' => false,
                'message' => 'Unable to read the stylesheet target.',
                'target' => '',
                'current' => '',
                'proposed' => '',
            ];
        }

        $blockId = substr(sha1($selector . '|' . $styleProperty), 0, 12);
        $pattern = '/\/\* cssHolmes:begin ' . preg_quote($blockId, '/') . ' \*\/.*?\/\* cssHolmes:end ' . preg_quote($blockId, '/') . ' \*\/\s*/s';
        $current = preg_match($pattern, (string)$existing, $matches) === 1
            ? trim((string)($matches[0] ?? ''))
            : '/* No existing cssHolmes-managed block for this selector/property in the target stylesheet. */';

        return [
            'supported' => true,
            'can_apply' => true,
            'message' => 'Preview of the stylesheet patch that will be written.',
            'target' => $relativeTarget,
            'current' => $current,
            'proposed' => $this->styleBlock($blockId, $change),
        ];
    }

    /**
     * @param array<string, mixed> $suggestion
     */
    private function resolveStylesheetTarget(ThemeTarget $theme, array $suggestion): string
    {
        $targets = array_values(array_filter(
            is_array($suggestion['targets'] ?? null) ? $suggestion['targets'] : [],
            static fn ($target): bool => is_string($target) && str_ends_with(strtolower((string)$target), '.css')
        ));

        foreach ($targets as $target) {
            $absoluteTarget = $this->absoluteThemePath($theme, (string)$target);
            if ('' !== $absoluteTarget && is_file($absoluteTarget)) {
                return (string)$target;
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($theme->path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && 'css' === strtolower($fileInfo->getExtension())) {
                return $this->relativeThemePath($theme, $fileInfo->getPathname());
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $suggestion
     */
    private function resolveTextTarget(ThemeTarget $theme, array $suggestion): string
    {
        $targets = array_values(array_filter(
            is_array($suggestion['targets'] ?? null) ? $suggestion['targets'] : [],
            static fn ($target): bool => is_string($target) && preg_match('/\.(tpl|html|php)$/i', (string)$target) === 1
        ));

        foreach ($targets as $target) {
            $absoluteTarget = $this->absoluteThemePath($theme, (string)$target);
            if ('' !== $absoluteTarget && is_file($absoluteTarget)) {
                return (string)$target;
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($theme->path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && preg_match('/\.(tpl|html|php)$/i', $fileInfo->getFilename()) === 1) {
                return $this->relativeThemePath($theme, $fileInfo->getPathname());
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $change
     */
    private function styleBlock(string $blockId, array $change): string
    {
        $selector = trim((string)($change['selector'] ?? ''));
        $styleProperty = trim((string)($change['style_property'] ?? ''));
        $before = (string)($change['before'] ?? '');
        $after = trim((string)($change['after'] ?? ''));
        $summary = trim((string)($change['summary'] ?? ''));

        $lines = [];
        $lines[] = '/* cssHolmes:begin ' . $blockId . ' */';
        $lines[] = '/* cssHolmes applied style patch */';
        if ('' !== $summary) {
            $lines[] = '/* ' . str_replace('*/', '* /', $summary) . ' */';
        }
        if ('' !== $before) {
            $lines[] = '/* Before: ' . str_replace('*/', '* /', $before) . ' */';
        }
        $lines[] = $selector . ' {';
        $lines[] = '    ' . $styleProperty . ': ' . $after . ';';
        $lines[] = '}';
        $lines[] = '/* cssHolmes:end ' . $blockId . ' */';

        return implode(PHP_EOL, $lines);
    }

    private function absoluteThemePath(ThemeTarget $theme, string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ('' === $relativePath || str_contains($relativePath, '..')) {
            return '';
        }

        $themeRoot = str_replace('\\', '/', rtrim($theme->path, '/\\'));
        $absolute = $themeRoot . '/' . $relativePath;
        $normalized = str_replace('\\', '/', $absolute);

        return str_starts_with($normalized, $themeRoot . '/') ? $absolute : '';
    }

    private function manifestPath(ThemeTarget $theme): string
    {
        return rtrim($theme->path, '/\\') . '/theme.json';
    }

    private function relativeThemePath(ThemeTarget $theme, string $absolutePath): string
    {
        $themeRoot = str_replace('\\', '/', rtrim($theme->path, '/\\'));
        $absolutePath = str_replace('\\', '/', $absolutePath);

        return ltrim(substr($absolutePath, strlen($themeRoot)), '/');
    }
}
