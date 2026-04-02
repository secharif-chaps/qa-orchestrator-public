<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\DocumentSeenStatus;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class DocumentSeenStatusDoctrineGateway implements DocumentSeenStatusGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isDocumentSeen(User $user, string $documentId): bool
    {
        $seenStatuses = $this->areDocumentsSeen($user, [$documentId]);

        return $seenStatuses[$documentId] ?? false;
    }

    public function areDocumentsSeen(User $user, array $documentIds): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $documentUuids = [];
        foreach ($documentIds as $documentId) {
            $documentUuids[] = Uuid::fromString($documentId);
        }

        $qb = $this->entityManager->createQueryBuilder();

        $results = $qb
            ->select('dss.documentId')
            ->from(DocumentSeenStatus::class, 'dss')
            ->where('dss.user = :user')
            ->andWhere('dss.documentId IN (:documentIds)')
            ->setParameter('user', $user)
            ->setParameter('documentIds', $documentUuids)
            ->getQuery()
            ->getResult();

        $seenDocumentIds = [];
        foreach ($results as $row) {
            $seenDocumentIds[$row['documentId']->toString()] = true;
        }

        $result = [];
        foreach ($documentIds as $documentId) {
            $result[$documentId] = isset($seenDocumentIds[$documentId]);
        }

        return $result;
    }

    public function findByUserAndDocument(User $user, string $documentId): ?DocumentSeenStatus
    {
        $documentUuid = Uuid::fromString($documentId);

        return $this->entityManager
            ->getRepository(DocumentSeenStatus::class)
            ->findOneBy([
                'user' => $user,
                'documentId' => $documentUuid,
            ]);
    }

    public function save(DocumentSeenStatus $documentSeenStatus): void
    {
        $this->entityManager->persist($documentSeenStatus);
        $this->entityManager->flush();
    }
}
