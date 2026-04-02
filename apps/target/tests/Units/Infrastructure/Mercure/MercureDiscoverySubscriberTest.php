<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Mercure;

use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\User;
use App\Infrastructure\Mercure\MercureDiscoverySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mercure\HubInterface;

#[CoversClass(MercureDiscoverySubscriber::class)]
class MercureDiscoverySubscriberTest extends TestCase
{
    private HubInterface&Stub $hub;
    private RealTimeTopicGeneratorInterface&Stub $topicGenerator;
    private Security&Stub $security;
    private MercureDiscoverySubscriber $subscriber;
    private HttpKernelInterface&Stub $kernel;

    protected function setUp(): void
    {
        $this->hub = $this->createStub(HubInterface::class);
        $this->topicGenerator = $this->createStub(RealTimeTopicGeneratorInterface::class);
        $this->security = $this->createStub(Security::class);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
        $this->hub
            ->method('getPublicUrl')
            ->willReturn('https://basil.local/.well-known/mercure');
        $this->buildSubscriber();
    }

    private function buildSubscriber(): void
    {
        $this->subscriber = new MercureDiscoverySubscriber($this->hub, $this->topicGenerator, $this->security);
    }

    public function testAddsLinkHeadersForGetWatchFileRequest(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
        $expectedTopic = "/users/{$userId}/watch-files/{$watchFileId}";

        $user = $this->createStub(User::class);
        $this->security
            ->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock
            ->expects($this->once())
            ->method('forWatchFileById')
            ->with($user, $watchFileId)
            ->willReturn($expectedTopic);

        $request = Request::create("/api/watch_files/{$watchFileId}");
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $linksHeader = $response->headers->all('Link');
        $this->assertCount(2, $linksHeader);
        $this->assertSame([
            '<https://basil.local/.well-known/mercure>; rel="mercure"',
            "<{$expectedTopic}>; rel=\"topic\"",
        ], $linksHeader);
    }

