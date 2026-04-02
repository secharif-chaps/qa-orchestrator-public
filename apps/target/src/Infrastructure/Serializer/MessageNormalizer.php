<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\Chat\Content\FileContent;
use App\Domain\Chat\Content\FunctionCallContent;
use App\Domain\Chat\Content\FunctionResponseContent;
use App\Domain\Chat\Content\MessageContent;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Message;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Custom normalizer for Message entities to optimize serialization performance.
 *
 * This normalizer bypasses the default Symfony serializer for Message entities
 * when the 'message:read' group is active, directly transforming Message entities
 * into array representations to avoid Doctrine proxy lazy loading overhead.
 *
 * Performance optimization for TAR-233.
 */
readonly class MessageNormalizer implements NormalizerInterface
{
    private const string SUPPORTED_GROUP = 'message:read';

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array|string|int|float|bool|\ArrayObject|null {
        if (!$data instanceof Message) {
            return null;
        }

        // Build the message array directly to avoid reflection and proxy overhead
        $messageArray = [
            '@id' => \sprintf(
                '/api/conversations/%s/messages/%s',
                $data->getConversation()?->getId() ?? '',
                $data->getId() ?? ''
            ),
            '@type' => 'Message',
            'id' => $data->getId(),
            'role' => $data->getRole()
                ->value,
            'status' => $data->getStatus()
                ->value,
            'retryCount' => $data->getRetryCount(),
            'createdAt' => $data->getCreatedAt()
                ->format(\DateTimeInterface::RFC3339_EXTENDED),
            'metadata' => $data->getMetadata(),
            'updatedAt' => $data->getUpdatedAt()?->format(\DateTimeInterface::RFC3339_EXTENDED),
            'contents' => [],
        ];

        // Add updatedBy reference if available
        $updatedBy = $data->getUpdatedBy();
        if (null !== $updatedBy) {
            $messageArray['updatedBy'] = [
                '@id' => \sprintf('/api/users/%s', $updatedBy->getId()),
                '@type' => 'User',
                'id' => $updatedBy->getId(),
            ];
        }

        // Serialize contents without lazy loading
        foreach ($data->getContents() as $content) {
            $messageArray['contents'][] = $this->normalizeContent($content);
        }

        // Add conversation reference
        $conversation = $data->getConversation();
        if (null !== $conversation) {
            $messageArray['conversation'] = [
                '@id' => \sprintf('/api/conversations/%s', $conversation->getId()),
                '@type' => 'Conversation',
                'id' => $conversation->getId(),
            ];
        }

        // Add createdBy reference if available
        $createdBy = $data->getCreatedBy();
        if (null !== $createdBy) {
            $messageArray['createdBy'] = [
                '@id' => \sprintf('/api/users/%s', $createdBy->getId()),
                '@type' => 'User',
                'id' => $createdBy->getId(),
                'defaultThumbnail' => $createdBy->getDefaultThumbnail(),
            ];
        }

        return $messageArray;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof Message) {
            return false;
        }

        // Only support when message:read group is active
        /** @var list<string> $groups */
        $groups = $context['groups'] ?? [];

        return \in_array(self::SUPPORTED_GROUP, $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Message::class => true,
        ];
    }

    /**
     * Normalize a MessageContent to an array without using the full serializer.
     *
     * @return array<string, mixed>
     */
    private function normalizeContent(MessageContent $content): array
    {
        $contentArray = [
            '@type' => $this->getContentTypeName($content),
            'id' => $content->getId(),
            'createdAt' => $content->getCreatedAt()
                ->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];

        // Add type-specific fields
        if ($content instanceof TextContent) {
            $contentArray['content'] = $content->getContent();
        } elseif ($content instanceof FunctionCallContent) {
            $contentArray['functionName'] = $content->getFunctionName();
            $contentArray['parameters'] = $content->getParameters();
        } elseif ($content instanceof FunctionResponseContent) {
            $contentArray['functionName'] = $content->getFunctionName();
            $contentArray['result'] = $content->getResult();
            $contentArray['metadata'] = $content->getMetadata();
        } elseif ($content instanceof FileContent) {
            $contentArray['filename'] = $content->getFilename();
            $contentArray['mimeType'] = $content->getMimeType();
            $contentArray['size'] = $content->getSize();
            $contentArray['path'] = $content->getPath();
        }

        return $contentArray;
    }

    /**
     * Get the type name for a MessageContent subclass.
     */
    private function getContentTypeName(MessageContent $content): string
    {
        return match (true) {
            $content instanceof TextContent => 'TextContent',
            $content instanceof FunctionCallContent => 'FunctionCallContent',
            $content instanceof FunctionResponseContent => 'FunctionResponseContent',
            $content instanceof FileContent => 'FileContent',
            default => 'MessageContent',
        };
    }
}
