<?php

declare(strict_types=1);

use XoopsModules\Cssholmes\Designer\ConflictException;

require_once dirname(__DIR__, 2) . '/mainfile.php';
require_once __DIR__ . '/include/designer.php';

if (isset($GLOBALS['xoopsLogger'])) {
    $GLOBALS['xoopsLogger']->activated = false;
    $GLOBALS['xoopsLogger']->renderingEnabled = false;
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$reply = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
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
    $store = cssholmes_designer_store();
    if ($method === 'GET') {
        $theme = \Xmf\Request::getString('theme', '', 'GET');
        $state = $store->load($theme);
    } else {
        if (!$access['save']) {
            $reply(403, ['ok' => false, 'error' => 'Workbench permission is required to save theme settings.']);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) {
            $reply(413, ['ok' => false, 'error' => 'Theme settings are too large.']);
        }
        $input = fopen('php://input', 'rb');
        $raw = $input === false ? false : stream_get_contents($input, 131073);
        if (is_resource($input)) {
            fclose($input);
        }
        if ($raw === false || strlen($raw) > 131072) {
            $reply(413, ['ok' => false, 'error' => 'Theme settings are too large.']);
        }
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
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
        $theme = $data['theme'] ?? null;
        $revision = $data['revision'] ?? null;
        if (!is_string($theme) || !is_string($revision)) {
            throw new InvalidArgumentException('Theme and revision are required.');
        }
        $operation = $data['op'] ?? null;
        if ($operation === 'save' && is_array($data['rules'] ?? null)) {
            $state = $store->save($theme, $data['rules'], $revision);
        } elseif ($operation === 'restore') {
            $state = $store->restore($theme, $revision);
        } else {
            throw new InvalidArgumentException('Use save with a rules list, or restore.');
        }
    }
    $reply(200, [
        'ok' => true, 'state' => $state, 'canSave' => $access['save'],
        'token' => $GLOBALS['xoopsSecurity']->createToken(1800, 'CSSHOLMES_DESIGNER'),
    ]);
} catch (ConflictException $error) {
    $reply(409, ['ok' => false, 'error' => 'This theme was saved elsewhere. Reload its settings before saving again.']);
} catch (InvalidArgumentException | JsonException $error) {
    $reply(422, ['ok' => false, 'error' => $error instanceof JsonException ? 'Invalid JSON settings.' : $error->getMessage()]);
} catch (Throwable $error) {
    $reply(500, ['ok' => false, 'error' => 'Theme settings could not be read or saved. Check the theme configuration permissions.']);
}
