<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\State;

use ApiPlatform\Elasticsearch\Extension\RequestBodySearchCollectionExtensionInterface;
use ApiPlatform\Elasticsearch\Paginator;
use ApiPlatform\Elasticsearch\State\Options;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * Collection provider for OpenSearch that exposes aggregations.
 *
 * @template T of object
 *
 * @implements ProviderInterface<PaginatorWithAggregations<T>>
 */
class CollectionProviderWithAggregations implements ProviderInterface
{
    /**
     * @param RequestBodySearchCollectionExtensionInterface[] $collectionExtensions
     */
    public function __construct(
        private readonly Client $client,
        private readonly DenormalizerInterface $denormalizer,
        private readonly Pagination $pagination,
        private readonly iterable $collectionExtensions = [],
    ) {
    }

    /**
     * @return PaginatorWithAggregations<T>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): PaginatorWithAggregations {
        $resourceClass = $operation->getClass();
        Assert::string($resourceClass, 'Resource class must be a string');

        $body = [];

        foreach ($this->collectionExtensions as $collectionExtension) {
            $body = $collectionExtension->applyToCollection($body, $resourceClass, $operation, $context);
        }

        if (!isset($body['query']) && !isset($body['aggs'])) {
            $body['query'] = [
                'match_all' => new \stdClass(),
            ];
        }

        $limit = $body['size'] ??= $this->pagination->getLimit($operation, $context);
        $offset = $body['from'] ??= $this->pagination->getOffset($operation, $context);

        $options = $operation->getStateOptions();
        if (!$options instanceof Options) {
            throw new \LogicException(\sprintf(
                'When you use the %s provider, make sure the state options must be an instance of %s for the operation.',
                self::class,
                Options::class
            ), );
        }

        $index = $options->getIndex();
        if (null === $index) {
            throw new \LogicException(\sprintf(
                'When you use the %s provider, make sure the OpenSearch index must be set for the operation.',
                self::class
            ), );
        }

        $params = [
            'index' => $index,
            'body' => $body,
        ];

        if (isset($context['routing']) && \is_string($context['routing']) && '' !== $context['routing']) {
            $params['routing'] = $context['routing'];
        }

        try {
            $documents = $this->client->search($params);
        } catch (Missing404Exception $e) {
            throw new Error(title: 'Not Found', detail: $e->getMessage(), status: 404, originalTrace: $e->getTrace());
        }

        Assert::isArray($documents, 'OpenSearch response must be an array');

        // Create standard Paginator
        $paginator = new Paginator($this->denormalizer, $documents, $resourceClass, $limit, $offset, $context);

        // Extract aggregations and wrap in PaginatorWithAggregations
        $aggregations = $documents['aggregations'] ?? [];
        Assert::isArray($aggregations, 'Aggregations must be an array');

        /** @var PaginatorWithAggregations<T> */
        return new PaginatorWithAggregations($paginator, $aggregations, $resourceClass);
    }
}
