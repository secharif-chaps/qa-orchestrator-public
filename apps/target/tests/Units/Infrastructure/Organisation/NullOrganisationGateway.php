<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Organisation;

use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullOrganisationGateway implements OrganisationGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, Organisation>
     */
    public array $organisations = [];

    public function findByKeycloakId(string $keycloakId): ?Organisation
    {
        foreach ($this->organisations as $organisation) {
            if ($organisation->getKeycloakId() === $keycloakId) {
                return $organisation;
            }
        }

        return null;
    }

    public function save(Organisation $organisation): void
    {
        if (null === $organisation->getId()) {
            $this->forcePropertyValue($organisation, Uuid::v4()->toRfc4122());
        }

        $this->organisations[$organisation->getId()] = $organisation;
    }
}