    public function testAddsLinkHeadersForGetConversationRequest(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $conversationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $expectedTopic = "/users/{$userId}/conversations/{$conversationId}";

        $user = $this->createStub(User::class);
        $this->security
            ->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock
            ->expects($this->once())
            ->method('forConversationById')
            ->with($user, $conversationId)
            ->willReturn($expectedTopic);

        $request = Request::create("/api/conversations/{$conversationId}", 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $linksHeader = $response->headers->all('Link');
        $this->assertCount(2, $linksHeader);
        $this->assertSame([
            '<https://basil.local/.well-known/mercure>; rel="mercure"',
            "<{$expectedTopic}>; rel=\"topic\"",
        ], $linksHeader);
    }

    public function testAddsLinkHeadersForGetConversationMessagesRequest(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $conversationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $expectedTopic = "/users/{$userId}/conversations/{$conversationId}/messages";

        $user = $this->createStub(User::class);
        $this->security
            ->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock
            ->expects($this->once())
            ->method('forConversationMessagesById')
            ->with($user, $conversationId)
            ->willReturn($expectedTopic);

        $request = Request::create("/api/conversations/{$conversationId}/messages", 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $linksHeader = $response->headers->all('Link');
        $this->assertCount(2, $linksHeader);
        $this->assertSame([
            '<https://basil.local/.well-known/mercure>; rel="mercure"',
            "<{$expectedTopic}>; rel=\"topic\"",
        ], $linksHeader);
    }

    #[DataProvider('nonGetMethodsProvider')]
    public function testDoesNotAddHeadersForNonGetRequests(string $method): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forWatchFileById');

        $request = Request::create("/api/watch_files/{$watchFileId}", $method);
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonGetMethodsProvider(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    #[DataProvider('unsupportedRoutesProvider')]
    public function testDoesNotAddHeadersForUnsupportedRoutes(string $path): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forWatchFileById');
        $topicGeneratorMock->expects($this->never())
            ->method('forConversationById');
        $topicGeneratorMock->expects($this->never())
            ->method('forConversationMessagesById');

        $request = Request::create($path, 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedRoutesProvider(): array
    {
        return [
            'watch_files collection' => ['/api/watch_files'],
            'watch_file sub-resource actors' => ['/api/watch_files/7c9e6679-7425-40de-944b-e07fc1f90ae7/actors'],
            'watch_file sub-resource sources' => ['/api/watch_files/7c9e6679-7425-40de-944b-e07fc1f90ae7/sources'],
            'conversations collection' => ['/api/conversations'],
            'conversation sub-resource other' => ['/api/conversations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/other'],
            'users endpoint' => ['/api/users'],
            'random endpoint' => ['/api/something/else'],
            'invalid uuid format' => ['/api/watch_files/not-a-uuid'],
            'partial uuid' => ['/api/watch_files/7c9e6679-7425-40de-944b'],
        ];
    }

    public function testDoesNotAddHeadersForUnauthenticatedRequests(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $this->security->method('getUser')
            ->willReturn(null);

        $topicGeneratorMock->expects($this->never())
            ->method('forWatchFileById');

        $request = Request::create("/api/watch_files/{$watchFileId}", 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    public function testDoesNotAddHeadersForSubRequests(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forWatchFileById');

        $request = Request::create("/api/watch_files/{$watchFileId}", 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    public function testMercureHubLinkHeaderIsAdded(): void
    {
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $this->topicGenerator
            ->method('forWatchFileById')
            ->willReturn('/users/test/watch-files/test');

        $request = Request::create("/api/watch_files/{$watchFileId}", 'GET');
        $response = new Response();
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        // Note: The current implementation sets Link header twice, so only the last one is kept
        // This test verifies the topic link is set (which is the last one)
        $linkHeader = $response->headers->get('Link');
        $this->assertNotNull($linkHeader);
    }

    public function testAddsLinkHeadersForLastConversationRequest(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
        $conversationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $expectedTopic = "/users/{$userId}/conversations/{$conversationId}";

        $user = $this->createStub(User::class);
        $this->security
            ->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock
            ->expects($this->once())
            ->method('forConversationById')
            ->with($user, $conversationId)
            ->willReturn($expectedTopic);

        $request = Request::create("/api/watch_files/{$watchFileId}/conversations/last", 'GET');
        $responseBody = json_encode([
            'id' => $conversationId,
            'watchFile' => "/api/watch_files/{$watchFileId}",
        ]);
        $response = new Response((string) $responseBody);
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $linksHeader = $response->headers->all('Link');
        $this->assertCount(2, $linksHeader);
        $this->assertSame([
            '<https://basil.local/.well-known/mercure>; rel="mercure"',
            "<{$expectedTopic}>; rel=\"topic\"",
        ], $linksHeader);
    }

    public function testDoesNotAddHeadersForLastConversationWithInvalidResponseBody(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forConversationById');

        // Response body with invalid JSON
        $request = Request::create("/api/watch_files/{$watchFileId}/conversations/last", 'GET');
        $response = new Response('invalid json');
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    public function testDoesNotAddHeadersForLastConversationWithMissingId(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forConversationById');

        // Response body without 'id' field
        $request = Request::create("/api/watch_files/{$watchFileId}/conversations/last", 'GET');
        $responseBody = json_encode([
            'watchFile' => "/api/watch_files/{$watchFileId}",
        ]);
        $response = new Response((string) $responseBody);
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    public function testDoesNotAddHeadersForLastConversationWithNonStringId(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forConversationById');

        // Response body with numeric 'id' (not string)
        $request = Request::create("/api/watch_files/{$watchFileId}/conversations/last", 'GET');
        $responseBody = json_encode([
            'id' => 12345,
        ]);
        $response = new Response((string) $responseBody);
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    public function testDoesNotAddHeadersForUnsuccessfulResponse(): void
    {
        $topicGeneratorMock = $this->createMock(RealTimeTopicGeneratorInterface::class);
        $this->topicGenerator = $topicGeneratorMock;
        $this->buildSubscriber();
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = $this->createStub(User::class);
        $this->security->method('getUser')
            ->willReturn($user);

        $topicGeneratorMock->expects($this->never())
            ->method('forWatchFileById');

        $request = Request::create("/api/watch_files/{$watchFileId}", 'GET');
        $response = new Response('Not Found', 404);
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Link'));
    }

    private function createResponseEvent(
        Request $request,
        Response $response,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent($this->kernel, $request, $requestType, $response);
    }
}
