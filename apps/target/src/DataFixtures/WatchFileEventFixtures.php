<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Factory\WatchFile\WatchFileEventFactory;
use App\Domain\Actor\Actor;
use App\Domain\Document\ExtractionStatus;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use OpenSearch\Client;

class WatchFileEventFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly Client $openSearchClient,
    ) {
    }

    public function getDependencies(): array
    {
        return [OpenSearchSetupFixture::class, WatchFileFixtures::class, ActorFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(54321);

        $watchFiles = $this->getWatchFiles($manager);
        $actors = $this->getActors($manager);
        $events = [];

        foreach ($watchFiles as $watchFile) {
            for ($i = 0; $i < 20; ++$i) {
                $event = WatchFileEventFactory::create($watchFile, $actors);

                $events[] = $this->normalizeEvent($event);
            }
        }

        $this->indexEventsToOpenSearch($events);

        echo \sprintf("Generated %d watchfile events and indexed to OpenSearch.\n", \count($events));
    }

    /**
     * @return array<int, WatchFile>
     */
    private function getWatchFiles(ObjectManager $manager): array
    {
        $watchFileRepository = $manager->getRepository(WatchFile::class);

        return $watchFileRepository->findAll();
    }

    /**
     * @return array<int, Actor>
     */
    private function getActors(ObjectManager $manager): array
    {
        $actorRepository = $manager->getRepository(Actor::class);

        return $actorRepository->findAll();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function indexEventsToOpenSearch(array $events): void
    {
        $indexName = WatchFileEvent::INDEX_NAME;
        $totalEvents = \count($events);

        $batchSize = max(1, (int) ceil($totalEvents / 3));
        $batches = array_chunk($events, $batchSize);

        $totalUploadedCount = 0;

        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            echo \sprintf(
                "Processing batch %d/%d with %d events...\n",
                $batchNumber,
                \count($batches),
                \count($batch)
            );

            $bulkBody = [];

            foreach ($batch as $event) {
                $eventId = \is_string($event['id']) ? $event['id'] : 'unknown';

                $bulkBody[] = [
                    'index' => [
                        '_index' => $indexName,
                        '_id' => $eventId,
                    ],
                ];

                $bulkBody[] = $event;
            }

            $responseArray = $this->openSearchClient->bulk([
                'body' => $bulkBody,
            ]);

            if (isset($responseArray['items'])) {
                $uploadedCount = \count($responseArray['items']);
                $totalUploadedCount += $uploadedCount;
                echo \sprintf(
                    "Batch %d: Successfully uploaded %d events to OpenSearch.\n",
                    $batchNumber,
                    $uploadedCount
                );
            }

            if (isset($responseArray['errors']) && $responseArray['errors']) {
                echo "Errors occurred during bulk upload:\n";
                foreach ($responseArray['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        echo \sprintf(
                            "  - Event ID %s: %s\n",
                            $item['index']['_id'],
                            $item['index']['error']['reason']
                        );
                    }
                }
            }
        }

        echo \sprintf("Total: Uploaded %d events to OpenSearch.\n", $totalUploadedCount);
    }

    /**
     * @param array{
     *     id: string,
     *     title: array{fr: string, en: string}|null,
     *     startDate: \DateTimeImmutable,
     *     endDate: \DateTimeImmutable,
     *     description: array{fr: string, en: string},
     *     eventType: string,
     *     actors: array<int, array{id: string, name: string, role: string}>,
     *     documentLinks: array<int, array{id: string, text_extract: string}>,
     *     extractionStatus: ExtractionStatus,
     *     createdAt: \DateTimeImmutable,
     *     watchFile: array{id: string, name: string},
     * } $event
     *
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $event): array
    {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'startDate' => $event['startDate']->format('Y-m-d\TH:i:s'),
            'endDate' => $event['endDate']->format('Y-m-d\TH:i:s'),
            'description' => $event['description'],
            'eventType' => $event['eventType'],
            'actors' => $event['actors'],
            'documentLinks' => $event['documentLinks'],
            'extractionStatus' => $event['extractionStatus']->value,
            'createdAt' => $event['createdAt']->format('Y-m-d\TH:i:s'),
            'watchFile' => $event['watchFile'],
        ];
    }
}
