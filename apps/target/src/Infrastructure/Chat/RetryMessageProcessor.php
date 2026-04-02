<?php

declare(strict_types=1);

namespace App\Infrastructure\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chat\RetryMessageAction;
use App\Domain\Chat\MaxRetryAttemptsExceededException;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageCannotBeRetriedException;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, Message>
 */
class RetryMessageProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly MessageGatewayInterface $messageGateway,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        if (!isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Message ID must be provided in the URL');
        }

        $messageId = $uriVariables['id'];
        if (!\is_string($messageId)) {
            throw new BadRequestHttpException('Message ID must be a string');
        }

        if (!Uuid::isValid($messageId)) {
            throw new BadRequestHttpException('Message ID must be a valid UUID');
        }

        try {
            $message = $this->messageGateway->get($messageId);
        } catch (MessageNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('Message with ID %s not found.', $messageId), previous: $e);
        }

        $conversation = $message->getConversation();
        if (null === $conversation) {
            throw new BadRequestHttpException('Message does not belong to a conversation');
        }

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $conversation)) {
            throw new AccessDeniedHttpException('You do not have permission to retry this message.');
        }

        try {
            $action = new RetryMessageAction(messageId: Uuid::fromString($messageId));

            /** @var Message $result */
            $result = $this->handle($action);

            return $result;
        } catch (HandlerFailedException $e) {
            // Extract the wrapped exception from the message bus wrapper
            foreach ($e->getWrappedExceptions() as $wrapped) {
                if ($wrapped instanceof MessageCannotBeRetriedException
                    || $wrapped instanceof MaxRetryAttemptsExceededException) {
                    throw new BadRequestHttpException($wrapped->getMessage(), previous: $wrapped);
                }
            }
            throw $e;
        }
    }
}
