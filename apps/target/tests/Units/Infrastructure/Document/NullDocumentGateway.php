<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Tests\Utils\EntityUtilsTrait;

class NullDocumentGateway implements DocumentGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, Document>
     */
    private array $documents = [];

    public function get(string $id): Document
    {
        if (!isset($this->documents[$id])) {
            throw DocumentNotFoundException::withId($id);
        }

        return $this->documents[$id];
    }

    public function save(Document $document, bool $waitForRefresh = false): void
    {
        $this->documents[$document->getId()] = $document;
    }

    /**
     * @param array<int, Document> $documents
     */
    public function saveBulk(array $documents): void
    {
        foreach ($documents as $document) {
            $this->save($document);
        }
    }

    public function markEventsAsExtracted(string $documentId, bool $extracted): void
    {
        // No-op for test implementation
        // In a real implementation, this would update the document in the store
    }

    /**
     * @var array<string, int>
     */
    private array $documentCounts = [];

    /**
     * @var array<string, int>
     */
    private array $exceedingCounts = [];

    public function countDocumentsForWatchFile(string $watchFileId): int
    {
        return $this->documentCounts[$watchFileId] ?? 0;
    }

    public function countDocumentsPerWatchFile(): array
    {
        return $this->documentCounts;
    }

    public function findWatchFilesExceedingDocumentQuota(int $quota): array
    {
        return array_filter($this->exceedingCounts, static fn (int $count): bool => $count > $quota);
    }

    public function setDocumentCount(string $watchFileId, int $count): void
    {
        $this->documentCounts[$watchFileId] = $count;
    }

    /**
     * @param array<string, int> $exceedingCounts
     */
    public function setExceedingCounts(array $exceedingCounts): void
    {
        $this->exceedingCounts = $exceedingCounts;
        foreach ($exceedingCounts as $watchFileId => $count) {
            $this->setDocumentCount($watchFileId, $count);
        }
    }

    public function findByProviderId(string $providerId): ?Document
    {
        foreach ($this->documents as $document) {
            if ($document->getProviderId() === $providerId) {
                return $document;
            }
        }

        return null;
    }

    public function findByCollectTaskId(string $collectTaskId): array
    {
        return array_values(array_filter(
            $this->documents,
            static fn (Document $document): bool => $document->getCollectTaskId() === $collectTaskId,
        ));
    }

    public function findByCanonicalUrl(string $canonicalUrl, ?string $excludeDocumentId = null): ?Document
    {
        foreach ($this->documents as $document) {
            if ($document->getCanonicalUrl() !== $canonicalUrl) {
                continue;
            }

            if (null !== $excludeDocumentId && $document->getId() === $excludeDocumentId) {
                continue;
            }

            return $document;
        }

        return null;
    }

    public function findStaleAiValidationDocuments(\DateTimeImmutable $cutoff, int $limit): array
    {
        return [];
    }

    public function findAllIds(?string $watchFileId = null, int $limit = 500, ?string $searchAfter = null): array
    {
        $ids = array_keys($this->documents);
        sort($ids);

        if (null !== $watchFileId) {
            $ids = array_values(array_filter(
                $ids,
                fn (string $id): bool => $this->documents[$id]->getWatchFile()?->getId() === $watchFileId,
            ));
        }

        if (null !== $searchAfter) {
            $ids = array_values(array_filter($ids, static fn (string $id): bool => $id > $searchAfter));
        }

        return \array_slice($ids, 0, $limit > 0 ? $limit : null);
    }
}
