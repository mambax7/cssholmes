<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes\Cli;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

use XoopsModules\Cssholmes\Analyzer\AccessibilityAnalyzer;
use XoopsModules\Cssholmes\Analyzer\AnalysisContext;
use XoopsModules\Cssholmes\Analyzer\AnalyzerInterface;
use XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer;
use XoopsModules\Cssholmes\Analyzer\Finding;
use XoopsModules\Cssholmes\Analyzer\Severity;
use XoopsModules\Cssholmes\Analyzer\TemplateAnalyzer;
use XoopsModules\Cssholmes\Analyzer\ThemeFiles;
use XoopsModules\Cssholmes\Analyzer\ThemeManifestAnalyzer;

/**
 * Command-line theme scan: runs the theme analyzers on one or more theme
 * folders without a XOOPS installation, for release checks and CI.
 *
 * Exit codes: 0 = pass, 1 = findings at or above --fail-on, 2 = usage error.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */
final class ScanCommand
{
    public const EXIT_PASS = 0;
    public const EXIT_FINDINGS = 1;
    public const EXIT_USAGE = 2;

    private const FAIL_LEVELS = ['error', 'warning', 'info', 'never'];

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * @param string[] $argv arguments without the script name
     */
    public function run(array $argv): int
    {
        try {
            $options = $this->parse($argv);
        } catch (\InvalidArgumentException $exception) {
            fwrite($this->stderr, 'holmes-scan: ' . $exception->getMessage() . "\n\n" . $this->usage());

            return self::EXIT_USAGE;
        }

        if ($options['help']) {
            fwrite($this->stdout, $this->usage());

            return self::EXIT_PASS;
        }

        $reports = [];
        foreach ($options['themes'] as $themePath) {
            $reports[] = $this->scan($themePath, $options);
        }

        if ('json' === $options['format']) {
            $payload = 1 === count($reports) ? $reports[0] : ['themes' => $reports];
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
            fwrite($this->stdout, json_encode($payload, $flags) . "\n");
        } else {
            foreach ($reports as $report) {
                fwrite($this->stdout, $this->renderText($report, $options));
            }
        }

        foreach ($reports as $report) {
            if ('fail' === $report['result']) {
                return self::EXIT_FINDINGS;
            }
        }

        return self::EXIT_PASS;
    }

    /**
     * @param string[] $argv
     *
     * @return array<string, mixed> help, format, failOn, themes, analyzers, tokenPrefix, showInfo
     */
    private function parse(array $argv): array
    {
        $options = [
            'help' => false,
            'format' => 'text',
            'failOn' => 'error',
            'themes' => [],
            'analyzers' => [],
            'tokenPrefix' => null,
            'showInfo' => true,
        ];

        foreach ($argv as $argument) {
            if ('--help' === $argument || '-h' === $argument) {
                $options['help'] = true;
                continue;
            }

            if ('--no-info' === $argument) {
                $options['showInfo'] = false;
                continue;
            }

            if (str_starts_with($argument, '--')) {
                [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, '');
                switch ($name) {
                    case 'format':
                        if (!in_array($value, ['text', 'json'], true)) {
                            throw new \InvalidArgumentException("--format must be 'text' or 'json'.");
                        }
                        $options['format'] = $value;
                        break;
                    case 'fail-on':
                        if (!in_array($value, self::FAIL_LEVELS, true)) {
                            throw new \InvalidArgumentException(
                                '--fail-on must be one of: ' . implode(', ', self::FAIL_LEVELS) . '.'
                            );
                        }
                        $options['failOn'] = $value;
                        break;
                    case 'analyzers':
                        $names = array_filter(array_map('trim', explode(',', $value)));
                        foreach ($names as $analyzerName) {
                            if (!isset($this->analyzers()[$analyzerName])) {
                                throw new \InvalidArgumentException("Unknown analyzer '{$analyzerName}'.");
                            }
                        }
                        $options['analyzers'] = array_values($names);
                        break;
                    case 'token-prefix':
                        $options['tokenPrefix'] = '' === trim($value) ? null : $value;
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown option --{$name}.");
                }
                continue;
            }

            $options['themes'][] = $argument;
        }

        if (!$options['help']) {
            if ([] === $options['themes']) {
                throw new \InvalidArgumentException('No theme folder given.');
            }

            foreach ($options['themes'] as $themePath) {
                if (!is_dir($themePath)) {
                    throw new \InvalidArgumentException("'{$themePath}' is not a folder.");
                }
                if (!ThemeFiles::isTheme($themePath)) {
                    throw new \InvalidArgumentException("'{$themePath}' has no theme.tpl, theme.html or theme.json.");
                }
            }
        }

        return $options;
    }

    /**
     * @return array<string, AnalyzerInterface>
     */
    private function analyzers(): array
    {
        $analyzers = [];
        $all = [
            new ThemeManifestAnalyzer(),
            new DesignTokenAnalyzer(),
            new TemplateAnalyzer(),
            new AccessibilityAnalyzer(),
        ];
        foreach ($all as $analyzer) {
            $analyzers[$analyzer->name()] = $analyzer;
        }

        return $analyzers;
    }

