<?php

declare(strict_types=1);

namespace App\Domain\Document;

enum ManualValidationStatus: string
{
    case ACCEPTED = 'accept';
    case REFUSED = 'refuse';
    case UNCERTAIN = 'uncertain';
}
