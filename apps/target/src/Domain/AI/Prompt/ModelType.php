<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

enum ModelType: string
{
    case SMALL = 'small';
    case COMPLEX = 'complex';
}
