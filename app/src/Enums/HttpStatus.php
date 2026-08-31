<?php

declare(strict_types=1);

namespace App\Enums;

enum HttpStatus: int
{
    case BadRequest = 400;
    case NotFound = 404;
    case InternalServerError = 500;
    case ServiceUnavailable = 503;
}
