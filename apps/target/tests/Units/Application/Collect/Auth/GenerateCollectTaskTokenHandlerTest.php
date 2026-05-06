<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Auth;

use App\Application\Collect\Auth\GenerateCollectTaskTokenAction;
use App\Application\Collect\Auth\GenerateCollectTaskTokenHandler;
use App\Domain\Collect\CollectTask;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Utils\EntityUtilsTrait;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

class GenerateCollectTaskTokenHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private const string JWT_SECRET = 'test-secret-key-for-jwt-minimum-32bytes!';
    private GenerateCollectTaskTokenHandler $handler;
    private NullCollectTaskGateway $collectTaskGateway;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->handler = new GenerateCollectTaskTokenHandler(self::JWT_SECRET, $this->collectTaskGateway);
    }

    public function testGenerateTokenSuccessfully(): void
    {
        $collectTask = $this->createCollectTask('collect-task-id', 'source-id', 'watch-file-id');
        $this->collectTaskGateway->save($collectTask);

        $action = new GenerateCollectTaskTokenAction('collect-task-id');
        $token = ($this->handler)($action);
        $this->assertNotEmpty($token);

        // Verify token can be decoded
        $payload = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));

        $this->assertObjectHasProperty('source_id', $payload);
        $this->assertObjectHasProperty('watch_file_id', $payload);
        $this->assertObjectHasProperty('collect_task_id', $payload);
        $this->assertObjectHasProperty('iat', $payload);
        $this->assertObjectHasProperty('nbf', $payload);
        $this->assertObjectHasProperty('exp', $payload);
    }

    public function testGenerateTokenWithCorrectClaims(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'source-456', 'watchfile-789');
        $this->collectTaskGateway->save($collectTask);

        $beforeGeneration = time();
        $action = new GenerateCollectTaskTokenAction('task-123');
        $token = ($this->handler)($action);
        $afterGeneration = time();

        $payload = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));

        // Verify IDs
        $this->assertEquals('source-456', $payload->source_id);
        $this->assertEquals('watchfile-789', $payload->watch_file_id);
        $this->assertEquals('task-123', $payload->collect_task_id);

        // Verify timestamps
        $this->assertGreaterThanOrEqual($beforeGeneration, $payload->iat);
        $this->assertLessThanOrEqual($afterGeneration, $payload->iat);
        $this->assertEquals($payload->iat, $payload->nbf);
    }

    public function testGenerateTokenWithOneYearExpiration(): void
    {
        $collectTask = $this->createCollectTask('collect-task-id', 'source-id', 'watch-file-id');
        $this->collectTaskGateway->save($collectTask);

        $action = new GenerateCollectTaskTokenAction('collect-task-id');
        $token = ($this->handler)($action);

        $payload = JWT::decode($token, new Key(self::JWT_SECRET, 'HS256'));

        // Default expiration is 1 year (31536000 seconds)
        $expectedExpiration = $payload->iat + 31536000;
        $this->assertEquals($expectedExpiration, $payload->exp);

        // Verify token is valid for approximately 1 year
        $expirationDiff = $payload->exp - $payload->iat;
        $this->assertEquals(31536000, $expirationDiff);
    }

    public function testGenerateTokenWithDifferentCollectTaskIds(): void
    {
        $collectTask1 = $this->createCollectTask('task-001', 'source-001', 'watchfile-001');
        $collectTask2 = $this->createCollectTask('task-002', 'source-002', 'watchfile-002');

        $this->collectTaskGateway->save($collectTask1);
        $this->collectTaskGateway->save($collectTask2);

        $token1 = ($this->handler)(new GenerateCollectTaskTokenAction('task-001'));
        $token2 = ($this->handler)(new GenerateCollectTaskTokenAction('task-002'));

        $this->assertNotEquals($token1, $token2);

        $payload1 = JWT::decode($token1, new Key(self::JWT_SECRET, 'HS256'));
        $payload2 = JWT::decode($token2, new Key(self::JWT_SECRET, 'HS256'));

        $this->assertEquals('task-001', $payload1->collect_task_id);
        $this->assertEquals('source-001', $payload1->source_id);
        $this->assertEquals('watchfile-001', $payload1->watch_file_id);

        $this->assertEquals('task-002', $payload2->collect_task_id);
        $this->assertEquals('source-002', $payload2->source_id);
        $this->assertEquals('watchfile-002', $payload2->watch_file_id);
    }

    public function testGenerateTokenUsesHS256Algorithm(): void
    {
        $collectTask = $this->createCollectTask('collect-task-id', 'source-id', 'watch-file-id');
        $this->collectTaskGateway->save($collectTask);

        $action = new GenerateCollectTaskTokenAction('collect-task-id');
        $token = ($this->handler)($action);

        // Split JWT to get header
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $header = json_decode(base64_decode($parts[0]), true);
        $this->assertIsArray($header);
        $this->assertArrayHasKey('alg', $header);
        $this->assertArrayHasKey('typ', $header);
        $this->assertEquals('HS256', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
    }

    public function testGenerateTokenWithNullSourceIdThrowsError(): void
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');

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
        // Don't set ID on source, it will be null

        $collectTask = new CollectTask($source, $watchFile, 'bakus');
        $this->forcePropertyValue($collectTask, 'collect-task-id');

        $this->collectTaskGateway->save($collectTask);

        $action = new GenerateCollectTaskTokenAction('collect-task-id');

        $this->expectException(\LogicException::class);
        ($this->handler)($action);
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
