<?php

declare(strict_types=1);

namespace App\Domain\Chat;

enum MessageRole: string
{
    case Model = 'model';
    case User = 'user';
    case System = 'system';
    case SystemError = 'system_error';
}
