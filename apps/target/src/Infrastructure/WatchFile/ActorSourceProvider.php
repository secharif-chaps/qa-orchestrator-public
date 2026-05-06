<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\ApiFilter\ActorSourceFilter;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Source>
 */
class ActorSourceProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Source> $apiPlatformProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $apiPlatformProvider,
        private readonly ActorGatewayInterface $actorGateway,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    /**
     * @return iterable<Source>|Source|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|object|null
    {
        if (!isset($uriVariables['watchFileId']) || !\is_string($uriVariables['watchFileId'])) {
            throw new NotFoundHttpException('Watch file ID is required');
        }

        if (!isset($uriVariables['actorId']) || !\is_string($uriVariables['actorId'])) {
            throw new NotFoundHttpException('Actor ID is required');
        }

        // Check if the watchfile exists and user has access
        $watchFile = $this->watchFileGateway->get($uriVariables['watchFileId']);
        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have access to this watchfile.');
        }

        // Check if the actor exists
        $this->actorGateway->get($uriVariables['actorId']);

        // Get the Source resource metadata to find a GetCollection operation with filters
        $sourceMetadata = $this->resourceMetadataCollectionFactory->create(Source::class);
        $sourceResource = $sourceMetadata->getIterator()
->current();

        $sourceFilters = [];
        $operations = $sourceResource->getOperations();
        if (null !== $operations) {
            foreach ($operations as $op) {
                if ($op instanceof GetCollection) {
                    $sourceFilters = $op->getFilters() ?? [];
                    break;
                }
            }
        }

        $sourceFilters[] = ActorSourceFilter::class;

        // Create a new GetCollection operation with all filters including ActorSourceFilter
        $sourceGetCollectionOperation = new GetCollection(
            class: Source::class,
            filters: $sourceFilters,
            provider: null,
        );

        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            $context['filters'] = [];
        }
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'];
        $filters['actor.id'] = $uriVariables['actorId'];
        $filters['watchFile.id'] = $uriVariables['watchFileId'];
        $context['filters'] = $filters;

        return $this->apiPlatformProvider->provide($sourceGetCollectionOperation, $uriVariables, $context);
    }
}
