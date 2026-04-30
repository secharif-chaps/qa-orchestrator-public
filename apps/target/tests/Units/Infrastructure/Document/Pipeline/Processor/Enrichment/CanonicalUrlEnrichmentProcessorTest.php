<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Enrichment;

use App\Domain\Document\CanonicalUrlExtractor;
use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Enrichment\CanonicalUrlEnrichmentProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(CanonicalUrlEnrichmentProcessor::class)]
class CanonicalUrlEnrichmentProcessorTest extends TestCase
{
    private CanonicalUrlEnrichmentProcessor $processor;
    private Document&Stub $document;
    private WatchFile&Stub $watchFile;

    protected function setUp(): void
    {
        $this->processor = new CanonicalUrlEnrichmentProcessor(new CanonicalUrlExtractor());
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenContextIsHalted(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/article');
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withHalt(new TranslatedText('Bloqué', 'Blocked'));

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenCanonicalUrlAlreadyPresent(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/article');
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            canonicalUrl: 'https://example.com/already-resolved',
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenNoUrlAndNoRawHtml(): void
    {
        $this->document->method('getUrl')
            ->willReturn(null);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueWhenUrlPresentWithoutCanonical(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/article');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueWhenRawHtmlPresentWithoutUrl(): void
    {
        $this->document->method('getUrl')
            ->willReturn(null);
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            rawHtml: '<html><head><link rel="canonical" href="https://example.com/article"></head></html>',
        );

        self::assertTrue($this->processor->supports($context));
    }

    public function testProcessSetsCanonicalUrlFromSourceWhenNoHtml(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/article?utm_source=newsletter');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame('https://example.com/article', $result->canonicalUrl);
    }

    public function testProcessUsesCanonicalLinkFromRawHtml(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://lemonde.fr/republished?utm_source=newsletter');
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            rawHtml: '<html><head><link rel="canonical" href="https://www.afp.com/article/42"></head></html>',
        );

        $result = $this->processor->process($context);

        self::assertSame('https://www.afp.com/article/42', $result->canonicalUrl);
    }

    public function testProcessFallsBackToOgUrl(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/source');
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            rawHtml: '<html><head><meta property="og:url" content="https://example.com/og-article"></head></html>',
        );

        $result = $this->processor->process($context);

        self::assertSame('https://example.com/og-article', $result->canonicalUrl);
    }

    public function testProcessPreservesOtherContextFields(): void
    {
        $this->document->method('getUrl')
            ->willReturn('https://example.com/article');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame($this->document, $result->document);
        self::assertSame($this->watchFile, $result->watchFile);
        self::assertSame([], $result->signals);
        self::assertFalse($result->isHalted);
        self::assertNull($result->haltReason);
        self::assertNull($result->duplicateOf);
    }

    public function testProcessLeavesCanonicalUrlNullWhenNothingExtractable(): void
    {
        $this->document->method('getUrl')
            ->willReturn('javascript:alert(1)');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertNull($result->canonicalUrl);
    }
}
