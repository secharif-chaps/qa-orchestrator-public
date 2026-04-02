<?php

declare(strict_types=1);

namespace App\Application\Logo;

use App\Domain\Logo\LogoGatewayInterface;
use App\Domain\Logo\LogoNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
readonly class GetLogoHandler
{
    /**
     * @param LogoGatewayInterface[] $logoGateways
     */
    public function __construct(
        private iterable $logoGateways,
        private TagAwareCacheInterface $cacheAppDefault,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GetLogoAction $action): string
    {
        $this->logger->info('Fetching logo for domain', [
            'domain' => $action->domain,
        ]);

        $cacheKey = \sprintf('logo_%s', sha1($action->domain));

        return $this->cacheAppDefault->get($cacheKey, function (ItemInterface $item) use ($action) {
            foreach ($this->logoGateways as $gateway) {
                if (!$gateway->supports($action->domain)) {
                    continue;
                }

                try {
                    $logo = $gateway->getLogo($action->domain);

                    $this->logger->info('Logo fetched successfully', [
                        'domain' => $action->domain,
                        'gateway' => $gateway::class,
                        'size' => $logo->getSize(),
                        'mime_type' => $logo->mimeType,
                    ]);

                    $item->expiresAt(new \DateTime('+1 month'));
                    $item->tag('logo');

                    return $logo->content;
                } catch (LogoNotFoundException $e) {
                    $this->logger->info('Gateway failed, trying next', [
                        'domain' => $action->domain,
                        'gateway' => $gateway::class,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            $this->logger->error('All logo gateways failed', [
                'domain' => $action->domain,
            ]);

            throw new LogoNotFoundException($action->domain);
        });
    }
}
