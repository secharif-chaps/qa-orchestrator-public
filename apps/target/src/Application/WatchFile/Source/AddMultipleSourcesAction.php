<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

/**
 * Action to add multiple sources to a watch file.
 * Common fields are kept separate, while individual source data is in an array.
 */
readonly class AddMultipleSourcesAction
{
    /**
     * @param string                 $watchFileId      The ID of the watch file to add sources to
     * @param array<AddSourceAction> $sources          Array of individual source actions
     * @param string|null            $messageContentId Optional message content ID (common for all sources)
     */
    public function __construct(
        public string $watchFileId,
        public array $sources,
        public ?string $messageContentId = null,
    ) {
    }

    /**
     * Convert this action to individual AddSourceAction objects.
     *
     * @return array<AddSourceAction>
     */
    public function toIndividualActions(): array
    {
        $actions = [];

        foreach ($this->sources as $source) {
            // Create individual action with common fields overridden if provided
            $action = new AddSourceAction(
                watchFileId: $this->watchFileId,
                name: $source->name,
                type: $source->type,
                primaryDomain: $source->primaryDomain,
                url: $source->url,
                query: $source->query,
                description: $source->description,
                relevance: $source->relevance,
                parameters: $source->parameters,
                messageId: $this->messageContentId ?? $source->messageId,
                actorId: $source->actorId,
            );

            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * Get the count of sources to be added.
     */
    public function getSourceCount(): int
    {
        return \count($this->sources);
    }

    /**
     * Check if this action contains any sources.
     */
    public function hasSources(): bool
    {
        return !empty($this->sources);
    }
}
