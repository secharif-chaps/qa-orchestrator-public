<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Document\ExtractionStatus;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Faker\Factory;
use Faker\Generator;

class WatchFileEventFactory
{
    private static ?Generator $faker = null;

    private static function getFaker(): Generator
    {
        if (null === self::$faker) {
            self::$faker = Factory::create();
        }

        return self::$faker;
    }

    /**
     * @param WatchFile|string                                   $watchFile WatchFile object or watch file ID
     * @param array<int, Actor|array{id: string, name?: string}> $actors
     *
     * @return array{
     *     id: string,
     *     title: array{fr: string, en: string},
     *     startDate: \DateTimeImmutable,
     *     endDate: \DateTimeImmutable,
     *     description: array{fr: string, en: string},
     *     eventType: string,
     *     actors: array<int, array{id: string, name: string, role: string}>,
     *     documentLinks: array<int, array{id: string, text_extract: string}>,
     *     extractionStatus: ExtractionStatus,
     *     createdAt: \DateTimeImmutable,
     *     watchFile: array{id: string, name: string},
     * }
     */
    public static function create(WatchFile|string $watchFile, array $actors = []): array
    {
        $faker = self::getFaker();

        $eventType = self::generateEventType($faker);
        $startDate = $faker->dateTimeBetween('-6 months', 'now');
        $endDate = $faker->boolean(30) ? $faker->dateTimeBetween($startDate, '+2 weeks') : $startDate;

        $extractionStatuses = [
            ExtractionStatus::COMPLETED,
            ExtractionStatus::COMPLETED,
            ExtractionStatus::COMPLETED,
            ExtractionStatus::COMPLETED, // 80% completed
            ExtractionStatus::PENDING, // 10% pending
            ExtractionStatus::FAILED, // 10% failed
        ];

        // Extract watch file ID and name
        if ($watchFile instanceof WatchFile) {
            $watchFileId = $watchFile->getId();
            $watchFileName = $watchFile->getName();
        } else {
            $watchFileId = $watchFile;
            $watchFileName = $faker->company();
        }

        $description = self::generateDescription($eventType, $faker);
        $generatedActors = !empty($actors)
            ? self::generateActors($watchFileId, $actors, $faker)
            : self::generateActorsForWatchFile($watchFileId, $faker);

        return [
            'id' => $faker->uuid(),
            'title' => self::generateTitle($eventType, $generatedActors, $faker),
            'startDate' => \DateTimeImmutable::createFromInterface($startDate),
            'endDate' => \DateTimeImmutable::createFromInterface($endDate),
            'description' => $description,
            'eventType' => $eventType,
            'actors' => $generatedActors,
            'documentLinks' => self::generateDocumentLinks($faker),
            'extractionStatus' => $extractionStatuses[array_rand($extractionStatuses)],
            'createdAt' => \DateTimeImmutable::createFromInterface($faker->dateTimeBetween($startDate, 'now')),
            'watchFile' => [
                'id' => $watchFileId,
                'name' => $watchFileName,
            ],
        ];
    }

    private static function generateEventType(Generator $faker): string
    {
        $eventTypes = array_map(function (EventType $type): string {return $type->value; }, EventType::cases());

        return $eventTypes[array_rand($eventTypes)];
    }

