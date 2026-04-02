<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

use App\Domain\Document\Document;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\User\User;
use Symfony\Contracts\EventDispatcher\Event;

class DocumentManuallyValidatedEvent extends Event
{
    public function __construct(
        public readonly Document $document,
        public readonly ManualValidationStatus $validationStatus,
        public readonly User $validatedBy,
    ) {
    }
}
