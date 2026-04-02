<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Organisation;

use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationUser;
use App\Domain\Organisation\OrganisationUserGatewayInterface;
use App\Domain\User\User;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullOrganisationUserGateway implements OrganisationUserGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, OrganisationUser>
     */
    public array $organisationUsers = [];

    public function findByOrganisationAndUser(Organisation $organisation, User $user): ?OrganisationUser
    {
        foreach ($this->organisationUsers as $organisationUser) {
            if ($organisationUser->getOrganisation()->getKeycloakId() === $organisation->getKeycloakId()
                && $organisationUser->getUser()
->getId() === $user->getId()) {
                return $organisationUser;
            }
        }

        return null;
    }

    public function findByUser(User $user): array
    {
        $results = [];
        foreach ($this->organisationUsers as $organisationUser) {
            if ($organisationUser->getUser()->getId() === $user->getId()) {
                $results[] = $organisationUser;
            }
        }

        return $results;
    }

    public function save(OrganisationUser $organisationUser): void
    {
        if (null === $organisationUser->getId()) {
            $this->forcePropertyValue($organisationUser, Uuid::v4()->toRfc4122());
        }

        $this->organisationUsers[$organisationUser->getId()] = $organisationUser;
    }

    public function remove(OrganisationUser $organisationUser): void
    {
        if (null !== $organisationUser->getId()) {
            unset($this->organisationUsers[$organisationUser->getId()]);
        }
    }

    public function removeStaleForUser(User $user, array $validOrganisationKeycloakIds): void
    {
        foreach ($this->organisationUsers as $id => $organisationUser) {
            if ($organisationUser->getUser()->getId() === $user->getId()
                && !\in_array(
                    $organisationUser->getOrganisation()
->getKeycloakId(),
                    $validOrganisationKeycloakIds,
                    true
                )) {
                unset($this->organisationUsers[$id]);
            }
        }
    }
}
