<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chat\CancelConversationAction;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<Conversation, Conversation>
 */
class CancelConversationProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly ConversationGatewayInterface $conversationGateway,
        private readonly Security $security,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): Conversation {
        $conversationId = $uriVariables['id'] ?? null;
        if (!\is_string($conversationId)) {
            throw new BadRequestHttpException('Conversation ID is required.');
        }

        $conversation = $this->conversationGateway->get($conversationId);

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $conversation)) {
            throw new AccessDeniedHttpException('You do not have permission to cancel this conversation.');
        }

        if (!$conversation->getState()->canBeCancelled()) {
            throw new UnprocessableEntityHttpException('This conversation cannot be cancelled in its current state.');
        }

        /** @var Conversation */
        return $this->handle(new CancelConversationAction(conversationId: $conversationId));
    }
}
