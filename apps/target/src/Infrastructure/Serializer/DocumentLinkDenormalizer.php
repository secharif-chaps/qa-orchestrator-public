<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\WatchFileEvent\DocumentLink;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DocumentLinkDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        \assert(\is_array($data), 'Data must be an array');
        \assert(isset($data['id']) && \is_string($data['id']), 'Data must contain a string "id" field');

        /** @var string $id */
        $id = $data['id'];

        /** @var string|null $textExtract */
        $textExtract = isset($data['text_extract']) && \is_string($data['text_extract'])
            ? $data['text_extract']
            : null;

        return new DocumentLink($id, $textExtract);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return DocumentLink::class === $type && \is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DocumentLink::class => true,
        ];
    }
}
