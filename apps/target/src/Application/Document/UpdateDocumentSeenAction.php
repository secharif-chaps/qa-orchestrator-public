<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\SyncActionInterface;
use Symfony\Component\Uid\Uuid;

readonly class UpdateDocumentSeenAction implements SyncActionInterface
{
    public function __construct(
        public Uuid $documentId,
        public Uuid $userId,
    ) {
    }

    /**
     * Create an action for the current authenticated user.
     */
    public static function forCurrentUser(Uuid $documentId, Uuid $currentUserId): self
    {
        return new self($documentId, $currentUserId);
    }
}
