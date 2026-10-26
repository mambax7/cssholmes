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
 * Smarty-aware views of a XOOPS template source.
 *
 * The analyzers run regular expressions over raw template files. A Smarty tag
 * such as <{$block.title}> contains "}>", which ends an HTML tag early when a
 * naive pattern like /<img([^>]*)>/ meets it inside an attribute. This helper
 * turns a template into something the HTML checks can read safely:
 *
 *  - comments <{* ... *}> are removed;
 *  - <{literal}> wrappers are dropped and their content is kept verbatim;
 *  - output tags (<{$var}>, <{xoImgUrl ...}>, <{include ...}>) become the
 *    placeholder text SMARTY, so attributes keep a non-empty value and links
 *    with Smarty text are not reported as empty;
 *  - control tags (<{if}>, <{foreach}>, <{assign}>, closing tags) become a
 *    single space.
 *
 * Every view keeps the original line breaks, so line numbers found in a view
 * match the source file.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */
final class SmartySource
{
    public const PLACEHOLDER = 'SMARTY';

    /**
     * One Smarty tag, with quoted strings allowed to contain "}>".
     */
    public const TAG_PATTERN = '/<\{\*.*?\*\}>'
        . '|<\{(?:[^}"\'`]|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`[^`]*`|\}(?!>))*\}>/s';

    private const CONTROL_KEYWORDS = [
        'if', 'elseif', 'else', 'foreach', 'foreachelse', 'for', 'forelse',
        'section', 'sectionelse', 'while', 'assign', 'append', 'capture',
        'strip', 'nocache', 'block', 'function', 'call', 'break', 'continue',
        'config_load', 'extends', 'setfilter',
    ];

    /**
     * Remove Smarty comments, keeping line breaks.
     */
    public static function stripComments(string $source): string
    {
        return preg_replace_callback(
            '/<\{\*.*?\*\}>/s',
            static fn (array $match): string => self::newlinesOf($match[0]),
            $source
        ) ?? $source;
    }

    /**
     * Replace every Smarty tag by a neutral placeholder (see class comment).
     */
    public static function neutralise(string $source): string
    {
        $parts = preg_split(
            '/(<\{literal\}>.*?<\{\/literal\}>)/s',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if (false === $parts) {
            return $source;
        }

        $result = '';
        foreach ($parts as $part) {
            if (str_starts_with($part, '<{literal}>')) {
                $result .= substr($part, 11, -12);
                continue;
            }

            $result .= preg_replace_callback(
                self::TAG_PATTERN,
                static fn (array $match): string => self::replacementFor($match[0]),
                $part
            ) ?? $part;
        }

        return $result;
    }

    /**
     * A view for HTML checks: Smarty neutralised, and the bodies of <script>,
     * <style> and HTML comments blanked out.
     */
    public static function htmlView(string $source): string
    {
        $html = self::neutralise($source);

        $html = preg_replace_callback(
            '/(<(script|style)\b[^>]*>)(.*?)(<\/\2\s*>)/si',
            static fn (array $match): string => $match[1] . self::newlinesOf($match[3]) . $match[4],
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/<!--.*?-->/s',
            static fn (array $match): string => self::newlinesOf($match[0]),
            $html
        ) ?? $html;
    }

    /**
     * Whether an attribute string contains Smarty output outside quoted
     * values, for example <img <{$attributes}>>. Such attributes are unknown
     * until render time, so checks that look for a missing attribute skip them.
     */
    public static function hasDynamicAttributes(string $attributes): bool
    {
        $unquoted = preg_replace('/"[^"]*"|\'[^\']*\'/', '""', $attributes) ?? $attributes;

        return 1 === preg_match('/(?:^|\s)' . self::PLACEHOLDER . '(?:\s|$)/', $unquoted);
    }

    /**
     * Line number (1-based) of a byte offset.
     */
    public static function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, max(0, $offset)), "\n") + 1;
    }

    private static function replacementFor(string $tag): string
    {
        $newlines = self::newlinesOf($tag);
        if (str_starts_with($tag, '<{*')) {
            return $newlines;
        }

        $inner = trim(substr($tag, 2, -2));
        if ('' === $inner || str_starts_with($inner, '/')) {
            return ' ' . $newlines;
        }

        if (
            preg_match('/^([a-z_][a-z0-9_]*)/i', $inner, $match)
            && in_array(strtolower($match[1]), self::CONTROL_KEYWORDS, true)
        ) {
            return ' ' . $newlines;
        }

        return self::PLACEHOLDER . $newlines;
    }

    private static function newlinesOf(string $text): string
    {
        return str_repeat("\n", substr_count($text, "\n"));
    }
}
