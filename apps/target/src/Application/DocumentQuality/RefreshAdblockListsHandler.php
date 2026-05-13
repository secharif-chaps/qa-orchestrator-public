<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality;

use App\Domain\DocumentQuality\AdblockDomainListProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RefreshAdblockListsHandler
{
    public function __construct(
        private AdblockDomainListProviderInterface $provider,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RefreshAdblockListsAction $action): int
    {
        $count = $this->provider->refresh();

        $this->logger?->info('Adblock domain list refreshed', [
            'unique_domains' => $count,
        ]);

        return $count;
    }
}
