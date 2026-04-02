<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Document\EnrichDocumentWithAiValidationAction;

class ValidateDocumentAITriggerAgent extends TriggerAgent
{
    private const string NAME = 'ValidateDocumentAI';

    /**
     * @param array{id: string, content: string, referenceSubject: string} $data
     */
    public function __construct(array $data, \DateTime $triggeredAt = new \DateTime())
    {
        parent::__construct(
            name: self::NAME,
            data: $data,
            responseType: EnrichDocumentWithAiValidationAction::class,
            triggeredAt: $triggeredAt,
        );
    }
}
