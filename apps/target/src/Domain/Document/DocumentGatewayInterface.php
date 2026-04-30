<?php

declare(strict_types=1);

namespace App\Domain\Document;

interface DocumentGatewayInterface
{
    public function get(string $id): Document;

    public function save(Document $document): void;

    /**
     * Save multiple documents using bulk operation for better performance.
     *
     * @param array<int, Document> $documents
     */
    public function saveBulk(array $documents): void;

    /**
     * Mark a document as having events extracted.
     */
    public function markEventsAsExtracted(string $documentId, bool $extracted): void;

    /**
     * Count documents linked to a specific WatchFile.
     */
    public function countDocumentsForWatchFile(string $watchFileId): int;

    /**
     * Count documents per WatchFile.
     *
     * @return array<string, int> Associative array mapping WatchFile ID to document count
     */
    public function countDocumentsPerWatchFile(): array;

    /**
     * Find WatchFile IDs that exceed the document quota.
     *
     * @param int $quota The maximum number of documents allowed per WatchFile
     *
     * @return array<string, int> Associative array mapping WatchFile ID to document count for WatchFiles exceeding the quota
     */
    public function findWatchFilesExceedingDocumentQuota(int $quota): array;

    /**
     * Find a document by its provider_id.
     *
     * @return Document|null The document if found, null otherwise
     */
    public function findByProviderId(string $providerId): ?Document;

    /**
     * Find a document indexed under the given canonical URL.
     *
     * Caller is expected to pass an already-normalized canonical URL
     * (see CanonicalUrlExtractor — TAR-1140). The match is an exact
     * keyword lookup; no normalization happens here.
     *
     * @param string      $canonicalUrl      Pre-normalized canonical URL to search for
     * @param string|null $excludeDocumentId Optional document id to exclude from results
     *                                       (used when the lookup is run while indexing
     *                                       the document itself)
     *
     * @return Document|null The first matching document, or null if no match
     */
    public function findByCanonicalUrl(string $canonicalUrl, ?string $excludeDocumentId = null): ?Document;

    /**
     * Find documents with AI validation stuck in pending status since before the given cutoff.
     *
     * @return list<array{id: string, title: string, processedAt: string, watchFileId: ?string}>
     */
    public function findStaleAiValidationDocuments(\DateTimeImmutable $cutoff, int $limit): array;

    /**
     * Return a page of document IDs sorted by id, optionally filtered by WatchFile.
     * Use $searchAfter with the last id of the previous page to paginate past the
     * default OpenSearch `max_result_window` (10k documents).
     *
     * @return list<string>
     */
    public function findAllIds(?string $watchFileId = null, int $limit = 500, ?string $searchAfter = null): array;
}
