<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Actor\Actor;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SourceFixtures extends Fixture implements DependentFixtureInterface
{
    public const SOURCE_REFERENCE = 'source_';
    private static int $sourceCounter = 0;

    public function getDependencies(): array
    {
        return [WatchFileFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $watchFiles = $this->getWatchFiles($manager);

        $actors = $this->getActorsFromDatabase($manager);

        $this->createNuclearSources($watchFiles[0], $actors, $manager);

        $this->createHealthTechSources($watchFiles[1], $actors, $manager);

        $this->createElectricVehicleSources($watchFiles[2], $actors, $manager);

        $this->createCompetitiveIntelligenceSources($watchFiles[3], $actors, $manager);

        $this->createPharmacyCosmeticsSources($watchFiles[4], $actors, $manager);

        $manager->flush();
    }

    /**
     * @return array<int, WatchFile>
     */
    private function getWatchFiles(ObjectManager $manager): array
    {
        $watchFiles = [];
        $watchFileRepository = $manager->getRepository(WatchFile::class);

        $watchFile1 = $watchFileRepository->findOneBy([
            'name' => 'News gouvernementales - Nucléaire',
        ]);
        $watchFile2 = $watchFileRepository->findOneBy([
            'name' => 'Opportunités dans le secteur de la santé connectée',
        ]);
        $watchFile3 = $watchFileRepository->findOneBy([
            'name' => 'Évolution du marché des véhicules électriques',
        ]);
        $watchFile4 = $watchFileRepository->findOneBy([
            'name' => 'Analyse des acteurs de la veille concurrentielle',
        ]);
        $watchFile5 = $watchFileRepository->findOneBy([
            'name' => 'Employer Labels Monitoring in Pharmacy & Cosmetics',
        ]);

        if (null === $watchFile1 || null === $watchFile2 || null === $watchFile3 || null === $watchFile4 || null === $watchFile5) {
            throw new \RuntimeException(
                'Required watchfiles not found in database. Please run WatchFileFixtures first.'
            );
        }

        $watchFiles[] = $watchFile1;
        $watchFiles[] = $watchFile2;
        $watchFiles[] = $watchFile3;
        $watchFiles[] = $watchFile4;
        $watchFiles[] = $watchFile5;

        return $watchFiles;
    }

    /**
     * @return array<int, Actor>
     */
    private function getActorsFromDatabase(ObjectManager $manager): array
    {
        $actorRepository = $manager->getRepository(Actor::class);

        return $actorRepository->findAll();
    }

    /**
     * Get evenly distributed statuses for sources.
     *
     * @return array<int, SourceStatus>
     */
    private function getDistributedStatuses(int $count): array
    {
        $statuses = SourceStatus::cases();
        $distributedStatuses = [];

        for ($i = 0; $i < $count; ++$i) {
            $distributedStatuses[] = $statuses[$i % \count($statuses)];
        }

        shuffle($distributedStatuses);

        return $distributedStatuses;
    }

    /**
     * Get evenly distributed collector statuses for sources.
     *
     * @return array<int, CollectStatus>
     */
    private function getDistributedCollectStatuses(int $count): array
    {
        $collectStatuses = CollectStatus::cases();
        $distributedStatuses = [];

        for ($i = 0; $i < $count; ++$i) {
            $distributedStatuses[] = $collectStatuses[$i % \count($collectStatuses)];
        }

        shuffle($distributedStatuses);

        return $distributedStatuses;
    }

    /**
     * @param array<int, Actor> $actors
     */
    private function createNuclearSources(WatchFile $watchFile, array $actors, ObjectManager $manager): void
    {
        $watchFileActors = $watchFile->getWatchFileActors();
        $watchFileActorIds = [];
        foreach ($watchFileActors as $watchFileActor) {
            $watchFileActorIds[] = $watchFileActor->getActor()->getId();
        }

        if (empty($watchFileActorIds)) {
            return;
        }

        $sources = [
            [
                'name' => 'Le Monde - Énergie',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://www.lemonde.fr/energies/rss_full.xml',
                'primaryDomain' => 'lemonde.fr',
                'query' => 'nucléaire OR énergie',
                'description' => [
                    'fr' => 'Flux RSS du Monde spécialisé dans les questions énergétiques',
                    'en' => 'Le Monde RSS feed specialized in energy issues',
                ],
                'relevance' => [
                    'fr' => 'Source fiable pour les informations sur l\'énergie nucléaire en France',
                    'en' => 'Reliable source for nuclear energy information in France',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'Reuters Energy',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.reuters.com/business/',
                'primaryDomain' => 'reuters.com',
                'query' => 'nuclear energy',
                'description' => [
                    'fr' => 'Flux RSS Reuters pour les actualités énergétiques internationales',
                    'en' => 'Reuters RSS feed for international energy news',
                ],
                'relevance' => [
                    'fr' => 'Couverture internationale des marchés énergétiques',
                    'en' => 'International coverage of energy markets',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'EDF - Actualités Nucléaire',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.edf.fr/groupe-edf/espaces-dedies/journalistes/actualites',
                'primaryDomain' => 'edf.fr',
                'query' => 'nucléaire OR énergie nucléaire',
                'description' => [
                    'fr' => 'Actualités officielles EDF sur le nucléaire',
                    'en' => 'Official EDF news on nuclear energy',
                ],
                'relevance' => [
                    'fr' => 'Source officielle du principal opérateur nucléaire français',
                    'en' => 'Official source from the main French nuclear operator',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'Orano - Actualités',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.orano.group/fr/actus',
                'primaryDomain' => 'orano.group',
                'query' => 'cycle du combustible OR uranium',
                'description' => [
                    'fr' => 'Actualités Orano pour les informations sur le cycle du combustible',
                    'en' => 'Orano news for fuel cycle information',
                ],
                'relevance' => [
                    'fr' => 'Leader mondial du cycle du combustible nucléaire',
                    'en' => 'World leader in nuclear fuel cycle',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'Framatome - News',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.framatome.com/fr/actualites/',
                'primaryDomain' => 'framatome.com',
                'query' => 'réacteur OR technologie nucléaire',
                'description' => [
                    'fr' => 'Actualités Framatome sur les technologies nucléaires',
                    'en' => 'Framatome news on nuclear technologies',
                ],
                'relevance' => [
                    'fr' => 'Expert en conception et maintenance de réacteurs',
                    'en' => 'Expert in reactor design and maintenance',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'ASNR - Autorité de Sûreté Nucléaire et de Radioprotection',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.asnr.fr/actualites',
                'primaryDomain' => 'asnr.fr',
                'query' => 'sûreté nucléaire OR radioprotection OR contrôle',
                'description' => [
                    'fr' => 'Actualités officielles de l\'ASNR (fusion ASN-IRSN depuis janvier 2025) sur la sûreté nucléaire et la radioprotection',
                    'en' => 'Official ASNR (ASN-IRSN merger since January 2025) news on nuclear safety and radiation protection',
                ],
                'relevance' => [
                    'fr' => 'Autorité de contrôle de la sûreté nucléaire et de radioprotection en France',
                    'en' => 'Nuclear safety and radiation protection regulatory authority in France',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'CEA - Commissariat à l\'énergie atomique',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.cea.fr/presse',
                'primaryDomain' => 'cea.fr',
                'query' => 'recherche nucléaire OR innovation',
                'description' => [
                    'fr' => 'Espace presse du CEA pour les actualités sur la recherche nucléaire',
                    'en' => 'CEA press space for news on nuclear research',
                ],
                'relevance' => [
                    'fr' => 'Organisme de recherche public en énergie nucléaire',
                    'en' => 'Public research organization in nuclear energy',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'World Nuclear News',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://world-nuclear-news.org/rss/',
                'primaryDomain' => 'world-nuclear-news.org',
                'query' => 'nuclear power OR nuclear energy',
                'description' => [
                    'fr' => 'Actualités mondiales sur l\'énergie nucléaire',
                    'en' => 'World news on nuclear energy',
                ],
                'relevance' => [
                    'fr' => 'Couverture internationale de l\'industrie nucléaire',
                    'en' => 'International coverage of nuclear industry',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'Nuclear Energy Institute',
                'type' => SourceType::BLOG,
                'url' => 'https://www.nei.org/news',
                'primaryDomain' => 'nei.org',
                'query' => 'nuclear energy OR nuclear power',
                'description' => [
                    'fr' => 'Blog de l\'Institut de l\'énergie nucléaire américain',
                    'en' => 'US Nuclear Energy Institute blog',
                ],
                'relevance' => [
                    'fr' => 'Perspective américaine sur l\'industrie nucléaire',
                    'en' => 'US perspective on nuclear industry',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'IAEA - International Atomic Energy Agency',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.iaea.org/news',
                'primaryDomain' => 'iaea.org',
                'query' => 'nuclear safety OR nuclear technology',
                'description' => [
                    'fr' => 'Publications officielles de l\'AIEA',
                    'en' => 'Official IAEA publications',
                ],
                'relevance' => [
                    'fr' => 'Organisation internationale de l\'énergie atomique',
                    'en' => 'International Atomic Energy Agency',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
        ];

        $distributedStatuses = $this->getDistributedStatuses(\count($sources));
        $distributedCollectStatuses = $this->getDistributedCollectStatuses(\count($sources));

        foreach ($sources as $index => $sourceData) {
            $randomActorId = $watchFileActorIds[array_rand($watchFileActorIds)];
            $selectedActor = null;
            foreach ($actors as $actor) {
                if ($actor->getId() == $randomActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }

            if (null === $selectedActor) {
                continue;
            }

            $source = new Source(
                name: $sourceData['name'],
                description: TranslatedText::fromArray($sourceData['description']),
                type: $sourceData['type'],
                url: $sourceData['url'],
                primaryDomain: $sourceData['primaryDomain'],
                relevance: TranslatedText::fromArray($sourceData['relevance']),
                actor: $selectedActor,
                watchFile: $watchFile,
                query: $sourceData['query'],
                collectStatus: $distributedCollectStatuses[$index]
            );

            $source->setStatus($distributedStatuses[$index]);

            $manager->persist($source);

            $this->addReference(self::SOURCE_REFERENCE . self::$sourceCounter++, $source);
        }
    }

    /**
     * @param array<int, Actor> $actors
     */
    private function createHealthTechSources(WatchFile $watchFile, array $actors, ObjectManager $manager): void
    {
        // Get actors that belong to this watchfile
        $watchFileActors = $watchFile->getWatchFileActors();
        $watchFileActorIds = [];
        foreach ($watchFileActors as $watchFileActor) {
            $watchFileActorIds[] = $watchFileActor->getActor()->getId();
        }

        if (empty($watchFileActorIds)) {
            return;
        }

        $sources = [
            [
                'name' => 'Twitter - IA Santé',
                'type' => SourceType::SOCIAL_MEDIA_X_SEARCH,
                'url' => 'https://twitter.com/search?q=IA%20santé%20connectée%20OR%20%23healthtech',
                'primaryDomain' => 'twitter.com',
                'query' => 'IA santé connectée OR #healthtech',
                'description' => [
                    'fr' => 'Surveillance Twitter des discussions sur l\'IA en santé',
                    'en' => 'Twitter monitoring of AI in healthcare discussions',
                ],
                'relevance' => [
                    'fr' => 'Tendances et discussions en temps réel sur l\'IA médicale',
                    'en' => 'Real-time trends and discussions on medical AI',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'LinkedIn - Doctolib',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'url' => 'https://www.linkedin.com/company/doctolib',
                'primaryDomain' => 'linkedin.com',
                'query' => '',
                'description' => [
                    'fr' => 'Surveillance de l\'activité LinkedIn de Doctolib',
                    'en' => 'Monitoring of Doctolib LinkedIn activity',
                ],
                'relevance' => [
                    'fr' => 'Concurrent direct dans la santé connectée',
                    'en' => 'Direct competitor in connected healthcare',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'LinkedIn - Alan',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'url' => 'https://www.linkedin.com/company/alan',
                'primaryDomain' => 'linkedin.com',
                'query' => '',
                'description' => [
                    'fr' => 'Surveillance de l\'activité LinkedIn d\'Alan',
                    'en' => 'Monitoring of Alan LinkedIn activity',
                ],
                'relevance' => [
                    'fr' => 'Startup française innovante en santé digitale',
                    'en' => 'Innovative French startup in digital health',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'LinkedIn - Withings',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'url' => 'https://www.linkedin.com/company/withings',
                'primaryDomain' => 'linkedin.com',
                'query' => '',
                'description' => [
                    'fr' => 'Surveillance de l\'activité LinkedIn de Withings',
                    'en' => 'Monitoring of Withings LinkedIn activity',
                ],
                'relevance' => [
                    'fr' => 'Leader des objets connectés de santé',
                    'en' => 'Leader in connected health devices',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'Twitter - Health Tech France',
                'type' => SourceType::SOCIAL_MEDIA_X_SEARCH,
                'url' => 'https://twitter.com/search?q=healthtech%20France%20OR%20%23santéconnectée',
                'primaryDomain' => 'twitter.com',
                'query' => 'healthtech France OR #santéconnectée',
                'description' => [
                    'fr' => 'Surveillance Twitter des discussions santé tech en France',
                    'en' => 'Twitter monitoring of health tech discussions in France',
                ],
                'relevance' => [
                    'fr' => 'Tendances et discussions sur la santé connectée française',
                    'en' => 'Trends and discussions on French connected health',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'Digital Health Today',
                'type' => SourceType::BLOG,
                'url' => 'https://www.digitalhealthtoday.com',
                'primaryDomain' => 'digitalhealthtoday.com',
                'query' => 'health technology OR digital health',
                'description' => [
                    'fr' => 'Blog spécialisé dans les technologies de santé digitale',
                    'en' => 'Blog specialized in digital health technologies',
                ],
                'relevance' => [
                    'fr' => 'Actualités et analyses sur la santé digitale',
                    'en' => 'News and analysis on digital health',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'HIMSS - Healthcare Information',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.himss.org/news',
                'primaryDomain' => 'himss.org',
                'query' => 'healthcare IT OR digital transformation',
                'description' => [
                    'fr' => 'Actualités HIMSS sur l\'informatique de santé',
                    'en' => 'HIMSS news on healthcare IT',
                ],
                'relevance' => [
                    'fr' => 'Autorité mondiale en informatique de santé',
                    'en' => 'World authority in healthcare IT',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'FDA - Digital Health',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.fda.gov/medical-devices/digital-health-center-excellence',
                'primaryDomain' => 'fda.gov',
                'query' => 'digital health OR medical device software',
                'description' => [
                    'fr' => 'Publications FDA sur la santé digitale',
                    'en' => 'FDA publications on digital health',
                ],
                'relevance' => [
                    'fr' => 'Réglementations américaines sur la santé digitale',
                    'en' => 'US regulations on digital health',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'Nature - Digital Medicine',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.nature.com/subjects/digital-medicine',
                'primaryDomain' => 'nature.com',
                'query' => 'digital medicine OR health technology',
                'description' => [
                    'fr' => 'Publications scientifiques Nature sur la médecine digitale',
                    'en' => 'Nature scientific publications on digital medicine',
                ],
                'relevance' => [
                    'fr' => 'Recherche scientifique de pointe en médecine digitale',
                    'en' => 'Cutting-edge scientific research in digital medicine',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
        ];

        $distributedStatuses = $this->getDistributedStatuses(\count($sources));
        $distributedCollectStatuses = $this->getDistributedCollectStatuses(\count($sources));

        foreach ($sources as $index => $sourceData) {
            $randomActorId = $watchFileActorIds[array_rand($watchFileActorIds)];
            $selectedActor = null;
            foreach ($actors as $actor) {
                if ($actor->getId() == $randomActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }

            if (null === $selectedActor) {
                continue;
            }

            $source = new Source(
                name: $sourceData['name'],
                description: TranslatedText::fromArray($sourceData['description']),
                type: $sourceData['type'],
                url: $sourceData['url'],
                primaryDomain: $sourceData['primaryDomain'],
                relevance: TranslatedText::fromArray($sourceData['relevance']),
                actor: $selectedActor,
                watchFile: $watchFile,
                query: $sourceData['query'],
                collectStatus: $distributedCollectStatuses[$index]
            );

            $source->setStatus($distributedStatuses[$index]);

            $manager->persist($source);

            $this->addReference(self::SOURCE_REFERENCE . self::$sourceCounter++, $source);
        }
    }

    /**
     * @param array<int, Actor> $actors
     */
    private function createElectricVehicleSources(WatchFile $watchFile, array $actors, ObjectManager $manager): void
    {
        // Get actors that belong to this watchfile
        $watchFileActors = $watchFile->getWatchFileActors();
        $watchFileActorIds = [];
        foreach ($watchFileActors as $watchFileActor) {
            $watchFileActorIds[] = $watchFileActor->getActor()->getId();
        }

        // If no actors in  watchfile, skip creating sources
        if (empty($watchFileActorIds)) {
            return;
        }

        $sources = [
            [
                'name' => 'Tesla Blog',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.tesla.com/blog',
                'primaryDomain' => 'tesla.com',
                'query' => 'charging infrastructure OR supercharger',
                'description' => [
                    'fr' => 'Blog officiel Tesla pour les infrastructures de recharge',
                    'en' => 'Official Tesla blog for charging infrastructure',
                ],
                'relevance' => [
                    'fr' => 'Leader du marché des véhicules électriques',
                    'en' => 'Market leader in electric vehicles',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'European Commission - Transport',
                'type' => SourceType::WEBSITE,
                'url' => 'https://transport.ec.europa.eu',
                'primaryDomain' => 'ec.europa.eu',
                'query' => 'electric vehicles OR charging',
                'description' => [
                    'fr' => 'Publications officielles de la Commission européenne sur les transports',
                    'en' => 'Official European Commission publications on transport',
                ],
                'relevance' => [
                    'fr' => 'Réglementations européennes sur l\'électromobilité',
                    'en' => 'European regulations on electromobility',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'LinkedIn - Tesla',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'url' => 'https://www.linkedin.com/company/tesla-motors',
                'primaryDomain' => 'linkedin.com',
                'query' => '',
                'description' => [
                    'fr' => 'Surveillance de l\'activité LinkedIn de Tesla',
                    'en' => 'Monitoring of Tesla LinkedIn activity',
                ],
                'relevance' => [
                    'fr' => 'Leader mondial des véhicules électriques',
                    'en' => 'World leader in electric vehicles',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'LinkedIn - BYD',
                'type' => SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY,
                'url' => 'https://www.linkedin.com/company/byd-company-limited',
                'primaryDomain' => 'linkedin.com',
                'query' => '',
                'description' => [
                    'fr' => 'Surveillance de l\'activité LinkedIn de BYD',
                    'en' => 'Monitoring of BYD LinkedIn activity',
                ],
                'relevance' => [
                    'fr' => 'Constructeur chinois leader des véhicules électriques',
                    'en' => 'Leading Chinese electric vehicle manufacturer',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'Twitter - EV News',
                'type' => SourceType::SOCIAL_MEDIA_X_SEARCH,
                'url' => 'https://twitter.com/search?q=electric%20vehicle%20OR%20%23EV%20OR%20%23electromobility',
                'primaryDomain' => 'twitter.com',
                'query' => 'electric vehicle OR #EV OR #electromobility',
                'description' => [
                    'fr' => 'Surveillance Twitter des actualités véhicules électriques',
                    'en' => 'Twitter monitoring of electric vehicle news',
                ],
                'relevance' => [
                    'fr' => 'Tendances et discussions en temps réel sur l\'électromobilité',
                    'en' => 'Real-time trends and discussions on electromobility',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'Electrek',
                'type' => SourceType::BLOG,
                'url' => 'https://electrek.co',
                'primaryDomain' => 'electrek.co',
                'query' => 'electric vehicle OR EV news',
                'description' => [
                    'fr' => 'Blog spécialisé dans les véhicules électriques',
                    'en' => 'Blog specialized in electric vehicles',
                ],
                'relevance' => [
                    'fr' => 'Actualités et analyses sur l\'électromobilité',
                    'en' => 'News and analysis on electromobility',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'InsideEVs',
                'type' => SourceType::RSS_FEED,
                'url' => 'http://insideevs.com/rss/articles/all/',
                'primaryDomain' => 'insideevs.com',
                'query' => 'electric vehicle OR EV market',
                'description' => [
                    'fr' => 'Flux RSS InsideEVs sur les véhicules électriques',
                    'en' => 'InsideEVs RSS feed on electric vehicles',
                ],
                'relevance' => [
                    'fr' => 'Couverture complète du marché des véhicules électriques',
                    'en' => 'Comprehensive coverage of electric vehicle market',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
            [
                'name' => 'IEA - Transport',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.iea.org/energy-system/transport',
                'primaryDomain' => 'iea.org',
                'query' => 'electric vehicle OR EV adoption OR transport electrification',
                'description' => [
                    'fr' => 'Publications IEA sur le transport et les véhicules électriques',
                    'en' => 'IEA publications on transport and electric vehicles',
                ],
                'relevance' => [
                    'fr' => 'Données et analyses internationales sur l\'électromobilité et le transport durable',
                    'en' => 'International data and analysis on electromobility and sustainable transport',
                ],
                'watchFileIndex' => 2, // Évolution du marché des véhicules électriques
            ],
        ];

        $distributedStatuses = $this->getDistributedStatuses(\count($sources));
        $distributedCollectStatuses = $this->getDistributedCollectStatuses(\count($sources));

        foreach ($sources as $index => $sourceData) {
            $randomActorId = $watchFileActorIds[array_rand($watchFileActorIds)];
            $selectedActor = null;
            foreach ($actors as $actor) {
                if ($actor->getId() == $randomActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }

            if (null === $selectedActor) {
                continue;
            }

            $source = new Source(
                name: $sourceData['name'],
                description: TranslatedText::fromArray($sourceData['description']),
                type: $sourceData['type'],
                url: $sourceData['url'],
                primaryDomain: $sourceData['primaryDomain'],
                relevance: TranslatedText::fromArray($sourceData['relevance']),
                actor: $selectedActor,
                watchFile: $watchFile,
                query: $sourceData['query'],
                collectStatus: $distributedCollectStatuses[$index]
            );

            $source->setStatus($distributedStatuses[$index]);

            $manager->persist($source);

            $this->addReference(self::SOURCE_REFERENCE . self::$sourceCounter++, $source);
        }
    }

    /**
     * @param array<int, Actor> $actors
     */
    private function createCompetitiveIntelligenceSources(
        WatchFile $watchFile,
        array $actors,
        ObjectManager $manager,
    ): void {
        // Get actors that belong to this watchfile
        $watchFileActors = $watchFile->getWatchFileActors();
        $watchFileActorIds = [];
        foreach ($watchFileActors as $watchFileActor) {
            $watchFileActorIds[] = $watchFileActor->getActor()->getId();
        }

        // If no actors in watchFileActorIds, skip creating sources
        if (empty($watchFileActorIds)) {
            return;
        }

        $sources = [
            [
                'name' => 'TechCrunch - Health Tech',
                'type' => SourceType::BLOG,
                'url' => 'https://techcrunch.com/category/health/',
                'primaryDomain' => 'techcrunch.com',
                'query' => 'health tech OR digital health',
                'description' => [
                    'fr' => 'Blog TechCrunch spécialisé dans les technologies de santé',
                    'en' => 'TechCrunch blog specialized in health technologies',
                ],
                'relevance' => [
                    'fr' => 'Actualités et tendances des startups de santé',
                    'en' => 'News and trends of health startups',
                ],
                'watchFileIndex' => 1, // Opportunités dans le secteur de la santé connectée
            ],
            [
                'name' => 'Gartner - Market Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.gartner.com/en/topics/market-intelligence',
                'primaryDomain' => 'gartner.com',
                'query' => 'market intelligence OR competitive intelligence',
                'description' => [
                    'fr' => 'Analyses Gartner sur l\'intelligence de marché',
                    'en' => 'Gartner analysis on market intelligence',
                ],
                'relevance' => [
                    'fr' => 'Expertise reconnue en veille concurrentielle',
                    'en' => 'Recognized expertise in competitive intelligence',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'Forrester - Competitive Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.forrester.com/topic/competitive-intelligence',
                'primaryDomain' => 'forrester.com',
                'query' => 'competitive intelligence OR market research',
                'description' => [
                    'fr' => 'Recherches Forrester sur l\'intelligence concurrentielle',
                    'en' => 'Forrester research on competitive intelligence',
                ],
                'relevance' => [
                    'fr' => 'Concurrent direct dans l\'analyse de marché',
                    'en' => 'Direct competitor in market analysis',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'Nature - Energy Research',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.nature.com/subjects/energy',
                'primaryDomain' => 'nature.com',
                'query' => 'nuclear energy OR renewable energy',
                'description' => [
                    'fr' => 'Publications scientifiques Nature sur l\'énergie',
                    'en' => 'Nature scientific publications on energy',
                ],
                'relevance' => [
                    'fr' => 'Recherche scientifique de pointe en énergie',
                    'en' => 'Cutting-edge scientific research in energy',
                ],
                'watchFileIndex' => 0, // News gouvernementales - Nucléaire
            ],
            [
                'name' => 'McKinsey - Competitive Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.mckinsey.com/capabilities/strategy-and-corporate-finance/our-insights',
                'primaryDomain' => 'mckinsey.com',
                'query' => 'competitive intelligence OR market analysis',
                'description' => [
                    'fr' => 'Analyses McKinsey sur l\'intelligence concurrentielle',
                    'en' => 'McKinsey analysis on competitive intelligence',
                ],
                'relevance' => [
                    'fr' => 'Cabinet de conseil reconnu en stratégie d\'entreprise',
                    'en' => 'Recognized consulting firm in corporate strategy',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'Deloitte - Market Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www2.deloitte.com/insights/us/en/topics/strategy.html',
                'primaryDomain' => 'deloitte.com',
                'query' => 'market intelligence OR competitive analysis',
                'description' => [
                    'fr' => 'Recherches Deloitte sur l\'intelligence de marché',
                    'en' => 'Deloitte research on market intelligence',
                ],
                'relevance' => [
                    'fr' => 'Big Four expert en analyse de marché',
                    'en' => 'Big Four expert in market analysis',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'PwC - Strategy&',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.strategyand.pwc.com/gx/en/insights',
                'primaryDomain' => 'strategyand.pwc.com',
                'query' => 'competitive strategy OR market research',
                'description' => [
                    'fr' => 'Insights Strategy& sur la stratégie concurrentielle',
                    'en' => 'Strategy& insights on competitive strategy',
                ],
                'relevance' => [
                    'fr' => 'Division stratégie de PwC spécialisée en intelligence concurrentielle',
                    'en' => 'PwC strategy division specialized in competitive intelligence',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'KPMG - Market Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://home.kpmg/xx/en/home/insights.html',
                'primaryDomain' => 'home.kpmg',
                'query' => 'market intelligence OR competitive landscape',
                'description' => [
                    'fr' => 'Analyses KPMG sur l\'intelligence de marché',
                    'en' => 'KPMG analysis on market intelligence',
                ],
                'relevance' => [
                    'fr' => 'Big Four expert en analyse concurrentielle',
                    'en' => 'Big Four expert in competitive analysis',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
            [
                'name' => 'BCG - Competitive Intelligence',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.bcg.com/insights',
                'primaryDomain' => 'bcg.com',
                'query' => 'competitive intelligence OR market strategy',
                'description' => [
                    'fr' => 'Insights BCG sur l\'intelligence concurrentielle',
                    'en' => 'BCG insights on competitive intelligence',
                ],
                'relevance' => [
                    'fr' => 'Cabinet de conseil stratégique reconnu mondialement',
                    'en' => 'World-renowned strategic consulting firm',
                ],
                'watchFileIndex' => 3, // Analyse des acteurs de la veille concurrentielle
            ],
        ];

        $distributedStatuses = $this->getDistributedStatuses(\count($sources));
        $distributedCollectStatuses = $this->getDistributedCollectStatuses(\count($sources));

        foreach ($sources as $index => $sourceData) {
            $randomActorId = $watchFileActorIds[array_rand($watchFileActorIds)];
            $selectedActor = null;
            foreach ($actors as $actor) {
                if ($actor->getId() == $randomActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }

            if (null === $selectedActor) {
                continue;
            }

            $source = new Source(
                name: $sourceData['name'],
                description: TranslatedText::fromArray($sourceData['description']),
                type: $sourceData['type'],
                url: $sourceData['url'],
                primaryDomain: $sourceData['primaryDomain'],
                relevance: TranslatedText::fromArray($sourceData['relevance']),
                actor: $selectedActor,
                watchFile: $watchFile,
                query: $sourceData['query'],
                collectStatus: $distributedCollectStatuses[$index]
            );

            $source->setStatus($distributedStatuses[$index]);

            $manager->persist($source);

            $this->addReference(self::SOURCE_REFERENCE . self::$sourceCounter++, $source);
        }
    }

    /**
     * @param array<int, Actor> $actors
     */
    private function createPharmacyCosmeticsSources(WatchFile $watchFile, array $actors, ObjectManager $manager): void
    {
        // Get actors that belong to this watchfile
        $watchFileActors = $watchFile->getWatchFileActors();
        $watchFileActorIds = [];
        foreach ($watchFileActors as $watchFileActor) {
            $watchFileActorIds[] = $watchFileActor->getActor()->getId();
        }

        // If no actors in watchfile, skip creating sources
        if (empty($watchFileActorIds)) {
            return;
        }

        $sources = [
            [
                'name' => 'Pharmaceutiques RSS',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://www.pharmaceutiques.com/feed',
                'primaryDomain' => 'pharmaceutiques.com',
                'query' => 'marque employeur OR label OR réputation OR classement OR cosmétique',
                'description' => [
                    'fr' => 'Flux RSS spécialisé dans l\'actualité pharmaceutique et cosmétique',
                    'en' => 'RSS feed specialized in pharmaceutical and cosmetic news',
                ],
                'relevance' => [
                    'fr' => 'Source spécialisée pour les actualités du secteur pharmaceutique et cosmétique',
                    'en' => 'Specialized source for pharmaceutical and cosmetic sector news',
                ],
                'watchFileIndex' => 4, // Employer Labels Monitoring in Pharmacy & Cosmetics
            ],
            [
                'name' => 'Top Employers Institute RSS',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://www.top-employers.com/feed/',
                'primaryDomain' => 'top-employers.com',
                'query' => 'pharmaceutical OR cosmetics OR employer label OR certification OR ranking',
                'description' => [
                    'fr' => 'Flux RSS de l\'institut Top Employers pour les certifications employeur',
                    'en' => 'Top Employers Institute RSS feed for employer certifications',
                ],
                'relevance' => [
                    'fr' => 'Source officielle des certifications Top Employers',
                    'en' => 'Official source for Top Employers certifications',
                ],
                'watchFileIndex' => 4, // Employer Labels Monitoring in Pharmacy & Cosmetics
            ],
            [
                'name' => 'Premium Beauty News',
                'type' => SourceType::WEBSITE,
                'url' => 'https://www.premiumbeautynews.com/en',
                'primaryDomain' => 'premiumbeautynews.com',
                'query' => 'employer OR label OR ranking OR reputation OR pharmacy OR cosmetics',
                'description' => [
                    'fr' => 'Site Premium Beauty News pour l\'actualité cosmétique et pharmaceutique',
                    'en' => 'Premium Beauty News website for cosmetic and pharmaceutical industry news',
                ],
                'relevance' => [
                    'fr' => 'Actualités spécialisées dans l\'industrie cosmétique et pharmaceutique',
                    'en' => 'Specialized news in the cosmetic and pharmaceutical industry',
                ],
                'watchFileIndex' => 4, // Employer Labels Monitoring in Pharmacy & Cosmetics
            ],
            [
                'name' => 'Le Monde – Toute l\'actualité RSS',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://www.lemonde.fr/rss/en_continu.xml',
                'primaryDomain' => 'lemonde.fr',
                'query' => 'meilleur employeur OR label OR classement OR réputation OR pharmacie OR cosmétique OR secteur OR certification',
                'description' => [
                    'fr' => 'Flux RSS généraliste Le Monde pour les actualités employeur',
                    'en' => 'General Le Monde RSS feed for employer news',
                ],
                'relevance' => [
                    'fr' => 'Source généraliste de référence pour les actualités françaises',
                    'en' => 'Reference generalist source for French news',
                ],
                'watchFileIndex' => 4, // Employer Labels Monitoring in Pharmacy & Cosmetics
            ],
            [
                'name' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'type' => SourceType::RSS_FEED,
                'url' => 'https://www.forbes.fr/feed/',
                'primaryDomain' => 'forbes.fr',
                'query' => 'pharma OR cosmétique OR employeurs OR classement OR réputation',
                'description' => [
                    'fr' => 'Flux RSS Forbes France pour les classements employeurs',
                    'en' => 'Forbes France RSS feed for employer rankings',
                ],
                'relevance' => [
                    'fr' => 'Source de référence pour les classements employeurs Forbes',
                    'en' => 'Reference source for Forbes employer rankings',
                ],
                'watchFileIndex' => 4, // Employer Labels Monitoring in Pharmacy & Cosmetics
            ],
        ];

        // Define specific statuses for Pharmacy & Cosmetics sources to match database
        $sourceStatuses = [
            'Pharmaceutiques RSS' => [
                'status' => SourceStatus::INACTIVE,
                'collect_status' => CollectStatus::STOPPED,
            ],
            'Top Employers Institute RSS' => [
                'status' => SourceStatus::INACTIVE,
                'collect_status' => CollectStatus::STOPPED,
            ],
            'Premium Beauty News' => [
                'status' => SourceStatus::INACTIVE,
                'collect_status' => CollectStatus::STOPPED,
            ],
            'Le Monde – Toute l\'actualité RSS' => [
                'status' => SourceStatus::ACTIVE,
                'collect_status' => CollectStatus::STOPPED,
            ],
            'Forbes \'Meilleurs Employeurs\' RSS' => [
                'status' => SourceStatus::ACTIVE,
                'collect_status' => CollectStatus::STOPPED,
            ],
        ];

        foreach ($sources as $index => $sourceData) {
            // Select a random actor that belongs to this watchfile
            $randomActorId = $watchFileActorIds[array_rand($watchFileActorIds)];
            $selectedActor = null;
            foreach ($actors as $actor) {
                if ($actor->getId() == $randomActorId) {
                    $selectedActor = $actor;
                    break;
                }
            }

            if (null === $selectedActor) {
                continue; // Skip if actor not found
            }

            // Get the specific status for this source
            $sourceStatus = $sourceStatuses[$sourceData['name']];

            $source = new Source(
                name: $sourceData['name'],
                description: TranslatedText::fromArray($sourceData['description']),
                type: $sourceData['type'],
                url: $sourceData['url'],
                primaryDomain: $sourceData['primaryDomain'],
                relevance: TranslatedText::fromArray($sourceData['relevance']),
                actor: $selectedActor,
                watchFile: $watchFile,
                query: $sourceData['query'],
                collectStatus: $sourceStatus['collect_status']
            );

            // Set the specific status for this source
            $source->setStatus($sourceStatus['status']);

            $manager->persist($source);
        }
    }
}
