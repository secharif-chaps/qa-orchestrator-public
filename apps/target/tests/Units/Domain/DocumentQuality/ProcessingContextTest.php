<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessingContext::class)]
class ProcessingContextTest extends TestCase
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
        $context = new ProcessingContext($this->document, $this->watchFile);

        $signal = new Signal(
            value: 0.8,
            weight: 1.0,
            category: SignalCategory::CONTENT_QUALITY,
            reason: new TranslatedText(fr: 'Score de contenu élevé', en: 'High content score'),
        );

        $newContext = $context->withSignal('content_score', $signal);

        // New context has the signal
        self::assertArrayHasKey('content_score', $newContext->signals);
        self::assertSame($signal, $newContext->signals['content_score']);

        // Original context is unchanged (immutability)
        self::assertEmpty($context->signals);
    }

    public function testWithEarlyDecisionCreatesNewContext(): void
    {
        $context = new ProcessingContext($this->document, $this->watchFile);

        $newContext = $context->withEarlyDecision(QualityDecision::REJECTED, 'Blocked domain');

        // New context has the early decision
        self::assertSame(QualityDecision::REJECTED, $newContext->earlyDecision);
        self::assertSame('Blocked domain', $newContext->earlyDecisionReason);

        // Original context is unchanged (immutability)
        self::assertNull($context->earlyDecision);
        self::assertNull($context->earlyDecisionReason);
    }

    public function testHasEarlyDecisionReturnsFalseWhenNull(): void
    {
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertFalse($context->hasEarlyDecision());
    }

    public function testHasEarlyDecisionReturnsTrueWhenSet(): void
    {
        $context = new ProcessingContext($this->document, $this->watchFile);

        $contextWithDecision = $context->withEarlyDecision(QualityDecision::REJECTED, 'Blocked domain');

        self::assertTrue($contextWithDecision->hasEarlyDecision());
    }
}