    /**
     * @param array<string, mixed> $options see parse()
     *
     * @return array<string, mixed>
     */
    private function scan(string $themePath, array $options): array
    {
        $realPath = realpath($themePath);
        $themePath = false === $realPath ? $themePath : $realPath;
        $context = new AnalysisContext($themePath, basename($themePath), null, $options['tokenPrefix']);

        $selected = $this->analyzers();
        if ([] !== $options['analyzers']) {
            $selected = array_intersect_key($selected, array_flip($options['analyzers']));
        }

        $findings = [];
        foreach ($selected as $analyzer) {
            foreach ($analyzer->analyze($context) as $finding) {
                $findings[] = ['analyzer' => $analyzer->name(), 'finding' => $finding];
            }
        }

        usort(
            $findings,
            static function (array $left, array $right): int {
                /** @var Finding $a */
                $a = $left['finding'];
                /** @var Finding $b */
                $b = $right['finding'];

                return [$a->severity->weight(), $a->target, $a->line ?? 0, $a->ruleId]
                    <=> [$b->severity->weight(), $b->target, $b->line ?? 0, $b->ruleId];
            }
        );

        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        $rows = [];
        foreach ($findings as $entry) {
            /** @var Finding $finding */
            $finding = $entry['finding'];
            $counts[$finding->severity->value]++;
            $rows[] = ['analyzer' => $entry['analyzer']] + $finding->toArray();
        }

        return [
            'theme' => basename($themePath),
            'path' => $themePath,
            'mode' => ThemeFiles::hasManifest($themePath) ? 'xtf' : 'generic',
            'name' => ThemeFiles::displayName($themePath),
            'fail_on' => $options['failOn'],
            'counts' => $counts,
            'result' => $this->failed($counts, $options['failOn']) ? 'fail' : 'pass',
            'findings' => $rows,
        ];
    }

    /**
     * @param array{error: int, warning: int, info: int} $counts
     */
    private function failed(array $counts, string $failOn): bool
    {
        return match ($failOn) {
            'never' => false,
            'info' => $counts['error'] + $counts['warning'] + $counts['info'] > 0,
            'warning' => $counts['error'] + $counts['warning'] > 0,
            default => $counts['error'] > 0,
        };
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $options see parse()
     */
    private function renderText(array $report, array $options): string
    {
        $name = '' !== $report['name'] ? ' (' . $report['name'] . ')' : '';
        $out = sprintf("cssHolmes scan: %s%s, %s theme\n", $report['theme'], $name, $report['mode']);

        $groups = [
            Severity::Error->value => 'Errors',
            Severity::Warning->value => 'Warnings',
            Severity::Info->value => 'Info',
        ];

        foreach ($groups as $severity => $title) {
            if (Severity::Info->value === $severity && !$options['showInfo']) {
                continue;
            }

            $rows = array_values(array_filter(
                $report['findings'],
                static fn (array $row): bool => $row['severity'] === $severity
            ));

            $out .= sprintf("\n%s (%d)\n", $title, count($rows));
            foreach ($rows as $row) {
                $location = $row['target'] . (null !== $row['line'] ? ':' . $row['line'] : '');
                $out .= sprintf("  %-26s %s\n", $row['rule_id'], $location);
                $out .= $this->wrap($row['message'], '    ', '    ');
                if (null !== $row['suggestion'] && '' !== $row['suggestion']) {
                    $out .= $this->wrap($row['suggestion'], '    > ', '      ');
                }
            }
        }

        $plural = static fn (int $count, string $word): string => $count . ' ' . $word . (1 === $count ? '' : 's');
        $out .= sprintf(
            "\nResult: %s (%s, %s, %d info; fail on %s)\n",
            strtoupper($report['result']),
            $plural((int)$report['counts']['error'], 'error'),
            $plural((int)$report['counts']['warning'], 'warning'),
            $report['counts']['info'],
            $report['fail_on']
        );

        return $out;
    }

    /**
     * Word-wrap a line of the text report to 80 columns (long words stay whole).
     */
    private function wrap(string $text, string $firstIndent, string $nextIndent): string
    {
        $width = 80 - strlen($firstIndent);
        $lines = explode("\n", wordwrap($text, $width, "\n", false));

        return $firstIndent . implode("\n" . $nextIndent, $lines) . "\n";
    }

    private function usage(): string
    {
        return <<<'TXT'
Usage: php modules/cssholmes/bin/holmes-scan.php [options] <theme-folder> [...]

Runs the cssHolmes theme analyzers (manifest, design tokens, templates,
accessibility) on theme folders. No XOOPS installation is needed.

Options:
  --format=text|json      Output format (default: text)
  --fail-on=LEVEL         error (default), warning, info or never
  --analyzers=LIST        Comma-separated subset: theme-manifest,
                          design-token, template, accessibility
  --token-prefix=NAME     Custom-property prefix of a generic theme, for
                          example mt for --mt-*; learnt from the CSS if omitted
  --no-info               Leave info findings out of the text report
  -h, --help              Show this help

Exit codes: 0 pass, 1 findings at or above --fail-on, 2 usage error.

TXT;
    }
}
