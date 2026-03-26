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

use XoopsModules\Cssholmes\Analyzer\Finding;
use XoopsModules\Cssholmes\Analyzer\ThemeTarget;

final class ScanStore
{
    private const STORE_FILE = 'scans.json';

    public function __construct(private readonly string $modulePath)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $data = $this->readStore();
        usort(
            $data,
            static fn (array $left, array $right): int => strcmp((string)($right['id'] ?? ''), (string)($left['id'] ?? ''))
        );

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTheme(string $scope, string $themeKey, int $limit = 10): array
    {
        $records = array_values(array_filter(
            $this->all(),
            static fn (array $record): bool => (string)($record['scope'] ?? '') === $scope
                && (string)($record['theme_key'] ?? '') === $themeKey
        ));

        return array_slice($records, 0, max(1, $limit));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestForTheme(string $scope, string $themeKey, string $excludeId = ''): ?array
    {
        foreach ($this->forTheme($scope, $themeKey) as $record) {
            if ('' !== $excludeId && (string)($record['id'] ?? '') === $excludeId) {
                continue;
            }

            return $record;
        }

        return null;
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, array<int, Finding>> $analyzerResults
     *
     * @return array<string, mixed>
     */
    public function saveScan(ThemeTarget $theme, array $counts, array $analyzerResults): array
    {
        $records = $this->readStore();
        $record = [
            'id' => date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8),
            'created_at' => date(DATE_ATOM),
            'scope' => $theme->scope,
            'theme_key' => $theme->key,
            'theme_label' => $theme->label,
            'theme_path' => $theme->path,
            'counts' => $counts,
            'findings' => $this->flattenFindings($analyzerResults),
        ];

        $records[] = $record;
        $this->writeStore($records);

        return $record;
    }

    /**
     * @param array<string, array<int, Finding>> $analyzerResults
     *
     * @return array<int, array<string, int|string|null>>
     */
    private function flattenFindings(array $analyzerResults): array
    {
        $flattened = [];

        foreach ($analyzerResults as $analyzerLabel => $findings) {
            foreach ($findings as $finding) {
                $row = $finding->toArray();
                $row['analyzer'] = $analyzerLabel;
                $flattened[] = $row;
            }
        }

        usort(
            $flattened,
            static function (array $left, array $right): int {
                $severityComparison = strcmp((string)($left['severity'] ?? ''), (string)($right['severity'] ?? ''));
                if (0 !== $severityComparison) {
                    return $severityComparison;
                }

                $ruleComparison = strcmp((string)($left['rule_id'] ?? ''), (string)($right['rule_id'] ?? ''));
                if (0 !== $ruleComparison) {
                    return $ruleComparison;
                }

                return strcmp((string)($left['target'] ?? ''), (string)($right['target'] ?? ''));
            }
        );

        return $flattened;
    }

    private function storePath(): string
    {
        return $this->modulePath . '/data/workbench/' . self::STORE_FILE;
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->storePath());
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readStore(): array
    {
        $path = $this->storePath();
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (false === $json || '' === trim($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function writeStore(array $records): void
    {
        $this->ensureDirectory();
        file_put_contents(
            $this->storePath(),
            json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
