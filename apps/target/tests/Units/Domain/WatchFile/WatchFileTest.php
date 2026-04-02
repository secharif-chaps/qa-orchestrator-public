<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\AnalysisResult;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileState;
use PHPUnit\Framework\TestCase;

class WatchFileTest extends TestCase
{
    public function testAddActorAndRemoveActor(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('John Doe', new Organisation('Test Org', 'test-org-id'));
        $watchFile->addActor($actor, ActorType::CUSTOMER, TranslatedText::fromArray([
            'fr' => 'explication',
            'en' => 'explanation',
        ]), 50.0, null);
        $this->assertCount(1, $watchFile->getWatchFileActors());
        $watchFile->removeActor($actor);
        $this->assertCount(0, $watchFile->getWatchFileActors());
    }

    public function testAddSourceAndRemoveSource(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'SourceName',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $watchFile->addSource($source);
        $this->assertCount(1, $watchFile->getSources());
        $watchFile->removeSource($source);
        $this->assertCount(0, $watchFile->getSources());
    }

    public function testStateTransitions(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->assertEquals(WatchFileState::NEW, $watchFile->getState());
        $analysis = new AnalysisResult($watchFile);
        $watchFile->analyzeNeeds($analysis);
        $this->assertEquals(WatchFileState::NEEDS_ANALYZED, $watchFile->getState());
        $watchFile->generateQuestions([]);
        $this->assertEquals(WatchFileState::QUESTIONS_GENERATED, $watchFile->getState());
        $watchFile->searchQueryGenerated();
        $this->assertEquals(WatchFileState::SEARCH_QUERY_GENERATED, $watchFile->getState());
        $watchFile->searchResultsRetrieved();
        $this->assertEquals(WatchFileState::SEARCH_RESULTS_RETRIEVED, $watchFile->getState());
        $watchFile->filterUrls();
        $this->assertEquals(WatchFileState::FILTER_URLS, $watchFile->getState());
        $watchFile->temporalFraming();
        $this->assertEquals(WatchFileState::TEMPORAL_FRAMING, $watchFile->getState());
        $watchFile->actorsDetected();
        $this->assertEquals(WatchFileState::ACTORS_DETECTED, $watchFile->getState());
        $watchFile->sourcesDetected();
        $this->assertEquals(WatchFileState::SOURCES_DETECTED, $watchFile->getState());
        $watchFile->detectMonitoringType(MonitoringType::COMPETITIVE);
        $this->assertEquals(WatchFileState::MONITORING_TYPE_DETECTED, $watchFile->getState());
        $watchFile->referenceSubjectDetected();
        $this->assertEquals(WatchFileState::REFERENCE_SUBJECT_DETECTED, $watchFile->getState());
        $watchFile->fail();
        $this->assertEquals(WatchFileState::FAILED, $watchFile->getState());
    }

    public function testInvalidTransitionThrows(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->expectException(\InvalidArgumentException::class);
        $watchFile->generateQuestions([]); // impossible depuis NEW
    }

    public function testGetWatchFileActorsReturnsAlphabeticallySorted(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        // Add actors in non-alphabetical order
        $watchFile->addActor(
            new Actor('Zebra Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Apple Inc', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Microsoft', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        $this->assertEquals(['Apple Inc', 'Microsoft', 'Zebra Corp'], $labels);
    }

    public function testGetWatchFileActorsCaseInsensitive(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFile->addActor(
            new Actor('apple', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('BANANA', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Cherry', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        $this->assertEquals(['apple', 'BANANA', 'Cherry'], $labels);
    }

    public function testGetWatchFileActorsWithAccents(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFile->addActor(
            new Actor('Électricité SA', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Energie Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Alpha', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        $this->assertCount(3, $labels);
        $this->assertEquals('Alpha', $labels[0]);
        $this->assertContains('Électricité SA', $labels);
        $this->assertContains('Energie Corp', $labels);
    }

    public function testGetWatchFileActorsWithNumbers(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFile->addActor(
            new Actor('123 Industries', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('9gag', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Alpha Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('007 Agency', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        // Numbers should be sorted before letters
        $this->assertEquals(['007 Agency', '123 Industries', '9gag', 'Alpha Corp'], $labels);
    }

    public function testGetWatchFileActorsWithSpecialCharacters(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFile->addActor(
            new Actor('A&B Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('A.C.M.E', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('ABC Inc', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('A-Team', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        // Special characters are sorted according to Unicode code points by Collator
        $this->assertCount(4, $labels);
        $this->assertEquals('A-Team', $labels[0]); // Hyphen has lower code point
        $this->assertContains('ABC Inc', $labels); // Should be last (letters after special chars)
        $this->assertContains('A&B Corp', $labels);
        $this->assertContains('A.C.M.E', $labels);
    }

    public function testGetWatchFileActorsWithEmojis(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        $watchFile->addActor(
            new Actor('🔥 Fire Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('⭐ Star Inc', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Alpha', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('🎯 Target Co', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        // Emojis are sorted according to their Unicode code points by Collator
        $this->assertCount(4, $labels);
        $this->assertContains('Alpha', $labels);
        $this->assertContains('🔥 Fire Corp', $labels);
        $this->assertContains('🎯 Target Co', $labels);
        $this->assertContains('⭐ Star Inc', $labels);

        $actors2 = $watchFile->getWatchFileActors()
->toArray();
        $labels2 = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors2);
        $this->assertEquals($labels, $labels2, 'Sorting should be consistent across multiple calls');
    }

    public function testGetWatchFileActorsComplexMixedCase(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));

        // Complex test case with various characters
        $watchFile->addActor(
            new Actor('🚀 SpaceX', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('42 Labs', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('APPLE', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Électricité FR', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('$Cash Corp', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('a-team', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('#Digital', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Ångström AB', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('007 Bond', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('⭐ Premium', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('Zürich Bank', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );
        $watchFile->addActor(
            new Actor('énergies Vertes', new Organisation('Test Org', 'test-org-id')),
            ActorType::COMPETITOR,
            null,
            50.0,
            null
        );

        $actors = $watchFile->getWatchFileActors()
->toArray();
        $labels = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $actors);

        // Verify all actors are present and sorted consistently
        $this->assertCount(12, $labels);
        $expectedActors = [
            '#Digital',
            '⭐ Premium',
            '🚀 SpaceX',
            '$Cash Corp',
            '007 Bond',
            '42 Labs',
            'a-team',
            'Ångström AB',
            'APPLE',
            'Électricité FR',
            'énergies Vertes',
            'Zürich Bank',
        ];

        $this->assertSame($expectedActors, $labels);
    }
}
