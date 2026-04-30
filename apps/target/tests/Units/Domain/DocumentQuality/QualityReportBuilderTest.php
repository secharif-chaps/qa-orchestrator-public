<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\DefaultScoringStrategy;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\QualityReportBuilder;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualityReportBuilder::class)]
class QualityReportBuilderTest extends TestCase
{
    private Document $document;
    private WatchFile $watchFile;
    private QualityReportBuilder $builder;

    protected function setUp(): void
    {
        $this->document = $this->createStub(Document::class);
        $this->document->method('getId')
            ->willReturn('550e8400-e29b-41d4-a716-446655440000');

        $this->watchFile = $this->createStub(WatchFile::class);
        $this->builder = new QualityReportBuilder(new DefaultScoringStrategy());
    }

    public function testHaltedContextProducesRejectedReport(): void
    {
        $reason = new TranslatedText('Domaine bloqué détecté', 'Blocked domain detected');
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withSignal('high_signal', new Signal(
                value: 0.9,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
            ))
            ->withHalt($reason);

        $report = $this->builder->build($context, new QualityConfig());

        self::assertSame(QualityDecision::REJECTED, $report->decision);
        self::assertNull($report->overallScore);
        self::assertSame($reason, $report->decisionReason);
    }

    public function testFinalScoreIsMinOfCategoryAverages(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withSignal('infra_signal', new Signal(
                value: 0.9,
                weight: 1.0,
                category: SignalCategory::INFRASTRUCTURE_TRUST,
            ))
            ->withSignal('content_signal', new Signal(
                value: 0.3,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
            ));

        $report = $this->builder->build($context, new QualityConfig());

        self::assertEquals(0.3, $report->overallScore);
    }

    public function testRoutingToAccepted(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withSignal('strong_signal', new Signal(
                value: 0.9,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
            ));

        $report = $this->builder->build($context, new QualityConfig());

        self::assertSame(QualityDecision::ACCEPTED, $report->decision);
    }

    public function testRoutingToReview(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withSignal('medium_signal', new Signal(
                value: 0.5,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
            ));

        $report = $this->builder->build($context, new QualityConfig());

        self::assertSame(QualityDecision::REVIEW, $report->decision);
    }

    public function testRoutingToLowQuality(): void
    {
        $context = new DocumentPipelineContext($this->document, $this->watchFile)
            ->withSignal('weak_signal', new Signal(
                value: 0.1,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
            ));

        $report = $this->builder->build($context, new QualityConfig());

        self::assertSame(QualityDecision::LOW_QUALITY, $report->decision);
    }
}
