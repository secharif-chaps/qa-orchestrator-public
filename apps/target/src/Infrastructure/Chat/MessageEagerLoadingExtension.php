<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Custom Doctrine ORM extension that delegates eager loading to MessageGateway.
 *
 * This extension ensures that the MessageContent and User relations are eager-loaded
 * when fetching messages to prevent N+1 query issues during serialization.
 *
 * Performance optimization for TAR-233.
 */
readonly class MessageEagerLoadingExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private MessageGatewayInterface $messageGateway,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Message::class !== $resourceClass) {
            return;
        }

        $this->messageGateway->applyEagerLoading($queryBuilder);
    }
}
