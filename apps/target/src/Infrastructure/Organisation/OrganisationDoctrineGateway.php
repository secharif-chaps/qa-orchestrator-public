<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation;

use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class OrganisationDoctrineGateway implements OrganisationGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByKeycloakId(string $keycloakId): ?Organisation
    {
        return $this->entityManager->getRepository(Organisation::class)->findOneBy([
            'keycloakId' => $keycloakId,
        ]);
    }

    public function save(Organisation $organisation): void
    {
        $this->entityManager->persist($organisation);
        $this->entityManager->flush();
    }
}
