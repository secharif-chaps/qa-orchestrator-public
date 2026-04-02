<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\HubInterface;

/**
 * Adds Mercure auto-discovery Link headers to API responses for supported resources.
 *
 * This subscriber adds the standard Mercure Link headers following RFC 8288:
 * - `Link: <hub-url>; rel="mercure"` - The Mercure hub URL for SSE connections
 * - `Link: <topic>; rel="topic"` - The topic to subscribe to for this resource
 *
 * Supported routes (GET only):
 * - GET /api/watch_files/{id} → watch-files topic
 * - GET /api/watch_files/{id}/conversations/last → conversations topic
 * - GET /api/conversations/{id} → conversations topic
 * - GET /api/conversations/{id}/messages → conversation-messages topic
 *
 * Topics are user-scoped: /users/{userId}/{resourceType}/{resourceId}
 */
readonly class MercureDiscoverySubscriber
{
    public function __construct(
        private HubInterface $hub,
        private RealTimeTopicGeneratorInterface $topicGenerator,
        private Security $security,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -5)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only add discovery headers for GET requests
        if ('GET' !== $request->getMethod()) {
            return;
        }

        $response = $event->getResponse();
        if (!$response->isSuccessful()) {
            return;
        }

        $path = $request->getPathInfo();

        $resourceInfo = $this->extractResourceInfo($path, $response);
        if (null === $resourceInfo) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $hubUrl = $this->hub->getPublicUrl();

        $topic = $this->buildTopic($resourceInfo['type'], $resourceInfo['id'], $user);
        if (null === $topic) {
            return;
        }

        $linkMercureHub = \sprintf('<%s>; rel="mercure"', $hubUrl);
        $response->headers->set('Link', $linkMercureHub, false);

        $linkMercureTopic = \sprintf('<%s>; rel="topic"', $topic);
        $response->headers->set('Link', $linkMercureTopic, false);
    }

    /**
     * Extracts resource information from the request path.
     *
     * Only matches exact supported routes:
     * - /api/watch_files/{uuid}
     * - /api/conversations/{uuid}
     * - /api/watch_files/{uuid}/conversations/last
     * - /api/conversations/{uuid}/messages
     *
     * @return array{type: string, id: string}|null
     */
    private function extractResourceInfo(string $path, Response $response): ?array
    {
        // UUID pattern: 8-4-4-4-12 hexadecimal characters
        $uuid = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';

        // Order matters: more specific patterns first
        $patterns = [
            // GET /api/conversations/{id}/messages - messages collection
            'conversation_messages' => "#^/api/conversations/({$uuid})/messages$#",
            // GET /api/watch_files/{id} - single watch file
            'watch_file' => "#^/api/watch_files/({$uuid})$#",
            // GET /api/conversations/{id} - single conversation
            'conversation' => "#^/api/conversations/({$uuid})$#",
            // GET /api/watch_files/{uuid}/conversations/last - last conversation (ID extracted from response body)
            'conversation_last' => "#^/api/watch_files/{$uuid}/conversations/last$#",
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                // For conversation_last, the conversation ID is in the response body, not the URL
                if ('conversation_last' === $type) {
                    $conversationId = $this->extractConversationIdFromResponse($response);
                    if (null === $conversationId) {
                        return null;
                    }

                    return [
                        'type' => $type,
                        'id' => $conversationId,
                    ];
                }

                // Other patterns have a capture group for the resource ID
                if (!isset($matches[1])) {
                    return null;
                }

                return [
                    'type' => $type,
                    'id' => $matches[1],
                ];
            }
        }

        return null;
    }

    /**
     * Extracts the conversation ID from the response body.
     *
     * Used for /api/watch_files/{uuid}/conversations/last where the conversation ID
     * is returned in the response body, not present in the URL.
     */
    private function extractConversationIdFromResponse(Response $response): ?string
    {
        $content = $response->getContent();
        if (false === $content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return null;
        }

        // API Platform uses 'id' for resource identifiers
        if (!isset($data['id']) || !\is_string($data['id'])) {
            return null;
        }

        return $data['id'];
    }

    private function buildTopic(string $type, string $resourceId, User $user): ?string
    {
        return match ($type) {
            'watch_file' => $this->topicGenerator->forWatchFileById($user, $resourceId),
            'conversation', 'conversation_last' => $this->topicGenerator->forConversationById($user, $resourceId),
            'conversation_messages' => $this->topicGenerator->forConversationMessagesById($user, $resourceId),
            default => null,
        };
    }
}
