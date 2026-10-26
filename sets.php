<?php

declare(strict_types=1);

use XoopsModules\Cssholmes\Designer\ConflictException;
use XoopsModules\Cssholmes\Designer\SetFormat;
use XoopsModules\Cssholmes\Designer\SetLibrary;

require_once dirname(__DIR__, 2) . '/mainfile.php';
require_once __DIR__ . '/include/designer.php';

if (isset($GLOBALS['xoopsLogger'])) {
    $GLOBALS['xoopsLogger']->activated = false;
    $GLOBALS['xoopsLogger']->renderingEnabled = false;
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$reply = static function (int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    exit;
};
$access = cssholmes_designer_access();
if (!$access['edit']) {
    $signedIn = is_object($GLOBALS['xoopsUser'] ?? null);
    $reply(403, [
        'ok' => false, 'code' => $signedIn ? 'permission_denied' : 'auth_required',
        'error' => $signedIn ? 'Theme designer access is required.' : 'Sign in again in another tab, then retry this action. Your draft is kept.',
    ]);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    $reply(405, ['ok' => false, 'error' => 'Use GET or POST.']);
}

try {
    $actor = ['id' => (int) $GLOBALS['xoopsUser']->getVar('uid'), 'name' => (string) $GLOBALS['xoopsUser']->getVar('uname', 'n')];
    if ($actor['id'] < 1) {
        $reply(403, ['ok' => false, 'error' => 'A registered designer account is required.']);
    }
    $module = xoops_getHandler('module')->getByDirname('cssholmes');
    $admin = $GLOBALS['xoopsUser']->isAdmin((int) $module->getVar('mid'));
    $store = cssholmes_designer_store();
    $format = new SetFormat($store);
    $library = new SetLibrary(XOOPS_VAR_PATH . '/configs/cssholmes/designer');
    $palettes = $format->builtins();
    $extra = [];
    $data = [];
    if ($method === 'POST') {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) {
            $reply(413, ['ok' => false, 'error' => 'Configuration sets must be smaller than 128 KiB.']);
        }
        $input = fopen('php://input', 'rb');
        $raw = $input === false ? false : stream_get_contents($input, 131073);
        if (is_resource($input)) {
            fclose($input);
        }
        if ($raw === false || strlen($raw) > 131072) {
            $reply(413, ['ok' => false, 'error' => 'Configuration sets must be smaller than 128 KiB.']);
        }
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('A JSON object is required.');
        }
        $token = $data['token'] ?? null;
        if (!is_string($token) || !$GLOBALS['xoopsSecurity']->check(false, $token, 'CSSHOLMES_DESIGNER')) {
            $reply(403, [
                'ok' => false, 'code' => 'csrf_expired',
                'error' => 'Your editing token was renewed. Try the action again. Your draft is kept.',
                'token' => $GLOBALS['xoopsSecurity']->createToken(1800, 'CSSHOLMES_DESIGNER'),
            ]);
        }
    }
    $theme = $method === 'GET' ? \Xmf\Request::getString('theme', (string) ($GLOBALS['xoopsConfig']['theme_set'] ?? 'default'), 'GET') : ($data['theme'] ?? null);
    if (!is_string($theme)) {
        throw new InvalidArgumentException('A theme is required.');
    }
    $state = $store->load($theme);
    $resolve = static function (string $id) use ($library, $palettes, $format, $theme): array {
        if ($id === 'original') {
            return ['rules' => [], 'warnings' => [], 'document' => $format->fromRules($theme, 'Original defaults', [])];
        }
        foreach ($palettes as $palette) {
            if ($palette['id'] === $id) {
                return $format->resolve($palette['payload'], $theme) + ['document' => $palette['payload']];
            }
        }
        $record = $library->get($id);
        return $format->resolve($record['payload'], $theme) + ['document' => $record['payload']];
    };
    if ($method === 'GET') {
        $action = \Xmf\Request::getString('action', 'list', 'GET');
        if (in_array($action, ['preview', 'export'], true)) {
            $preview = $resolve(\Xmf\Request::getString('id', '', 'GET'));
            $extra[$action === 'export' ? 'exported' : 'preview'] = $action === 'export' ? $preview['document'] : $preview;
        } elseif ($action !== 'list') {
            throw new InvalidArgumentException('Unknown library action.');
        }
    } else {
        $operation = $data['op'] ?? null;
        if (!is_string($operation)) {
            throw new InvalidArgumentException('An operation is required.');
        }
        if (!$access['save'] && !in_array($operation, ['like', 'exportCurrent', 'previewImport'], true)) {
            $reply(403, ['ok' => false, 'error' => 'Workbench permission is required to change saved sets or the active theme.']);
        }
        $string = static function (string $key, string $default = '') use ($data): string {
            $value = $data[$key] ?? $default;
            if (!is_string($value)) {
                throw new InvalidArgumentException('Invalid ' . $key . '.');
            }
            return $value;
        };
        $document = static function () use ($data, $format): array {
            if (!is_array($data['document'] ?? null)) {
                throw new InvalidArgumentException('A configuration document is required.');
            }
            $document = $data['document'];
            if (array_diff(array_keys($document), ['theme', 'rules']) === []
                && is_string($document['theme'] ?? null) && is_array($document['rules'] ?? null)) {
                return $format->fromRules($document['theme'], 'Imported theme set', $document['rules']);
            }
            return $format->normalize($document);
        };
        if (in_array($operation, ['saveSet', 'updateSet', 'exportCurrent'], true)) {
            if (!is_array($data['rules'] ?? null)) {
                throw new InvalidArgumentException('A rules list is required.');
            }
            $name = $string('name', 'My theme set');
            if ($operation === 'exportCurrent' && trim($name) === '') {
                $name = 'Theme configuration';
            }
            $payload = $format->fromRules($theme, $name, $data['rules']);
            if ($operation === 'exportCurrent') {
                $extra['document'] = $payload;
            } elseif ($operation === 'saveSet') {
                $extra['set'] = $library->create($theme, $payload['name'], $payload, $actor);
            } else {
                $existing = $library->get($string('id'));
                if (!$admin && $existing['author']['id'] !== $actor['id']) {
                    $reply(403, ['ok' => false, 'error' => 'Only the author or a module administrator can update this set.']);
                }
                if ($existing['theme'] !== $theme) {
                    throw new InvalidArgumentException('Save a new set to keep an adapted copy in another theme.');
                }
                $extra['set'] = $library->update($existing['id'], $payload['name'], $payload, $actor, $admin, $string('setRevision'));
            }
        } elseif ($operation === 'importSet' || $operation === 'previewImport') {
            $payload = $document();
            if (isset($data['name']) && $string('name') !== '') {
                $payload['name'] = $string('name');
                $payload = $format->normalize($payload);
            }
            $extra['preview'] = $format->resolve($payload, $theme) + ['document' => $payload];
            if ($operation === 'importSet') {
                $extra['set'] = $library->create($theme, $payload['name'], $payload, $actor);
            }
        } elseif ($operation === 'cloneSet') {
            $payload = $resolve($string('id'))['document'];
            $payload['name'] = $string('name');
            $payload = $format->normalize($payload);
            $extra['set'] = $library->create($theme, $payload['name'], $payload, $actor);
        } elseif ($operation === 'like') {
            if (!is_bool($data['liked'] ?? null)) {
                throw new InvalidArgumentException('The liked value must be true or false.');
            }
            $extra['set'] = $library->like($string('id'), $actor['id'], $data['liked']);
        } elseif ($operation === 'deleteSet') {
            $existing = $library->get($string('id'));
            if (!$admin && $existing['author']['id'] !== $actor['id']) {
                $reply(403, ['ok' => false, 'error' => 'Only the author or a module administrator can delete this set.']);
            }
            $library->delete($existing['id'], $actor, $admin, $string('setRevision'));
        } elseif ($operation === 'activate' || $operation === 'reset') {
            $rules = $operation === 'reset' ? [] : $resolve($string('id'))['rules'];
            $state = $store->save($theme, $rules, $string('revision'));
        } else {
            throw new InvalidArgumentException('Unknown library operation.');
        }
    }
    $summary = static function (array $record) use ($actor, $admin): array {
        $record['likes'] = count($record['likedBy']);
        $record['liked'] = in_array($actor['id'], $record['likedBy'], true);
        $record['editable'] = $admin || $record['author']['id'] === $actor['id'];
        unset($record['payload'], $record['likedBy']);
        return $record;
    };
    if (isset($extra['set'])) {
        $extra['set'] = $summary($extra['set']);
    }
    $themes = [];
    foreach (new DirectoryIterator(XOOPS_ROOT_PATH . '/themes') as $folder) {
        if (!$folder->isDot() && $folder->isDir() && !$folder->isLink()
            && preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,79}$/D', $folder->getFilename())
            && (is_file($folder->getPathname() . '/theme.tpl') || is_file($folder->getPathname() . '/theme.html'))) {
            $themes[] = $folder->getFilename();
        }
    }
    natcasesort($themes);
    $reply(200, $extra + [
        'ok' => true, 'state' => $state, 'canSave' => $access['save'], 'isAdmin' => $admin,
        'token' => $GLOBALS['xoopsSecurity']->createToken(1800, 'CSSHOLMES_DESIGNER'),
        'library' => [
            'themes' => array_values($themes), 'sets' => array_map($summary, $library->all()),
            'palettes' => array_map(static function (array $palette): array {
                unset($palette['payload']);
                return $palette;
            }, $palettes),
        ],
    ]);
} catch (ConflictException $error) {
    $reply(409, ['ok' => false, 'error' => 'These settings changed elsewhere. Reload the library before saving again.']);
} catch (InvalidArgumentException | JsonException $error) {
    $reply(422, ['ok' => false, 'error' => $error instanceof JsonException ? 'Invalid JSON configuration.' : $error->getMessage()]);
} catch (Throwable $error) {
    $reply(500, ['ok' => false, 'error' => 'The theme-set library could not be read or saved. Check its private storage permissions.']);
}
