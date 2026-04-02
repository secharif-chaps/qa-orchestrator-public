<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\Message;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<Message>
 */
readonly class MessageCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Message> $apiPlatformProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $apiPlatformProvider,
        private ConversationGatewayInterface $conversationGateway,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!isset($uriVariables['id']) || !\is_string($uriVariables['id'])) {
            throw new \RuntimeException('Conversation ID is required.');
        }

        $conversation = $this->conversationGateway->get($uriVariables['id']);

        // Check if the user has WATCH_FILE_EDIT permission on the conversation's watch file
        if (!$this->security->isGranted(WatchFileVoter::EDIT, $conversation)) {
            throw new AccessDeniedHttpException('You do not have permission to view messages for this conversation.');
        }

        return $this->apiPlatformProvider->provide($operation, $uriVariables, $context);
    }
}
