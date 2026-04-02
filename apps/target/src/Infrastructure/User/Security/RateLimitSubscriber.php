<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RateLimitSubscriber
{
    private const string API_PREFIX = '/api';
    public const int ANONYMOUS_LIMIT = 500;
    public const int AUTHENTICATED_LIMIT = 2000;
    private ?RateLimit $currentLimit = null;

    /**
     * @param list<string> $excludedPaths
     */
    public function __construct(
        private readonly RateLimiterFactoryInterface $apiAnonymousLimiter,
        private readonly RateLimiterFactoryInterface $apiAuthenticatedLimiter,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly array $excludedPaths = ['/api/docs', '/api/contexts', '/api/healthcheck'],
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, self::API_PREFIX)) {
            return;
        }

        if ($this->shouldSkipRateLimit($path)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (null !== $user) {
            $identifier = \sprintf('user_%s', hash('sha256', $user->getUserIdentifier()));
            $limiter = $this->apiAuthenticatedLimiter->create($identifier);
        } else {
            $identifier = \sprintf('ip_%s', hash('sha256', $request->getClientIp() ?? 'unknown'));
            $limiter = $this->apiAnonymousLimiter->create($identifier);
        }

        $this->currentLimit = $limiter->consume();

        if (!$this->currentLimit->isAccepted()) {
            $retryAfter = max(1, $this->currentLimit->getRetryAfter()->getTimestamp() - time());

            $message = $this->translator->trans('rate_limit.exceeded', [
                'retryAfter' => $retryAfter,
            ]);

            throw new TooManyRequestsHttpException(retryAfter: $retryAfter, message: $message);
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->currentLimit) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->set('X-RateLimit-Limit', (string) $this->currentLimit->getLimit());
        $response->headers->set('X-RateLimit-Remaining', (string) $this->currentLimit->getRemainingTokens());
        $response->headers->set('X-RateLimit-Reset', (string) $this->currentLimit->getRetryAfter()->getTimestamp());

        $this->currentLimit = null;
    }

    private function shouldSkipRateLimit(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }
}
