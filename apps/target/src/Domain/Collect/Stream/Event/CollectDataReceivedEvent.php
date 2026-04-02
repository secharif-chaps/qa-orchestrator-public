<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream\Event;

readonly class CollectDataReceivedEvent extends AbstractCollectEvent
{
    /** @var list<string> */
    public const array DOCUMENT_TYPES = ['merged_result', 'raw_result', 'document_refined_result'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $collectTaskId,
        string $providerTaskId,
        public array $data,
        public \DateTimeImmutable $receivedAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($collectTaskId, $providerTaskId);
    }

    public function getType(): ?string
    {
        if (!empty($this->data['type']) && \is_string($this->data['type'])) {
            return $this->data['type'];
        }

        return null;
    }

    public function isDocument(): bool
    {
        $type = $this->getType();

        return \in_array($type, self::DOCUMENT_TYPES, true);
    }
}
