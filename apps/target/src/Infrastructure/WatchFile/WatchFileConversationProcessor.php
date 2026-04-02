<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Chat\CreateConversationAction;
use App\Domain\Chat\Conversation;
use App\UserInterface\Dto\Chat\UserMessageDto;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<UserMessageDto, Conversation>
 */
class WatchFileConversationProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof DeleteOperationInterface) {
            throw new \RuntimeException('The WatchFileConversationProcessor does not support delete operations.');
        }

        $watchFileId = $uriVariables['id'] ?? null;
        if (null === $watchFileId) {
            throw new \RuntimeException('WatchFile ID is required.');
        }

        $action = new CreateConversationAction(watchFileId: $watchFileId, message: $data->content);

        return $this->handle($action);
    }
}
