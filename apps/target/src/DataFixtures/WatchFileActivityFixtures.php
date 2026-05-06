<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Actor\ActorType;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WatchFileActivityFixtures extends Fixture implements DependentFixtureInterface
{
    public const WATCHFILE_ACTIVITY_REFERENCE = 'watch_file_activity_';

    public function getDependencies(): array
    {
        return [WatchFileFixtures::class, UserFixtures::class, SourceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $basilUser = $this->getReference(UserFixtures::BASIL_USER_REFERENCE, User::class);
        $activityIndex = 0;

        // Get all watchfiles
        $watchFiles = [];
        for ($i = 0; $i < 5; ++$i) {
            /** @var WatchFile $watchFile */
            $watchFile = $this->getReference(WatchFileFixtures::WATCHFILE_REFERENCE . $i, WatchFile::class);
            $watchFiles[] = $watchFile;
        }

        // Get all sources
        $sources = [];
        for ($i = 0; $i < 10; ++$i) {
            try {
                /** @var Source $source */
                $source = $this->getReference(SourceFixtures::SOURCE_REFERENCE . $i, Source::class);
                $sources[] = $source;
            } catch (\Exception $e) {
                // Source might not exist, continue
                continue;
            }
        }

        foreach ($watchFiles as $watchFileIndex => $watchFile) {
            // 1. Create activity for watchfile creation
            $createdAt = new \DateTime();
            $createdAt->modify('-' . (30 - $watchFileIndex * 5) . ' days');

            $creationActivity = new WatchFileActivity(
                $watchFile,
                $basilUser,
                WatchFileActivityActionType::CREATED,
                [
                    'watch_file_name' => $watchFile->getName(),
                ],
                $watchFile->getOrganisation(),
            );

            // Set custom creation date
            $reflection = new \ReflectionClass($creationActivity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($creationActivity, $createdAt);

            $manager->persist($creationActivity);
            $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $creationActivity);

            // 2. Add update activities (name changes, objective updates, etc.)
            $updateActivities = [
                [
                    'changes' => [
                        'name' => [
                            'old' => $watchFile->getName(),
                            'new' => $watchFile->getName() . ' (Updated)',
                        ],
                    ],
                    'days_ago' => 25 - $watchFileIndex * 3,
                ],
                [
                    'changes' => [
                        'user_objective' => [
                            'old' => $watchFile->getUserObjective(),
                            'new' => $watchFile->getUserObjective() . ' - Enhanced objective with additional focus areas.',
                        ],
                    ],
                    'days_ago' => 20 - $watchFileIndex * 2,
                ],
                [
                    'changes' => [
                        'query' => [
                            'old' => null,
                            'new' => 'enhanced search query for ' . strtolower($watchFile->getName()),
                        ],
                    ],
                    'days_ago' => 15 - $watchFileIndex,
                ],
            ];

            foreach ($updateActivities as $updateData) {
                $updateAt = new \DateTime();
                $updateAt->modify('-' . $updateData['days_ago'] . ' days');

                $updateActivity = new WatchFileActivity(
                    $watchFile,
                    $basilUser,
                    WatchFileActivityActionType::UPDATED,
                    $updateData['changes'],
                    $watchFile->getOrganisation(),
                );

                // Set custom creation date
                $reflection = new \ReflectionClass($updateActivity);
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($updateActivity, $updateAt);

                $manager->persist($updateActivity);
                $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $updateActivity);
            }

            // 3. Add status change activities
            $statusChanges = [
                [
                    'old_status' => WatchFileStatus::DRAFT,
                    'new_status' => WatchFileStatus::ENABLED,
                    'days_ago' => 18 - $watchFileIndex * 2,
                ],
                [
                    'old_status' => WatchFileStatus::ENABLED,
                    'new_status' => WatchFileStatus::DRAFT,
                    'days_ago' => 12 - $watchFileIndex,
                ],
                [
                    'old_status' => WatchFileStatus::DRAFT,
                    'new_status' => WatchFileStatus::ENABLED,
                    'days_ago' => 8 - $watchFileIndex,
                ],
            ];

            foreach ($statusChanges as $statusData) {
                $statusAt = new \DateTime();
                $statusAt->modify('-' . $statusData['days_ago'] . ' days');

                $statusActivity = new WatchFileActivity(
                    $watchFile,
                    $basilUser,
                    WatchFileActivityActionType::STATUS_CHANGED,
                    [
                        'old_status' => $statusData['old_status']->value,
                        'new_status' => $statusData['new_status']->value,
                    ],
                    $watchFile->getOrganisation(),
                );

                // Set custom creation date
                $reflection = new \ReflectionClass($statusActivity);
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($statusActivity, $statusAt);

                $manager->persist($statusActivity);
                $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $statusActivity);
            }

            // 4. Add source status change activities for sources belonging to this watchfile
            foreach ($sources as $sourceIndex => $source) {
                if ($source->getWatchFile()->getId() === $watchFile->getId()) {
                    $sourceStatusChanges = [
                        [
                            'status' => SourceStatus::ACTIVE,
                            'days_ago' => 22 - $watchFileIndex * 2 - $sourceIndex,
                        ],
                        [
                            'status' => SourceStatus::INACTIVE,
                            'days_ago' => 16 - $watchFileIndex - $sourceIndex,
                        ],
                        [
                            'status' => SourceStatus::ACTIVE,
                            'days_ago' => 10 - $sourceIndex,
                        ],
                    ];

                    foreach ($sourceStatusChanges as $sourceStatusData) {
                        $sourceStatusAt = new \DateTime();
                        $sourceStatusAt->modify('-' . $sourceStatusData['days_ago'] . ' days');

                        $sourceStatusActivity = new WatchFileActivity(
                            $watchFile,
                            $basilUser,
                            WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
                            [
                                'source_name' => $source->getName(),
                                'source_id' => $source->getId(),
                                'source_type' => $source->getType()
->value,
                                'source_url' => $source->getUrl(),
                                'status' => $sourceStatusData['status']->value,
                            ],
                            $watchFile->getOrganisation(),
                        );

                        // Set custom creation date
                        $reflection = new \ReflectionClass($sourceStatusActivity);
                        $createdAtProperty = $reflection->getProperty('createdAt');
                        $createdAtProperty->setAccessible(true);
                        $createdAtProperty->setValue($sourceStatusActivity, $sourceStatusAt);

                        $manager->persist($sourceStatusActivity);
                        $this->addReference(
                            self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++,
                            $sourceStatusActivity
                        );
                    }
                }
            }

            // 5. Add actor added activities for actors in this watchfile
            $watchFileActors = $watchFile->getWatchFileActors();
            foreach ($watchFileActors as $actorIndex => $watchFileActor) {
                $actorAddedAt = new \DateTime();
                $actorAddedAt->modify('-' . (24 - $watchFileIndex * 3 - $actorIndex) . ' days');

                $actorAddedActivity = new WatchFileActivity(
                    $watchFile,
                    $basilUser,
                    WatchFileActivityActionType::ACTOR_ADDED,
                    [
                        'actor_name' => $watchFileActor->getActor()
->getLabel(),
                        'actor_id' => $watchFileActor->getActor()
->getId(),
                        'actor_type' => $watchFileActor->getType(),
                        'explanation' => $watchFileActor->getExplanations(),
                        'score' => $watchFileActor->getScore(),
                    ],
                    $watchFile->getOrganisation(),
                );

                // Set custom creation date
                $reflection = new \ReflectionClass($actorAddedActivity);
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($actorAddedActivity, $actorAddedAt);

                $manager->persist($actorAddedActivity);
                $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $actorAddedActivity);
            }

            // 6. Add source added activities for sources in this watchfile
            foreach ($sources as $sourceIndex => $source) {
                if ($source->getWatchFile()->getId() === $watchFile->getId()) {
                    $sourceAddedAt = new \DateTime();
                    $sourceAddedAt->modify('-' . (23 - $watchFileIndex * 3 - $sourceIndex) . ' days');

                    $sourceAddedActivity = new WatchFileActivity(
                        $watchFile,
                        $basilUser,
                        WatchFileActivityActionType::SOURCE_ADDED,
                        [
                            'source_name' => $source->getName(),
                            'source_id' => $source->getId(),
                            'source_type' => $source->getType()
->value,
                            'source_url' => $source->getUrl(),
                            'primary_domain' => $source->getPrimaryDomain(),
                            'actor_name' => $source->getActor()?->getLabel(),
                            'actor_id' => $source->getActor()?->getId(),
                        ],
                        $watchFile->getOrganisation(),
                    );

                    // Set custom creation date
                    $reflection = new \ReflectionClass($sourceAddedActivity);
                    $createdAtProperty = $reflection->getProperty('createdAt');
                    $createdAtProperty->setAccessible(true);
                    $createdAtProperty->setValue($sourceAddedActivity, $sourceAddedAt);

                    $manager->persist($sourceAddedActivity);
                    $this->addReference(
                        self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++,
                        $sourceAddedActivity
                    );
                }
            }
        }

        // Add specific activities for Pharmacy & Cosmetics watchfile (index 4)
        $pharmacyWatchFile = $watchFiles[4];
        $this->createPharmacyCosmeticsActivities($pharmacyWatchFile, $basilUser, $manager, $activityIndex);

        $manager->flush();
    }

    private function createPharmacyCosmeticsActivities(
        WatchFile $watchFile,
        User $user,
        ObjectManager $manager,
        int &$activityIndex,
    ): void {
        // 1. Watch file creation activity
        $createdAt = new \DateTime();
        $createdAt->modify('-1 day');

        $creationActivity = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => $watchFile->getName(),
            ],
            $watchFile->getOrganisation(),
        );

        $reflection = new \ReflectionClass($creationActivity);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($creationActivity, $createdAt);

        $manager->persist($creationActivity);
        $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $creationActivity);

        // 2. Reference subject update activity
        $updateAt = new \DateTime();
        $updateAt->modify('-23 hours');

        $updateActivity = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::UPDATED,
            [
                'referenceSubject' => [
                    'old' => null,
                    'new' => $watchFile->getReferenceSubject(),
                ],
            ],
            $watchFile->getOrganisation(),
        );

        $reflection = new \ReflectionClass($updateActivity);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($updateActivity, $updateAt);

        $manager->persist($updateActivity);
        $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $updateActivity);

        // 3. Actor addition activities
        $pharmacyActors = [
            [
                'actor_id' => '1f09497c-d2c3-6526-98c6-873f56cb3406',
                'actor_name' => 'Servier',
                'actor_type' => ActorType::COMPETITOR,
                'explanation' => [
                    'fr' => 'Servier est un groupe pharmaceutique international souvent mis en avant dans les tableaux de réputation employeur.',
                    'en' => 'Servier is an international pharmaceutical group often highlighted in employer reputation tables.',
                ],
                'score' => 0.88,
                'primary_domain' => 'servier.com',
                'minutes_ago' => 22,
            ],
            [
                'actor_id' => '1f09497c-d40f-68f8-86d9-873f56cb3406',
                'actor_name' => 'Sanofi',
                'actor_type' => ActorType::COMPETITOR,
                'explanation' => [
                    'fr' => 'Sanofi est un groupe pharmaceutique mondialement présent, régulièrement reconnu dans les classements employeurs du secteur santé.',
                    'en' => 'Sanofi is a pharmaceutical group operating globally and regularly recognized in rankings as an employer in the healthcare sector.',
                ],
                'score' => 0.98,
                'primary_domain' => 'sanofi.com',
                'minutes_ago' => 21,
            ],
            [
                'actor_id' => '1f09497c-d4ed-6f90-a61e-873f56cb3406',
                'actor_name' => 'Bio Mérieux',
                'actor_type' => ActorType::COMPETITOR,
                'explanation' => [
                    'fr' => 'Bio Mérieux, spécialisé dans le diagnostic in vitro, est souvent cité dans les études de réputation employeur.',
                    'en' => 'Bio Mérieux specializes in in vitro diagnostics and is visible in employer reputation studies.',
                ],
                'score' => 0.90,
                'primary_domain' => 'biomerieux.com',
                'minutes_ago' => 20,
            ],
            [
                'actor_id' => '1f09497c-d5c6-6ff2-b2cb-873f56cb3406',
                'actor_name' => 'Groupe Rocher',
                'actor_type' => ActorType::COMPETITOR,
                'explanation' => [
                    'fr' => 'Groupe Rocher est un acteur important de la cosmétique, fréquemment reconnu dans les classements européens de labels employeurs.',
                    'en' => 'Groupe Rocher is a major player in the cosmetics industry with frequent recognition in European employer label rankings.',
                ],
                'score' => 0.95,
                'primary_domain' => 'groupe-rocher.com',
                'minutes_ago' => 19,
            ],
            [
                'actor_id' => '1f09497c-d69f-6d3e-8399-873f56cb3406',
                'actor_name' => 'SVR',
                'actor_type' => ActorType::COMPETITOR,
                'explanation' => [
                    'fr' => 'SVR est une marque cosmétique pharmaceutique souvent citée dans les études européennes de labels employeurs.',
                    'en' => 'SVR is a notable pharmaceutical cosmetic brand often cited in European employer label studies.',
                ],
                'score' => 0.85,
                'primary_domain' => 'laboratoiresvr.com',
                'minutes_ago' => 18,
            ],
        ];

        foreach ($pharmacyActors as $actorData) {
            $actorAt = new \DateTime();
            $actorAt->modify('-' . $actorData['minutes_ago'] . ' minutes');

            $actorActivity = new WatchFileActivity(
                $watchFile,
                $user,
                WatchFileActivityActionType::ACTOR_ADDED,
                $actorData,
                $watchFile->getOrganisation(),
            );

            $reflection = new \ReflectionClass($actorActivity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($actorActivity, $actorAt);

            $manager->persist($actorActivity);
            $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $actorActivity);
        }

        // 4. Source status change activities
        $sourceStatusChanges = [
            [
                'source_name' => 'Pharmaceutiques RSS',
                'source_id' => '1f09497e-cb58-67a8-9f3d-873f56cb3406',
                'source_type' => 'rss_feed',
                'source_url' => 'https://www.pharmaceutiques.com/feed',
                'status' => 'inactive',
                'old_status' => 'active',
                'minutes_ago' => 15,
            ],
            [
                'source_name' => 'Premium Beauty News RSS',
                'source_id' => '1f09497e-ca2b-67ea-9a3d-873f56cb3406',
                'source_type' => 'rss_feed',
                'source_url' => 'https://www.premiumbeautynews.com/rss.xml',
                'status' => 'inactive',
                'old_status' => 'active',
                'minutes_ago' => 14,
            ],
            [
                'source_name' => 'Top Employers Institute RSS',
                'source_id' => '1f09497e-cda2-622a-8590-873f56cb3406',
                'source_type' => 'rss_feed',
                'source_url' => 'https://www.top-employers.com/en/feed/rss/',
                'status' => 'inactive',
                'old_status' => 'active',
                'minutes_ago' => 13,
            ],
        ];

        foreach ($sourceStatusChanges as $sourceData) {
            $sourceAt = new \DateTime();
            $sourceAt->modify('-' . $sourceData['minutes_ago'] . ' minutes');

            $sourceActivity = new WatchFileActivity(
                $watchFile,
                $user,
                WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
                $sourceData,
                $watchFile->getOrganisation(),
            );

            $reflection = new \ReflectionClass($sourceActivity);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($sourceActivity, $sourceAt);

            $manager->persist($sourceActivity);
            $this->addReference(self::WATCHFILE_ACTIVITY_REFERENCE . $activityIndex++, $sourceActivity);
        }
    }
}
