<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\AI\LlmOutputSanitizerInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateWatchFileReferenceSubjectHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LlmOutputSanitizerInterface $llmOutputSanitizer,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(UpdateWatchFileReferenceSubjectAction $action): WatchFile
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        $oldReferenceSubject = $watchFile->getReferenceSubject();

        // Sanitize and set human-readable version
        $sanitizedReferenceSubject = $this->llmOutputSanitizer->sanitizeTranslatedText($action->referenceSubject);
        $watchFile->setReferenceSubject($sanitizedReferenceSubject);

        // Set LLM version if provided (sanitize as plain text)
        if (null !== $action->referenceSubjectLlm) {
            $sanitizedLlmVersion = $this->llmOutputSanitizer->sanitize($action->referenceSubjectLlm);
            $watchFile->setReferenceSubjectLlm($sanitizedLlmVersion);
        }

        $this->watchFileGateway->save($watchFile);

        // Publish real-time update to all authorized users
        $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

        // Dispatch event for activity logging using the watch file creator
        $user = $watchFile->getCreatedBy();
        if ($user instanceof User) {
            $this->eventDispatcher->dispatch($action->toEvent($watchFile, $user, $oldReferenceSubject));
        }

        $this->logger?->info('WatchFile referenceSubject updated successfully', [
            'watch_file_id' => $watchFile->getId(),
            'reference_subject' => $action->referenceSubject,
        ]);

        return $watchFile;
    }
}
