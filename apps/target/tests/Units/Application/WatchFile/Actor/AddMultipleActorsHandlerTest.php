<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Actor;

use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\Actor\AddMultipleActorsAction;
use App\Application\WatchFile\Actor\AddMultipleActorsHandler;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

class AddMultipleActorsHandlerTest extends TestCase
{
    private NullWatchFileGateway $watchFileGateway;
    private MessageBusInterface&Stub $messageBus;
    private LoggerInterface&Stub $logger;
    private AddMultipleActorsHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new AddMultipleActorsHandler($this->messageBus, $this->logger);
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testProcessMultipleActorsSuccessfully(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Actor 1',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Première explication d\'acteur',
                    'en' => 'First actor explanation',
                ],
                'primaryDomain' => 'actor1.com',
                'score' => 0.85,
            ],
            [
                'name' => 'Actor 2',
                'type' => 'individual',
                'explanation' => [
                    'fr' => 'Deuxième explication d\'acteur',
                    'en' => 'Second actor explanation',
                ],
                'primaryDomain' => 'actor2.com',
                'score' => 0.92,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations
        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (AddActorAction $action) {
                // Validate the action based on the actor name
                if ('Actor 1' === $action->name) {
                    $this->assertEquals(ActorType::OTHER, $action->type);
                    $this->assertInstanceOf(TranslatedText::class, $action->explanation);
                    $this->assertEquals('Première explication d\'acteur', $action->explanation->fr);
                    $this->assertEquals('First actor explanation', $action->explanation->en);
                    $this->assertEquals('actor1.com', $action->primaryDomain);
                    $this->assertEquals(0.85, $action->score);
                } elseif ('Actor 2' === $action->name) {
                    $this->assertEquals('individual', $action->type);
                    $this->assertInstanceOf(TranslatedText::class, $action->explanation);
                    $this->assertEquals('Deuxième explication d\'acteur', $action->explanation->fr);
                    $this->assertEquals('Second actor explanation', $action->explanation->en);
                    $this->assertEquals('actor2.com', $action->primaryDomain);
                    $this->assertEquals(0.92, $action->score);
                } else {
                    $this->fail('Unexpected actor name: ' . $action->name);
                }

                return new Envelope($action);
            });

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testProcessSingleActorSuccessfully(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Single Actor',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Explication d\'acteur unique',
                    'en' => 'Single actor explanation',
                ],
                'primaryDomain' => 'single.com',
                'score' => 0.75,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddActorAction $action) {
                return 'Single Actor' === $action->name
                       && ActorType::OTHER === $action->type
                       && 'Explication d\'acteur unique' === $action->explanation->fr
                       && 'Single actor explanation' === $action->explanation->en
                       && 'single.com' === $action->primaryDomain
                       && 0.75 === $action->score;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testProcessEmptyActorsList(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [];
        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations

        $messageBusMock->expects($this->never())
->method('dispatch');

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testSkipActorWithMissingName(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name?: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Valid Actor',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Explication d\'acteur valide',
                    'en' => 'Valid actor explanation',
                ],
                'primaryDomain' => 'valid.com',
                'score' => 0.80,
            ],
            [
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Acteur sans nom',
                    'en' => 'Missing name actor',
                ],
                'primaryDomain' => 'missing.com',
                'score' => 0.70,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations
        $loggerMock->expects($this->once())
            ->method('warning')
            ->with('Invalid actor data: missing or invalid name', [
                'actor_data' => [
                    'type' => 'other',
                    'explanation' => [
                        'fr' => 'Acteur sans nom',
                        'en' => 'Missing name actor',
                    ],
                    'primaryDomain' => 'missing.com',
                    'score' => 0.70,
                ],
            ]);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddActorAction $action) {
                return 'Valid Actor' === $action->name;
            }));

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testSkipActorWithInvalidNameType(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: mixed, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 123, // Invalid type
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Acteur avec type de nom invalide',
                    'en' => 'Invalid name type actor',
                ],
                'primaryDomain' => 'invalid.com',
                'score' => 0.70,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations
        $loggerMock->expects($this->once())
            ->method('warning')
            ->with('Invalid actor data: missing or invalid name', [
                'actor_data' => [
                    'name' => 123,
                    'type' => 'other',
                    'explanation' => [
                        'fr' => 'Acteur avec type de nom invalide',
                        'en' => 'Invalid name type actor',
                    ],
                    'primaryDomain' => 'invalid.com',
                    'score' => 0.70,
                ],
            ]);

        $messageBusMock->expects($this->never())
            ->method('dispatch');

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testHandleActorDispatchException(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Actor 1',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Première explication d\'acteur',
                    'en' => 'First actor explanation',
                ],
                'primaryDomain' => 'actor1.com',
                'score' => 0.85,
            ],
            [
                'name' => 'Actor 2',
                'type' => 'individual',
                'explanation' => [
                    'fr' => 'Deuxième explication d\'acteur',
                    'en' => 'Second actor explanation',
                ],
                'primaryDomain' => 'actor2.com',
                'score' => 0.92,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations
        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                new Envelope(new AddActorAction(
                    watchFileId: 'test-watch-file-id',
                    name: 'Actor 1',
                    type: ActorType::OTHER,
                    explanation: TranslatedText::fromArray([
                        'fr' => 'Première explication d\'acteur',
                        'en' => 'First actor explanation',
                    ]),
                    primaryDomain: 'actor1.com',
                    score: 0.85
                )),
                $this->throwException(new \Exception('Dispatch failed'))
            );

        $loggerMock->expects($this->once())
            ->method('error')
            ->with('Failed to dispatch actor action', [
                'actor_name' => 'Actor 2',
                'exception' => 'Dispatch failed',
            ]);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Actor 1',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Explication d\'acteur',
                    'en' => 'Actor explanation',
                ],
                'primaryDomain' => 'actor1.com',
                'score' => 0.85,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'invalid-watch-file-id');

        // Expectations
        $loggerMock->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message, $context) {
                $this->assertEquals('Failed to retrieve watch file "invalid-watch-file-id" (anonymous user)', $message);
                $this->assertEquals('invalid-watch-file-id', $context['watch_file_id']);
                $this->assertStringContainsString('WatchFile', $context['exception']);
            });

        // Act & Assert
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');
        ($this->handler)($action);
    }

    public function testGeneralExceptionIsLoggedAndRethrown(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildHandler();
        // Arrange
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Actor 1',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Explication d\'acteur',
                    'en' => 'Actor explanation',
                ],
                'primaryDomain' => 'actor1.com',
                'score' => 0.85,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Mock messageBus to throw exception
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('General error'));

        // Expectations
        $loggerMock->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message, $context) {
                // Error log is from processActorData
                $this->assertEquals('Failed to dispatch actor action', $message);
                $this->assertEquals('Actor 1', $context['actor_name']);
                $this->assertEquals('General error', $context['exception']);
            });

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }

    public function testHandlerWithoutLogger(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $handlerWithoutLogger = new AddMultipleActorsHandler($this->messageBus, null);
        $handlerWithoutLogger->setWatchFileGateway($this->watchFileGateway);

        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->watchFileGateway->save($watchFile);

        /** @var array<int, array{name: string, type: string, explanation: array{fr: string, en: string}, primaryDomain: string, score: float}> $actors */
        $actors = [
            [
                'name' => 'Actor 1',
                'type' => 'other',
                'explanation' => [
                    'fr' => 'Explication d\'acteur',
                    'en' => 'Actor explanation',
                ],
                'primaryDomain' => 'actor1.com',
                'score' => 0.85,
            ],
        ];

        $action = new AddMultipleActorsAction($actors, 'test-watch-file-id');

        // Expectations
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AddActorAction $action) {
                return 'Actor 1' === $action->name;
            }))
            ->willReturn(new Envelope(new AddActorAction(
                watchFileId: 'test-watch-file-id',
                name: 'Actor 1',
                type: ActorType::OTHER,
                explanation: TranslatedText::fromArray([
                    'fr' => 'Explication d\'acteur',
                    'en' => 'Actor explanation',
                ]),
                primaryDomain: 'actor1.com',
                score: 0.85
            )));

        // Act
        $result = $handlerWithoutLogger($action);

        // Assert
        $this->assertSame($watchFile, $result);
    }
}
