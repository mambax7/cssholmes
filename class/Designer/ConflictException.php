<?php

declare(strict_types=1);

namespace XoopsModules\Cssholmes\Designer;

use RuntimeException;

if (!defined('XOOPS_ROOT_PATH')) {
    http_response_code(404);
    exit;
}

final class ConflictException extends RuntimeException
{
}
