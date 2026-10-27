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
 * Resolves <{include file="..."}> tags inside one theme folder.
 *
 * XOOPS sets the Smarty template directory to the themes folder, so themes
 * normally include their partials as "$theme_name/tpl/header.tpl" after
 * <{assign var=theme_name value=$xoTheme->folderName}>. This resolver
 * understands:
 *
 *  - variables assigned from $xoTheme->folderName or $xoops_theme in the theme
 *    (plus the conventional $theme_name and $xoops_theme), written as $name,
 *    {$name} or `$name` inside double quotes;
 *  - $xoTheme->folderName used directly, including `$xoTheme->folderName`;
 *  - |cat:'...' chains on an unquoted variable;
 *  - plain relative names, looked up from the theme root and then from the
 *    folder of the including template.
 *
 * db: templates, absolute file: paths and anything still holding a variable
 * are reported as not resolvable and never as missing.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */
final class TemplateIncludeResolver
{
    public const STATUS_THEME = 'theme';
    public const STATUS_MODULE = 'db';
    public const STATUS_EXTERNAL = 'external';
    public const STATUS_DYNAMIC = 'dynamic';

    private const MAX_DEPTH = 10;

    private const INCLUDE_PATTERN = '/<\{\s*include\s+([^}]*(?:\}(?!>)[^}]*)*)\}>/s';

    private string $themePath;
    private string $themeName;

    /** @var string[]|null */
    private ?array $themeVariables = null;

    public function __construct(string $themePath)
    {
        $this->themePath = rtrim(str_replace('\\', '/', $themePath), '/');
        $this->themeName = basename($this->themePath);
    }

    public function themeName(): string
    {
        return $this->themeName;
    }

