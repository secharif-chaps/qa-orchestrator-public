<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Application\Agent\EventExtractionTriggerAgent;
use App\Application\Document\UpdateDocumentEventsAction;
use App\Domain\Shared\TranslatedText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventExtractionIntegrationTest extends KernelTestCase
{
    public function testEventExtractionTriggerAgentCreation(): void
    {
        $agent = new EventExtractionTriggerAgent([
            'documentId' => 'test-doc-123',
            'watchFileId' => 'test-watchfile-456',
            'documentContent' => 'Tesla a annoncé un partenariat stratégique avec Panasonic...',
            'referenceSubject' => new TranslatedText(
                'Veille technologique automobile électrique',
                'Electric vehicle technology monitoring'
            ),
            'recentEvents' => [],
        ]);

        $this->assertSame('ExtractEventsFromDocument', $agent->name);
        $this->assertSame('test-watchfile-456', $agent->watchFileId);
        $this->assertSame(UpdateDocumentEventsAction::class, $agent->responseType);
    }

    public function testUpdateDocumentEventsActionWithEvents(): void
    {
        $action = new UpdateDocumentEventsAction(
            documentId: 'test-doc-123',
            watchFileId: 'test-watchfile-456',
            events: [
                [
                    'start_date' => '2025-10-13T10:00:00Z',
                    'end_date' => null,
                    'description' => [
                        'fr' => 'Partenariat Tesla-Panasonic',
                        'en' => 'Tesla-Panasonic partnership',
                    ],
                    'event_type' => 'commercial_business',
                    'actors' => [
                        [
                            'name' => 'Tesla',
                            'role' => 'Signataire',
                        ],
                        [
                            'name' => 'Panasonic',
                            'role' => 'Partenaire',
                        ],
                    ],
                ],
            ]
        );

        $this->assertTrue($action->hasEvents());
        $this->assertFalse($action->hasFailed());
        $this->assertCount(1, $action->events);
    }

    public function testUpdateDocumentEventsActionWithError(): void
    {
        $action = new UpdateDocumentEventsAction(
            documentId: 'test-doc-123',
            watchFileId: 'test-watchfile-456',
            events: [],
            error: 'AI processing failed'
        );

        $this->assertFalse($action->hasEvents());
        $this->assertTrue($action->hasFailed());
        $this->assertSame('AI processing failed', $action->error);
    }

    public function testUpdateDocumentEventsActionEmpty(): void
    {
        $action = new UpdateDocumentEventsAction(
            documentId: 'test-doc-123',
            watchFileId: 'test-watchfile-456',
            events: []
        );

        $this->assertFalse($action->hasEvents());
        $this->assertFalse($action->hasFailed());
    }
}
