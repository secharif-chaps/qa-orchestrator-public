<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Document\UpdateDocumentSummaryAction;

class DocumentSummaryTriggerAgent extends TriggerAgent
{
    private const string NAME = 'DocumentSummary';

    public function __construct(array $data, \DateTime $triggeredAt = new \DateTime())
    {
        parent::__construct(
            name: self::NAME,
            data: $data,
            responseType: UpdateDocumentSummaryAction::class,
            triggeredAt: $triggeredAt,
        );
    }
}
