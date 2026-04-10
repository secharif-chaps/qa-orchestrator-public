<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Conversation>
 */
final readonly class LastWatchFileConversationProvider implements ProviderInterface
{
    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            throw new \RuntimeException(
                'The LastWatchFileConversationProvider does not support collection operations.'
            );
        }

        $watchFileId = $uriVariables['watchFileId'] ?? null;
        if (null === $watchFileId) {
            return null;
        }

        try {
            return $this->conversationGateway->getLastConversationByWatchFileId($watchFileId);
        } catch (ConversationNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
