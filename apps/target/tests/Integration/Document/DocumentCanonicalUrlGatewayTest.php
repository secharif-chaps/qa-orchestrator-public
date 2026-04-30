<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test: round-trips a Document through the real OpenSearch test
 * index (provisioned by `task target:test:integration:setup`) to verify that
 * `findByCanonicalUrl` returns the expected document, honours the
 * `excludeDocumentId` filter, and yields null when no match exists.
 */
#[CoversClass(\App\Infrastructure\Document\DocumentOpenSearchGateway::class)]
final class DocumentCanonicalUrlGatewayTest extends KernelTestCase
{
    private const string TEST_RUN_TAG = 'tar-1141-canonical-url-test';
    private DocumentGatewayInterface $gateway;
    private Client $openSearch;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var DocumentGatewayInterface $gateway */
        $gateway = self::getContainer()->get(DocumentGatewayInterface::class);
        $this->gateway = $gateway;

        /** @var Client $client */
        $client = self::getContainer()->get(Client::class);
        $this->openSearch = $client;

        $this->cleanupTestDocuments();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDocuments();
        parent::tearDown();
    }

    #[Test]
    public function findsDocumentByExactCanonicalUrlMatch(): void
    {
        $canonical = 'https://example.com/article/' . self::TEST_RUN_TAG . '-1';
        $document = $this->indexDocument('doc-canonical-1', $canonical);

        $found = $this->gateway->findByCanonicalUrl($canonical);

        self::assertNotNull($found);
        self::assertSame($document->getId(), $found->getId());
        self::assertSame($canonical, $found->getCanonicalUrl());
    }

    #[Test]
    public function returnsNullWhenCanonicalUrlNotIndexed(): void
    {
        $found = $this->gateway->findByCanonicalUrl(
            'https://example.com/missing/' . self::TEST_RUN_TAG . '-never-indexed',
        );

        self::assertNull($found);
    }

    #[Test]
    public function excludesGivenDocumentIdFromResults(): void
    {
        $canonical = 'https://afp.com/article/' . self::TEST_RUN_TAG . '-syndicated';
        $original = $this->indexDocument('doc-canonical-original', $canonical);
        $duplicate = $this->indexDocument('doc-canonical-duplicate', $canonical);

        $found = $this->gateway->findByCanonicalUrl($canonical, excludeDocumentId: $duplicate->getId());

        self::assertNotNull($found);
        self::assertSame($original->getId(), $found->getId());
    }

    #[Test]
    public function returnsNullWhenOnlyExcludedDocumentMatches(): void
    {
        $canonical = 'https://example.com/article/' . self::TEST_RUN_TAG . '-only-self';
        $document = $this->indexDocument('doc-canonical-only-self', $canonical);

        $found = $this->gateway->findByCanonicalUrl($canonical, excludeDocumentId: $document->getId());

        self::assertNull($found);
    }

    #[Test]
    public function distinguishesDocumentsWithDifferentCanonicalUrls(): void
    {
        $canonicalA = 'https://example.com/article/' . self::TEST_RUN_TAG . '-a';
        $canonicalB = 'https://example.com/article/' . self::TEST_RUN_TAG . '-b';

        $documentA = $this->indexDocument('doc-canonical-a', $canonicalA);
        $documentB = $this->indexDocument('doc-canonical-b', $canonicalB);

        $foundA = $this->gateway->findByCanonicalUrl($canonicalA);
        $foundB = $this->gateway->findByCanonicalUrl($canonicalB);

        self::assertNotNull($foundA);
        self::assertSame($documentA->getId(), $foundA->getId());
        self::assertNotNull($foundB);
        self::assertSame($documentB->getId(), $foundB->getId());
    }

    #[Test]
    public function ignoresDocumentsWithoutCanonicalUrl(): void
    {
        // A document indexed without canonicalUrl must not be returned by
        // a lookup on any non-empty canonical.
        $this->indexDocument('doc-without-canonical', null);

        $found = $this->gateway->findByCanonicalUrl(
            'https://example.com/article/' . self::TEST_RUN_TAG . '-no-canonical',
        );

        self::assertNull($found);
    }

    #[Test]
    public function canonicalUrlMatchIsCaseSensitive(): void
    {
        // The gateway expects a *pre-normalized* canonical URL from the caller
        // (TAR-1140 lowercases scheme/host before storage). Variants must not
        // match — that responsibility lives with the producer, not the lookup.
        $canonical = 'https://example.com/article/' . self::TEST_RUN_TAG . '-case';
        $this->indexDocument('doc-canonical-case', $canonical);

        $found = $this->gateway->findByCanonicalUrl('https://Example.com/article/' . self::TEST_RUN_TAG . '-case');

        self::assertNull($found);
    }

    private function indexDocument(string $id, ?string $canonicalUrl): Document
    {
        $document = new Document(
            id: $id,
            title: 'Integration test document',
            excerpt: 'Excerpt for canonical URL integration test',
            type: 'html',
            datePublish: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            dateCollect: new \DateTimeImmutable('2026-01-02T00:00:00Z'),
            content: '<p>Test content for canonical URL integration test.</p>',
            status: DocumentStatus::PENDING,
        );

        if (null !== $canonicalUrl) {
            $document->setCanonicalUrl($canonicalUrl);
        }

        $this->gateway->save($document);
        $this->refreshDocumentIndex();

        return $document;
    }

    private function refreshDocumentIndex(): void
    {
        $this->openSearch->indices()
            ->refresh([
                'index' => Document::INDEX_NAME,
            ]);
    }

    private function cleanupTestDocuments(): void
    {
        try {
            $this->openSearch->deleteByQuery([
                'index' => Document::INDEX_NAME,
                'body' => [
                    'query' => [
                        'wildcard' => [
                            'canonicalUrl' => '*' . self::TEST_RUN_TAG . '*',
                        ],
                    ],
                ],
                'refresh' => true,
                'conflicts' => 'proceed',
            ]);
            $this->openSearch->deleteByQuery([
                'index' => Document::INDEX_NAME,
                'body' => [
                    'query' => [
                        'ids' => [
                            'values' => ['doc-without-canonical'],
                        ],
                    ],
                ],
                'refresh' => true,
                'conflicts' => 'proceed',
            ]);
        } catch (\Exception) {
            // Cleanup is best-effort; failures here must not mask the actual test result.
        }
    }
}