    /**
     * Generate a short and explicit event title in both French and English (max 100 characters each).
     *
     * @param array<int, array{id: string, name: string, role: string}> $actors
     *
     * @return array{fr: string, en: string}
     */
    private static function generateTitle(string $eventType, array $actors, Generator $faker): array
    {
        // If no actors, generate a generic title based on event type
        if (empty($actors)) {
            return match ($eventType) {
                'commercial_business' => [
                    'fr' => 'Événement commercial',
                    'en' => 'Commercial event',
                ],
                'financial' => [
                    'fr' => 'Événement financier',
                    'en' => 'Financial event',
                ],
                'organizational_hr' => [
                    'fr' => 'Changement organisationnel',
                    'en' => 'Organizational change',
                ],
                'technological_rd' => [
                    'fr' => 'Initiative R&D',
                    'en' => 'R&D initiative',
                ],
                'regulatory_political' => [
                    'fr' => 'Mise à jour réglementaire',
                    'en' => 'Regulatory update',
                ],
                'market_competitors' => [
                    'fr' => 'Développement du marché',
                    'en' => 'Market development',
                ],
                'societal_environmental' => [
                    'fr' => 'Initiative RSE',
                    'en' => 'CSR initiative',
                ],
                default => [
                    'fr' => 'Événement',
                    'en' => 'Event',
                ],
            };
        }

        $actorNames = array_map(fn (array $actor): string => $actor['name'], $actors);
        $primaryActor = $actorNames[0];
        $secondaryActor = $actorNames[1] ?? null;

        $titles = match ($eventType) {
            'commercial_business' => $secondaryActor
                ? [
                    'fr' => \sprintf('Partenariat entre %s et %s', $primaryActor, $secondaryActor),
                    'en' => \sprintf('Partnership between %s and %s', $primaryActor, $secondaryActor),
                ]
                : [
                    'fr' => \sprintf('Partenariat commercial avec %s', $primaryActor),
                    'en' => \sprintf('Business partnership with %s', $primaryActor),
                ],
            'financial' => $secondaryActor
                ? [
                    'fr' => \sprintf('Acquisition de %s par %s', $secondaryActor, $primaryActor),
                    'en' => \sprintf('Acquisition of %s by %s', $secondaryActor, $primaryActor),
                ]
                : [
                    'fr' => \sprintf('Événement financier impliquant %s', $primaryActor),
                    'en' => \sprintf('Financial event involving %s', $primaryActor),
                ],
            'organizational_hr' => [
                'fr' => \sprintf('Changement organisationnel chez %s', $primaryActor),
                'en' => \sprintf('Organizational change at %s', $primaryActor),
            ],
            'technological_rd' => [
                'fr' => \sprintf('Initiative R&D de %s', $primaryActor),
                'en' => \sprintf('R&D initiative by %s', $primaryActor),
            ],
            'regulatory_political' => [
                'fr' => \sprintf('Mise à jour réglementaire pour %s', $primaryActor),
                'en' => \sprintf('Regulatory update for %s', $primaryActor),
            ],
            'market_competitors' => [
                'fr' => \sprintf('Développement du marché impliquant %s', $primaryActor),
                'en' => \sprintf('Market development involving %s', $primaryActor),
            ],
            'societal_environmental' => [
                'fr' => \sprintf('Initiative RSE de %s', $primaryActor),
                'en' => \sprintf('CSR initiative by %s', $primaryActor),
            ],
            default => [
                'fr' => \sprintf('Événement impliquant %s', $primaryActor),
                'en' => \sprintf('Event involving %s', $primaryActor),
            ],
        };

        // Ensure titles don't exceed MAX_TITLE_LENGTH characters
        foreach ($titles as $lang => $title) {
            if (mb_strlen($title) > WatchFileEvent::MAX_TITLE_LENGTH) {
                $titles[$lang] = mb_substr($title, 0, WatchFileEvent::MAX_TITLE_LENGTH - 3) . '...';
            }
        }

        return $titles;
    }

