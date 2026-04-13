<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Announce this module's OpenAPI schema to the API gateway registry.
 */
interface GatewayRegistryClientInterface
{
    /**
     * Notify the gateway that this module is ready with the given schema hash.
     *
     * @param string $openApiSchemaHash SHA-256 hex digest of the OpenAPI JSON schema
     *
     * @return string The action taken by the gateway ("rediscovered", "unchanged", or "error")
     */
    public function announce(string $openApiSchemaHash): string;
}
