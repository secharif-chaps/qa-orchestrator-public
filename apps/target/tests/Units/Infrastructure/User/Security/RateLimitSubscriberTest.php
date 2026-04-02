<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\User\Security;

use App\Infrastructure\User\Security\RateLimitSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RateLimitSubscriberTest extends TestCase
{
    private RateLimiterFactoryInterface&Stub $anonymousLimiterFactory;
    private RateLimiterFactoryInterface&Stub $authenticatedLimiterFactory;
    private TokenStorageInterface&Stub $tokenStorage;
    private TranslatorInterface&Stub $translator;
    private HttpKernelInterface&Stub $kernel;
    private RateLimitSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->anonymousLimiterFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);
        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
        $this->buildSubscriber();
    }

    private function buildSubscriber(): void
    {
        $this->subscriber = new RateLimitSubscriber(
            $this->anonymousLimiterFactory,
            $this->authenticatedLimiterFactory,
            $this->tokenStorage,
            $this->translator,
        );
    }

    public function testOnKernelRequestSkipsSubRequests(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $this->buildSubscriber();
        $request = Request::create('/api/test');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $anonymousLimiterFactoryMock->expects($this->never())
->method('create');
        $authenticatedLimiterFactoryMock->expects($this->never())
->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipsNonApiPaths(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $this->buildSubscriber();
        $request = Request::create('/admin/dashboard');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $anonymousLimiterFactoryMock->expects($this->never())
->method('create');
        $authenticatedLimiterFactoryMock->expects($this->never())
->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    #[DataProvider('skipPathsProvider')]
    public function testOnKernelRequestSkipsExcludedPaths(string $path): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $this->buildSubscriber();
        $request = Request::create($path);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $anonymousLimiterFactoryMock->expects($this->never())
->method('create');
        $authenticatedLimiterFactoryMock->expects($this->never())
->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function skipPathsProvider(): iterable
    {
        yield 'docs' => ['/api/docs'];
        yield 'docs subpath' => ['/api/docs/openapi.json'];
        yield 'contexts' => ['/api/contexts'];
        yield 'contexts subpath' => ['/api/contexts/User'];
    }

    public function testOnKernelRequestUsesAnonymousLimiterWhenNoUser(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $this->buildSubscriber();
        $request = Request::create('/api/watch_files');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $rateLimit = $this->createAcceptedRateLimit();
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $anonymousLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->with(\sprintf('ip_%s', hash('sha256', '192.168.1.100')))
            ->willReturn($limiter);

        $authenticatedLimiterFactoryMock->expects($this->never())
->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestUsesAuthenticatedLimiterWhenUserExists(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $this->buildSubscriber();
        $request = Request::create('/api/watch_files');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('user-123');

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $rateLimit = $this->createAcceptedRateLimit();
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $authenticatedLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->with(\sprintf('user_%s', hash('sha256', 'user-123')))
            ->willReturn($limiter);

        $anonymousLimiterFactoryMock->expects($this->never())
->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestThrowsExceptionWhenRateLimitExceeded(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $translatorMock = $this->createMock(TranslatorInterface::class);
        $this->translator = $translatorMock;
        $this->buildSubscriber();
        $request = Request::create('/api/watch_files');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $retryAfter = new \DateTimeImmutable('+60 seconds');
        $rateLimit = $this->createRejectedRateLimit($retryAfter);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $anonymousLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $translatorMock->expects($this->once())
            ->method('trans')
            ->with('rate_limit.exceeded', $this->callback(function (array $params): bool {
                return isset($params['retryAfter']) && \is_int($params['retryAfter']);
            }))
            ->willReturn('Rate limit exceeded. Please try again in 60 seconds.');

        $this->expectException(TooManyRequestsHttpException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Please try again in 60 seconds.');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelResponseSkipsSubRequests(): void
    {
        $request = Request::create('/api/test');
        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }

    public function testOnKernelResponseSkipsWhenNoCurrentLimit(): void
    {
        $request = Request::create('/api/test');
        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-RateLimit-Limit'));
    }

    public function testOnKernelResponseAddsRateLimitHeaders(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $this->buildSubscriber();
        // First, trigger a request to set currentLimit
        $request = Request::create('/api/watch_files');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $retryAfter = new \DateTimeImmutable('+3600 seconds');
        $rateLimit = $this->createAcceptedRateLimit(1000, 999, $retryAfter);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $anonymousLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $this->subscriber->onKernelRequest($requestEvent);

        // Now test the response
        $response = new Response();
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($responseEvent);

        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
        $this->assertSame('1000', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('999', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame((string) $retryAfter->getTimestamp(), $response->headers->get('X-RateLimit-Reset'));
    }

    public function testOnKernelRequestUsesDefaultIpWhenNoClientIpOverride(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $this->buildSubscriber();
        $request = Request::create('/api/watch_files');
        // Request::create uses 127.0.0.1 by default
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $rateLimit = $this->createAcceptedRateLimit();
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $anonymousLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->with(\sprintf('ip_%s', hash('sha256', '127.0.0.1')))
            ->willReturn($limiter);

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipsCustomExcludedPaths(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $authenticatedLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->authenticatedLimiterFactory = $authenticatedLimiterFactoryMock;
        $this->buildSubscriber();
        $subscriber = new RateLimitSubscriber(
            $this->anonymousLimiterFactory,
            $this->authenticatedLimiterFactory,
            $this->tokenStorage,
            $this->translator,
            ['/api/health', '/api/status'],
        );

        $request = Request::create('/api/health');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $anonymousLimiterFactoryMock->expects($this->never())
->method('create');
        $authenticatedLimiterFactoryMock->expects($this->never())
->method('create');

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestDoesNotSkipDefaultPathsWhenCustomExcludedPathsProvided(): void
    {
        $anonymousLimiterFactoryMock = $this->createMock(RateLimiterFactoryInterface::class);
        $this->anonymousLimiterFactory = $anonymousLimiterFactoryMock;
        $tokenStorageMock = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorageMock;
        $this->buildSubscriber();
        $subscriber = new RateLimitSubscriber(
            $this->anonymousLimiterFactory,
            $this->authenticatedLimiterFactory,
            $this->tokenStorage,
            $this->translator,
            ['/api/health'],
        );

        $request = Request::create('/api/docs');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tokenStorageMock->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $rateLimit = $this->createAcceptedRateLimit();
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($rateLimit);

        $anonymousLimiterFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $subscriber->onKernelRequest($event);
    }

    private function createAcceptedRateLimit(
        int $limit = 1000,
        int $remaining = 999,
        ?\DateTimeImmutable $retryAfter = null,
    ): RateLimit {
        $retryAfter ??= new \DateTimeImmutable('+3600 seconds');

        $rateLimit = $this->createStub(RateLimit::class);
        $rateLimit->method('isAccepted')
->willReturn(true);
        $rateLimit->method('getLimit')
->willReturn($limit);
        $rateLimit->method('getRemainingTokens')
->willReturn($remaining);
        $rateLimit->method('getRetryAfter')
->willReturn($retryAfter);

        return $rateLimit;
    }

    private function createRejectedRateLimit(\DateTimeImmutable $retryAfter): RateLimit
    {
        $rateLimit = $this->createStub(RateLimit::class);
        $rateLimit->method('isAccepted')
->willReturn(false);
        $rateLimit->method('getRetryAfter')
->willReturn($retryAfter);

        return $rateLimit;
    }
}
