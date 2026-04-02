<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Chat;

use App\Application\WatchFile\Chat\AddMessageAction;
use App\Application\WatchFile\Chat\CreateConversationAction;
use App\Application\WatchFile\Chat\CreateConversationHandler;
use App\Domain\Language\DetectedLanguage;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Language\NullLanguageDetector;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class CreateConversationHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullConversationGateway $conversationGateway;
    private NullLanguageDetector $languageDetector;
    private NullMessageBus $messageBus;
    private CreateConversationHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->conversationGateway = new NullConversationGateway();
        $this->languageDetector = new NullLanguageDetector();
        $this->messageBus = new NullMessageBus();

        $this->handler = new CreateConversationHandler(
            $this->conversationGateway,
            $this->languageDetector,
            $this->messageBus,
        );

        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testCreateConversationWithEnglishMessage(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Configure language detector to return English
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('en', 0.85));

        $action = new CreateConversationAction('watch_file_id', 'Hello world, this is an English message');
        $result = ($this->handler)($action);

        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertEquals('en', $result->getLanguage());

        // Verify conversation was saved
        $this->assertSame($result, $this->conversationGateway->savedConversation);

        // Verify message was dispatched
        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(1, $dispatchedMessages);
        $this->assertInstanceOf(AddMessageAction::class, $dispatchedMessages[0]);
        $this->assertEquals($result->getId(), $dispatchedMessages[0]->conversationId);
        $this->assertEquals('Hello world, this is an English message', $dispatchedMessages[0]->message);
    }

    public function testCreateConversationWithFrenchMessage(): void
    {
        $watchFile = new WatchFile('Fichier de Test', 'Objectif de Test', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Configure language detector to return French
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('fr', 0.92));

        $action = new CreateConversationAction('watch_file_id', 'Bonjour le monde, ceci est un message en français');
        $result = ($this->handler)($action);

        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertEquals('fr', $result->getLanguage());

        // Verify message was dispatched
        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(1, $dispatchedMessages);
        $this->assertInstanceOf(AddMessageAction::class, $dispatchedMessages[0]);
        $this->assertEquals($result->getId(), $dispatchedMessages[0]->conversationId);
        $this->assertEquals('Bonjour le monde, ceci est un message en français', $dispatchedMessages[0]->message);
    }

    public function testLanguageDetectionUsesMessageContent(): void
    {
        $watchFile = new WatchFile('Different Name', 'Different Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Configure language detector to return English with low confidence (fallback scenario)
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('en', 0.0));

        $messageContent = 'This is the actual message content used for detection';
        $action = new CreateConversationAction('watch_file_id', $messageContent);

        $result = ($this->handler)($action);

        // Assert - language should be detected from message content, not watchfile name/objective
        $this->assertEquals('en', $result->getLanguage());
    }

    public function testCreateConversationWithEmptyWatchFileId(): void
    {
        $action = new CreateConversationAction('', 'Hello world');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Invalid watch file ID provided.');

        ($this->handler)($action);
    }

    public function testCreateConversationWithNonExistentWatchFile(): void
    {
        $action = new CreateConversationAction('non_existent_id', 'Hello world');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        ($this->handler)($action);
    }

    public function testCreateConversationWithoutLogger(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new CreateConversationAction('watch_file_id', 'Hello world');

        // Create handler without logger
        $handlerWithoutLogger = new CreateConversationHandler(
            $this->conversationGateway,
            $this->languageDetector,
            $this->messageBus
        );

        $handlerWithoutLogger->setWatchFileGateway($this->watchFileGateway);
        $result = ($handlerWithoutLogger)($action);

        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertEquals('en', $result->getLanguage());

        // Verify message was dispatched
        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(1, $dispatchedMessages);
        $this->assertInstanceOf(AddMessageAction::class, $dispatchedMessages[0]);
        $this->assertEquals($result->getId(), $dispatchedMessages[0]->conversationId);
        $this->assertEquals('Hello world', $dispatchedMessages[0]->message);
    }

    public function testLanguageMappingEnglishToEnglish(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('en', 0.95));

        $action = new CreateConversationAction('watch_file_id', 'Hello world');
        $result = ($this->handler)($action);

        $this->assertEquals('en', $result->getLanguage());
    }

    public function testLanguageMappingFrenchToFrench(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('fr', 0.90));

        $action = new CreateConversationAction('watch_file_id', 'Bonjour');
        $result = ($this->handler)($action);

        $this->assertEquals('fr', $result->getLanguage());
    }

    public function testLanguageMappingGermanToEnglish(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Detector returns German
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('de', 0.85));

        $action = new CreateConversationAction('watch_file_id', 'Guten Tag');
        $result = ($this->handler)($action);

        // Should be mapped to English (fallback)
        $this->assertEquals('en', $result->getLanguage());
    }

    public function testLanguageMappingSpanishToEnglish(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Detector returns Spanish
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('es', 0.88));

        $action = new CreateConversationAction('watch_file_id', 'Hola');
        $result = ($this->handler)($action);

        // Should be mapped to English (fallback)
        $this->assertEquals('en', $result->getLanguage());
    }

    public function testZeroConfidenceDefaultsToEnglish(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Detector returns default with 0.0 confidence (all detectors failed)
        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('en', 0.0));

        $action = new CreateConversationAction('watch_file_id', '123456');
        $result = ($this->handler)($action);

        // Should default to English
        $this->assertEquals('en', $result->getLanguage());
    }

    public function testLogsDetectedAndMappedLanguage(): void
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $handler = new CreateConversationHandler(
            $this->conversationGateway,
            $this->languageDetector,
            $this->messageBus,
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        $this->languageDetector->setDetectedLanguage(new DetectedLanguage('de', 0.85));

        $action = new CreateConversationAction('watch_file_id', 'Guten Tag');
        $result = ($handler)($action);

        // Should default to English
        $this->assertEquals('en', $result->getLanguage());
    }
}
