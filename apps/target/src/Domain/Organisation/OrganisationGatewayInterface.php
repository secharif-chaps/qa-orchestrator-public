<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

interface OrganisationGatewayInterface
{
    public function findByKeycloakId(string $keycloakId): ?Organisation;

    public function save(Organisation $organisation): void;
}
