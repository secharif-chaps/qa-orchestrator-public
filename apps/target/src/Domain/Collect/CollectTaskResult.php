<?php

declare(strict_types=1);

namespace App\Domain\Collect;

readonly class CollectTaskResult
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        private bool $success,
        private ?array $data = null,
        private ?string $errorMessage = null,
        private ?array $metadata = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public static function success(array $data = [], array $metadata = []): self
    {
        return new self(true, $data, null, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function failure(string $errorMessage, array $metadata = []): self
    {
        return new self(false, null, $errorMessage, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}
