<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Chat;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Language\LanguageDetectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler]
readonly class CreateConversationHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
        private LanguageDetectorInterface $languageDetector,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CreateConversationAction $action): Conversation
    {
        $watchFile = $this->getWatchFile($action->watchFileId);

        // Detect language from the message content
        $detectedLanguage = $this->languageDetector->detect($action->message);

        $languageCode = $detectedLanguage->languageCode;
        if (!\in_array($languageCode, Conversation::SUPPORTED_LANGUAGES, true)) {
            $this->logger?->info('Detected language is not supported, defaulting to English', [
                'detected_language' => $detectedLanguage->languageCode,
                'confidence' => $detectedLanguage->confidence,
            ]);

            $languageCode = Conversation::FALLBACK_LANGUAGE;
        }

        $conversation = new Conversation($watchFile);
        $conversation->setLanguage($languageCode);
        $this->conversationGateway->save($conversation);

        $this->logger?->info('Conversation created successfully', [
            'conversation_id' => $conversation->getId(),
            'watch_file_id' => $watchFile->getId(),
            'detected_language' => $detectedLanguage->languageCode,
            'detected_confidence' => $detectedLanguage->confidence,
            'mapped_language' => $languageCode,
            'text_length' => \strlen($action->message),
        ]);

        // Trigger the first message
        $addMessageAction = new AddMessageAction(
            $conversation->getId() ?? throw new \LogicException('Conversation does not exist'),
            $action->message,
        );

        $this->messageBus->dispatch($addMessageAction, [new DispatchAfterCurrentBusStamp()]);

        $this->logger?->info('First message triggered successfully', [
            'conversation_id' => $conversation->getId(),
        ]);

        return $conversation;
    }
}
