<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Auth;

use App\Application\Collect\Auth\ValidateCollectTaskTokenAction;
use App\Application\Collect\Auth\ValidateCollectTaskTokenHandler;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\CollectTaskTokenClaimsMissingException;
use App\Domain\Collect\Exception\CollectTaskTokenMismatchException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Utils\EntityUtilsTrait;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class ValidateCollectTaskTokenHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private const string JWT_SECRET = 'test-secret-key-for-jwt-minimum-32bytes!';
    private ValidateCollectTaskTokenHandler $handler;
    private NullCollectTaskGateway $collectTaskGateway;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->handler = new ValidateCollectTaskTokenHandler(self::JWT_SECRET, $this->collectTaskGateway);
    }

    public function testValidateTokenSuccessfully(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'source-456', 'watchfile-789');
        $this->collectTaskGateway->save($collectTask);

        $token = $this->generateValidToken('task-123', 'source-456', 'watchfile-789');

        $action = new ValidateCollectTaskTokenAction($token);
        $result = ($this->handler)($action);

        $this->assertInstanceOf(CollectTask::class, $result);
        $this->assertEquals('task-123', $result->getId());
        $this->assertEquals('source-456', $result->getSource()->getId());
        $this->assertEquals('watchfile-789', $result->getWatchFile()->getId());
    }

    public function testValidateTokenWithInvalidFormat(): void
    {
        $invalidToken = 'this-is-not-a-valid-jwt-token';

        $this->expectException(\Exception::class);

        $action = new ValidateCollectTaskTokenAction($invalidToken);
        ($this->handler)($action);
    }

    public function testValidateTokenWithInvalidSignature(): void
    {
        $this->createCollectTask('task-123', 'source-456', 'watchfile-789');

        // Generate token with different secret
        $token = $this->generateTokenWithSecret(
            'task-123',
            'source-456',
            'watchfile-789',
            'wrong-secret-key-that-is-at-least-32bytes!'
        );

        $this->expectException(\Exception::class);

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMissingSourceIdClaim(): void
    {
        $payload = [
            // 'source_id' => 'source-456', // Missing
            'watch_file_id' => 'watchfile-789',
            'collect_task_id' => 'task-123',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $this->expectException(CollectTaskTokenClaimsMissingException::class);
        $this->expectExceptionMessage(
            'Invalid collect task token: Missing required claims (source_id, watch_file_id, collect_task_id).'
        );

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMissingWatchFileIdClaim(): void
    {
        $payload = [
            'source_id' => 'source-456',
            // 'watch_file_id' => 'watchfile-789', // Missing
            'collect_task_id' => 'task-123',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $this->expectException(CollectTaskTokenClaimsMissingException::class);
        $this->expectExceptionMessage(
            'Invalid collect task token: Missing required claims (source_id, watch_file_id, collect_task_id).'
        );

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMissingCollectTaskIdClaim(): void
    {
        $payload = [
            'source_id' => 'source-456',
            'watch_file_id' => 'watchfile-789',
            // 'collect_task_id' => 'task-123', // Missing
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $this->expectException(CollectTaskTokenClaimsMissingException::class);
        $this->expectExceptionMessage(
            'Invalid collect task token: Missing required claims (source_id, watch_file_id, collect_task_id).'
        );

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMismatchedSourceId(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'source-456', 'watchfile-789');
        $this->collectTaskGateway->save($collectTask);

        // Token has different source_id than the actual collect task
        $token = $this->generateValidToken('task-123', 'wrong-source-id', 'watchfile-789');

        $this->expectException(CollectTaskTokenMismatchException::class);
        $this->expectExceptionMessage('Invalid collect task token: source_id does not match collect task.');

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMismatchedWatchFileId(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'source-456', 'watchfile-789');
        $this->collectTaskGateway->save($collectTask);

        // Token has different watch_file_id than the actual collect task
        $token = $this->generateValidToken('task-123', 'source-456', 'wrong-watchfile-id');

        $this->expectException(CollectTaskTokenMismatchException::class);
        $this->expectExceptionMessage('Invalid collect task token: watch_file_id does not match collect task.');

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMismatchedBothIds(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'source-456', 'watchfile-789');
        $this->collectTaskGateway->save($collectTask);

        // Token has different IDs than the actual collect task
        $token = $this->generateValidToken('task-123', 'wrong-source', 'wrong-watchfile');

        $this->expectException(CollectTaskTokenMismatchException::class);
        $this->expectExceptionMessage('Invalid collect task token: source_id does not match collect task.');

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateExpiredToken(): void
    {
        $payload = [
            'source_id' => 'source-456',
            'watch_file_id' => 'watchfile-789',
            'collect_task_id' => 'task-123',
            'iat' => time() - 7200, // 2 hours ago
            'nbf' => time() - 7200,
            'exp' => time() - 3600, // Expired 1 hour ago
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $this->expectException(\Exception::class);

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenNotYetValid(): void
    {
        $payload = [
            'source_id' => 'source-456',
            'watch_file_id' => 'watchfile-789',
            'collect_task_id' => 'task-123',
            'iat' => time(),
            'nbf' => time() + 3600, // Not valid for another hour
            'exp' => time() + 7200,
        ];

        $token = JWT::encode($payload, self::JWT_SECRET, 'HS256');

        $this->expectException(\Exception::class);

        $action = new ValidateCollectTaskTokenAction($token);
        ($this->handler)($action);
    }

    public function testValidateTokenWithMultipleCollectTasks(): void
    {
        $collectTask1 = $this->createCollectTask('task-001', 'source-001', 'watchfile-001');
        $collectTask2 = $this->createCollectTask('task-002', 'source-002', 'watchfile-002');

        $this->collectTaskGateway->save($collectTask1);
        $this->collectTaskGateway->save($collectTask2);

        $token1 = $this->generateValidToken('task-001', 'source-001', 'watchfile-001');
        $token2 = $this->generateValidToken('task-002', 'source-002', 'watchfile-002');

        $result1 = ($this->handler)(new ValidateCollectTaskTokenAction($token1));
        $result2 = ($this->handler)(new ValidateCollectTaskTokenAction($token2));

        $this->assertEquals('task-001', $result1->getId());
        $this->assertEquals('source-001', $result1->getSource()->getId());
        $this->assertEquals('watchfile-001', $result1->getWatchFile()->getId());

        $this->assertEquals('task-002', $result2->getId());
        $this->assertEquals('source-002', $result2->getSource()->getId());
        $this->assertEquals('watchfile-002', $result2->getWatchFile()->getId());
    }

    public function testValidateTokenReturnsCorrectCollectTask(): void
    {
        $collectTask = $this->createCollectTask('task-999', 'source-999', 'watchfile-999');
        $this->collectTaskGateway->save($collectTask);

        $token = $this->generateValidToken('task-999', 'source-999', 'watchfile-999');

        $action = new ValidateCollectTaskTokenAction($token);
        $result = ($this->handler)($action);

        $this->assertSame($collectTask, $result);
    }

    private function generateValidToken(string $collectTaskId, string $sourceId, string $watchFileId): string
    {
        return $this->generateTokenWithSecret($collectTaskId, $sourceId, $watchFileId, self::JWT_SECRET);
    }

    private function generateTokenWithSecret(
        string $collectTaskId,
        string $sourceId,
        string $watchFileId,
        string $secret,
    ): string {
        $now = time();
        $payload = [
            'source_id' => $sourceId,
            'watch_file_id' => $watchFileId,
            'collect_task_id' => $collectTaskId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600, // Valid for 1 hour
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function createCollectTask(string $collectTaskId, string $sourceId, string $watchFileId): CollectTask
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        $source = new Source(
            'source-name',
            TranslatedText::fromArray([
                'fr' => 'Description en français',
                'en' => 'Description in English',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Pertinence en français',
                'en' => 'Relevance in English',
            ]),
            null,
            $watchFile,
        );
        $this->forcePropertyValue($source, $sourceId);

        $collectTask = new CollectTask($source, $watchFile, 'bakus');
        $this->forcePropertyValue($collectTask, $collectTaskId);

        return $collectTask;
    }
}
