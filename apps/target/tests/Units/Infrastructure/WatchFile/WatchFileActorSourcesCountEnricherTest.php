<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Infrastructure\WatchFile\WatchFileActorSourcesCountEnricher;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(WatchFileActorSourcesCountEnricher::class)]
class WatchFileActorSourcesCountEnricherTest extends TestCase
{
    use EntityUtilsTrait;
    private NullSourceGateway $sourceGateway;
    private WatchFileActorSourcesCountEnricher $enricher;

    protected function setUp(): void
    {
        $this->sourceGateway = new NullSourceGateway();
        $this->enricher = new WatchFileActorSourcesCountEnricher($this->sourceGateway);
    }

    public function testSupports(): void
    {
        $this->assertSame(WatchFileActor::class, $this->enricher->supports());
    }

    public function testEnrichSetsSourcesCountOnSingleWatchFileActor(): void
    {
        $watchFileId = Uuid::v4()->toString();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, Uuid::v4()->toString());

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $this->forcePropertyValue($watchFileActor, Uuid::v4()->toString());

        $source1 = $this->createSource($watchFile, $actor, 'Source 1', 'https://example1.com');
        $this->sourceGateway->save($source1);

        $source2 = $this->createSource($watchFile, $actor, 'Source 2', 'https://example2.com');
        $this->sourceGateway->save($source2);

        $result = $this->enricher->enrich($watchFileActor, [
            'watchFileId' => $watchFileId,
        ]);

        $this->assertSame($watchFileActor, $result);
        $this->assertSame(2, $watchFileActor->getSourcesCount());
    }

    public function testEnrichSetsZeroCountWhenNoSources(): void
    {
        $watchFileId = Uuid::v4()->toString();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, Uuid::v4()->toString());

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $this->forcePropertyValue($watchFileActor, Uuid::v4()->toString());

        $result = $this->enricher->enrich($watchFileActor, [
            'watchFileId' => $watchFileId,
        ]);

        $this->assertSame($watchFileActor, $result);
        $this->assertSame(0, $watchFileActor->getSourcesCount());
    }

    public function testEnrichCollectionSetsCountsOnMultipleWatchFileActors(): void
    {
        $watchFileId = Uuid::v4()->toString();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);

        $actor1 = new Actor('Actor 1', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor1, Uuid::v4()->toString());

        $actor2 = new Actor('Actor 2', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor2, Uuid::v4()->toString());

        $actor3 = new Actor('Actor 3', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor3, Uuid::v4()->toString());

        $watchFileActor1 = new WatchFileActor($actor1, $watchFile);
        $this->forcePropertyValue($watchFileActor1, Uuid::v4()->toString());

        $watchFileActor2 = new WatchFileActor($actor2, $watchFile);
        $this->forcePropertyValue($watchFileActor2, Uuid::v4()->toString());

        $watchFileActor3 = new WatchFileActor($actor3, $watchFile);
        $this->forcePropertyValue($watchFileActor3, Uuid::v4()->toString());

        // Actor 1 has 3 sources
        for ($i = 0; $i < 3; ++$i) {
            $source = $this->createSource($watchFile, $actor1, "Source A{$i}", "https://actor1-{$i}.com");
            $this->sourceGateway->save($source);
        }

        // Actor 2 has 1 source
        $source = $this->createSource($watchFile, $actor2, 'Source B', 'https://actor2.com');
        $this->sourceGateway->save($source);

        // Actor 3 has no sources

        $watchFileActors = [$watchFileActor1, $watchFileActor2, $watchFileActor3];

        $result = $this->enricher->enrichCollection($watchFileActors, [
            'watchFileId' => $watchFileId,
        ]);

        $this->assertSame($watchFileActors, $result);
        $this->assertSame(3, $watchFileActor1->getSourcesCount());
        $this->assertSame(1, $watchFileActor2->getSourcesCount());
        $this->assertSame(0, $watchFileActor3->getSourcesCount());
    }

    public function testEnrichCollectionReturnsEntitiesWithoutModificationWhenNoWatchFileIdInContext(): void
    {
        $watchFileId = Uuid::v4()->toString();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, Uuid::v4()->toString());

        $watchFileActor = new WatchFileActor($actor, $watchFile);
        $this->forcePropertyValue($watchFileActor, Uuid::v4()->toString());

        $watchFileActors = [$watchFileActor];

        $result = $this->enricher->enrichCollection($watchFileActors, []);

        $this->assertSame($watchFileActors, $result);
        $this->assertNull($watchFileActor->getSourcesCount());
    }

    public function testEnrichCollectionWithEmptyArray(): void
    {
        $watchFileId = Uuid::v4()->toString();

        $result = $this->enricher->enrichCollection([], [
            'watchFileId' => $watchFileId,
        ]);

        $this->assertSame([], $result);
    }

    public function testEnrichOnlyCountsSourcesForSpecificWatchFile(): void
    {
        $watchFile1Id = Uuid::v4()->toString();
        $watchFile2Id = Uuid::v4()->toString();
        $user = new User('user1', 'user1@example.com', [], 'User 1');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, $watchFile1Id);

        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, $watchFile2Id);

        $actor = new Actor('Shared Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, Uuid::v4()->toString());

        $watchFileActor = new WatchFileActor($actor, $watchFile1);
        $this->forcePropertyValue($watchFileActor, Uuid::v4()->toString());

        // Source in watchFile1
        $source1 = $this->createSource($watchFile1, $actor, 'Source in WF1', 'https://wf1.com');
        $this->sourceGateway->save($source1);

        // Source in watchFile2 (should not be counted)
        $source2 = $this->createSource($watchFile2, $actor, 'Source in WF2', 'https://wf2.com');
        $this->sourceGateway->save($source2);

        $result = $this->enricher->enrich($watchFileActor, [
            'watchFileId' => $watchFile1Id,
        ]);

        $this->assertSame($watchFileActor, $result);
        $this->assertSame(1, $watchFileActor->getSourcesCount());
    }

    private function createSource(WatchFile $watchFile, Actor $actor, string $name, string $url): Source
    {
        $source = new Source(
            name: $name,
            description: TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            type: SourceType::WEBSITE,
            url: $url,
            primaryDomain: parse_url($url, \PHP_URL_HOST) ?: 'example.com',
            relevance: TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            actor: $actor,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, Uuid::v4()->toString());

        return $source;
    }
}
