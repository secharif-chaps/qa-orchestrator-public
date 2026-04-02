<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Agent\ChatSessionMessageAgent;
use App\Application\Collect\Collector\GetCollectorListAction;
use App\Domain\Actor\ActorType;
use App\Domain\AI\ChatSessionInterface;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class N8nChatSession implements ChatSessionInterface
{
    use HandleTrait;

    public function __construct(
        private readonly Security $security,
        private readonly NormalizerInterface $normalizer,
        private readonly LoggerInterface $logger,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function sendMessage(Conversation $conversation, Message $message): Message
    {
        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->messageBus->dispatch(
            new ChatSessionMessageAgent(
                $this->buildPayload($conversation, $message),
                $conversation
                    ->getWatchFile()
                    ->getId(),
                $userId,
            ),
        );

        return $message;
    }

    /**
     * @return array{
     *     watchFile: array<string, mixed>,
     *     userMessageContentText: string,
     *     userMessageId: string,
     *     conversationId: string,
     *     conversationLanguage: string,
     *     metadata: array{
     *         collectors_list: array<string, mixed>,
     *         source_types: list<string>,
     *         actor_types: list<string>,
     *     }
     * }
     */
    private function buildPayload(Conversation $conversation, Message $message): array
    {
        try {
            $collectors = $this->handle(new GetCollectorListAction());
        } catch (\Throwable $exception) {
            // Handle the case where the collector list cannot be retrieved
            $collectors = [];

            $this->logger->warning(
                'Failed to retrieve collectors list for N8nChatSession',
                [
                    'conversationId' => $conversation->getId(),
                    'exception' => $exception,
                ]
            );
        }

        $sourceTypes = SourceType::values();

        $conversationId = $conversation->getId();
        if (null === $conversationId) {
            throw new \LogicException('Conversation ID cannot be null when building payload');
        }

        $messageContent = $message
            ->getContents()
            ->first();

        if (!$messageContent instanceof TextContent) {
            throw new \LogicException('Message must have at least one content when building payload');
        }

        $messageId = $message->getId();
        if (null === $messageId) {
            throw new \LogicException('Message ID cannot be null when building payload');
        }

        return [
            'watchFile' => $this->normalize($conversation->getWatchFile(), 'watch_file'),
            'userMessageContentText' => $messageContent->getContent(),
            'userMessageId' => $messageId,
            'conversationId' => $conversationId,
            'conversationLanguage' => $conversation->getLanguage(),
            'metadata' => [
                'collectors_list' => $this->normalize($collectors, 'collector'),
                'source_types' => $sourceTypes,
                'actor_types' => ActorType::values(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(mixed $data, string $groupName): array
    {
        /** @var array<string, mixed> */
        return $this->normalizer->normalize($data, 'json', [
            'groups' => [\sprintf('%s:llm', $groupName)],
        ],);
    }
}
