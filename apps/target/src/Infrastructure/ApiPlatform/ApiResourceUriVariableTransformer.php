<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform;

use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UriVariableTransformerInterface;

/**
 * Transforms URI variables that are API Platform resources.
 *
 * This transformer handles cases where URI variables contain API resource objects,
 * typically in subresource scenarios where a parent resource is passed as a URI variable.
 */
class ApiResourceUriVariableTransformer implements UriVariableTransformerInterface
{
    public function __construct(
        private readonly ResourceClassResolverInterface $resourceClassResolver,
    ) {
    }

    /**
     * @param array<int, string>   $types
     * @param array<string, mixed> $context
     */
    public function transform(mixed $value, array $types, array $context = []): mixed
    {
        $targetType = $types[0] ?? null;

        if (null === $targetType) {
            return $value;
        }

        // If the value is already an object of the target type, return it as-is
        if (\is_object($value) && $value instanceof $targetType) {
            return $value;
        }

        // If the target type is not a resource class, let other transformers handle it
        if (!$this->resourceClassResolver->isResourceClass($targetType)) {
            return $value;
        }

        // If the value is a string (identifier), we can't transform it to a resource here
        // This should be handled by State Providers, not URI variable transformers
        // Return the value as-is to let the State Provider handle the resolution
        if (\is_string($value)) {
            return $value;
        }

        // For other cases (e.g., arrays, objects of different types), return as-is
        return $value;
    }

    /**
     * @param array<int, string>   $types
     * @param array<string, mixed> $context
     */
    public function supportsTransformation(mixed $value, array $types, array $context = []): bool
    {
        $targetType = $types[0] ?? null;

        if (null === $targetType) {
            return false;
        }

        // Support transformation if the target type is a resource class
        if (!$this->resourceClassResolver->isResourceClass($targetType)) {
            return false;
        }

        // Support if the value is already an object of the target type
        if (\is_object($value) && $value instanceof $targetType) {
            return true;
        }

        // Support if the value is a string (identifier) for a resource class
        // The actual resolution will be done by State Providers
        return \is_string($value);
    }
}
