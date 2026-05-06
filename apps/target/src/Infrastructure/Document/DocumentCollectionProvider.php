<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Document\Document;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\OpenSearch\State\CollectionProviderWithAggregations;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<PaginatorWithAggregations<Document>>
 */
readonly class DocumentCollectionProvider implements ProviderInterface
{
    public function __construct(
        /**
         * @var CollectionProviderWithAggregations<Document>
         */
        private CollectionProviderWithAggregations $collectionProvider,
        private WatchFileGatewayInterface $watchFileGateway,
        private EntityEnrichmentOrchestratorInterface $entityEnrichmentOrchestrator,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<Document>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?iterable
    {
        Assert::keyExists($uriVariables, 'watchFileId', 'Watch file ID is required.');
        Assert::stringNotEmpty($uriVariables['watchFileId'], 'Watch file ID must be a non-empty string.');

        $watchFileId = trim((string) $uriVariables['watchFileId']);
        Assert::uuid($watchFileId, 'Watch file ID must be a valid UUID.');

        $watchFile = $this->watchFileGateway->get($watchFileId);

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedException('You do not have permission to view documents for this watchfile.');
        }

        if (!isset($context['filters'])) {
            $context['filters'] = [];
        }

        Assert::isArray($context['filters'], 'Context filters must be an array.');
        $context['filters']['watchFile.id'] = $watchFile->getId();

        $organisationId = $watchFile->getOrganisation()
->getId();
        if (null !== $organisationId && '' !== $organisationId) {
            $context['routing'] = $organisationId;
        }

        $result = $this->collectionProvider->provide($operation, $uriVariables, $context);

        return $this->entityEnrichmentOrchestrator->enrichCollection($result, [
            'user' => $this->security->getUser(),
        ]);
    }
}