    /**
     * All include tags of a template.
     *
     * @return array<int, array{tag: string, argument: string, line: int, status: string, path: string, exists: bool}>
     */
    public function includesIn(string $source, string $currentFile = ''): array
    {
        $source = SmartySource::stripComments($source);
        if (!preg_match_all(self::INCLUDE_PATTERN, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $includes = [];
        foreach ($matches as $match) {
            $argument = $this->fileArgument((string)$match[1][0]);
            if ('' === $argument) {
                continue;
            }

            $resolved = $this->resolve($argument, $currentFile);
            $includes[] = [
                'tag' => (string)$match[0][0],
                'argument' => $argument,
                'line' => SmartySource::lineAt($source, (int)$match[0][1]),
                'status' => $resolved['status'],
                'path' => $resolved['path'],
                'exists' => $resolved['exists'],
            ];
        }

        return $includes;
    }

    /**
     * Resolve the value of a file= argument (quotes included).
     *
     * @return array{status: string, path: string, exists: bool}
     */
    public function resolve(string $argument, string $currentFile = ''): array
    {
        $value = $this->evaluate(trim($argument));
        if (null === $value || str_contains($value, '$') || str_contains($value, '`')) {
            return ['status' => self::STATUS_DYNAMIC, 'path' => '', 'exists' => false];
        }

        if (str_starts_with($value, 'db:')) {
            return ['status' => self::STATUS_MODULE, 'path' => substr($value, 3), 'exists' => false];
        }

        if (str_starts_with($value, 'file:')) {
            $value = substr($value, 5);
        }

        $value = str_replace('\\', '/', $value);
        if (preg_match('#^(?:[a-z]+:|/|[A-Za-z]:/)#i', $value)) {
            return ['status' => self::STATUS_EXTERNAL, 'path' => $value, 'exists' => false];
        }

        if (str_starts_with($value, $this->themeName . '/')) {
            $relative = $this->normalise(substr($value, strlen($this->themeName) + 1));

            return $relative === '../'
                ? ['status' => self::STATUS_EXTERNAL, 'path' => $value, 'exists' => false]
                : $this->themeResult($relative);
        }

        $relative = $this->normalise($value);
        if (str_starts_with($relative, '../')) {
            return ['status' => self::STATUS_EXTERNAL, 'path' => $value, 'exists' => false];
        }

        if (is_file($this->themePath . '/' . $relative)) {
            return $this->themeResult($relative);
        }

        $currentDirectory = dirname(str_replace('\\', '/', $currentFile));
        if ('' !== $currentFile && '.' !== $currentDirectory) {
            $nearby = $this->normalise($currentDirectory . '/' . $value);
            if (!str_starts_with($nearby, '../') && is_file($this->themePath . '/' . $nearby)) {
                return $this->themeResult($nearby);
            }
        }

        $firstSegment = explode('/', $relative)[0];
        if (
            str_contains($relative, '/') && !is_dir($this->themePath . '/' . $firstSegment)
            && is_dir(dirname($this->themePath) . '/' . $firstSegment)
        ) {
            return ['status' => self::STATUS_EXTERNAL, 'path' => $value, 'exists' => false];
        }

        return $this->themeResult($relative);
    }

    /**
     * The page template (theme.tpl or theme.html) with every resolvable
     * theme include replaced by the content of the included file.
     */
    public function expandedPage(): string
    {
        foreach (['theme.tpl', 'theme.html'] as $candidate) {
            if (is_file($this->themePath . '/' . $candidate)) {
                return $this->expand($candidate);
            }
        }

        return '';
    }

    /**
     * A template with its theme includes inlined, recursively.
     *
     * @param string[] $stack
     */
    public function expand(string $relativeFile, int $depth = 0, array $stack = []): string
    {
        $fullPath = $this->themePath . '/' . $relativeFile;
        if (!is_file($fullPath)) {
            return '';
        }

        $source = file_get_contents($fullPath);
        if (false === $source) {
            return '';
        }

        $source = SmartySource::stripComments($source);
        if ($depth >= self::MAX_DEPTH) {
            return $source;
        }

        $stack[] = $relativeFile;

        return preg_replace_callback(
            self::INCLUDE_PATTERN,
            function (array $match) use ($relativeFile, $depth, $stack): string {
                $argument = $this->fileArgument($match[1]);
                if ('' === $argument) {
                    return $match[0];
                }

                $resolved = $this->resolve($argument, $relativeFile);
                if (
                    self::STATUS_THEME !== $resolved['status'] || !$resolved['exists']
                    || in_array($resolved['path'], $stack, true)
                ) {
                    return $match[0];
                }

                return $this->expand($resolved['path'], $depth + 1, $stack);
            },
            $source
        ) ?? $source;
    }

    /**
     * Names of variables that hold the theme folder name.
     *
     * @return string[]
     */
    public function themeVariables(): array
    {
        if (null !== $this->themeVariables) {
            return $this->themeVariables;
        }

        $variables = ['theme_name', 'xoops_theme'];
        foreach (['theme.tpl', 'theme.html'] as $candidate) {
            $path = $this->themePath . '/' . $candidate;
            if (!is_file($path)) {
                continue;
            }

            $source = (string)file_get_contents($path);
            $pattern = '/<\{\s*assign\s+var\s*=\s*["\']?([a-z_][a-z0-9_]*)["\']?\s+value\s*=\s*'
                . '(?:\$xoTheme->folderName|\$xoops_theme)\s*\}>/i';
            if (preg_match_all($pattern, $source, $matches)) {
                $variables = array_merge($variables, $matches[1]);
            }
        }

        $this->themeVariables = array_values(array_unique($variables));

        return $this->themeVariables;
    }

    /**
     * Extract the raw file= value from the attributes of an include tag.
     */
    private function fileArgument(string $attributes): string
    {
        $pattern = '/(?:^|\s)file\s*=\s*("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\''
            . '|(?:[^\s"\']|"[^"]*"|\'[^\']*\')+)/';
        if (!preg_match($pattern, trim($attributes), $match)) {
            return '';
        }

        return trim($match[1]);
    }

    /**
     * Turn a file= value into a literal path, or null when it cannot be known.
     */
    private function evaluate(string $argument): ?string
    {
        if (str_starts_with($argument, "'") && str_ends_with($argument, "'") && strlen($argument) >= 2) {
            return substr($argument, 1, -1);
        }

        if (str_starts_with($argument, '"') && str_ends_with($argument, '"') && strlen($argument) >= 2) {
            return $this->interpolate(substr($argument, 1, -1));
        }

        // Unquoted: $var|cat:'...'|cat:"..."
        $segments = preg_split('/\|cat:/', $argument);
        if (false === $segments) {
            return null;
        }

        $value = '';
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (preg_match('/^(["\'])(.*)\1$/s', $segment, $match)) {
                $value .= '"' === $match[1] ? $this->interpolate($match[2]) : $match[2];
                continue;
            }

            $variable = $this->themeFolderVariable($segment);
            if (null === $variable) {
                return null;
            }

            $value .= $variable;
        }

        return $value;
    }

    /**
     * Replace theme-name variables inside a double-quoted Smarty string.
     */
    private function interpolate(string $value): string
    {
        $folderPattern = '/`\$xoTheme->folderName`|\{\$xoTheme->folderName\}|\$xoTheme->folderName/';
        $value = preg_replace($folderPattern, $this->themeName, $value) ?? $value;

        foreach ($this->themeVariables() as $variable) {
            $quoted = preg_quote($variable, '/');
            $pattern = '/`\$' . $quoted . '`|\{\$' . $quoted . '\}|\$' . $quoted . '(?![a-z0-9_.\->\[])/i';
            $value = preg_replace($pattern, $this->themeName, $value) ?? $value;
        }

        return $value;
    }

    private function themeFolderVariable(string $expression): ?string
    {
        if ('$xoTheme->folderName' === $expression) {
            return $this->themeName;
        }

        foreach ($this->themeVariables() as $variable) {
            if ('$' . $variable === $expression) {
                return $this->themeName;
            }
        }

        return null;
    }

    /**
     * @return array{status: string, path: string, exists: bool}
     */
    private function themeResult(string $relative): array
    {
        return [
            'status' => self::STATUS_THEME,
            'path' => $relative,
            'exists' => '' !== $relative && is_file($this->themePath . '/' . $relative),
        ];
    }

    private function normalise(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    // Leaves the theme folder: never resolvable inside it.
                    return '../';
                }
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
