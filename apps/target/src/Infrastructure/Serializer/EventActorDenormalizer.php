<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\WatchFileEvent\EventActor;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class EventActorDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        \assert(\is_array($data), 'Data must be an array');
        \assert(isset($data['id']) && \is_string($data['id']), 'Data must contain a string "id" field');
        \assert(isset($data['name']) && \is_string($data['name']), 'Data must contain a string "name" field');
        \assert(isset($data['role']) && \is_string($data['role']), 'Data must contain a string "role" field');

        /** @var string $id */
        $id = $data['id'];
        /** @var string $name */
        $name = $data['name'];
        /** @var string $role */
        $role = $data['role'];

        return new EventActor($id, $name, $role);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return EventActor::class === $type && \is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            EventActor::class => true,
        ];
    }
}
