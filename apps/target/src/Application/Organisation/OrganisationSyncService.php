<?php

declare(strict_types=1);

namespace App\Application\Organisation;

use App\Domain\Organisation\MembershipType;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationGatewayInterface;
use App\Domain\Organisation\OrganisationRole;
use App\Domain\Organisation\OrganisationUser;
use App\Domain\Organisation\OrganisationUserGatewayInterface;
use App\Domain\User\User;

class OrganisationSyncService
{
    public function __construct(
        private readonly OrganisationGatewayInterface $organisationGateway,
        private readonly OrganisationUserGatewayInterface $organisationUserGateway,
    ) {
    }

    /**
     * Syncs the user's organisation from normalised JWT claims.
     *
     * @param array{org_id: string, org_name: string} $claims normalised by TenantContextListener
     */
    public function syncFromToken(User $user, array $claims): Organisation
    {
        $organisation = $this->findOrCreateOrganisation($claims['org_id'], $claims['org_name']);

        $this->syncMembership($user, $organisation, OrganisationRole::MEMBER);
        $this->pruneStaleOrgMemberships($user, [$claims['org_id']]);

        return $organisation;
    }

    private function findOrCreateOrganisation(string $keycloakId, string $name): Organisation
    {
        $organisation = $this->organisationGateway->findByKeycloakId($keycloakId);

        if (null === $organisation) {
            $organisation = new Organisation($name, $keycloakId);
            $this->organisationGateway->save($organisation);
        }

        return $organisation;
    }

    private function syncMembership(User $user, Organisation $organisation, OrganisationRole $role): void
    {
        $organisationUser = $this->organisationUserGateway->findByOrganisationAndUser($organisation, $user);

        if (null === $organisationUser) {
            $organisationUser = new OrganisationUser($organisation, $user, $role, MembershipType::MANAGED);
        } else {
            $organisationUser->setRole($role);
            $organisationUser->setSyncedAt(new \DateTimeImmutable());
        }

        $this->organisationUserGateway->save($organisationUser);
    }

    /**
     * @param list<string> $validOrgKeycloakIds
     */
    private function pruneStaleOrgMemberships(User $user, array $validOrgKeycloakIds): void
    {
        $this->organisationUserGateway->removeStaleForUser($user, $validOrgKeycloakIds);
    }
}
