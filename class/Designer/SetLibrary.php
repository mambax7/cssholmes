<?php

declare(strict_types=1);

namespace XoopsModules\Cssholmes\Designer;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

if (!defined('XOOPS_ROOT_PATH')) {
    http_response_code(404);
    exit;
}

/** Private, bounded library. Controllers supply authenticated identities and CSRF checks. */
final class SetLibrary
{
    private const MAX_BYTES = 4194304;
    private string $root;
    private SetFormat $format;

    public function __construct(string $configRoot)
    {
        $path = str_replace('\\', '/', $configRoot);
        if (preg_match('~^(?:[A-Za-z]:/|/)~', $path) !== 1 || preg_match('~(?:^|/)\.{1,2}(?:/|$)|\x00~', $path)) {
            throw new RuntimeException('Invalid theme set storage configuration.');
        }
        $this->root = rtrim(preg_replace('~/+~', '/', $path), '/');
        $themes = str_replace('\\', '/', XOOPS_ROOT_PATH) . '/themes';
        $this->format = new SetFormat(new ThemeConfigStore($themes, $this->root));
        $this->directory($this->root, false);
    }

    /** Public reads neither create the directory nor create lock files. */
    public function all(): array
    {
        $this->directory($this->root, false);
        if (!$this->exists($this->file())) {
            return [];
        }
        $this->checkFile($this->lock());
        $handle = $this->io(fn() => fopen($this->lock(), 'rb'));
        try {
            $this->io(static fn() => flock($handle, LOCK_SH));
            return $this->read();
        } finally {
            fclose($handle);
        }
    }

    public function get(string $id): array
    {
        $this->id($id);
        return $this->find($this->all(), $id);
    }

