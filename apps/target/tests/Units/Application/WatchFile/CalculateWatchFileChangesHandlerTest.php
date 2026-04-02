<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\CalculateWatchFileChangesAction;
use App\Application\WatchFile\CalculateWatchFileChangesHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CalculateWatchFileChangesHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private CalculateWatchFileChangesHandler $handler;

    protected function setUp(): void
    {
        $normalizer = $this->createStub(NormalizerInterface::class);
        $normalizer->method('normalize')
            ->willReturnCallback(function ($object, $format = null, $context = []) {
                if ($object instanceof WatchFile) {
                    return [
                        'name' => $object->getName(),
                        'userObjective' => $object->getUserObjective(),
                        'referenceSubject' => $object->getReferenceSubject(),
                        'state' => $object->getState()
->value,
                    ];
                }

                return [];
            });
        $this->handler = new CalculateWatchFileChangesHandler($normalizer);
    }

    public function testCalculateChangesWithModifiedField(): void
    {
        $originalWatchFile = new WatchFile('old-name', 'old-objective', new Organisation('Test Org', 'test-org-id'));
        $updatedWatchFile = new WatchFile('new-name', 'old-objective', new Organisation('Test Org', 'test-org-id'));
        $updatedWatchFile->setReferenceSubject(new TranslatedText('new-query', 'new-query'));

        $action = new CalculateWatchFileChangesAction($originalWatchFile, $updatedWatchFile);
        $changes = ($this->handler)($action);

        $this->assertArrayHasKey('name', $changes);
        $this->assertEquals('old-name', $changes['name']['old']);
        $this->assertEquals('new-name', $changes['name']['new']);

        $this->assertArrayHasKey('referenceSubject', $changes);
        $this->assertNull($changes['referenceSubject']['old']);
        $this->assertEquals(TranslatedText::fromArray([
            'fr' => 'new-query',
            'en' => 'new-query',
        ]), $changes['referenceSubject']['new']);
    }

    public function testCalculateChangesWithAddedField(): void
    {
        $originalWatchFile = new WatchFile('name', 'objective', new Organisation('Test Org', 'test-org-id'));
        $updatedWatchFile = new WatchFile('name', 'new-objective', new Organisation('Test Org', 'test-org-id'));
        $updatedWatchFile->setReferenceSubject(new TranslatedText('new-query', 'new-query'));

        $action = new CalculateWatchFileChangesAction($originalWatchFile, $updatedWatchFile);
        $changes = ($this->handler)($action);

        $this->assertArrayHasKey('userObjective', $changes);
        $this->assertEquals('objective', $changes['userObjective']['old']);
        $this->assertEquals('new-objective', $changes['userObjective']['new']);

        $this->assertArrayHasKey('referenceSubject', $changes);
        $this->assertNull($changes['referenceSubject']['old']);
        $this->assertEquals(TranslatedText::fromArray([
            'fr' => 'new-query',
            'en' => 'new-query',
        ]), $changes['referenceSubject']['new']);
    }

    public function testCalculateChangesWithDeletedField(): void
    {
        $originalWatchFile = new WatchFile('name', 'objective', new Organisation('Test Org', 'test-org-id'));
        $originalWatchFile->setReferenceSubject(new TranslatedText('old-query', 'old-query'));
        $updatedWatchFile = new WatchFile('name', 'objective', new Organisation('Test Org', 'test-org-id'));

        $action = new CalculateWatchFileChangesAction($originalWatchFile, $updatedWatchFile);
        $changes = ($this->handler)($action);

        $this->assertArrayHasKey('referenceSubject', $changes);
        $this->assertEquals(TranslatedText::fromArray([
            'fr' => 'old-query',
            'en' => 'old-query',
        ]), $changes['referenceSubject']['old']);
        $this->assertNull($changes['referenceSubject']['new']);
    }

    public function testCalculateChangesWithNoChanges(): void
    {
        $originalWatchFile = new WatchFile('name', 'objective', new Organisation('Test Org', 'test-org-id'));
        $updatedWatchFile = new WatchFile('name', 'objective', new Organisation('Test Org', 'test-org-id'));

        $action = new CalculateWatchFileChangesAction($originalWatchFile, $updatedWatchFile);
        $changes = ($this->handler)($action);

        $this->assertEmpty($changes);
    }
}
