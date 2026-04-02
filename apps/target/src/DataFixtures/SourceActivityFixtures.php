<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Source\Source;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SourceActivityFixtures extends Fixture implements DependentFixtureInterface
{
    public const SOURCE_ACTIVITY_REFERENCE = 'source_activity_';

    public function getDependencies(): array
    {
        return [WatchFileFixtures::class, UserFixtures::class, SourceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $basilUser = $this->getReference(UserFixtures::BASIL_USER_REFERENCE, User::class);
        $activityIndex = 0;

        $sourceRepository = $manager->getRepository(Source::class);
        $sources = $sourceRepository->findAll();

        // Separate pharmacy & cosmetics sources from others
        $pharmacyCosmeticsSources = [];
        $otherSources = [];

        foreach ($sources as $source) {
            $pharmacySourceNames = [
                'Pharmaceutiques RSS',
                'Top Employers Institute RSS',
                'Premium Beauty News RSS',
                'Le Monde – Toute l\'actualité RSS',
                'Forbes \'Meilleurs Employeurs\' RSS',
            ];

            if (\in_array($source->getName(), $pharmacySourceNames)) {
                $pharmacyCosmeticsSources[] = $source;
            } else {
                $otherSources[] = $source;
            }
        }

        // Process other sources with standard method
        foreach ($otherSources as $sourceIndex => $source) {
            $this->createActivitiesForSource($source, $basilUser, $sourceIndex, $manager, $activityIndex);
        }

        // Process pharmacy & cosmetics sources with specialized method
        if (!empty($pharmacyCosmeticsSources)) {
            $this->createPharmacyCosmeticsSourceActivities(
                $pharmacyCosmeticsSources,
                $basilUser,
                $manager,
                $activityIndex
            );
        }

        $manager->flush();
    }

    private function createActivitiesForSource(
        Source $source,
        User $user,
        int $sourceIndex,
        ObjectManager $manager,
        int &$activityIndex,
    ): void {
        // Define activities for each source with realistic scenarios
        $activities = $this->generateSourceActivities($source, $sourceIndex);

        foreach ($activities as $activityData) {
            /** @var SourceActivityActionType $actionType */
            $actionType = $activityData['actionType'];
            /** @var array<mixed> $actionData */
            $actionData = $activityData['actionData'];

            $activity = new SourceActivity($source, $user, $actionType, $actionData);

            // Set custom createdAt using reflection
            $reflection = new \ReflectionClass($activity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($activity, $activityData['createdAt']);

            $manager->persist($activity);
            $this->addReference(self::SOURCE_ACTIVITY_REFERENCE . $activityIndex++, $activity);
        }
    }

    /**
     * @return array<string, mixed>[]
     */
    private function generateSourceActivities(Source $source, int $sourceIndex): array
    {
        $activities = [];
        $baseDate = new \DateTime();
        $baseDate->modify('-' . (5 - $sourceIndex) . ' days');

        // Determine number of days (2 or 3)
        $numberOfDays = ($sourceIndex % 2) + 2; // 2 or 3 days

        // Start from the oldest day and work forward chronologically
        $startDay = clone $baseDate;
        $startDay->modify('-' . ($numberOfDays - 1) . ' days');
        $startDay->setTime(8, 0);

        // Day 1 (oldest) - Always SOURCE_ADDED_TO_WATCHFILE and SOURCE_CONNECTED
        $day1 = clone $startDay;

        // 1. SOURCE_ADDED_TO_WATCHFILE - Always first
        $addAt = clone $day1;
        $addAt->modify('+30 minutes');
        $activities[] = [
            'actionType' => SourceActivityActionType::SOURCE_ADDED_TO_WATCHFILE,
            'actionData' => [
                'source_name' => $source->getName(),
                'watch_file_name' => 'Watch File ' . ($sourceIndex + 1),
                'added_by' => 'user',
            ],
            'createdAt' => $addAt,
        ];

        // 2. SOURCE_CONNECTED - Always second
        $connectedAt = clone $day1;
        $connectedAt->setTime(9, 0);
        $activities[] = [
            'actionType' => SourceActivityActionType::SOURCE_CONNECTED,
            'actionData' => [
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'source_type' => $source->getType()
->value,
                'source_url' => $source->getUrl(),
                'connection_method' => 'manual',
            ],
            'createdAt' => $connectedAt,
        ];

        // 3. SOURCE_CONFIG_UPDATED - 60% chance
        if (($sourceIndex % 5) < 3) {
            $configAt = clone $day1;
            $configAt->setTime(11, ($sourceIndex * 7) % 60);

            $activities[] = [
                'actionType' => SourceActivityActionType::SOURCE_CONFIG_UPDATED,
                'actionData' => [
                    'changed_field' => 'query',
                    'old_value' => null,
                    'new_value' => 'enhanced search query for ' . strtolower($source->getName()),
                    'change_reason' => 'user_optimization',
                ],
                'createdAt' => $configAt,
            ];
        }

        // Day 2 - SOURCE_ERROR and SOURCE_RECOVERED (if error)
        $day2 = clone $startDay;
        $day2->modify('+1 day');
        $hasError = ($sourceIndex % 5) < 2; // 40% chance

        if ($hasError) {
            // SOURCE_ERROR
            $errorAt = clone $day2;
            $errorAt->setTime(14, ($sourceIndex * 11) % 60);

            $activities[] = [
                'actionType' => SourceActivityActionType::SOURCE_ERROR,
                'actionData' => [
                    'error_type' => 'connection_timeout',
                    'error_message' => 'Connection to ' . $source->getUrl() . ' timed out after 30 seconds',
                    'retry_count' => 3,
                    'last_attempt' => $errorAt->format('Y-m-d H:i:s'),
                ],
                'createdAt' => $errorAt,
            ];

            // SOURCE_RECOVERED - Always after SOURCE_ERROR
            $recoveryAt = clone $day2;
            $recoveryAt->setTime(15, 30 + (($sourceIndex * 13) % 30)); // 30-59 minutes

            $activities[] = [
                'actionType' => SourceActivityActionType::SOURCE_RECOVERED,
                'actionData' => [
                    'source_name' => $source->getName(),
                    'recovery_method' => 'automatic_retry',
                    'downtime_minutes' => 30 + (($sourceIndex * 17) % 90), // 30-119 minutes
                ],
                'createdAt' => $recoveryAt,
            ];
        } else {
            // If no error, add SOURCE_CONFIG_UPDATED to avoid empty day
            $configAt = clone $day2;
            $configAt->setTime(10, ($sourceIndex * 19) % 60);

            $activities[] = [
                'actionType' => SourceActivityActionType::SOURCE_CONFIG_UPDATED,
                'actionData' => [
                    'changed_field' => 'refresh_interval',
                    'old_value' => '300',
                    'new_value' => '180',
                    'change_reason' => 'performance_optimization',
                ],
                'createdAt' => $configAt,
            ];
        }

        // Day 3 (if 3 days) - Additional SOURCE_CONFIG_UPDATED events
        if (3 === $numberOfDays) {
            $day3 = clone $startDay;
            $day3->modify('+2 days');

            // Add SOURCE_CONFIG_UPDATED on day 3
            $configAt = clone $day3;
            $configAt->setTime(13, ($sourceIndex * 31) % 60);

            $activities[] = [
                'actionType' => SourceActivityActionType::SOURCE_CONFIG_UPDATED,
                'actionData' => [
                    'changed_field' => 'timeout',
                    'old_value' => '30',
                    'new_value' => '45',
                    'change_reason' => 'stability_improvement',
                ],
                'createdAt' => $configAt,
            ];
        }

        // Sort activities by date (newest first)
        usort($activities, function ($a, $b) {
            return $b['createdAt'] <=> $a['createdAt'];
        });

        return $activities;
    }

    /**
     * @param list<Source> $sources
     */
    private function createPharmacyCosmeticsSourceActivities(
        array $sources,
        User $user,
        ObjectManager $manager,
        int &$activityIndex,
    ): void {
        // Find Pharmacy & Cosmetics sources by name
        $pharmacySources = array_filter($sources, function ($source) {
            $pharmacySourceNames = [
                'Pharmaceutiques RSS',
                'Top Employers Institute RSS',
                'Premium Beauty News RSS',
                'Le Monde – Toute l\'actualité RSS',
                'Forbes \'Meilleurs Employeurs\' RSS',
            ];

            return \in_array($source->getName(), $pharmacySourceNames);
        });

        foreach ($pharmacySources as $sourceIndex => $source) {
            // 1. Source connection activity
            $connectedAt = new \DateTime();
            $connectedAt->modify('-1 day');

            $connectionActivity = new SourceActivity(
                $source,
                $user,
                SourceActivityActionType::SOURCE_CONNECTED,
                [
                    'source_id' => $source->getId(),
                    'source_name' => $source->getName(),
                    'source_type' => $source->getType()
->value,
                    'source_url' => $source->getUrl(),
                    'connection_method' => 'manual',
                ]
            );

            $reflection = new \ReflectionClass($connectionActivity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($connectionActivity, $connectedAt);

            $manager->persist($connectionActivity);
            $this->addReference(self::SOURCE_ACTIVITY_REFERENCE . $activityIndex++, $connectionActivity);

            // 2. Data retrieval activities
            $dataRetrievalActivities = [
                [
                    'data' => [
                        'retrieved_items' => 25 + $sourceIndex * 5,
                        'retrieval_method' => 'rss_feed',
                        'success_rate' => 98.0 + $sourceIndex * 0.2,
                        'processing_time_ms' => 800 + $sourceIndex * 50,
                        'keywords_matched' => ['employer', 'label', 'ranking', 'reputation'],
                    ],
                    'hours_ago' => 20 - $sourceIndex * 2,
                ],
                [
                    'data' => [
                        'retrieved_items' => 18 + $sourceIndex * 3,
                        'retrieval_method' => 'rss_feed',
                        'success_rate' => 96.5 + $sourceIndex * 0.3,
                        'processing_time_ms' => 900 + $sourceIndex * 75,
                        'keywords_matched' => ['pharma', 'cosmetics', 'employer', 'award'],
                    ],
                    'hours_ago' => 12 - $sourceIndex,
                ],
            ];

            foreach ($dataRetrievalActivities as $retrievalData) {
                $retrievalAt = new \DateTime();
                $retrievalAt->modify('-' . $retrievalData['hours_ago'] . ' hours');

                $retrievalActivity = new SourceActivity(
                    $source,
                    $user,
                    SourceActivityActionType::SOURCE_DATA_RETRIEVED,
                    $retrievalData['data']
                );

                $reflection = new \ReflectionClass($retrievalActivity);
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($retrievalActivity, $retrievalAt);

                $manager->persist($retrievalActivity);
                $this->addReference(self::SOURCE_ACTIVITY_REFERENCE . $activityIndex++, $retrievalActivity);
            }

            // 3. Configuration update activities
            $configActivities = [
                [
                    'config_data' => [
                        'changed_field' => 'query',
                        'old_value' => null,
                        'new_value' => $source->getQuery(),
                        'change_reason' => 'pharmacy_cosmetics_optimization',
                    ],
                    'hours_ago' => 15 - $sourceIndex,
                ],
                [
                    'config_data' => [
                        'changed_field' => 'status',
                        'old_value' => 'active',
                        'new_value' => 'inactive',
                        'change_reason' => 'temporary_maintenance',
                    ],
                    'hours_ago' => 5 - $sourceIndex,
                ],
            ];

            foreach ($configActivities as $configData) {
                $configAt = new \DateTime();
                $configAt->modify('-' . $configData['hours_ago'] . ' hours');

                $configActivity = new SourceActivity(
                    $source,
                    $user,
                    SourceActivityActionType::SOURCE_CONFIG_UPDATED,
                    $configData['config_data']
                );

                $reflection = new \ReflectionClass($configActivity);
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($configActivity, $configAt);

                $manager->persist($configActivity);
                $this->addReference(self::SOURCE_ACTIVITY_REFERENCE . $activityIndex++, $configActivity);
            }

            // 4. Error activities (for some sources)
            if (0 === $sourceIndex % 2) {
                $errorActivities = [
                    [
                        'error_data' => [
                            'error_type' => 'rss_parse_error',
                            'error_message' => 'Invalid RSS format detected in ' . $source->getName(),
                            'retry_count' => 2,
                            'last_attempt' => new \DateTime()
->modify('-3 hours')
->format('Y-m-d H:i:s'),
                        ],
                        'hours_ago' => 3,
                    ],
                ];

                foreach ($errorActivities as $errorData) {
                    $errorAt = new \DateTime();
                    $errorAt->modify('-' . $errorData['hours_ago'] . ' hours');

                    $errorActivity = new SourceActivity(
                        $source,
                        $user,
                        SourceActivityActionType::SOURCE_ERROR,
                        $errorData['error_data']
                    );

                    $reflection = new \ReflectionClass($errorActivity);
                    $createdAtProperty = $reflection->getProperty('createdAt');
                    $createdAtProperty->setAccessible(true);
                    $createdAtProperty->setValue($errorActivity, $errorAt);

                    $manager->persist($errorActivity);
                    $this->addReference(self::SOURCE_ACTIVITY_REFERENCE . $activityIndex++, $errorActivity);
                }
            }
        }
    }
}
