<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AsMessageHandler]
readonly class CalculateWatchFileChangesHandler
{
    public function __construct(
        private NormalizerInterface $normalizer,
    ) {
    }

    /**
     * Calculates changes between original and updated watch file data arrays.
     *
     * @return array<string,array{old:mixed,new:mixed}> Array of changes with old and new values
     */
    public function __invoke(CalculateWatchFileChangesAction $action): array
    {
        /** @var array<string, mixed> $originalNormalized */
        $originalNormalized = $this->normalizer->normalize($action->originalData, null, [
            'groups' => ['watch_file:write'],
        ]) ?? [];
        /** @var array<string, mixed> $updatedNormalized */
        $updatedNormalized = $this->normalizer->normalize($action->updatedData, null, [
            'groups' => ['watch_file:write'],
        ]) ?? [];
        $changes = [];

        // Check for modified and added fields
        foreach ($updatedNormalized as $field => $newValue) {
            if (!\array_key_exists($field, $originalNormalized)) {
                // Field was added
                $changes[$field] = [
                    'old' => null,
                    'new' => $newValue,
                ];
            } elseif ($originalNormalized[$field] !== $newValue) {
                // Field was modified
                $changes[$field] = [
                    'old' => $originalNormalized[$field],
                    'new' => $newValue,
                ];
            }
        }

        // Check for deleted fields
        foreach ($originalNormalized as $field => $oldValue) {
            if (!\array_key_exists($field, $updatedNormalized)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }
}
