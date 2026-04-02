<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Extension;

use ApiPlatform\Elasticsearch\Extension\RequestBodySearchCollectionExtensionInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Document\Document;

/**
 * Extension to add highlighting to OpenSearch search requests when search filter is present.
 */
class HighlightSearchCollectionExtension implements RequestBodySearchCollectionExtensionInterface
{
    /**
     * @param array<string, mixed> $requestBody
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function applyToCollection(
        array $requestBody,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): array {
        // Only apply to Document resources
        if (Document::class !== $resourceClass) {
            return $requestBody;
        }

        if (
            !isset($context['filters'])
            || !\is_array($context['filters'])
            || empty($context['filters']['search'])
        ) {
            return $requestBody;
        }

        // Add highlighting if search filter is present
        $requestBody['highlight'] = [
            'fields' => [
                'title' => new \stdClass(),
                'content' => new \stdClass(),
            ],
            'pre_tags' => ['<mark class="mark">'],
            'post_tags' => ['</mark>'],
            'fragment_size' => 150,
            'number_of_fragments' => 3,
        ];

        return $requestBody;
    }
}
