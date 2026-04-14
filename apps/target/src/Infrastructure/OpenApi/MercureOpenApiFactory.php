<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Header;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Twig\Environment;

/**
 * Decorates the OpenAPI factory to add Mercure real-time documentation.
 *
 * This factory adds:
 * - Link header documentation for Mercure hub discovery (rel="mercure")
 * - Link header documentation for topic subscription (rel="topic")
 * - General real-time updates information in the API description
 *
 * Only GET requests on specific endpoints receive Link headers for auto-discovery.
 */
final readonly class MercureOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * Paths that support Mercure auto-discovery via Link headers.
     * These must match the patterns in MercureDiscoverySubscriber.
     *
     * @var array<string, string>
     */
    private const array PATHS_WITH_LINK_HEADER = [
        '/api/watch_files/{watchFileId}' => '/users/{userId}/watch-files/{watchFileId}',
        '/api/watch_files/{watchFileId}/conversations/last' => '/users/{userId}/conversations/{conversationId}',
        '/api/conversations/{id}/messages' => '/users/{userId}/conversations/{conversationId}/messages',
    ];
    private const int TOKEN_EXPIRATION_HOURS = 1;

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

        $openApi = $this->addMercureDescription($openApi);
        $openApi = $this->addLinkHeaderToRelevantPaths($openApi);

        return $openApi;
    }

    private function addMercureDescription(OpenApi $openApi): OpenApi
    {
        $info = $openApi->getInfo();
        $description = $info->getDescription();

        $mercureInfo = $this->twig->render('openapi/mercure_real_time.md.twig', [
            'token_expiration_hours' => self::TOKEN_EXPIRATION_HOURS,
        ]);

        return $openApi->withInfo($info->withDescription($description . "\n" . $mercureInfo));
    }

    private function addLinkHeaderToRelevantPaths(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $newPathsObject = new Paths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $topicExample = $this->getTopicExampleForPath($path);
            if (null !== $topicExample) {
                $pathItem = $this->addLinkHeaderToGetOperation($pathItem, $topicExample);
            }
            $newPathsObject->addPath($path, $pathItem);
        }

        return $openApi->withPaths($newPathsObject);
    }

    /**
     * Returns the topic example for a path if it supports Mercure auto-discovery.
     */
    private function getTopicExampleForPath(string $path): ?string
    {
        return self::PATHS_WITH_LINK_HEADER[$path] ?? null;
    }

    /**
     * Adds Link headers only to the GET operation of a path item.
     * Mercure auto-discovery is only available on GET requests.
     */
    private function addLinkHeaderToGetOperation(PathItem $pathItem, string $topicExample): PathItem
    {
        $operation = $pathItem->getGet();
        if (null === $operation) {
            return $pathItem;
        }

        $responses = $operation->getResponses() ?? [];
        $newResponses = [];

        foreach ($responses as $statusCode => $response) {
            if ($statusCode >= 200 && $statusCode < 300 && $response instanceof Response) {
                $response = $this->addLinkHeadersToResponse($response, $topicExample);
            }
            $newResponses[$statusCode] = $response;
        }

        return $pathItem->withGet($operation->withResponses($newResponses));
    }

    /**
     * Adds both Mercure Link headers to a response:
     * - rel="mercure": The Mercure hub URL for SSE connection
     * - rel="topic": The topic URL to subscribe to for this resource.
     */
    private function addLinkHeadersToResponse(Response $response, string $topicExample): Response
    {
        $existingHeaders = $response->getHeaders();
        $existingArray = null !== $existingHeaders ? (array) $existingHeaders : [];

        $linkHeaders = [
            'Link' => new Header(
                description: <<<'DESC'
                    Mercure discovery headers (RFC 8288). Two Link headers are returned:
                    1. `rel="mercure"` - The Mercure hub URL for SSE connection
                    2. `rel="topic"` - The topic URL to subscribe to for real-time updates on this resource
                    DESC
                ,
                schema: [
                    'type' => 'string',
                    'example' => \sprintf(
                        '<https://basil.local/.well-known/mercure>; rel="mercure", <%s>; rel="topic"',
                        $topicExample,
                    ),
                ],
            ),
        ];

        return $response->withHeaders(new \ArrayObject(array_merge($existingArray, $linkHeaders)));
    }
}
