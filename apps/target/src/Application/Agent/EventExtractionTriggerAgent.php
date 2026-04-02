<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Document\UpdateDocumentEventsAction;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFileEvent\WatchFileEvent;

class EventExtractionTriggerAgent extends TriggerAgent
{
    private const string NAME = 'ExtractEventsFromDocument';

    /**
     * @param array{documentId: string, watchFileId: string, documentContent: string, referenceSubject: TranslatedText|null, recentEvents: array<int, WatchFileEvent>} $data
     */
    public function __construct(array $data, \DateTime $triggeredAt = new \DateTime())
    {
        parent::__construct(
            name: self::NAME,
            data: $data,
            responseType: UpdateDocumentEventsAction::class,
            watchFileId: $data['watchFileId'],
            triggeredAt: $triggeredAt,
        );
    }
}
