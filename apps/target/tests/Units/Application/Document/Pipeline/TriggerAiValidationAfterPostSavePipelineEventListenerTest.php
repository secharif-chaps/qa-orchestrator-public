<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document\Pipeline;

use App\Application\Document\Pipeline\PostSavePipelineCompletedEvent;
use App\Application\Document\Pipeline\TriggerAiValidationAfterPostSavePipelineEventListener;
use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Domain\Document\AIValidation;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\QualityDecision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[CoversClass(TriggerAiValidationAfterPostSavePipelineEventListener::class)]
class TriggerAiValidationAfterPostSavePipelineEventListenerTest extends TestCase
{
    /** @var MessageBusInterface&\PHPUnit\Framework\MockObject\MockObject */
    private MessageBusInterface $messageBus;

    /** @var DocumentGatewayInterface&\PHPUnit\Framework\MockObject\MockObject */
    private DocumentGatewayInterface $documentGateway;
    private TriggerAiValidationAfterPostSavePipelineEventListener $listener;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->document = $this->createStub(Document::class);

        $this->listener = new TriggerAiValidationAfterPostSavePipelineEventListener(
            $this->documentGateway,
            $this->messageBus,
        );
    }

    #[DataProvider('skippedDecisionsProvider')]
    public function testSkipsDispatchForLowQualityOrRejectedDecisions(string $decision): void
    {
        $this->documentGateway->expects($this->never())
->method('get');
        $this->messageBus->expects($this->never())
->method('dispatch');

        ($this->listener)(new PostSavePipelineCompletedEvent(
            documentId: 'doc-1',
            reportId: 'report-1',
            overallScore: 0.1,
            decision: $decision,
        ));
    }

    /** @return array<string, array{string}> */
    public static function skippedDecisionsProvider(): array
    {
        return [
            'low_quality' => [QualityDecision::LOW_QUALITY->value],
            'rejected' => [QualityDecision::REJECTED->value],
        ];
    }

    public function testSkipsDispatchWhenAiValidationAlreadyExists(): void
    {
        $this->document->method('getAiValidation')
->willReturn($this->createStub(AIValidation::class));
        $this->documentGateway->method('get')
->with('doc-1')
->willReturn($this->document);
        $this->messageBus->expects($this->never())
->method('dispatch');

        ($this->listener)(new PostSavePipelineCompletedEvent(
            documentId: 'doc-1',
            reportId: 'report-1',
            overallScore: 0.75,
            decision: QualityDecision::ACCEPTED->value,
        ));
    }

    #[DataProvider('eligibleDecisionsProvider')]
    public function testDispatchesAiValidationForEligibleDocuments(string $decision): void
    {
        $this->document->method('getAiValidation')
->willReturn(null);
        $this->documentGateway->method('get')
->with('doc-1')
->willReturn($this->document);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    static fn (mixed $message): bool => $message instanceof TriggerDocumentAiValidationAction
                    && 'doc-1' === $message->documentId
                ),
                $this->callback(static fn (array $stamps): bool => isset($stamps[0])
                    && $stamps[0] instanceof DispatchAfterCurrentBusStamp),
            )
            ->willReturn(new Envelope(new TriggerDocumentAiValidationAction('doc-1')));

        ($this->listener)(new PostSavePipelineCompletedEvent(
            documentId: 'doc-1',
            reportId: 'report-1',
            overallScore: 0.75,
            decision: $decision,
        ));
    }

    /** @return array<string, array{string}> */
    public static function eligibleDecisionsProvider(): array
    {
        return [
            'accepted' => [QualityDecision::ACCEPTED->value],
            'review' => [QualityDecision::REVIEW->value],
        ];
    }
}