    public function create(string $theme, string $name, array $payload, array $author): array
    {
        $author = $this->actor($author);
        $name = $this->name($name);
        $payload = $this->format->normalize($payload);
        if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $theme) !== 1) {
            throw new InvalidArgumentException('Invalid library theme.');
        }
        return $this->mutate(function (array &$sets) use ($theme, $name, $payload, $author): array {
            if (count($sets) >= 200 || count(array_filter($sets, static fn(array $set): bool => $set['author']['id'] === $author['id'])) >= 20) {
                throw new InvalidArgumentException('Theme set library quota reached (200 total, 20 per author).');
            }
            $now = $this->now();
            $record = ['id' => bin2hex(random_bytes(16)), 'theme' => $theme, 'name' => $name, 'author' => $author,
                'created' => $now, 'modified' => $now, 'revision' => '', 'payload' => $payload, 'likedBy' => []];
            $record['revision'] = $this->revision($record);
            $sets[] = $record;
            return $record;
        });
    }

    public function update(string $id, string $name, array $payload, array $actor, bool $admin, string $revision): array
    {
        $this->id($id);
        $actor = $this->actor($actor);
        $name = $this->name($name);
        $payload = $this->format->normalize($payload);
        return $this->mutate(function (array &$sets) use ($id, $name, $payload, $actor, $admin, $revision): array {
            $record = $this->find($sets, $id);
            $this->authorize($record, $actor, $admin, $revision);
            $record['name'] = $name;
            $record['payload'] = $payload;
            $record['modified'] = $this->now();
            $record['revision'] = $this->revision($record);
            foreach ($sets as $index => $set) {
                if ($set['id'] === $id) {
                    $sets[$index] = $record;
                    break;
                }
            }
            return $record;
        });
    }

    public function delete(string $id, array $actor, bool $admin, string $revision): void
    {
        $this->id($id);
        $actor = $this->actor($actor);
        $this->mutate(function (array &$sets) use ($id, $actor, $admin, $revision): void {
            $this->authorize($this->find($sets, $id), $actor, $admin, $revision);
            $sets = array_values(array_filter($sets, static fn(array $set): bool => $set['id'] !== $id));
        });
    }

    public function like(string $id, int $uid, bool $liked): array
    {
        $this->id($id);
        if ($uid < 1) {
            throw new InvalidArgumentException('Sign in to like a theme set.');
        }
        return $this->mutate(function (array &$sets) use ($id, $uid, $liked): array {
            $this->find($sets, $id);
            foreach ($sets as &$record) {
                if ($record['id'] !== $id) {
                    continue;
                }
                $record['likedBy'] = array_values(array_filter($record['likedBy'], static fn(int $other): bool => $other !== $uid));
                if ($liked) {
                    $record['likedBy'][] = $uid;
                    sort($record['likedBy'], SORT_NUMERIC);
                }
                return $record;
            }
            throw new InvalidArgumentException('Unknown theme set.');
        });
    }

    private function mutate(callable $operation): mixed
    {
        $this->directory($this->root, true);
        if ($this->exists($this->lock())) {
            $this->checkFile($this->lock());
        }
        $handle = $this->io(fn() => fopen($this->lock(), 'c+b'));
        try {
            $this->io(static fn() => flock($handle, LOCK_EX));
            $this->checkFile($this->lock());
            $sets = $this->read();
            $result = $operation($sets);
            $json = json_encode(['version' => 1, 'sets' => $sets], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($json) > self::MAX_BYTES) {
                throw new InvalidArgumentException('Theme set library exceeds 4 MiB.');
            }
            $temporary = $this->root . '/.' . bin2hex(random_bytes(16)) . '.tmp';
            try {
                $stream = $this->io(static fn() => fopen($temporary, 'xb'));
                try {
                    $this->io(static fn() => chmod($temporary, 0600));
                    if ($this->io(static fn() => fwrite($stream, $json)) !== strlen($json)) {
                        throw new RuntimeException('Unable to store theme sets.');
                    }
                    $this->io(static fn() => fflush($stream));
                } finally {
                    fclose($stream);
                }
                if ($this->exists($this->file())) {
                    $this->checkFile($this->file());
                }
                $this->io(fn() => rename($temporary, $this->file()));
            } finally {
                if ($this->exists($temporary)) {
                    $this->io(static fn() => unlink($temporary));
                }
            }
            return $result;
        } finally {
            fclose($handle);
        }
    }

    private function read(): array
    {
        if (!$this->exists($this->file())) {
            return [];
        }
        $this->checkFile($this->file());
        if ($this->io(fn() => filesize($this->file())) > self::MAX_BYTES) {
            throw new RuntimeException('Invalid stored theme set library.');
        }
        try {
            $document = json_decode($this->io(fn() => file_get_contents($this->file())), true, 24, JSON_THROW_ON_ERROR);
            if (!is_array($document) || array_keys($document) !== ['version', 'sets'] || $document['version'] !== 1
                || !is_array($document['sets']) || !array_is_list($document['sets']) || count($document['sets']) > 200) {
                throw new InvalidArgumentException('Invalid theme set library.');
            }
            $ids = [];
            $owners = [];
            foreach ($document['sets'] as $record) {
                if (!is_array($record) || array_keys($record) !== ['id', 'theme', 'name', 'author', 'created', 'modified', 'revision', 'payload', 'likedBy']) {
                    throw new InvalidArgumentException('Invalid stored theme set.');
                }
                $this->id($record['id']);
                $this->name($record['name']);
                $author = $this->actor($record['author']);
                foreach (['created', 'modified'] as $field) {
                    if (!is_string($record[$field]) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/D', $record[$field]) !== 1) {
                        throw new InvalidArgumentException('Invalid stored timestamp.');
                    }
                }
                if (!is_array($record['payload']) || $this->format->normalize($record['payload']) !== $record['payload']
                    || !is_string($record['theme']) || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $record['theme']) !== 1
                    || !is_string($record['revision'])
                    || !hash_equals($this->revision($record), $record['revision']) || !is_array($record['likedBy']) || !array_is_list($record['likedBy'])) {
                    throw new InvalidArgumentException('Invalid stored theme set data.');
                }
                $likes = [];
                foreach ($record['likedBy'] as $uid) {
                    if (!is_int($uid) || $uid < 1 || isset($likes[$uid])) {
                        throw new InvalidArgumentException('Invalid stored likes.');
                    }
                    $likes[$uid] = true;
                }
                $owners[$author['id']] = ($owners[$author['id']] ?? 0) + 1;
                if (isset($ids[$record['id']]) || $owners[$author['id']] > 20) {
                    throw new InvalidArgumentException('Invalid stored theme set quota or identity.');
                }
                $ids[$record['id']] = true;
            }
            return $document['sets'];
        } catch (JsonException | InvalidArgumentException | \TypeError $error) {
            throw new RuntimeException('Invalid stored theme set library; no changes were saved.', 0, $error);
        }
    }

    private function authorize(array $record, array $actor, bool $admin, string $revision): void
    {
        if (!$admin && $record['author']['id'] !== $actor['id']) {
            throw new InvalidArgumentException('Only the author or a module administrator can change this set.');
        }
        if (!hash_equals($record['revision'], $revision)) {
            throw new ConflictException('This theme set changed. Reload before saving.');
        }
    }

    private function revision(array $record): string
    {
        unset($record['revision'], $record['likedBy']);
        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    private function find(array $sets, string $id): array
    {
        foreach ($sets as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }
        throw new InvalidArgumentException('Unknown theme set.');
    }

    private function id(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid theme set ID.');
        }
    }

    private function name(mixed $name): string
    {
        if (!is_string($name) || preg_match('/^[^<>\p{C}]{1,80}$/uD', $name) !== 1 || trim($name) === '') {
            throw new InvalidArgumentException('Names must contain 1–80 plain Unicode characters.');
        }
        return trim($name);
    }

    private function actor(array $actor): array
    {
        if (!isset($actor['id'], $actor['name']) || !is_int($actor['id']) || $actor['id'] < 1) {
            throw new InvalidArgumentException('An authenticated author is required.');
        }
        return ['id' => $actor['id'], 'name' => $this->name($actor['name'])];
    }

    private function file(): string
    {
        return $this->root . '/sets-library.json';
    }

    private function lock(): string
    {
        return $this->root . '/sets-library.lock';
    }

    private function checkFile(string $file): void
    {
        if (!is_file($file) || is_link($file) || $this->canonical(dirname((string) realpath($file))) !== $this->canonical((string) realpath($this->root))) {
            throw new RuntimeException('Unsafe theme set storage file.');
        }
    }

    private function directory(string $directory, bool $create): void
    {
        $parent = dirname($directory);
        if ($parent !== $directory) {
            $this->directory($parent, $create);
        }
        if (is_link($directory) || ($this->exists($directory) && !is_dir($directory))) {
            throw new RuntimeException('Unsafe theme set storage directory.');
        }
        if (!is_dir($directory) && $create) {
            $this->io(static fn() => mkdir($directory, 0700));
        }
        if (is_dir($directory) && $parent !== $directory
            && $this->canonical((string) realpath($directory)) !== $this->canonical((string) realpath($parent) . '/' . basename($directory))) {
            throw new RuntimeException('Theme set storage cannot traverse linked directories.');
        }
    }

    private function canonical(string $path): string
    {
        $path = rtrim(preg_replace('~/+~', '/', str_replace('\\', '/', $path)), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private function exists(string $path): bool
    {
        clearstatcache(true, $path);
        return file_exists($path) || is_link($path);
    }

    private function io(callable $operation): mixed
    {
        set_error_handler(static function (): never {
            throw new RuntimeException('Unable to access theme set storage.');
        });
        try {
            $result = $operation();
            if ($result === false) {
                throw new RuntimeException('Unable to access theme set storage.');
            }
            return $result;
        } finally {
            restore_error_handler();
        }
    }
}
