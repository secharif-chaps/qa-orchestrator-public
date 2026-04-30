<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document\Pipeline;

use App\Application\Document\Pipeline\PostSavePipelineCompletedEvent;
use App\Application\Document\Pipeline\RunPostSavePipelineAction;
use App\Application\Document\Pipeline\RunPostSavePipelineHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Document\Pipeline\PostSaveDocumentPipelineInterface;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\QualityReport;
use App\Domain\DocumentQuality\QualityReportBuilder;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\Document\Pipeline\PostSaveDocumentPipeline;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for the asynchronous orchestration done by RunPostSavePipelineHandler:
 * load document → run post-save pipeline → build QualityReport → save report
 * → dispatch PostSavePipelineCompletedEvent.
 *
 * The integration suite covers the real Doctrine + message-bus path; this
 * unit test pins the orchestration logic and the error branch (document
 * without WatchFile).
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RunPostSavePipelineHandler::class)]
class RunPostSavePipelineHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private DocumentGatewayInterface&MockObject $documentGateway;
    private WatchFileGatewayInterface&MockObject $watchFileGateway;
    private PostSaveDocumentPipelineInterface $postSavePipeline;
    private QualityReportBuilder&MockObject $qualityReportBuilder;
    private QualityReportGatewayInterface&MockObject $qualityReportGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private RunPostSavePipelineHandler $handler;

    protected function setUp(): void
    {
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->watchFileGateway = $this->createMock(WatchFileGatewayInterface::class);
        // Real pipeline with no processors → returns the seed context unchanged.
        $this->postSavePipeline = new PostSaveDocumentPipeline([]);
        $this->qualityReportBuilder = $this->createMock(QualityReportBuilder::class);
        $this->qualityReportGateway = $this->createMock(QualityReportGatewayInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $this->handler = new RunPostSavePipelineHandler(
            documentGateway: $this->documentGateway,
            watchFileGateway: $this->watchFileGateway,
            postSavePipeline: $this->postSavePipeline,
            qualityReportBuilder: $this->qualityReportBuilder,
            qualityReportGateway: $this->qualityReportGateway,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    public function testRunsPipelineBuildsReportAndDispatchesEvent(): void
    {
        $watchFile = $this->makeWatchFile('wf-1');
        $document = $this->makeDocument('doc-1', $watchFile);

        $this->documentGateway
            ->expects($this->once())
            ->method('get')
            ->with('doc-1')
            ->willReturn($document);

        $this->watchFileGateway
            ->expects($this->once())
            ->method('get')
            ->with('wf-1')
            ->willReturn($watchFile);

        $expectedReport = $this->makeReport('doc-1', QualityDecision::ACCEPTED, overallScore: 0.85);
        $this->qualityReportBuilder
            ->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf(DocumentPipelineContext::class), $this->isInstanceOf(QualityConfig::class))
            ->willReturn($expectedReport);

        $this->qualityReportGateway
            ->expects($this->once())
            ->method('save')
            ->with($expectedReport)
            ->willReturn('persisted-report-id');

        $dispatched = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched): object {
                $dispatched = $event;

                return $event;
            });
        $handler = new RunPostSavePipelineHandler(
            documentGateway: $this->documentGateway,
            watchFileGateway: $this->watchFileGateway,
            postSavePipeline: $this->postSavePipeline,
            qualityReportBuilder: $this->qualityReportBuilder,
            qualityReportGateway: $this->qualityReportGateway,
            eventDispatcher: $eventDispatcher,
        );

        $handler(new RunPostSavePipelineAction(documentId: 'doc-1'));

        self::assertInstanceOf(PostSavePipelineCompletedEvent::class, $dispatched);
        self::assertSame('doc-1', $dispatched->documentId);
        self::assertSame('persisted-report-id', $dispatched->reportId);
        self::assertSame(0.85, $dispatched->overallScore);
        self::assertSame(QualityDecision::ACCEPTED->value, $dispatched->decision);
    }

    public function testThrowsWhenDocumentHasNoAssociatedWatchFile(): void
    {
        $document = $this->makeDocument('doc-2', watchFile: null);

        $this->documentGateway
            ->method('get')
            ->with('doc-2')
            ->willReturn($document);

        $this->watchFileGateway
            ->expects($this->never())
            ->method('get');

        $this->qualityReportBuilder
            ->expects($this->never())
            ->method('build');

        $this->qualityReportGateway
            ->expects($this->never())
            ->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document "doc-2" has no associated WatchFile.');

        ($this->handler)(new RunPostSavePipelineAction(documentId: 'doc-2'));
    }

    public function testRejectedReportIsStillPersistedAndEventDispatched(): void
    {
        $watchFile = $this->makeWatchFile('wf-rejected');
        $document = $this->makeDocument('doc-rejected', $watchFile);

        $this->documentGateway->method('get')
            ->willReturn($document);
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $rejectedReport = $this->makeReport(
            documentId: 'doc-rejected',
            decision: QualityDecision::REJECTED,
            overallScore: null,
            decisionReason: new TranslatedText('Domaine bloqué', 'Blocked domain'),
        );

        $this->qualityReportBuilder
            ->method('build')
            ->willReturn($rejectedReport);

        $this->qualityReportGateway
            ->expects($this->once())
            ->method('save')
            ->with($rejectedReport)
            ->willReturn('rejected-report-id');

        $dispatched = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched): object {
                $dispatched = $event;

                return $event;
            });
        $handler = new RunPostSavePipelineHandler(
            documentGateway: $this->documentGateway,
            watchFileGateway: $this->watchFileGateway,
            postSavePipeline: $this->postSavePipeline,
            qualityReportBuilder: $this->qualityReportBuilder,
            qualityReportGateway: $this->qualityReportGateway,
            eventDispatcher: $eventDispatcher,
        );

        $handler(new RunPostSavePipelineAction(documentId: 'doc-rejected'));

        self::assertInstanceOf(PostSavePipelineCompletedEvent::class, $dispatched);
        self::assertSame(QualityDecision::REJECTED->value, $dispatched->decision);
        self::assertNull($dispatched->overallScore);
        self::assertSame('rejected-report-id', $dispatched->reportId);
    }

    public function testPipelineContextIsBuiltFromDocumentAndWatchFile(): void
    {
        $watchFile = $this->makeWatchFile('wf-context');
        $document = $this->makeDocument('doc-context', $watchFile);

        $this->documentGateway->method('get')
            ->willReturn($document);
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $capturedContext = null;
        $this->qualityReportBuilder
            ->expects($this->once())
            ->method('build')
            ->willReturnCallback(
                function (DocumentPipelineContext $context) use (&$capturedContext, $document): QualityReport {
                    $capturedContext = $context;

                    return $this->makeReport($document->getId(), QualityDecision::REVIEW, 0.5);
                },
            );

        $this->qualityReportGateway->method('save')
            ->willReturn('report-id');

        ($this->handler)(new RunPostSavePipelineAction(documentId: 'doc-context'));

        self::assertNotNull($capturedContext);
        self::assertSame($document, $capturedContext->document);
        self::assertSame($watchFile, $capturedContext->watchFile);
        self::assertFalse($capturedContext->isHalted);
    }

    private function makeWatchFile(string $id): WatchFile
    {
        $organisation = new Organisation('Test Org', 'test-org-id');
        $watchFile = new WatchFile('Test WF', 'objective', $organisation);
        $this->forcePropertyValue($watchFile, $id);

        return $watchFile;
    }

    private function makeDocument(string $id, ?WatchFile $watchFile): Document
    {
        $document = new Document(
            id: $id,
            title: 'Test Document',
            excerpt: 'Excerpt for unit test',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Body</p>',
        );

        if (null !== $watchFile) {
            $document->setWatchFile($watchFile);
        }

        return $document;
    }

    private function makeReport(
        string $documentId,
        QualityDecision $decision,
        ?float $overallScore = null,
        ?TranslatedText $decisionReason = null,
    ): QualityReport {
        return new QualityReport(
            documentId: $documentId,
            overallScore: $overallScore,
            categoryScores: [],
            signals: [],
            decision: $decision,
            decisionReason: $decisionReason,
        );
    }
}
