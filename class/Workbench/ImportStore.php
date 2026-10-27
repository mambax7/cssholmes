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

final class ImportStore
{
    private const STORE_FILE = 'imports.json';

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
     * @return array<string, mixed>|null
     */
    public function find(string $importId): ?array
    {
        foreach ($this->readStore() as $record) {
            if ((string)($record['id'] ?? '') === $importId) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    public function saveImport(array $summary, string $rawPayload): array
    {
        $records = $this->readStore();
        $record = [
            'id' => date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8),
            'created_at' => date(DATE_ATOM),
            'payload_type' => (string)($summary['payload_type'] ?? 'review_export'),
            'payload_label' => (string)($summary['payload_label'] ?? 'cssHolmes review export'),
            'scope' => (string)($summary['scope'] ?? ''),
            'theme' => (string)($summary['theme'] ?? ''),
            'generated_at' => (string)($summary['generated_at'] ?? ''),
            'kind_counts' => is_array($summary['kind_counts'] ?? null) ? $summary['kind_counts'] : [],
            'changes' => is_array($summary['changes'] ?? null) ? $summary['changes'] : [],
            'config_summary' => is_array($summary['config_summary'] ?? null) ? $summary['config_summary'] : [],
            'raw_payload' => $rawPayload,
        ];
        $records[] = $record;
        $this->writeStore($records);

        return $record;
    }

    public function updateChangeStatus(string $importId, int $changeIndex, string $status): bool
    {
        $status = in_array($status, ['accepted', 'rejected', 'pending'], true) ? $status : 'pending';
        $records = $this->readStore();

        foreach ($records as $recordIndex => $record) {
            if ((string)($record['id'] ?? '') !== $importId) {
                continue;
            }

            if (!isset($record['changes'][$changeIndex]) || !is_array($record['changes'][$changeIndex])) {
                return false;
            }

            $records[$recordIndex]['changes'][$changeIndex]['status'] = $status;
            $this->writeStore($records);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function markChangeApplied(string $importId, int $changeIndex, string $target, string $message, array $meta = []): bool
    {
        $records = $this->readStore();

        foreach ($records as $recordIndex => $record) {
            if ((string)($record['id'] ?? '') !== $importId) {
                continue;
            }

            if (!isset($record['changes'][$changeIndex]) || !is_array($record['changes'][$changeIndex])) {
                return false;
            }

            $records[$recordIndex]['changes'][$changeIndex]['applied_at'] = date(DATE_ATOM);
            $records[$recordIndex]['changes'][$changeIndex]['applied_target'] = $target;
            $records[$recordIndex]['changes'][$changeIndex]['applied_message'] = $message;
            $records[$recordIndex]['changes'][$changeIndex]['applied_meta'] = $meta;
            unset($records[$recordIndex]['changes'][$changeIndex]['rolled_back_at'], $records[$recordIndex]['changes'][$changeIndex]['rolled_back_message']);
            $this->writeStore($records);

            return true;
        }

        return false;
    }

    public function markChangeRolledBack(string $importId, int $changeIndex, string $message): bool
    {
        $records = $this->readStore();

        foreach ($records as $recordIndex => $record) {
            if ((string)($record['id'] ?? '') !== $importId) {
                continue;
            }

            if (!isset($record['changes'][$changeIndex]) || !is_array($record['changes'][$changeIndex])) {
                return false;
            }

            $records[$recordIndex]['changes'][$changeIndex]['rolled_back_at'] = date(DATE_ATOM);
            $records[$recordIndex]['changes'][$changeIndex]['rolled_back_message'] = $message;
            unset(
                $records[$recordIndex]['changes'][$changeIndex]['applied_at'],
                $records[$recordIndex]['changes'][$changeIndex]['applied_target'],
                $records[$recordIndex]['changes'][$changeIndex]['applied_message'],
                $records[$recordIndex]['changes'][$changeIndex]['applied_meta']
            );
            $this->writeStore($records);

            return true;
        }

        return false;
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
