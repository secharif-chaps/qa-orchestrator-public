<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Header;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\Infrastructure\User\Security\RateLimitSubscriber;
use Twig\Environment;

/**
 * Decorates the OpenAPI factory to add rate limiting documentation.
 *
 * This factory adds:
 * - Rate limit response headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset)
 * - 429 Too Many Requests response documentation
 * - General rate limiting information in the API description
 */
final readonly class RateLimitOpenApiFactory implements OpenApiFactoryInterface
{
    private const array EXCLUDED_PATH_PREFIXES = ['/api/docs', '/api/contexts'];

    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi = $this->addRateLimitDescription($openApi);
        $openApi = $this->addRateLimitHeadersToAllPaths($openApi);

        return $openApi;
    }

    private function addRateLimitDescription(OpenApi $openApi): OpenApi
    {
        $info = $openApi->getInfo();
        $description = $info->getDescription();

        $rateLimitInfo = $this->twig->render('openapi/rate_limiting.md.twig', [
            'anonymous_limit' => RateLimitSubscriber::ANONYMOUS_LIMIT,
            'authenticated_limit' => RateLimitSubscriber::AUTHENTICATED_LIMIT,
        ]);

        return $openApi->withInfo($info->withDescription($description . "\n" . $rateLimitInfo));
    }

    private function addRateLimitHeadersToAllPaths(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $newPathsObject = new Paths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            if ($this->isExcludedPath($path)) {
                $newPathsObject->addPath($path, $pathItem);
                continue;
            }

            $newPathsObject->addPath($path, $this->addRateLimitHeadersToPathItem($pathItem));
        }

        return $openApi->withPaths($newPathsObject);
    }

    private function isExcludedPath(string $path): bool
    {
        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function addRateLimitHeadersToPathItem(PathItem $pathItem): PathItem
    {
        $methods = ['Get', 'Post', 'Put', 'Patch', 'Delete'];

        foreach ($methods as $method) {
            $getter = 'get' . $method;
            $wither = 'with' . $method;

            $operation = $pathItem->$getter();
            if (null === $operation) {
                continue;
            }

            $responses = $operation->getResponses() ?? [];
            $newResponses = [];

            foreach ($responses as $statusCode => $response) {
                if ($statusCode >= 200 && $statusCode < 300 && $response instanceof Response) {
                    $response = $this->addRateLimitHeadersToResponse($response);
                }
                $newResponses[$statusCode] = $response;
            }

            // Add 429 response if not present
            if (!isset($newResponses[429])) {
                $newResponses[429] = $this->create429Response();
            }

            $operation = $operation->withResponses($newResponses);
            $pathItem = $pathItem->$wither($operation);
        }

        return $pathItem;
    }

    private function addRateLimitHeadersToResponse(Response $response): Response
    {
        $existingHeaders = $response->getHeaders();
        $existingArray = null !== $existingHeaders ? (array) $existingHeaders : [];

        $rateLimitHeaders = [
            'X-RateLimit-Limit' => new Header(
                description: \sprintf(
                    'Maximum number of requests allowed per hour. Authenticated: %d, Anonymous: %d',
                    RateLimitSubscriber::AUTHENTICATED_LIMIT,
                    RateLimitSubscriber::ANONYMOUS_LIMIT
                ),
                schema: [
                    'type' => 'integer',
                    'example' => RateLimitSubscriber::AUTHENTICATED_LIMIT,
                ],
            ),
            'X-RateLimit-Remaining' => new Header(
                description: 'Number of requests remaining in the current rate limit window',
                schema: [
                    'type' => 'integer',
                    'example' => 1999,
                ],
            ),
            'X-RateLimit-Reset' => new Header(
                description: 'Unix timestamp indicating when the rate limit window resets',
                schema: [
                    'type' => 'integer',
                    'example' => 1701432000,
                ],
            ),
        ];

        return $response->withHeaders(new \ArrayObject(array_merge($existingArray, $rateLimitHeaders)));
    }

    private function create429Response(): Response
    {
        return new Response(
            description: 'Rate limit exceeded. Check the Retry-After header for when to retry.',
            content: new \ArrayObject([
                'application/problem+json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'example' => '/errors/429',
                            ],
                            'title' => [
                                'type' => 'string',
                                'example' => 'An error occurred',
                            ],
                            'status' => [
                                'type' => 'integer',
                                'example' => 429,
                            ],
                            'detail' => [
                                'type' => 'string',
                                'example' => 'Rate limit exceeded. Please try again in 42 seconds.',
                            ],
                        ],
                    ],
                ],
            ]),
            headers: new \ArrayObject([
                'Retry-After' => new Header(
                    description: 'Number of seconds to wait before making another request',
                    schema: [
                        'type' => 'integer',
                        'example' => 3600,
                    ],
                ),
                'X-RateLimit-Limit' => new Header(
                    description: 'Maximum number of requests allowed per hour',
                    schema: [
                        'type' => 'integer',
                        'example' => RateLimitSubscriber::AUTHENTICATED_LIMIT,
                    ],
                ),
                'X-RateLimit-Remaining' => new Header(
                    description: 'Number of requests remaining (0 when rate limited)',
                    schema: [
                        'type' => 'integer',
                        'example' => 0,
                    ],
                ),
                'X-RateLimit-Reset' => new Header(
                    description: 'Unix timestamp indicating when the rate limit window resets',
                    schema: [
                        'type' => 'integer',
                        'example' => 1701432000,
                    ],
                ),
            ]),
        );
    }
}
