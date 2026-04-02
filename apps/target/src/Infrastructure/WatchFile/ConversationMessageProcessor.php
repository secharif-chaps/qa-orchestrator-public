<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Chat\AddMessageAction;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Chat\UserMessageDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<UserMessageDto, Conversation>
 */
class ConversationMessageProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly ConversationGatewayInterface $conversationGateway,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof DeleteOperationInterface) {
            throw new \RuntimeException('The WatchFileMessageProcessor does not support delete operations.');
        }

        $conversationId = $uriVariables['id'] ?? null;
        if (!\is_string($conversationId)) {
            throw new \RuntimeException('WatchFile ID is required.');
        }

        $conversation = $this->conversationGateway->get($conversationId);

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $conversation)) {
            throw new AccessDeniedHttpException('You do not have permission to post messages for this conversation.');
        }

        $action = new AddMessageAction(conversationId: $conversationId, message: $data->content);

        return $this->handle($action);
    }
}
