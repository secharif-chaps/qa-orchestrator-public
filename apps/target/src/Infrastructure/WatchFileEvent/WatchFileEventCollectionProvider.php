<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\State\CollectionProviderWithAggregations;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<PaginatorWithAggregations<WatchFileEvent>>
 */
readonly class WatchFileEventCollectionProvider implements ProviderInterface
{
    public function __construct(
        /**
         * @var CollectionProviderWithAggregations<WatchFileEvent>
         */
        private CollectionProviderWithAggregations $collectionProvider,
        private WatchFileGatewayInterface $watchFileGateway,
        private Security $security,
    ) {
    }

    /**
     * @return PaginatorWithAggregations<WatchFileEvent>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): PaginatorWithAggregations {
        Assert::keyExists($uriVariables, 'watchFileId', 'Watch file ID is required.');
        Assert::stringNotEmpty($uriVariables['watchFileId'], 'Watch file ID must be a non-empty string.');

        $watchFileId = trim((string) $uriVariables['watchFileId']);
        Assert::uuid($watchFileId, 'Watch file ID must be a valid UUID.');

        $watchFile = $this->watchFileGateway->get($watchFileId);

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedException('You do not have permission to view events for this watch file.');
        }

        if (!isset($context['filters'])) {
            $context['filters'] = [];
        }

        Assert::isArray($context['filters'], 'Context filters must be an array.');
        $context['filters']['watchFile.id'] = $watchFile->getId();

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
