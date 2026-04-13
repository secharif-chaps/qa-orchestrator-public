<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Gateway;

use App\Domain\Shared\GatewayRegistryClientInterface;

class NullGatewayRegistryClient implements GatewayRegistryClientInterface
{
    /** @var list<string> */
    public array $announcedHashes = [];
    public string $nextAction = 'unchanged';

    public function announce(string $openApiSchemaHash): string
    {
        $this->announcedHashes[] = $openApiSchemaHash;

        return $this->nextAction;
    }
}
