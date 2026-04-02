<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\User\User;

/**
 * @implements EntityEnricherInterface<Document>
 */
readonly class DocumentSeenStatusEnricher implements EntityEnricherInterface
{
    public function __construct(
        private DocumentSeenStatusGatewayInterface $documentSeenStatusGateway,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $user = $context['user'] ?? null;
        if (!$user instanceof User) {
            return $entity;
        }

        $isSeen = $this->documentSeenStatusGateway->isDocumentSeen($user, $entity->getId());
        $entity->setIsSeen($isSeen);

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        $user = $context['user'] ?? null;
        if (!$user instanceof User) {
            return $entities;
        }

        $documentIds = [];
        $documentMap = [];

        foreach ($entities as $entity) {
            $documentId = $entity->getId();
            $documentIds[] = $documentId;
            $documentMap[$documentId] = $entity;
        }

        if (empty($documentIds)) {
            return $entities;
        }

        $seenStatuses = $this->documentSeenStatusGateway->areDocumentsSeen($user, $documentIds);

        foreach ($seenStatuses as $documentId => $isSeen) {
            if (isset($documentMap[$documentId])) {
                $documentMap[$documentId]->setIsSeen($isSeen);
            }
        }

        return $entities;
    }

    public function supports(): string
    {
        return Document::class;
    }
}
