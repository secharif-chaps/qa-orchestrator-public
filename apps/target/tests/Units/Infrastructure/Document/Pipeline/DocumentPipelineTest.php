<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\DocumentProcessorInterface;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\DocumentPipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentPipeline::class)]
class DocumentPipelineTest extends TestCase
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

    public function testProcessorsRunInIterationOrder(): void
    {
        // Symfony's tagged_iterator injects processors in descending priority order.
        // The pipeline must respect that order — it does not sort itself.
        /** @var \ArrayObject<int, string> $callOrder */
        $callOrder = new \ArrayObject();

        $first = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(DocumentPipelineContext $context): DocumentPipelineContext
            {
                $this->callOrder->append('first');

                return $context->withSignal('first_signal', new Signal(
                    value: 0.8,
                    weight: 1.0,
                    category: SignalCategory::INFRASTRUCTURE_TRUST,
                ));
            }

            public function supports(DocumentPipelineContext $context): bool
            {
                return true;
            }
        };

        $second = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(DocumentPipelineContext $context): DocumentPipelineContext
            {
                $this->callOrder->append('second');

                return $context->withSignal('second_signal', new Signal(
                    value: 0.8,
                    weight: 1.0,
                    category: SignalCategory::METADATA,
                ));
            }

            public function supports(DocumentPipelineContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentPipeline([$first, $second]);
        $pipeline->process($this->document, $this->watchFile);

        self::assertSame(['first', 'second'], $callOrder->getArrayCopy());
    }

    public function testReturnsContextWithAccumulatedSignals(): void
    {
        $processor = new class implements DocumentProcessorInterface {
            public function process(DocumentPipelineContext $context): DocumentPipelineContext
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

            public function supports(DocumentPipelineContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentPipeline([$processor]);
        $context = $pipeline->process($this->document, $this->watchFile);

        self::assertCount(2, $context->signals);
        self::assertArrayHasKey('infra_signal', $context->signals);
        self::assertArrayHasKey('content_signal', $context->signals);
    }

    public function testHaltedContextStopsPipelineExecution(): void
    {
        /** @var \ArrayObject<int, string> $callOrder */
        $callOrder = new \ArrayObject();

        $halting = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(DocumentPipelineContext $context): DocumentPipelineContext
            {
                $this->callOrder->append('halting');

                return $context->withHalt(new TranslatedText('Domaine bloqué', 'Blocked domain'));
            }

            public function supports(DocumentPipelineContext $context): bool
            {
                return true;
            }
        };

        $later = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(DocumentPipelineContext $context): DocumentPipelineContext
            {
                $this->callOrder->append('later');

                return $context;
            }

            public function supports(DocumentPipelineContext $context): bool
            {
                return true;
            }
        };

        $pipeline = new DocumentPipeline([$halting, $later]);
        $context = $pipeline->process($this->document, $this->watchFile);

        // The later processor must not have been called.
        self::assertSame(['halting'], $callOrder->getArrayCopy());
        self::assertTrue($context->isHalted);
        self::assertNotNull($context->haltReason);
        self::assertSame('Blocked domain', $context->haltReason->en);
        self::assertSame('Domaine bloqué', $context->haltReason->fr);
    }

    public function testNotSupportedProcessorIsSkipped(): void
    {
        /** @var \ArrayObject<int, string> $callOrder */
        $callOrder = new \ArrayObject();

        $skipped = new class($callOrder) implements DocumentProcessorInterface {
            /** @param \ArrayObject<int, string> $callOrder */
            public function __construct(
                private \ArrayObject $callOrder,
            ) {
            }

            public function process(DocumentPipelineContext $context): DocumentPipelineContext
            {
                $this->callOrder->append('skipped');

                return $context;
            }

            public function supports(DocumentPipelineContext $context): bool
            {
                return false;
            }
        };

        $pipeline = new DocumentPipeline([$skipped]);
        $pipeline->process($this->document, $this->watchFile);

        self::assertEmpty($callOrder->getArrayCopy());
    }
}
