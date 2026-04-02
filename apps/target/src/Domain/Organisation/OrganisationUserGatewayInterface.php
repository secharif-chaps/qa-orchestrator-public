<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\User\User;

interface OrganisationUserGatewayInterface
{
    public function findByOrganisationAndUser(Organisation $organisation, User $user): ?OrganisationUser;

    /**
     * @return list<OrganisationUser>
     */
    public function findByUser(User $user): array;

    public function save(OrganisationUser $organisationUser): void;

    public function remove(OrganisationUser $organisationUser): void;

    /**
     * @param list<string> $validOrganisationKeycloakIds
     */
    public function removeStaleForUser(User $user, array $validOrganisationKeycloakIds): void;
}
