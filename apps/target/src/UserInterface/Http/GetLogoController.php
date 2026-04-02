<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Logo\GetLogoAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class GetLogoController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly RateLimiterFactoryInterface $logoApiLimiter,
    ) {
        $this->messageBus = $messageBus;
    }

    #[Route(
        '/api/logo/{domain}',
        requirements: [
            'domain' => '[a-zA-Z0-9\-\.]{3,253}', // Basic domain validation
        ],
        methods: ['GET'],
    )]
    public function __invoke(string $domain, Request $request): Response
    {
        $limiter = $this->logoApiLimiter->create($request->getClientIp());
        $limit = $limiter->consume();

        $headers = [
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Retry-After' => $limit
                ->getRetryAfter()
                ->getTimestamp() - time(),
            'X-RateLimit-Limit' => $limit->getLimit(),
        ];

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retryAfter: $headers['X-RateLimit-Retry-After'],
                headers: $headers,
            );
        }

        try {
            // Validate and clean the domain
            $action = new GetLogoAction($domain);
        } catch (\InvalidArgumentException $e) {
            return new Response($e->getMessage(), 400);
        }

        $logo = $this->handle($action);

        if (!\is_string($logo)) {
            return new Response('Invalid logo response', 500);
        }

        $response = new Response($logo);
        $response->headers->add($headers);

        // Set content type based on content
        $contentType = $this->detectContentType($logo);
        $response->headers->set('Content-Type', $contentType);

        // Add CORS headers - restrict to same domain for security
        $host = $request->headers->get('origin', 'http://localhost');

        $response->headers->set('Access-Control-Allow-Origin', $host);
        $response->headers->set('Access-Control-Allow-Methods', 'GET');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');

        // Cache headers
        $response->setPublic();
        $response->setMaxAge(2592000); // 30 days
        $response->setSharedMaxAge(2592000); // 30 days

        return $response;
    }

    private function detectContentType(string $content): string
    {
        // Check if it's SVG by looking for XML declaration or svg tag
        if (str_starts_with($content, '<svg') || str_starts_with($content, '<?xml')) {
            return 'image/svg+xml';
        }

        // Use getimagesizefromstring for other image types
        $imageInfo = @getimagesizefromstring($content);
        if (false !== $imageInfo) {
            return $imageInfo['mime'];
        }

        // Default fallback
        return 'image/png';
    }
}
