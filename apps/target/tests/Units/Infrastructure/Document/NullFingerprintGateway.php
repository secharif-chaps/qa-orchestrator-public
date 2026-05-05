<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Deduplication\FingerprintGatewayInterface;
use App\Domain\Document\Document;

/**
 * In-memory test double for {@see FingerprintGatewayInterface}: callers
 * pre-load documents via {@see addDocument()} and the four lookups walk
 * the in-memory map applying the same rules as the OpenSearch impl
 * (exact contentHash, exact simHash, "shares ≥ 1 lshBand", "shares ≥ 1
 * titleShingle").
 */
class NullFingerprintGateway implements FingerprintGatewayInterface
{
    /** @var array<string, Document> */
    private array $documents = [];

    public function addDocument(Document $document): void
    {
        $this->documents[$document->getId()] = $document;
    }

    public function findByContentHash(string $contentHash, ?string $excludeDocumentId = null): ?Document
    {
        foreach ($this->documents as $id => $document) {
            if ($id === $excludeDocumentId) {
                continue;
            }

            $fingerprint = $document->getFingerprint();
            if (null !== $fingerprint && $fingerprint->contentHash === $contentHash) {
                return $document;
            }
        }

        return null;
    }

    public function findBySimHash(string $simHash, ?string $excludeDocumentId = null): array
    {
        $matches = [];
        foreach ($this->documents as $id => $document) {
            if ($id === $excludeDocumentId) {
                continue;
            }

            $fingerprint = $document->getFingerprint();
            if (null !== $fingerprint && $fingerprint->simHash === $simHash) {
                $matches[] = $document;
            }
        }

        return $matches;
    }

    public function findByLshBands(array $bandHashes, ?string $excludeDocumentId = null, int $limit = 50): array
    {
        $bandSet = array_flip($bandHashes);
        $matches = [];
        foreach ($this->documents as $id => $document) {
            if ($id === $excludeDocumentId) {
                continue;
            }

            $fingerprint = $document->getFingerprint();
            if (null === $fingerprint) {
                continue;
            }

            foreach ($fingerprint->lshBands as $band) {
                if (isset($bandSet[$band])) {
                    $matches[] = $document;
                    break;
                }
            }

            if (\count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    public function findByTitleShingles(array $titleShingles, ?string $excludeDocumentId = null, int $limit = 50): array
    {
        $shingleSet = array_flip($titleShingles);
        $matches = [];
        foreach ($this->documents as $id => $document) {
            if ($id === $excludeDocumentId) {
                continue;
            }

            $fingerprint = $document->getFingerprint();
            if (null === $fingerprint) {
                continue;
            }

            foreach ($fingerprint->titleShingles as $shingle) {
                if (isset($shingleSet[$shingle])) {
                    $matches[] = $document;
                    break;
                }
            }

            if (\count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }
}
