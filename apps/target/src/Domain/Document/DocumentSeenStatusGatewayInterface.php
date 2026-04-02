<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\User\User;

interface DocumentSeenStatusGatewayInterface
{
    public function isDocumentSeen(User $user, string $documentId): bool;

    /**
     * @param array<string> $documentIds
     *
     * @return array<string, bool> Array with document IDs as keys and seen status as values
     */
    public function areDocumentsSeen(User $user, array $documentIds): array;

    public function findByUserAndDocument(User $user, string $documentId): ?DocumentSeenStatus;

    public function save(DocumentSeenStatus $documentSeenStatus): void;
}
