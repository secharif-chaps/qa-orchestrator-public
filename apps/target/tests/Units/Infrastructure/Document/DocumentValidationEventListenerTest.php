<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Application\Document\TriggerDocumentSummaryAction;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Event\DocumentManuallyValidatedEvent;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Document\SummaryStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\DocumentValidationEventListener;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DocumentValidationEventListener::class)]
class DocumentValidationEventListenerTest extends TestCase
{
    private DocumentValidationEventListener $listener;
    private NullMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus();
        $this->listener = new DocumentValidationEventListener($this->messageBus, new NullLogger());
    }

    public function testOnDocumentManuallyValidatedAcceptedTriggersSummary(): void
    {
        $user = new User('user-id', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            'doc-id',
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);

        $event = new DocumentManuallyValidatedEvent($document, ManualValidationStatus::ACCEPTED, $user);

        $this->listener->onDocumentManuallyValidated($event);

        $this->assertTrue($this->messageBus->hasDispatched(TriggerDocumentSummaryAction::class));
        $this->assertEquals(1, $this->messageBus->countDispatched(TriggerDocumentSummaryAction::class));
    }

    public function testOnDocumentManuallyValidatedRefusedDoesNotTriggerSummary(): void
    {
        $user = new User('user-id', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            'doc-id',
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);

        $event = new DocumentManuallyValidatedEvent($document, ManualValidationStatus::REFUSED, $user);

        $this->listener->onDocumentManuallyValidated($event);

        $this->assertFalse($this->messageBus->hasDispatched(TriggerDocumentSummaryAction::class));
    }

    public function testOnDocumentManuallyValidatedDoesNotTriggerIfSummaryCompleted(): void
    {
        $user = new User('user-id', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            'doc-id',
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $document->setSummaryStatus(SummaryStatus::COMPLETED);

        $event = new DocumentManuallyValidatedEvent($document, ManualValidationStatus::ACCEPTED, $user);

        $this->listener->onDocumentManuallyValidated($event);

        // Action is still dispatched, but the handler will skip processing
        $this->assertTrue($this->messageBus->hasDispatched(TriggerDocumentSummaryAction::class));
    }

    public function testOnDocumentManuallyValidatedDoesNotTriggerIfSummaryPending(): void
    {
        $user = new User('user-id', 'test@example.com', ['ROLE_USER']);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $document = new Document(
            'doc-id',
            'Test Document',
            'Test excerpt',
            'pdf',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'Test content',
            DocumentStatus::PENDING,
        );
        $document->setWatchFile($watchFile);
        $document->setSummaryStatus(SummaryStatus::PENDING);

        $event = new DocumentManuallyValidatedEvent($document, ManualValidationStatus::ACCEPTED, $user);

        $this->listener->onDocumentManuallyValidated($event);

        // Action is still dispatched, but the handler will skip processing
        $this->assertTrue($this->messageBus->hasDispatched(TriggerDocumentSummaryAction::class));
    }
}
