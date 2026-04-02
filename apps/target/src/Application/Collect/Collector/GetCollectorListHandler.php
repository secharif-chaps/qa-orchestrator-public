<?php

declare(strict_types=1);

namespace App\Application\Collect\Collector;

use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ValueObject\Collector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
readonly class GetCollectorListHandler
{
    public function __construct(
        private CacheInterface $cache,
        private ProviderGatewayInterface $providerGateway,
    ) {
    }

    /**
     * @return list<Collector>
     */
    public function __invoke(GetCollectorListAction $action): array
    {
        return $this->cache->get(
            'collectors_list',
            function (ItemInterface $item) {
                $item->expiresAfter(24 * 60 * 60); // 1 day

                return $this->providerGateway->getCollectors();
            }
        );
    }
}
