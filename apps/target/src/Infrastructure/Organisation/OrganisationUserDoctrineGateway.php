<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation;

use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationUser;
use App\Domain\Organisation\OrganisationUserGatewayInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

class OrganisationUserDoctrineGateway implements OrganisationUserGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByOrganisationAndUser(Organisation $organisation, User $user): ?OrganisationUser
    {
        return $this->entityManager->getRepository(OrganisationUser::class)->findOneBy([
            'organisation' => $organisation,
            'user' => $user,
        ]);
    }

    public function findByUser(User $user): array
    {
        return $this->entityManager->getRepository(OrganisationUser::class)->findBy([
            'user' => $user,
        ]);
    }

    public function save(OrganisationUser $organisationUser): void
    {
        $this->entityManager->persist($organisationUser);
        $this->entityManager->flush();
    }

    public function remove(OrganisationUser $organisationUser): void
    {
        $this->entityManager->remove($organisationUser);
        $this->entityManager->flush();
    }

    public function removeStaleForUser(User $user, array $validOrganisationKeycloakIds): void
    {
        if ([] === $validOrganisationKeycloakIds) {
            return;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(OrganisationUser::class, 'ou')
            ->where('ou.user = :user')
            ->andWhere(
                $qb->expr()
->notIn(
    'ou.organisation',
    $this->entityManager->createQueryBuilder()
        ->select('o.id')
        ->from(Organisation::class, 'o')
        ->where('o.keycloakId IN (:validKeycloakIds)')
        ->getDQL()
)
            )
            ->setParameter('user', $user)
            ->setParameter('validKeycloakIds', $validOrganisationKeycloakIds)
            ->getQuery()
            ->execute();
    }
}
