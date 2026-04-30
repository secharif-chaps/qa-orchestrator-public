<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Pipeline;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentPipelineContext::class)]
class DocumentPipelineContextTest extends TestCase
{
    private Document $document;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->document = $this->createStub(Document::class);
        $this->document->method('getId')
            ->willReturn('550e8400-e29b-41d4-a716-446655440000');

        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testWithSignalCreatesNewContextWithSignal(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $signal = new Signal(
            value: 0.8,
            weight: 1.0,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(fr: 'Score de contenu élevé', en: 'High content score'),
        );

        $newContext = $context->withSignal('content_score', $signal);

        self::assertArrayHasKey('content_score', $newContext->signals);
        self::assertSame($signal, $newContext->signals['content_score']);

        // Original context is unchanged (immutability)
        self::assertEmpty($context->signals);
    }

    public function testWithHaltCreatesNewContextWithHaltFlag(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile);
        $reason = new TranslatedText('Domaine bloqué', 'Blocked domain');

        $newContext = $context->withHalt($reason);

        self::assertTrue($newContext->isHalted);
        self::assertSame($reason, $newContext->haltReason);

        // Original context is unchanged (immutability)
        self::assertFalse($context->isHalted);
        self::assertNull($context->haltReason);
    }

    public function testIsHaltedReturnsFalseByDefault(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertFalse($context->isHalted);
    }

    public function testIsHaltedReturnsTrueAfterWithHalt(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $haltedContext = $context->withHalt(new TranslatedText('Domaine bloqué', 'Blocked domain'));

        self::assertTrue($haltedContext->isHalted);
    }

    public function testWithDuplicateOfCarriesTheReferencedDocumentId(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $duplicateContext = $context->withDuplicateOf('original-doc-id');

        self::assertSame('original-doc-id', $duplicateContext->duplicateOf);
        self::assertNull($context->duplicateOf);
    }
}
