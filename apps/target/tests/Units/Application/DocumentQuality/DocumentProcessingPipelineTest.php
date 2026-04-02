<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\DefaultScoringStrategy;
use App\Domain\DocumentQuality\DocumentProcessorInterface;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\DocumentQuality\DocumentProcessingPipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DocumentProcessingPipeline::class)]
class DocumentProcessingPipelineTest extends TestCase
{
    private Document $document;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->document = $this->createStub(Document::class);
        $this->document->method('getId')
->willReturn('550e8400-e29b-41d4-a716-446655440000');

        $this->watchFile = $this->createStub(WatchFile::class);
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig());
    }

    public function testProcessorsSortedByPriorityDescending(): void
    {
        // The pipeline itself does not sort — Symfony's tagged_iterator injects processors
        // in descending priority order. This test verifies that the pipeline respects the
        // iteration order and processes high-priority processors before low-priority ones,
        // matching the contract expected when Symfony wires the service.
        /** @var \ArrayObject<int, string> $callOrder */
        $callOrder = new \ArrayObject();

        $highPriorityProcessor = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(ProcessingContext $context): ProcessingContext
            {
                $this->callOrder->append('high');

                return $context->withSignal('high_signal', new Signal(
                    value: 0.8,
                    weight: 1.0,
                    category: SignalCategory::INFRASTRUCTURE_TRUST,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $lowPriorityProcessor = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(ProcessingContext $context): ProcessingContext
            {
                $this->callOrder->append('low');

                return $context->withSignal('low_signal', new Signal(
                    value: 0.8,
                    weight: 1.0,
                    category: SignalCategory::METADATA,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        // Processors are injected by Symfony already sorted descending by priority (high first).
        // The pipeline must preserve that order.
        $pipeline = new DocumentProcessingPipeline([
            $highPriorityProcessor,
            $lowPriorityProcessor,
        ], new DefaultScoringStrategy(), new NullLogger());
        $pipeline->process($this->document, $this->watchFile);

        self::assertSame(['high', 'low'], $callOrder->getArrayCopy());
    }

    public function testEarlyExitWhenEarlyDecisionSet(): void
    {
        /** @var \ArrayObject<int, string> $callOrder */
        $callOrder = new \ArrayObject();

        $rejectingProcessor = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(ProcessingContext $context): ProcessingContext
            {
                $this->callOrder->append('rejecting');

                return $context->withEarlyDecision(QualityDecision::REJECTED, 'Blocked domain');
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $laterProcessor = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(ProcessingContext $context): ProcessingContext
            {
                $this->callOrder->append('later');

                return $context;
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([
            $rejectingProcessor,
            $laterProcessor,
        ], new DefaultScoringStrategy(), new NullLogger());
        $pipeline->process($this->document, $this->watchFile);

        // The later processor should never have been called
        self::assertSame(['rejecting'], $callOrder->getArrayCopy());
    }

    public function testFinalScoreIsMinOfCategoryAverages(): void
    {
        // Category A (INFRASTRUCTURE_TRUST): single signal with value 0.9, weight 1.0 → average = 0.9
        // Category B (CONTENT_QUALITY): single signal with value 0.3, weight 1.0 → average = 0.3
        // min(0.9, 0.3) = 0.3
        $processor = new class implements DocumentProcessorInterface {
            public function process(ProcessingContext $context): ProcessingContext
            {
                $context = $context->withSignal('infra_signal', new Signal(
                    value: 0.9,
                    weight: 1.0,
                    category: SignalCategory::INFRASTRUCTURE_TRUST,
                ));

                return $context->withSignal('content_signal', new Signal(
                    value: 0.3,
                    weight: 1.0,
                    category: SignalCategory::CONTENT_QUALITY,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([$processor], new DefaultScoringStrategy(), new NullLogger());
        $report = $pipeline->process($this->document, $this->watchFile);

        self::assertEquals(0.3, $report->overallScore);
    }

    public function testRoutingToAccepted(): void
    {
        // Score >= 0.7 → ACCEPTED (default autoAcceptThreshold = 0.7)
        $processor = new class implements DocumentProcessorInterface {
            public function process(ProcessingContext $context): ProcessingContext
            {
                return $context->withSignal('strong_signal', new Signal(
                    value: 0.9,
                    weight: 1.0,
                    category: SignalCategory::CONTENT_QUALITY,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([$processor], new DefaultScoringStrategy(), new NullLogger());
        $report = $pipeline->process($this->document, $this->watchFile);

        self::assertSame(QualityDecision::ACCEPTED, $report->decision);
    }

    public function testRoutingToReview(): void
    {
        // Score between 0.2 and 0.7 → REVIEW
        $processor = new class implements DocumentProcessorInterface {
            public function process(ProcessingContext $context): ProcessingContext
            {
                return $context->withSignal('medium_signal', new Signal(
                    value: 0.5,
                    weight: 1.0,
                    category: SignalCategory::CONTENT_QUALITY,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([$processor], new DefaultScoringStrategy(), new NullLogger());
        $report = $pipeline->process($this->document, $this->watchFile);

        self::assertSame(QualityDecision::REVIEW, $report->decision);
    }

    public function testRoutingToLowQuality(): void
    {
        // Score < 0.2 → LOW_QUALITY (default autoRejectThreshold = 0.2)
        $processor = new class implements DocumentProcessorInterface {
            public function process(ProcessingContext $context): ProcessingContext
            {
                return $context->withSignal('weak_signal', new Signal(
                    value: 0.1,
                    weight: 1.0,
                    category: SignalCategory::CONTENT_QUALITY,
                ));
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([$processor], new DefaultScoringStrategy(), new NullLogger());
        $report = $pipeline->process($this->document, $this->watchFile);

        self::assertSame(QualityDecision::LOW_QUALITY, $report->decision);
    }

    public function testRejectedEarlyDecisionSkipsScoring(): void
    {
        $processor = new class implements DocumentProcessorInterface {
            public function process(ProcessingContext $context): ProcessingContext
            {
                // Add a signal that would otherwise produce a high score
                $context = $context->withSignal('good_signal', new Signal(
                    value: 1.0,
                    weight: 1.0,
                    category: SignalCategory::CONTENT_QUALITY,
                ));

                // Then immediately reject
                return $context->withEarlyDecision(QualityDecision::REJECTED, 'Blocked domain detected');
            }

            public function supports(ProcessingContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentProcessingPipeline([$processor], new DefaultScoringStrategy(), new NullLogger());
        $report = $pipeline->process($this->document, $this->watchFile);

        self::assertSame(QualityDecision::REJECTED, $report->decision);
        self::assertNull($report->overallScore);
    }
}
