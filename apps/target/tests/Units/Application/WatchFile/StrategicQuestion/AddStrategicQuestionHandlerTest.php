<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\StrategicQuestion;

use App\Application\WatchFile\StrategicQuestion\AddStrategicQuestionAction;
use App\Application\WatchFile\StrategicQuestion\AddStrategicQuestionHandler;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullStrategicQuestionGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class AddStrategicQuestionHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileGatewayInterface $watchFileGateway;
    private StrategicQuestionGatewayInterface $strategicQuestionGateway;
    private MessageGatewayInterface $messageGateway;
    private AddStrategicQuestionHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->strategicQuestionGateway = new NullStrategicQuestionGateway();
        $this->messageGateway = new NullMessageGateway();

        $this->handler = new AddStrategicQuestionHandler($this->strategicQuestionGateway, $this->messageGateway);
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testCreateStrategicQuestion(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id',
            questionFR: 'Quelle est la stratégie concurrentielle ?',
            questionEN: 'What is the competitive strategy?',
            contextFR: 'Contexte de la question',
            contextEN: 'Question context',
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
        );

        $result = ($this->handler)($action);

        $this->assertNotNull($result->getId());
        $this->assertEquals('Quelle est la stratégie concurrentielle ?', $result->getQuestion()->fr);
        $this->assertEquals('What is the competitive strategy?', $result->getQuestion()->en);
        $this->assertEquals('Contexte de la question', $result->getContext()->fr);
        $this->assertEquals('Question context', $result->getContext()->en);
        $this->assertEquals(MonitoringType::COMPETITIVE, $result->getMonitoringDimension());
        $this->assertEquals(1, $result->getPriority());
        $this->assertEquals('analysis', $result->getExpectedOutputType());
        $this->assertNull($result->getAddedByMessage());
        $this->assertSame($watchFile, $result->getWatchFile());
    }

    public function testCreateStrategicQuestionWithMessage(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $message = new Message();
        $this->forcePropertyValue($message, 'message-id-123');
        $this->messageGateway->save($message);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id',
            questionFR: 'Question FR',
            questionEN: 'Question EN',
            contextFR: 'Contexte FR',
            contextEN: 'Context EN',
            monitoringDimension: MonitoringType::STRATEGIC,
            priority: 2,
            expectedOutputType: 'report',
            messageId: 'message-id-123',
        );

        $result = ($this->handler)($action);

        $this->assertSame($message, $result->getAddedByMessage());
        $this->assertEquals(MonitoringType::STRATEGIC, $result->getMonitoringDimension());
        $this->assertEquals(2, $result->getPriority());
    }

    public function testCreateStrategicQuestionReturnExistingWhenDuplicate(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $existingQuestion = new StrategicQuestion(
            question: new TranslatedText('Question FR existante', 'Existing question EN'),
            context: new TranslatedText('Contexte existant', 'Existing context'),
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
            watchFile: $watchFile,
        );
        $this->strategicQuestionGateway->save($existingQuestion);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id',
            questionFR: 'Nouvelle question FR',
            questionEN: 'Existing question EN',
            contextFR: 'Nouveau contexte',
            contextEN: 'New context',
            monitoringDimension: MonitoringType::TECHNOLOGICAL,
            priority: 5,
            expectedOutputType: 'summary',
        );

        $result = ($this->handler)($action);

        $this->assertSame($existingQuestion, $result);
        $this->assertEquals('Question FR existante', $result->getQuestion()->fr);
        $this->assertEquals(MonitoringType::COMPETITIVE, $result->getMonitoringDimension());
        $this->assertEquals(1, $result->getPriority());
    }

    public function testCreateStrategicQuestionAllowsSameQuestionForDifferentWatchFiles(): void
    {
        $user = new User('user-id', 'user@example.com');

        $watchFile1 = new WatchFile('WatchFile 1', 'objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watch-file-id-1');
        $this->watchFileGateway->save($watchFile1);

        $watchFile2 = new WatchFile('WatchFile 2', 'objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watch-file-id-2');
        $this->watchFileGateway->save($watchFile2);

        $existingQuestion = new StrategicQuestion(
            question: new TranslatedText('Question FR', 'Same question EN'),
            context: new TranslatedText('Contexte', 'Context'),
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
            watchFile: $watchFile1,
        );
        $this->strategicQuestionGateway->save($existingQuestion);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id-2',
            questionFR: 'Question FR',
            questionEN: 'Same question EN',
            contextFR: 'Contexte',
            contextEN: 'Context',
            monitoringDimension: MonitoringType::COMPETITIVE,
            priority: 1,
            expectedOutputType: 'analysis',
        );

        $result = ($this->handler)($action);

        $this->assertNotSame($existingQuestion, $result);
        $this->assertSame($watchFile2, $result->getWatchFile());
    }

    public function testCreateStrategicQuestionWithWatchFileNotFoundThrowsException(): void
    {
        $action = new AddStrategicQuestionAction(
            watchFileId: 'non-existent-watch-file-id',
            questionFR: 'Question FR',
            questionEN: 'Question EN',
            contextFR: 'Contexte',
            contextEN: 'Context',
            monitoringDimension: MonitoringType::REGULATORY,
            priority: 3,
            expectedOutputType: 'compliance',
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        ($this->handler)($action);
    }

    public function testCreateStrategicQuestionWithMessageNotFoundCreatesWithoutMessage(): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id',
            questionFR: 'Question FR',
            questionEN: 'Question EN',
            contextFR: 'Contexte',
            contextEN: 'Context',
            monitoringDimension: MonitoringType::REGULATORY,
            priority: 3,
            expectedOutputType: 'compliance',
            messageId: 'non-existent-message-id',
        );

        $result = ($this->handler)($action);

        $this->assertNotNull($result->getId());
        $this->assertNull($result->getAddedByMessage());
    }

    #[DataProvider('monitoringTypeProvider')]
    public function testCreateStrategicQuestionWithAllMonitoringTypes(MonitoringType $monitoringType): void
    {
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'objective', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddStrategicQuestionAction(
            watchFileId: 'watch-file-id',
            questionFR: 'Question FR ' . $monitoringType->value,
            questionEN: 'Question EN ' . $monitoringType->value,
            contextFR: 'Contexte',
            contextEN: 'Context',
            monitoringDimension: $monitoringType,
            priority: 1,
            expectedOutputType: 'analysis',
        );

        $result = ($this->handler)($action);

        $this->assertEquals($monitoringType, $result->getMonitoringDimension());
    }

    /**
     * @return iterable<string, array{MonitoringType}>
     */
    public static function monitoringTypeProvider(): iterable
    {
        yield 'competitive' => [MonitoringType::COMPETITIVE];
        yield 'strategic' => [MonitoringType::STRATEGIC];
        yield 'commercial' => [MonitoringType::COMMERCIAL];
        yield 'technological' => [MonitoringType::TECHNOLOGICAL];
        yield 'regulatory' => [MonitoringType::REGULATORY];
    }
}
