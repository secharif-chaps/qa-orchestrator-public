<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Mercure;

use App\Application\Mercure\GenerateTokenAction;
use App\Application\Mercure\GenerateTokenHandler;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\User;
use App\Domain\User\UserNotFoundException;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Mercure\TokenDto;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerateTokenHandler::class)]
class GenerateTokenHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullUserGateway $userGateway;
    private RealTimeTopicGeneratorInterface&MockObject $topicGenerator;
    private string $mercureJwtSecret;
    private GenerateTokenHandler $handler;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->topicGenerator = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->mercureJwtSecret = 'test-secret-key-for-jwt-minimum-32bytes!';
        $this->handler = new GenerateTokenHandler(
            $this->userGateway,
            $this->topicGenerator,
            $this->mercureJwtSecret,
        );
    }

    public function testTokenContainsUriTemplatesInsteadOfExplicitResourceUrls(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId, 'user@example.com', [], 'testuser');
        $this->userGateway->save($user);

        $expectedTemplates = [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];

        $this->topicGenerator
            ->expects($this->once())
            ->method('getSubscriptionTemplates')
            ->with($user)
            ->willReturn($expectedTemplates);

        $action = new GenerateTokenAction($userId);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(TokenDto::class, $result);
        $decodedToken = JWT::decode($result->token, new Key($this->mercureJwtSecret, 'HS256'));

        // Verify token contains URI Templates instead of explicit resource URLs
        $this->assertObjectHasProperty('mercure', $decodedToken);
        $this->assertCount(3, $decodedToken->mercure->subscribe);
        $this->assertContains("/users/{$userId}/watch-files/{id}", $decodedToken->mercure->subscribe);
        $this->assertContains("/users/{$userId}/conversations/{id}", $decodedToken->mercure->subscribe);
        $this->assertContains("/users/{$userId}/conversations/{id}/messages", $decodedToken->mercure->subscribe);
    }

    public function testTokenSubscribeClaimMatchesFormatFromTopicGenerator(): void
    {
        // Arrange
        $userId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $user = new User($userId, 'test@example.com', [], 'testuser');
        $this->userGateway->save($user);

        $expectedTemplates = [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];

        $this->topicGenerator
            ->expects($this->once())
            ->method('getSubscriptionTemplates')
            ->with($user)
            ->willReturn($expectedTemplates);

        $action = new GenerateTokenAction($userId);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $decodedToken = JWT::decode($result->token, new Key($this->mercureJwtSecret, 'HS256'));

        // Verify subscribe claim exactly matches what the topic generator returns
        $this->assertEquals($expectedTemplates, $decodedToken->mercure->subscribe);
        // Verify publish rights remain empty (users cannot publish)
        $this->assertEquals([], $decodedToken->mercure->publish);
    }

    public function testTokenIsGeneratedFreshOnEachCall(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId, 'user@example.com', [], 'testuser');
        $this->userGateway->save($user);

        $expectedTemplates = [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];

        // The topic generator should be called twice (once per invocation)
        $this->topicGenerator
            ->expects($this->exactly(2))
            ->method('getSubscriptionTemplates')
            ->with($user)
            ->willReturn($expectedTemplates);

        $action = new GenerateTokenAction($userId);

        // Act - Call handler twice
        $result1 = ($this->handler)($action);
        // Simulate time passing to get different iat/exp values
        sleep(1);
        $result2 = ($this->handler)($action);

        // Assert - Both calls should produce valid tokens
        $this->assertInstanceOf(TokenDto::class, $result1);
        $this->assertInstanceOf(TokenDto::class, $result2);

        // Both tokens should be valid and decodable
        $decoded1 = JWT::decode($result1->token, new Key($this->mercureJwtSecret, 'HS256'));
        $decoded2 = JWT::decode($result2->token, new Key($this->mercureJwtSecret, 'HS256'));

        // The tokens should have different timestamps (proving they were generated fresh)
        // Note: If both calls happen within the same second, iat might be the same
        // so we verify that getSubscriptionTemplates was called twice (no caching)
        $this->assertNotNull($decoded1->iat);
        $this->assertNotNull($decoded2->iat);
    }

    public function testTokenExpirationIsSetToOneHour(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId, 'user@example.com', [], 'testuser');
        $this->userGateway->save($user);

        $expectedTemplates = [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];

        $this->topicGenerator
            ->expects($this->once())
            ->method('getSubscriptionTemplates')
            ->with($user)
            ->willReturn($expectedTemplates);

        $action = new GenerateTokenAction($userId);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $decodedToken = JWT::decode($result->token, new Key($this->mercureJwtSecret, 'HS256'));

        // Token expiration should be 1 hour (3600 seconds) from issuance
        $expectedExpiration = $decodedToken->iat + 3600;
        $this->assertEquals($expectedExpiration, $decodedToken->exp);
        $this->assertEquals($expectedExpiration, $result->expires_at);

        // Verify the expiration is roughly 1 hour from now (within a few seconds tolerance)
        $now = time();
        $this->assertGreaterThanOrEqual($now + 3595, $decodedToken->exp);
        $this->assertLessThanOrEqual($now + 3605, $decodedToken->exp);
    }

    public function testThrowsExceptionWhenUserNotFound(): void
    {
        // Arrange
        $action = new GenerateTokenAction('non-existent-user-id');

        // The topic generator should never be called if user is not found
        $this->topicGenerator
            ->expects($this->never())
            ->method('getSubscriptionTemplates');

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        ($this->handler)($action);
    }

    public function testTokenContainsCorrectUserIdentifier(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $userEmail = 'test.user@example.com';
        $user = new User($userId, $userEmail, [], 'testuser');
        $this->userGateway->save($user);

        $expectedTemplates = [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];

        $this->topicGenerator
            ->expects($this->once())
            ->method('getSubscriptionTemplates')
            ->with($user)
            ->willReturn($expectedTemplates);

        $action = new GenerateTokenAction($userId);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $decodedToken = JWT::decode($result->token, new Key($this->mercureJwtSecret, 'HS256'));

        // Verify the sub claim contains the user identifier (email)
        $this->assertEquals($userEmail, $decodedToken->sub);
    }
}
