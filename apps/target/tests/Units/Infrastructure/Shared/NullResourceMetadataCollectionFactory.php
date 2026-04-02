<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Null implementation of ResourceMetadataCollectionFactoryInterface for testing.
 */
class NullResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        /** @var class-string $resourceClass */
        $getCollectionOperation = new GetCollection(class: $resourceClass);
        $resourceMetadata = new ResourceMetadataCollection($resourceClass, [
            new ApiResource(operations: [$getCollectionOperation]),
        ]);

        return $resourceMetadata;
    }
}
