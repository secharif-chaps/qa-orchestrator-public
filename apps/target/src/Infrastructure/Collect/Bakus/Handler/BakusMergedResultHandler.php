<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Handler;

use App\Application\Document\IngestDocumentAction;
use App\Domain\Collect\CollectDataHandlerInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Document\Document;
use App\Domain\Document\HtmlMetadata;
use App\Infrastructure\Collect\Bakus\BakusProviderGateway;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BakusMergedResultHandler implements CollectDataHandlerInterface
{
    private const string MERGED_RESULT = 'merged_result';
    private const string RAW_RESULT = 'raw_result';
    private const string DOCUMENT_REFINED_RESULT = 'document_refined_result';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly BakusProviderGateway $gateway,
        private readonly ValidatorInterface $validator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function supports(CollectDataReceivedEvent $event): bool
    {
        $type = $event->getType();

        return self::MERGED_RESULT === $type
            || self::RAW_RESULT === $type
            || self::DOCUMENT_REFINED_RESULT === $type;
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        $eventType = $event->getType();

        // Log appropriate message based on event type
        if (self::DOCUMENT_REFINED_RESULT === $eventType) {
            $this->logger?->info('Processing document refined result data', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'data_type' => $eventType,
                'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
            ]);
        } elseif (self::RAW_RESULT === $eventType) {
            $this->logger?->info('Processing raw result data', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'data_type' => $eventType,
                'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
            ]);
        } else {
            $this->logger?->info('Processing merged result data', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'data_type' => $eventType ?? 'unknown',
                'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
            ]);
        }

        // Extract result data from payload
        $resultData = $event->data['result'] ?? [];
        if (!\is_array($resultData)) {
            $this->logger?->warning('Invalid result data structure', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'result_type' => \gettype($resultData),
            ]);

            return;
        }

        // Extract raw_id from Bakus data for synchronization between raw_result and document_refined_result
        // For document_refined_result, raw_id is at the root level of event->data
        // For raw_result and merged_result, raw_id is in result data
        // Note: Bakus sends this as 'raw_id' in JSON, we map it to providerId internally
        $providerId = $event->data['raw_id'] ?? $resultData['raw_id'] ?? null;

        // Convert to string if it's a number (Bakus sometimes sends raw_id as integer)
        if (\is_int($providerId)) {
            $providerId = (string) $providerId;
        }

        if (!\is_string($providerId) || '' === $providerId) {
            $this->logger?->error('Missing or invalid raw_id in result data', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'data_type' => $eventType,
                'payload_structure' => array_keys($event->data ?? []),
                'result_data_keys' => array_keys($resultData),
            ]);

            return;
        }

        // Handle document_refined_result differently
        if (self::DOCUMENT_REFINED_RESULT === $eventType) {
            /** @var array<string, mixed> $resultData */
            $this->handleRefinedResult($event, $resultData, $providerId);

            return;
        }

        if (null === $eventType) {
            $this->logger?->error('Event type is null, cannot process', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
            ]);

            return;
        }

        $documentOrigin = $event->data['document_origin'] ?? BakusProviderGateway::RESULT_TYPE_RAW;
        if (!\is_string($documentOrigin)) {
            $documentOrigin = BakusProviderGateway::RESULT_TYPE_RAW;
        }

        if (BakusProviderGateway::RESULT_TYPE_RAW !== $documentOrigin) {
            $this->logger?->warning(
                \sprintf('Invalid document origin, we do not handle document "%s" origin', $documentOrigin)
            );

            return;
        }

        // Log result data with truncated values for debugging
        $truncatedData = array_map(
            fn ($value) => \is_string($value) && \strlen($value) > 100
                ? substr($value, 0, 100) . '...'
                : $value,
            $resultData
        );
        $this->logger?->debug('Result data received', [
            'collect_task_id' => $event->collectTaskId,
            'result_data' => $truncatedData,
        ]);

        if (isset($resultData['content_type'])) {
            $contentType = $resultData['content_type'];
            if (!\is_string($contentType)) {
                $this->logger?->warning('Invalid content_type in merged result', [
                    'collect_task_id' => $event->collectTaskId,
                    'provider_name' => 'bakus',
                    'content_type_type' => \gettype($contentType),
                ]);

                return;
            }

            if (!$this->isSupportedContentType($contentType)) {
                $this->logger?->info('Content type is not valid: ' . $contentType);

                return;
            }

            $this->logger?->info('Content type is: ' . $contentType);
        }

        $hashDocumentSha1 = $resultData['hash_document_sha1'] ?? null;

        if (!$hashDocumentSha1 || !\is_string($hashDocumentSha1)) {
            $this->logger?->warning('No valid hash_document_sha1 found in merged result data', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'payload_structure' => array_keys($event->data ?? []),
            ]);

            return;
        }

        try {
            $this->logger?->info('Dispatching GetProviderDocumentAction', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'hash_document_sha1' => $hashDocumentSha1,
                'document_origin' => $documentOrigin,
            ]);

            $content = $this->gateway->getDocumentContent($hashDocumentSha1, $documentOrigin);

            $document = $this->buildDocument($content, $event, $providerId, $eventType);

            // Validate the entire document
            $violations = $this->validator->validate($document);
            if (\count($violations) > 0) {
                $this->logger?->warning('Document validation failed, skipping document', [
                    'collect_task_id' => $event->collectTaskId,
                    'provider_task_id' => $event->providerTaskId,
                    'provider_name' => 'bakus',
                    'hash_document_sha1' => $hashDocumentSha1,
                    'provider_id' => $providerId,
                    'violations' => (string) $violations,
                ]);

                return;
            }

            $this->messageBus->dispatch(new IngestDocumentAction($event->collectTaskId, $document));

            $this->logger?->info('Successfully processed document', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'hash_document_sha1' => $hashDocumentSha1,
                'provider_id' => $providerId,
                'data_type' => $eventType,
                'content_length' => \strlen($content),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to process document', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'hash_document_sha1' => $hashDocumentSha1,
                'provider_id' => $providerId,
                'data_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Build the document from the refined data directly.
     *
     * @param array<string, mixed> $resultData
     */
    private function handleRefinedResult(CollectDataReceivedEvent $event, array $resultData, string $providerId): void
    {
        try {
            $this->logger?->debug('Processing document_refined_result', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'provider_id' => $providerId,
                'result_data_keys' => array_keys($resultData),
            ]);

            $content = $resultData['content'] ?? $resultData['refined_content'] ?? '';

            if ('' === $content) {
                $hashDocumentSha1 = $resultData['hash_document_sha1'] ?? null;
                if (\is_string($hashDocumentSha1) && '' !== $hashDocumentSha1) {
                    $this->logger?->info('Fetching document content from Bakus for refined result', [
                        'collect_task_id' => $event->collectTaskId,
                        'provider_task_id' => $event->providerTaskId,
                        'provider_name' => 'bakus',
                        'provider_id' => $providerId,
                        'hash_document_sha1' => $hashDocumentSha1,
                    ]);
                    $content = $this->gateway->getDocumentContent(
                        $hashDocumentSha1,
                        BakusProviderGateway::RESULT_TYPE_REFINED
                    );
                } else {
                    $this->logger?->warning('No content available for refined result', [
                        'collect_task_id' => $event->collectTaskId,
                        'provider_task_id' => $event->providerTaskId,
                        'provider_name' => 'bakus',
                        'provider_id' => $providerId,
                        'result_data_keys' => array_keys($resultData),
                    ]);

                    return;
                }
            }

            if (!\is_string($content)) {
                $this->logger?->error('Content is not a string for refined result', [
                    'collect_task_id' => $event->collectTaskId,
                    'provider_task_id' => $event->providerTaskId,
                    'provider_name' => 'bakus',
                    'provider_id' => $providerId,
                    'content_type' => \gettype($content),
                ]);

                return;
            }

            $document = $this->buildDocument($content, $event, $providerId, self::DOCUMENT_REFINED_RESULT);

            $violations = $this->validator->validate($document);
            if (\count($violations) > 0) {
                $this->logger?->warning('Document validation failed for refined result, skipping document', [
                    'collect_task_id' => $event->collectTaskId,
                    'provider_task_id' => $event->providerTaskId,
                    'provider_name' => 'bakus',
                    'provider_id' => $providerId,
                    'violations' => (string) $violations,
                ]);

                return;
            }

            $this->messageBus->dispatch(new IngestDocumentAction($event->collectTaskId, $document));

            $this->logger?->info('Successfully processed refined document', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'provider_id' => $providerId,
                'content_length' => \strlen($content),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to process refined document', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function isSupportedContentType(string $contentType): bool
    {
        return str_starts_with($contentType, 'text');
    }

    /**
     * Build a document from the event data.
     *
     * @param string                   $content    The document content
     * @param CollectDataReceivedEvent $event      The event containing result data
     * @param string                   $providerId The provider_id for synchronization (extracted from raw_id in Bakus data)
     * @param string                   $eventType  The type of result (raw_result, document_refined_result, merged_result)
     */
    public function buildDocument(
        string $content,
        CollectDataReceivedEvent $event,
        string $providerId,
        string $eventType,
    ): Document {
        $resultData = $event->data['result'] ?? [];

        if (!\is_array($resultData)) {
            $resultData = [];
        }

        $isRefined = self::DOCUMENT_REFINED_RESULT === $eventType;

        $title = $this->extractTitle($resultData, $isRefined);

        $excerpt = $isRefined && isset($resultData['excerpt']) && \is_string(
            $resultData['excerpt']
        ) && '' !== $resultData['excerpt']
            ? $resultData['excerpt']
            : mb_substr(strip_tags($content), 0, 200);

        $rawContentType = \is_string($resultData['content_type'] ?? null) ? $resultData['content_type'] : 'html';
        $contentType = 'application/pdf' === $rawContentType ? 'pdf' : 'html';
        $cfcRestricted = $resultData['cfc_restricted'] ?? false;
        $url = \is_string($resultData['url'] ?? null) ? $resultData['url'] : null;

        $tsCollection = $resultData['ts_collection'] ?? null;
        $datePublish = (\is_string($tsCollection) && '' !== $tsCollection)
            ? new \DateTimeImmutable($tsCollection)
            : new \DateTimeImmutable();

        $dateCollect = new \DateTimeImmutable();

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: $contentType,
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $content,
            cfcRestricted: $cfcRestricted,
            url: $url,
        );

        $document->setProviderId($providerId);

        return $document;
    }

    /**
     * Extract the best available title from result data.
     *
     * Priority: title (refined) > url_title (raw) > URL path > 'Untitled Document'
     *
     * @param array<string, mixed> $resultData
     */
    private function extractTitle(array $resultData, bool $isRefined): string
    {
        // For refined results, prefer the 'title' field
        if ($isRefined && isset($resultData['title']) && \is_string(
            $resultData['title']
        ) && '' !== $resultData['title']) {
            return $resultData['title'];
        }

        // For all results, try url_title
        if (isset($resultData['url_title']) && \is_string(
            $resultData['url_title']
        ) && '' !== $resultData['url_title']) {
            return $resultData['url_title'];
        }

        // Fallback: extract a readable title from the URL
        if (isset($resultData['url']) && \is_string($resultData['url']) && '' !== $resultData['url']) {
            $parsedUrl = parse_url($resultData['url']);
            $host = $parsedUrl['host'] ?? '';
            $path = $parsedUrl['path'] ?? '';

            // Use path segments if meaningful (not just '/')
            if ('' !== $path && '/' !== $path) {
                $lastSegment = basename($path);
                // Clean up: remove extensions, replace hyphens/underscores with spaces
                $lastSegment = pathinfo($lastSegment, \PATHINFO_FILENAME);
                $lastSegment = str_replace(['-', '_'], ' ', $lastSegment);
                $lastSegment = ucfirst(trim($lastSegment));

                if ('' !== $lastSegment) {
                    return $lastSegment;
                }
            }

            if ('' !== $host) {
                return $host;
            }
        }

        return HtmlMetadata::UNTITLED;
    }
}