    /**
     * @return array{fr: string, en: string}
     */
    private static function generateDescription(string $eventType, Generator $faker): array
    {
        $eventContext = match ($eventType) {
            'commercial_business' => [
                'fr' => $faker->randomElement(['lancement', 'signature', 'acquisition', 'fusion', 'partenariat']),
                'en' => $faker->randomElement(['launch', 'signing', 'acquisition', 'merger', 'partnership']),
            ],
            'financial' => [
                'fr' => $faker->randomElement([
                    'levée de fonds',
                    'publication des résultats',
                    'introduction en bourse',
                    'refinancement',
                    'dividendes',
                ]),
                'en' => $faker->randomElement([
                    'funding round',
                    'financial results',
                    'IPO',
                    'refinancing',
                    'dividends',
                ]),
            ],
            'organizational_hr' => [
                'fr' => $faker->randomElement([
                    'nomination',
                    'restructuration',
                    'formation',
                    'recrutement',
                    'réorganisation',
                ]),
                'en' => $faker->randomElement([
                    'appointment',
                    'restructuring',
                    'training',
                    'recruitment',
                    'reorganization',
                ]),
            ],
            'technological_rd' => [
                'fr' => $faker->randomElement([
                    'développement technologique',
                    'innovation',
                    'brevet',
                    'recherche',
                    'investissement R&D',
                ]),
                'en' => $faker->randomElement([
                    'technological development',
                    'innovation',
                    'patent',
                    'research',
                    'R&D investment',
                ]),
            ],
            'regulatory_political' => [
                'fr' => $faker->randomElement([
                    'nouvelle réglementation',
                    'certification',
                    'conformité',
                    'audit réglementaire',
                    'changement législatif',
                ]),
                'en' => $faker->randomElement([
                    'new regulation',
                    'certification',
                    'compliance',
                    'regulatory audit',
                    'legislative change',
                ]),
            ],
            'market_competitors' => [
                'fr' => $faker->randomElement([
                    'entrée sur le marché',
                    'analyse concurrentielle',
                    'guerre des prix',
                    'consolidation',
                    'parts de marché',
                ]),
                'en' => $faker->randomElement([
                    'market entry',
                    'competitive analysis',
                    'price war',
                    'consolidation',
                    'market share',
                ]),
            ],
            'societal_environmental' => [
                'fr' => $faker->randomElement([
                    'engagement environnemental',
                    'initiative RSE',
                    'réduction des émissions',
                    'développement durable',
                    'certification écologique',
                ]),
                'en' => $faker->randomElement([
                    'environmental commitment',
                    'CSR initiative',
                    'emissions reduction',
                    'sustainable development',
                    'ecological certification',
                ]),
            ],
            default => [
                'fr' => 'événement',
                'en' => 'event',
            ],
        };

        /** @var string $frContext */
        $frContext = $eventContext['fr'];
        /** @var string $enContext */
        $enContext = $eventContext['en'];

        $frPrefix = ucfirst($frContext);
        $enPrefix = ucfirst($enContext);

        return [
            'fr' => $frPrefix . ' - ' . $faker->sentence(6),
            'en' => $enPrefix . ' - ' . $faker->sentence(6),
        ];
    }

    /**
     * @return array<int, array{id: string, text_extract: string}>
     */
    private static function generateDocumentLinks(Generator $faker): array
    {
        $numberOfLinks = $faker->numberBetween(1, 3);
        $links = [];

        for ($i = 0; $i < $numberOfLinks; ++$i) {
            $links[] = [
                'id' => $faker->uuid(),
                'text_extract' => $faker->sentence(15),
            ];
        }

        return $links;
    }

    /**
     * @param array<int, Actor|array{id: string, name?: string}> $actors
     *
     * @return array<int, array{id: string, name: string, role: string}>
     */
    private static function generateActors(string $watchFileId, array $actors, Generator $faker): array
    {
        if (empty($actors)) {
            return self::generateActorsForWatchFile($watchFileId, $faker);
        }

        $numberOfActors = $faker->numberBetween(1, 4);
        $result = [];

        $roles = ['acquirer', 'target', 'partner', 'competitor', 'regulator', 'investor', 'customer', 'supplier'];

        for ($i = 0; $i < $numberOfActors; ++$i) {
            $actor = $actors[array_rand($actors)];

            // Handle both Actor objects and array data
            if ($actor instanceof Actor) {
                $actorId = $actor->getId();
                $actorName = $actor->getLabel();
            } else {
                $actorId = $actor['id'];
                $actorName = $actor['name'] ?? $faker->company();
            }

            if (null === $actorId) {
                continue;
            }

            $result[] = [
                'id' => $actorId,
                'name' => $actorName,
                'role' => $roles[array_rand($roles)],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: string, name: string, role: string}>
     */
    private static function generateActorsForWatchFile(string $watchFileId, Generator $faker): array
    {
        $numberOfActors = $faker->numberBetween(1, 4);
        $actors = [];

        $roles = ['acquirer', 'target', 'partner', 'competitor', 'regulator', 'investor', 'customer', 'supplier'];

        $companies = [
            'TechCorp',
            'InnovateLabs',
            'Global Solutions Inc.',
            'FutureVision SA',
            'Alpha Industries',
            'Beta Systems',
            'Gamma Technologies',
            'Delta Enterprises',
        ];

        for ($i = 0; $i < $numberOfActors; ++$i) {
            $actors[] = [
                'id' => $faker->uuid(),
                'name' => $companies[array_rand($companies)],
                'role' => $roles[array_rand($roles)],
            ];
        }

        return $actors;
    }
}
