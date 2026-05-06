<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\RenameWatchFileAction;
use App\Application\WatchFile\Source\AddSourceAction;
use App\Application\WatchFile\UpdateWatchFileReferenceSubjectAction;
use App\Domain\Chat\Content\FunctionCallContent;
use App\Domain\Chat\Content\MessageContent;
use App\Domain\Chat\Event\MessageReceivedEvent;
use App\Domain\Shared\TranslatedText;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(event: MessageReceivedEvent::class)]
readonly class WatchFileFunctionCallEventListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MessageReceivedEvent $event): void
    {
        /** @var Collection<FunctionCallContent> $parts */
        $parts = $event->message->getContents()
->filter(static function (MessageContent $content) {
    return $content instanceof FunctionCallContent;
});

        if ($parts->isEmpty()) {
            $this->logger->debug(
                'No function call found in the message.',
                [
                    'messageId' => $event->message->getId(),
                ],
            );

            return;
        }

        $watchFile = $event->conversation->getWatchFile();
        if (null === $watchFile) {
            $this->logger->debug(
                'No watchfile found for the conversation.',
                [
                    'messageId' => $event->message->getId(),
                ],
            );

            return;
        }

        foreach ($parts as $part) {
            if (!str_starts_with($part->getFunctionName(), 'watchfile.')) {
                $this->logger->debug(
                    \sprintf('Function call is not related to watchfile: %s', $part->getFunctionName()),
                    [
                        'functionName' => $part->getFunctionName(),
                        'watchFileId' => $watchFile->getId(),
                        'messageId' => $event->message->getId(),
                    ],
                );

                continue;
            }

            $action = match ($part->getFunctionName()) {
                'watchfile.rename' => new RenameWatchFileAction(
                    watchFileId: $watchFile->getId(),
                    name: $part->getParameters()['watch_file_name'],
                    isManualRename: false,
                ),
                'watchfile.update_reference_subject' => new UpdateWatchFileReferenceSubjectAction(
                    watchFileId: $watchFile->getId(),
                    referenceSubject: $part->getParameters()['reference_subject'],
                ),
                'watchfile.add_actor' => new AddActorAction(
                    watchFileId: $watchFile->getId(),
                    name: $part->getParameters()['actor_name'],
                    type: $part->getParameters()['actor_category'],
                    explanation: TranslatedText::fromArray([
                        'fr' => $part->getParameters()['relevance_fr'],
                        'en' => $part->getParameters()['relevance_en'],
                    ]),
                    primaryDomain: $part->getParameters()['primary_domain'],
                    messageId: $part->getId(),
                ),
                'watchfile.add_source' => new AddSourceAction(
                    watchFileId: $watchFile->getId(),
                    name: $part->getParameters()['source_name'],
                    type: $part->getParameters()['source_type'],
                    primaryDomain: $part->getParameters()['primary_domain'],
                    url: $part->getParameters()['source_url'],
                    query: $part->getParameters()['source_query'],
                    description: TranslatedText::fromArray([
                        'fr' => $part->getParameters()['description_fr'],
                        'en' => $part->getParameters()['description_en'],
                    ]),
                    relevance: TranslatedText::fromArray([
                        'fr' => $part->getParameters()['relevance_fr'],
                        'en' => $part->getParameters()['relevance_en'],
                    ]),
                    messageId: $part->getId()
                ),
                default => null,
            };

            if (null === $action) {
                $this->logger->debug(
                    \sprintf('Unknown function call: %s', $part->getFunctionName()),
                    [
                        'watchFileId' => $watchFile->getId(),
                        'messageId' => $event->message->getId(),
                    ],
                );

                continue;
            }

            $this->messageBus->dispatch($action);
        }
    }
}
