<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Factory\Document\DocumentFactory;
use App\Domain\Actor\Actor;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentSeenStatus;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\SummaryStatus;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Serializer\DocumentNormalizer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use OpenSearch\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Uuid;

class DocumentFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly Client $openSearchClient,
        #[Autowire(service: DocumentNormalizer::class)]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            OpenSearchSetupFixture::class,
            WatchFileFixtures::class,
            ActorFixtures::class,
            SourceFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Set seed for reproducible random data
        mt_srand(12345);

        $watchFiles = $this->getWatchFiles($manager);
        $actors = $this->getActorsFromDatabase($manager);
        $sources = $this->getSourcesFromDatabase($manager);

        $documents = [];

        // Generate 50 documents for each watchfile except the last one
        for ($watchFileIndex = 0; $watchFileIndex < \count($watchFiles) - 1; ++$watchFileIndex) {
            $watchFile = $watchFiles[$watchFileIndex];

            for ($i = 0; $i < 50; ++$i) {
                $document = $this->createDocument($watchFile, $actors, $sources);
                $documents[] = $document;
            }
        }

        // Generate 10 specific documents for Pharmacy & Cosmetics watchfile
        $pharmacyWatchFile = $watchFiles[4]; // Index 4 is the Pharmacy & Cosmetics watchfile
        $pharmacyDocuments = $this->createPharmacyCosmeticsDocuments($pharmacyWatchFile, $actors, $sources);
        $documents = array_merge($documents, $pharmacyDocuments);

        // Add 2 quality pipeline test documents with fixed UUIDs and explicit URLs (HTTPS + HTTP)
        $qualityTestDocuments = $this->createQualityPipelineTestDocuments($pharmacyWatchFile, $actors, $sources);
        $documents = array_merge($documents, $qualityTestDocuments);

        // Upload documents to OpenSearch
        $this->indexDocumentsToOpenSearch($documents);

        // Create DocumentSeenStatus for the main user
        $this->createDocumentSeenStatuses($manager, $documents);

        echo \sprintf("Generated %d documents and indexed to OpenSearch.\n", \count($documents));
    }

    /**
     * @param array<int, Actor>  $actors
     * @param array<int, Source> $sources
     *
     * @return array<string, mixed>
     */
    private function createDocument(WatchFile $watchFile, array $actors, array $sources): array
    {
        if (empty($actors) || empty($sources)) {
            throw new \RuntimeException('No actors or Sources provided.');
        }
        $actor = $actors[array_rand($actors)];
        $source = $sources[array_rand($sources)];

        /** @var array<string, mixed> */
        $document = $this->normalizer->normalize(
            DocumentFactory::new()
                ->withWatchFile($watchFile)
                ->withSource($source)
                ->withActor($actor)
                ->create()
        );

        return $document;
    }

    /**
     * @return array<int, WatchFile>
     */
    private function getWatchFiles(ObjectManager $manager): array
    {
        $watchFileRepository = $manager->getRepository(WatchFile::class);

        // Get watchfiles by name since we know they exist from WatchFileFixtures
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

        return [$watchFile1, $watchFile2, $watchFile3, $watchFile4, $watchFile5];
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
     * @return array<int, Source>
     */
    private function getSourcesFromDatabase(ObjectManager $manager): array
    {
        $sourceRepository = $manager->getRepository(Source::class);

        return $sourceRepository->findAll();
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     */
    private function indexDocumentsToOpenSearch(array $documents): void
    {
        $indexName = Document::INDEX_NAME;
        $totalDocuments = \count($documents);

        // Split documents into 3 batches
        $batchSize = max(1, (int) ceil($totalDocuments / 3));
        $batches = array_chunk($documents, $batchSize);

        $totalUploadedCount = 0;

        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            echo \sprintf(
                "Processing batch %d/%d with %d documents...\n",
                $batchNumber,
                \count($batches),
                \count($batch)
            );

            $bulkBody = [];

            foreach ($batch as $document) {
                $docId = \is_string($document['id']) ? $document['id'] : 'unknown';

                // Add index action metadata
                $bulkBody[] = [
                    'index' => [
                        '_index' => $indexName,
                        '_id' => $docId,
                    ],
                ];

                // Add document data
                $bulkBody[] = $document;
            }

            // Execute bulk request
            $payload = $this->openSearchClient->bulk([
                'body' => $bulkBody,
            ]);

            if (isset($payload['errors']) && $payload['errors']) {
                throw new \RuntimeException('Some documents failed to index: ' . json_encode($payload));
            }

            $totalUploadedCount += \count($batch);
            echo \sprintf(
                "Batch %d completed. Total uploaded: %d/%d\n",
                $batchNumber,
                $totalUploadedCount,
                $totalDocuments
            );
        }
    }

    /**
     * Create documents with fixed UUIDs for quality pipeline testing.
     * Each document targets a specific processor branch to allow signal-level verification.
     * Fixed UUIDs ensure documents are stable and identifiable across fixture reloads.
     *
     * UUID ranges:
     *   000001–000002  HttpsProcessor         (HTTPS vs HTTP)
     *   000010–000012  PublicationDateProcessor (today / 2 years ago / future)
     *   000020–000021  WordCountProcessor      (medium ~400 words / short ~20 words)
     *   000030–000031  UrlPatternProcessor     (/article/ editorial / /login auth page)
     *
     * @param array<int, Actor>  $actors
     * @param array<int, Source> $sources
     *
     * @return array<int, array<string, mixed>>
     */
    private function createQualityPipelineTestDocuments(WatchFile $watchFile, array $actors, array $sources): array
    {
        $actor = $actors[0];
        $source = $sources[0];
        $now = new \DateTime()
->format('Y-m-d\TH:i:s');
        $twoYearsAgo = new \DateTime('-730 days')
->format('Y-m-d\TH:i:s');
        $futureMonth = new \DateTime('+30 days')
->format('Y-m-d\TH:i:s');

        $base = [
            'type' => 'html',
            'dateCollect' => $now,
            'status' => DocumentStatus::VALIDATED->value,
            'watchFileId' => $watchFile->getId(),
            'actorId' => $actor->getId(),
            'sourceId' => $source->getId(),
            'source' => [
                'id' => $source->getId(),
                'name' => $source->getName(),
                'url' => $source->getUrl(),
            ],
            'actor' => [
                'id' => $actor->getId(),
                'label' => $actor->getLabel(),
                'primaryDomain' => $actor->getPrimaryDomain(),
            ],
            'watchFile' => [
                'id' => $watchFile->getId(),
                'name' => $watchFile->getName(),
            ],
            'cfcRestricted' => false,
            'language' => 'en',
        ];

        return [
            // ─── HttpsProcessor ────────────────────────────────────────────────
            // https signal: 0.80 (HTTPS) vs 0.35 (HTTP)
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000001',
                'title' => '[Quality Test] HTTPS — https signal = 0.80',
                'excerpt' => 'Test document with HTTPS URL for quality pipeline validation.',
                'url' => 'https://example.com/quality-test/accepted',
                'datePublish' => $now,
                'isInteresting' => true,
            ]),
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000002',
                'title' => '[Quality Test] HTTP — https signal = 0.35',
                'excerpt' => 'Test document with HTTP URL for quality pipeline validation.',
                'url' => 'http://example.com/quality-test/low-quality',
                'datePublish' => $now,
                'isInteresting' => false,
            ]),

            // ─── PublicationDateProcessor ───────────────────────────────────────
            // publication_date signal: 0.85 (today) / 0.65 (1–3 years) / 0.25 (future)
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000010',
                'title' => '[Quality Test] Publication date: today — pub_date signal = 0.85',
                'excerpt' => 'Test document published today to exercise the fresh article branch of PublicationDateProcessor.',
                'url' => 'https://example.com/quality-test/pub-date-today',
                'datePublish' => $now,
                'isInteresting' => true,
            ]),
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000011',
                'title' => '[Quality Test] Publication date: 2 years ago — pub_date signal = 0.65',
                'excerpt' => 'Test document published 730 days ago to exercise the 1–3 year bracket of PublicationDateProcessor.',
                'url' => 'https://example.com/quality-test/pub-date-2-years-ago',
                'datePublish' => $twoYearsAgo,
                'isInteresting' => true,
            ]),
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000012',
                'title' => '[Quality Test] Publication date: future +30 days — pub_date signal = 0.25',
                'excerpt' => 'Test document with a future publication date to exercise the suspicious date branch of PublicationDateProcessor.',
                'url' => 'https://example.com/quality-test/pub-date-future',
                'datePublish' => $futureMonth,
                'isInteresting' => false,
            ]),

            // ─── WordCountProcessor ─────────────────────────────────────────────
            // word_count signal: 0.80 (200–1000 words) / 0.20 (< 50 words)
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000020',
                'title' => '[Quality Test] Word count: ~400 words — word_count signal = 0.80',
                'excerpt' => 'Test document with medium-length content (200–1000 words range).',
                'content' => $this->generateMediumWordCountContent(),
                'url' => 'https://example.com/quality-test/word-count-medium',
                'datePublish' => $now,
                'isInteresting' => true,
            ]),
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000021',
                'title' => '[Quality Test] Word count: ~20 words — word_count signal = 0.20',
                'excerpt' => 'Very short test document.',
                'content' => 'The award has been confirmed by the certification body. No additional details are currently available regarding the specific evaluation criteria.',
                'url' => 'https://example.com/quality-test/word-count-short',
                'datePublish' => $now,
                'isInteresting' => false,
            ]),

            // ─── UrlPatternProcessor ────────────────────────────────────────────
            // url_pattern signal: 0.85 (/article/ editorial) / 0.10 (/login auth page)
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000030',
                'title' => '[Quality Test] URL pattern: /article/ — url_pattern signal = 0.85',
                'excerpt' => 'Test document with an editorial article URL to exercise the positive pattern branch of UrlPatternProcessor.',
                'content' => $this->generateMediumWordCountContent(),
                'url' => 'https://example.com/article/employer-branding-pharma-2024',
                'datePublish' => $now,
                'isInteresting' => true,
            ]),
            array_merge($base, [
                'id' => '00000000-0000-4000-8000-000000000031',
                'title' => '[Quality Test] URL pattern: /login — url_pattern signal = 0.10',
                'excerpt' => 'Test document with a login page URL to exercise the authentication page branch of UrlPatternProcessor.',
                'url' => 'https://example.com/login',
                'datePublish' => $now,
                'isInteresting' => false,
            ]),
        ];
    }

    private function generateMediumWordCountContent(): string
    {
        return 'The pharmaceutical and cosmetic industry has witnessed significant growth in employer recognition programs over the past decade. Organizations in this sector now compete not only for market share but also for talented professionals, making employer branding a critical strategic priority for human resources departments worldwide.

Leading organizations such as L\'Oréal, Sanofi, and Servier consistently appear in prestigious employer rankings including the Top Employers certification and the Forbes Best Employers list. These recognitions reflect sustained investment in employee development, diversity initiatives, and workplace well-being programs that extend beyond standard compensation packages to include career development and flexible working arrangements.

The Top Employers Institute, founded in the Netherlands, certifies organizations based on rigorous criteria covering talent strategy, learning and development, well-being, diversity and inclusion, and leadership development practices. Companies seeking this certification must complete a detailed assessment covering human resources practices and organizational policies across all business units and geographic regions.

Forbes, in partnership with market research company Statista, surveys large numbers of employees across multiple countries each year. Participants evaluate their current employers on criteria including working conditions, salary levels, advancement opportunities, and overall satisfaction with the company culture. The resulting rankings are widely cited in recruitment campaigns and employer branding communications across the industry.

The Great Place to Work certification is awarded by the eponymous institute based on employee surveys and a rigorous assessment of workplace culture and management practices. The methodology distinguishes between employee perception and organizational practices, providing a comprehensive evaluation of the employment experience within a company.

In the pharmaceutical sector, employer labels serve functions beyond recruitment marketing. They signal organizational stability, ethical governance, and long-term commitment to workforce development, which can influence investor confidence and strategic partnership decisions. Companies with strong employer reputations tend to demonstrate higher employee retention rates, reducing ongoing recruitment costs and preserving valuable institutional knowledge.

The cosmetic industry faces distinct challenges in employer branding due to rapid product cycles and consumer trend sensitivity. Labels such as Happy at Work and Best Workplaces help cosmetic brands communicate their commitment to employee satisfaction in this competitive and dynamic environment.

Monitoring employer labels is therefore a valuable competitive intelligence activity for organizations operating in the pharmaceutical and cosmetic sectors. Understanding which competitors receive which certifications provides meaningful insight into their human resources strategies and competitive positioning in the talent market.';
    }

    /**
     * Create 10 specific documents for Pharmacy & Cosmetics watchfile
     * 7 validated, 3 refused, all from active sources (Le Monde and Forbes).
     *
     * @param array<int, Actor>  $actors
     * @param array<int, Source> $sources
     *
     * @return array<int, array<string, mixed>>
     */
    private function createPharmacyCosmeticsDocuments(WatchFile $watchFile, array $actors, array $sources): array
    {
        $documents = [];

        // Find the two active sources for this watchfile
        $activeSources = array_filter($sources, function ($source) use ($watchFile) {
            return $source->getWatchFile()
                    ->getId() === $watchFile->getId()
                && 'active' === $source->getStatus()
                    ->value
                && \in_array($source->getName(), [
                    'Le Monde – Toute l\'actualité RSS',
                    'Forbes \'Meilleurs Employeurs\' RSS',
                ]);
        });

        if (empty($activeSources)) {
            return $documents;
        }

        $activeSourcesArray = array_values($activeSources);

        // Get Pharmacy & Cosmetics actors
        $pharmacyActors = array_filter($actors, function ($actor) {
            $pharmacyActorNames = [
                'Servier', 'Groupe Rocher', 'SVR', 'Bio Mérieux', 'Sanofi',
                'ISDIN', 'Estée Lauder', 'L\'Oréal', 'L\'Occitane', 'Ipsen',
            ];

            return \in_array($actor->getLabel(), $pharmacyActorNames);
        });

        $pharmacyActorsArray = array_values($pharmacyActors);

        // Define 10 realistic documents about employer labels in pharmacy/cosmetics
        $documentData = [
            // 7 VALIDATED documents
            [
                'title' => 'L\'Oréal reçoit le label "Top Employer" pour la 3ème année consécutive',
                'excerpt' => 'Le groupe cosmétique français L\'Oréal a été distingué par l\'institut Top Employers pour ses pratiques RH exemplaires.',
                'content' => $this->generateLorealTopEmployerContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Article vérifié sur le site officiel de L\'Oréal et confirmé par Top Employers Institute.',
                    'en' => 'Article verified on L\'Oréal\'s official website and confirmed by Top Employers Institute.',
                ],
                'sourceName' => 'Le Monde – Toute l\'actualité RSS',
                'actorName' => 'L\'Oréal',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'L\'Oréal reçoit le label "Top Employer" pour la 3ème année consécutive, récompensant ses pratiques RH exemplaires et son engagement envers ses 88 000 collaborateurs mondiaux.',
                    'en' => 'L\'Oréal receives the "Top Employer" label for the 3rd consecutive year, rewarding its exemplary HR practices and commitment to its 88,000 global employees.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Sanofi Named Among Forbes Best Employers in Healthcare 2024',
                'excerpt' => 'French pharmaceutical giant Sanofi has been recognized by Forbes as one of the best employers in the healthcare sector.',
                'content' => $this->generateSanofiForbesContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Classement Forbes vérifié et recoupé avec les communications officielles de Sanofi.',
                    'en' => 'Forbes ranking verified and cross-referenced with Sanofi official communications.',
                ],
                'sourceName' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'actorName' => 'Sanofi',
                'language' => 'en',
                'summary' => [
                    'fr' => 'Sanofi est reconnu par Forbes comme l\'un des meilleurs employeurs du secteur de la santé en 2024, soulignant son excellence en matière de ressources humaines.',
                    'en' => 'Sanofi is recognized by Forbes as one of the best employers in the healthcare sector in 2024, highlighting its excellence in human resources.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Groupe Rocher distingué par le label "Great Place to Work"',
                'excerpt' => 'Le groupe Rocher, propriétaire de la marque L\'Occitane, obtient la certification Great Place to Work pour ses conditions de travail.',
                'content' => $this->generateGroupeRocherContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Certification confirmée sur le site Great Place to Work et communiqué officiel du groupe.',
                    'en' => 'Certification confirmed on Great Place to Work website and official group press release.',
                ],
                'sourceName' => 'Le Monde – Toute l\'actualité RSS',
                'actorName' => 'Groupe Rocher',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'Le Groupe Rocher obtient la certification Great Place to Work, récompensant ses excellentes conditions de travail et son engagement envers ses employés.',
                    'en' => 'Groupe Rocher receives the Great Place to Work certification, rewarding its excellent working conditions and commitment to its employees.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Bio Mérieux Earns "Best Workplaces" Recognition in France',
                'excerpt' => 'Bio Mérieux has been awarded the "Best Workplaces" certification for its outstanding employee experience and company culture.',
                'content' => $this->generateBioMerieuxContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Prix vérifié via la base de données officielle Best Workplaces et communiqué de presse de l\'entreprise.',
                    'en' => 'Award verified through Best Workplaces official database and company press release.',
                ],
                'sourceName' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'actorName' => 'Bio Mérieux',
                'language' => 'en',
                'summary' => [
                    'fr' => 'Bio Mérieux reçoit la certification "Best Workplaces" en France pour son expérience employé exceptionnelle et sa culture d\'entreprise.',
                    'en' => 'Bio Mérieux earns "Best Workplaces" recognition in France for its outstanding employee experience and company culture.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Servier classé parmi les "Meilleurs Employeurs" du secteur pharmaceutique',
                'excerpt' => 'Le laboratoire Servier figure dans le classement des meilleurs employeurs du secteur pharmaceutique français.',
                'content' => $this->generateServierContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Classement vérifié via l\'étude annuelle des meilleurs employeurs pharmaceutiques.',
                    'en' => 'Ranking verified through the annual study of best pharmaceutical employers.',
                ],
                'sourceName' => 'Le Monde – Toute l\'actualité RSS',
                'actorName' => 'Servier',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'Servier est classé parmi les meilleurs employeurs du secteur pharmaceutique français, reconnaissant ses pratiques RH et son environnement de travail.',
                    'en' => 'Servier is ranked among the best employers in the French pharmaceutical sector, recognizing its HR practices and work environment.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Estée Lauder Companies Named Fortune 100 Best Companies to Work For',
                'excerpt' => 'Estée Lauder Companies has been included in Fortune\'s prestigious list of the 100 Best Companies to Work For.',
                'content' => $this->generateEsteeLauderContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Classement Fortune confirmé et validé par rapport à la publication officielle du magazine Fortune.',
                    'en' => 'Fortune ranking confirmed and validated against official Fortune magazine publication.',
                ],
                'sourceName' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'actorName' => 'Estée Lauder',
                'language' => 'en',
                'summary' => [
                    'fr' => 'Estée Lauder Companies figure dans la prestigieuse liste Fortune des 100 meilleures entreprises où travailler, soulignant son excellence employeur.',
                    'en' => 'Estée Lauder Companies is named in Fortune\'s prestigious list of the 100 Best Companies to Work For, highlighting its employer excellence.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'Ipsen reçoit le label "Happy at Work" pour ses initiatives RH',
                'excerpt' => 'Le groupe pharmaceutique Ipsen a été récompensé pour ses politiques d\'amélioration de la qualité de vie au travail.',
                'content' => $this->generateIpsenContent(),
                'status' => DocumentStatus::VALIDATED,
                'validationReason' => [
                    'fr' => 'Label vérifié sur la plateforme Happy at Work et confirmé par Ipsen.',
                    'en' => 'Label verified on the Happy at Work platform and confirmed by Ipsen.',
                ],
                'sourceName' => 'Le Monde – Toute l\'actualité RSS',
                'actorName' => 'Ipsen',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'Ipsen reçoit le label "Happy at Work" pour ses initiatives RH visant à améliorer la qualité de vie au travail de ses employés.',
                    'en' => 'Ipsen receives the "Happy at Work" label for its HR initiatives aimed at improving employee quality of life at work.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            // 3 REFUSED documents
            [
                'title' => 'L\'Occitane en Provence obtient le label "Employeur de l\'année"',
                'excerpt' => 'La marque de cosmétiques naturels L\'Occitane a été récompensée pour son excellence en matière de ressources humaines.',
                'content' => $this->generateLoccitaneRefusedContent(),
                'status' => DocumentStatus::REJECTED,
                'validationReason' => [
                    'fr' => 'Information non vérifiable - aucun label "Employeur de l\'année" officiel trouvé.',
                    'en' => 'Unverifiable information - no official "Employer of the Year" label found.',
                ],
                'sourceName' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'actorName' => 'L\'Occitane',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'Article non vérifié concernant L\'Occitane et un prétendu label "Employeur de l\'année" qui n\'existe pas officiellement.',
                    'en' => 'Unverified article about L\'Occitane and a supposed "Employer of the Year" label that does not officially exist.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'SVR Laboratories Wins "Best Employer" Award in Cosmetics Industry',
                'excerpt' => 'SVR Laboratories has been recognized as the best employer in the cosmetics industry for its innovative HR practices.',
                'content' => $this->generateSVRRefusedContent(),
                'status' => DocumentStatus::REJECTED,
                'validationReason' => [
                    'fr' => 'Prix inexistant - aucune source officielle ne confirme cette distinction.',
                    'en' => 'Award non-existent - no official source confirms this distinction.',
                ],
                'sourceName' => 'Le Monde – Toute l\'actualité RSS',
                'actorName' => 'SVR',
                'language' => 'en',
                'summary' => [
                    'fr' => 'Article non vérifié concernant SVR Laboratories et un prix "Best Employer" inexistant dans l\'industrie cosmétique.',
                    'en' => 'Unverified article about SVR Laboratories and a non-existent "Best Employer" award in the cosmetics industry.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
            [
                'title' => 'ISDIN reçoit la certification "Employeur Excellence" 2024',
                'excerpt' => 'La marque dermocosmétique ISDIN a obtenu la certification "Employeur Excellence" pour ses pratiques RH.',
                'content' => $this->generateISDINRefusedContent(),
                'status' => DocumentStatus::REJECTED,
                'validationReason' => [
                    'fr' => 'Certification inexistante - aucun organisme officiel ne délivre ce label.',
                    'en' => 'Non-existent certification - no official body issues this label.',
                ],
                'sourceName' => 'Forbes \'Meilleurs Employeurs\' RSS',
                'actorName' => 'ISDIN',
                'language' => 'fr',
                'summary' => [
                    'fr' => 'Article non vérifié concernant ISDIN et une certification "Employeur Excellence" 2024 qui n\'existe pas officiellement.',
                    'en' => 'Unverified article about ISDIN and a non-existent "Employeur Excellence" 2024 certification.',
                ],
                'summaryStatus' => SummaryStatus::COMPLETED->value,
            ],
        ];

        foreach ($documentData as $data) {
            // Find the correct source and actor
            $source = null;
            $actor = null;

            foreach ($activeSourcesArray as $s) {
                if ($s->getName() === $data['sourceName']) {
                    $source = $s;
                    break;
                }
            }

            foreach ($pharmacyActorsArray as $a) {
                if ($a->getLabel() === $data['actorName']) {
                    $actor = $a;
                    break;
                }
            }

            if (!$source || !$actor) {
                continue;
            }

            $datePublish = $this->generateRandomDateTime();
            $dateCollect = $this->generateRandomDateTimeAfter($datePublish);
            $summaryGeneratedAt = $this->generateRandomDateTimeAfter($dateCollect);

            // Generate AI validation data based on document status
            $aiValidation = $this->generateAiValidationForPharmacyDocument($data['status'], $data['language']);

            $document = [
                'id' => $this->generateUuid(),
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'type' => 'html',
                'datePublish' => $datePublish->format('Y-m-d\TH:i:s'),
                'dateCollect' => $dateCollect->format('Y-m-d\TH:i:s'),
                'content' => $data['content'],
                'status' => $data['status']->value,
                'validationReason' => $data['validationReason'],
                'actorId' => $actor->getId(),
                'sourceId' => $source->getId(),
                'watchFileId' => $watchFile->getId(),
                'source' => [
                    'id' => $source->getId(),
                    'name' => $source->getName(),
                    'url' => $source->getUrl(),
                ],
                'actor' => [
                    'id' => $actor->getId(),
                    'label' => $actor->getLabel(),
                    'primaryDomain' => $actor->getPrimaryDomain(),
                ],
                'watchFile' => [
                    'id' => $watchFile->getId(),
                    'name' => $watchFile->getName(),
                ],
                'cfcRestricted' => false,
                'language' => $data['language'],
                'isInteresting' => DocumentStatus::VALIDATED === $data['status'],
                'insight' => DocumentStatus::VALIDATED === $data['status'] ? $this->generatePharmacyInsight(
                    $data['actorName']
                ) : null,
                'summary' => $data['summary'],
                'summaryStatus' => $data['summaryStatus'],
                'summaryGeneratedAt' => $summaryGeneratedAt->format('Y-m-d\TH:i:s'),
                'updatedAt' => null,
                'updatedBy' => null,
                'aiValidation' => $aiValidation,
            ];

            $documents[] = $document;
        }

        return $documents;
    }

    private function generateLorealTopEmployerContent(): string
    {
        return '<article>
    <h1>L\'Oréal reçoit le label "Top Employer" pour la 3ème année consécutive</h1>
    <p>Le groupe cosmétique français L\'Oréal a été distingué par l\'institut Top Employers pour ses pratiques RH exemplaires. Cette reconnaissance souligne l\'engagement du groupe en faveur de ses 88 000 collaborateurs à travers le monde.</p>

    <h2>Une politique RH innovante</h2>
    <p>L\'Oréal se distingue par ses initiatives en matière de diversité, d\'inclusion et de développement des talents. Le groupe a mis en place des programmes de mentoring et de formation continue qui bénéficient à 95% de ses employés.</p>

    <blockquote>
        <p>"Cette reconnaissance récompense notre engagement quotidien pour créer un environnement de travail où chacun peut s\'épanouir et contribuer à notre mission de beauté universelle", déclare Jean-Paul Agon, PDG du groupe.</p>
    </blockquote>

    <h2>Des résultats concrets</h2>
    <ul>
        <li>Taux de satisfaction des employés : 87%</li>
        <li>Égalité salariale : 99,2% entre hommes et femmes</li>
        <li>Formation : 40 heures par employé en moyenne</li>
    </ul>
</article>';
    }

    private function generateSanofiForbesContent(): string
    {
        return '<article>
    <h1>Sanofi Named Among Forbes Best Employers in Healthcare 2024</h1>
    <p>French pharmaceutical giant Sanofi has been recognized by Forbes as one of the best employers in the healthcare sector, ranking 15th out of 500 companies surveyed globally.</p>

    <h2>Employee-Centric Approach</h2>
    <p>Sanofi\'s recognition stems from its commitment to employee well-being, career development, and work-life balance. The company has invested heavily in digital transformation and flexible working arrangements.</p>

    <blockquote>
        <p>"Our people are at the heart of everything we do. This recognition validates our efforts to create an inclusive and supportive workplace," says Paul Hudson, CEO of Sanofi.</p>
    </blockquote>

    <h2>Key Achievements</h2>
    <ul>
        <li>Employee satisfaction score: 4.2/5</li>
        <li>Diversity index: 92%</li>
        <li>Internal promotion rate: 78%</li>
    </ul>
</article>';
    }

    private function generateGroupeRocherContent(): string
    {
        return '<article>
    <h1>Groupe Rocher distingué par le label "Great Place to Work"</h1>
    <p>Le groupe Rocher, propriétaire de la marque L\'Occitane, obtient la certification Great Place to Work pour ses conditions de travail exceptionnelles et sa culture d\'entreprise unique.</p>

    <h2>Une culture d\'entreprise forte</h2>
    <p>Le groupe Rocher se distingue par son approche humaniste et son engagement environnemental. Les employés bénéficient d\'un environnement de travail stimulant et de nombreuses initiatives de bien-être.</p>

    <h2>Initiatives remarquables</h2>
    <ul>
        <li>Programme de télétravail flexible</li>
        <li>Formation continue en développement durable</li>
        <li>Participation aux bénéfices</li>
    </ul>
</article>';
    }

    private function generateBioMerieuxContent(): string
    {
        return '<article>
    <h1>Bio Mérieux Earns "Best Workplaces" Recognition in France</h1>
    <p>Bio Mérieux has been awarded the "Best Workplaces" certification for its outstanding employee experience and company culture in the diagnostics industry.</p>

    <h2>Innovation and Collaboration</h2>
    <p>The company\'s success in employee satisfaction is driven by its collaborative culture and commitment to innovation in medical diagnostics.</p>

    <h2>Employee Benefits</h2>
    <ul>
        <li>Comprehensive health coverage</li>
        <li>Professional development programs</li>
        <li>Work-life balance initiatives</li>
    </ul>
</article>';
    }

    private function generateServierContent(): string
    {
        return '<article>
    <h1>Servier classé parmi les "Meilleurs Employeurs" du secteur pharmaceutique</h1>
    <p>Le laboratoire Servier figure dans le classement des meilleurs employeurs du secteur pharmaceutique français, grâce à ses pratiques RH innovantes et son engagement social.</p>

    <h2>Une approche responsable</h2>
    <p>Servier se distingue par son approche responsable et son engagement en faveur de l\'accès aux soins. L\'entreprise investit massivement dans la formation et le développement de ses collaborateurs.</p>
</article>';
    }

    private function generateEsteeLauderContent(): string
    {
        return '<article>
    <h1>Estée Lauder Companies Named Fortune 100 Best Companies to Work For</h1>
    <p>Estée Lauder Companies has been included in Fortune\'s prestigious list of the 100 Best Companies to Work For, highlighting its commitment to employee satisfaction and career development.</p>

    <h2>Beauty Industry Leadership</h2>
    <p>The company\'s recognition reflects its leadership in creating an inclusive workplace that values creativity, innovation, and personal growth.</p>
</article>';
    }

    private function generateIpsenContent(): string
    {
        return '<article>
    <h1>Ipsen reçoit le label "Happy at Work" pour ses initiatives RH</h1>
    <p>Le groupe pharmaceutique Ipsen a été récompensé pour ses politiques d\'amélioration de la qualité de vie au travail et son engagement en faveur du bien-être des employés.</p>

    <h2>Innovation RH</h2>
    <p>Ipsen se distingue par ses initiatives innovantes en matière de ressources humaines, notamment son programme de télétravail et ses formations en ligne.</p>
</article>';
    }

    private function generateLoccitaneRefusedContent(): string
    {
        return '<article>
    <h1>L\'Occitane en Provence obtient le label "Employeur de l\'année"</h1>
    <p>La marque de cosmétiques naturels L\'Occitane a été récompensée pour son excellence en matière de ressources humaines et son engagement environnemental.</p>

    <h2>Une reconnaissance méritée</h2>
    <p>Cette distinction récompense les efforts de L\'Occitane en faveur de ses employés et de l\'environnement.</p>
</article>';
    }

    private function generateSVRRefusedContent(): string
    {
        return '<article>
    <h1>SVR Laboratories Wins "Best Employer" Award in Cosmetics Industry</h1>
    <p>SVR Laboratories has been recognized as the best employer in the cosmetics industry for its innovative HR practices and employee satisfaction programs.</p>

    <h2>Industry Recognition</h2>
    <p>The award highlights SVR\'s commitment to creating a positive work environment and supporting employee development.</p>
</article>';
    }

    private function generateISDINRefusedContent(): string
    {
        return '<article>
    <h1>ISDIN reçoit la certification "Employeur Excellence" 2024</h1>
    <p>La marque dermocosmétique ISDIN a obtenu la certification "Employeur Excellence" pour ses pratiques RH et son engagement en faveur de ses collaborateurs.</p>

    <h2>Excellence reconnue</h2>
    <p>Cette certification souligne l\'engagement d\'ISDIN en faveur de la qualité de vie au travail et du développement professionnel.</p>
</article>';
    }

    private function generatePharmacyInsight(string $actorName): string
    {
        $insights = [
            'L\'Oréal' => 'Leader mondial de la cosmétique avec une forte culture d\'innovation et de diversité.',
            'Sanofi' => 'Géant pharmaceutique français reconnu pour ses pratiques RH et son engagement social.',
            'Groupe Rocher' => 'Groupe familial avec une culture d\'entreprise unique basée sur l\'humanisme.',
            'Bio Mérieux' => 'Spécialiste du diagnostic in vitro avec une approche collaborative et innovante.',
            'Servier' => 'Laboratoire pharmaceutique indépendant avec une approche responsable et sociale.',
            'Estée Lauder' => 'Groupe cosmétique américain leader avec une culture créative et inclusive.',
            'Ipsen' => 'Groupe pharmaceutique français spécialisé en oncologie avec des initiatives RH innovantes.',
        ];

        return $insights[$actorName] ?? 'Acteur reconnu dans le secteur pharmaceutique/cosmétique.';
    }

    private function generateRandomDateTime(): \DateTime
    {
        $start = new \DateTime('-1 year');
        $end = new \DateTime('now');
        $randomTimestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());

        return new \DateTime()
            ->setTimestamp($randomTimestamp);
    }

    private function generateRandomDateTimeAfter(\DateTime $after): \DateTime
    {
        $end = new \DateTime('now');
        $randomTimestamp = mt_rand($after->getTimestamp(), $end->getTimestamp());

        return new \DateTime()
            ->setTimestamp($randomTimestamp);
    }

    private function generateUuid(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }

    /**
     * Generate AI validation data for pharmacy documents based on their status.
     *
     * @return array<string, mixed>|null
     */
    private function generateAiValidationForPharmacyDocument(DocumentStatus $status, string $language): ?array
    {
        // Only generate AI validation for non-pending documents
        if (DocumentStatus::PENDING === $status) {
            return null;
        }

        $processedAt = $this->generateRandomDateTime();
        $referenceSubject = 'Employer Labels Monitoring in Pharmacy & Cosmetics';

        $result = match ($status) {
            DocumentStatus::VALIDATED => [
                'confidenceScore' => mt_rand(80, 95),
                'validationReason' => $this->generateValidationReason(
                    'Document validé par l\'IA avec une forte confiance. Source fiable et informations cohérentes.',
                    'Document validated by AI with high confidence. Reliable source and consistent information.',
                    $language
                ),
            ],
            default => [
                'confidenceScore' => mt_rand(10, 20),
                'validationReason' => $this->generateValidationReason(
                    'Document rejeté par l\'IA. Informations non vérifiables ou source non fiable.',
                    'Document rejected by AI. Unverifiable information or unreliable source.',
                    $language
                ),
            ],
        };

        $confidenceScore = $result['confidenceScore'];
        $validationReason = $result['validationReason'];

        return [
            'status' => $this->getAiValidationStatusFromConfidence($confidenceScore)
                ->value,
            'confidenceScore' => $confidenceScore,
            'validationReason' => $validationReason,
            'processedAt' => $processedAt->format('Y-m-d\TH:i:s'),
            'referenceSubject' => $referenceSubject,
        ];
    }

    /**
     * Generate validation reason based on language preference.
     *
     * @return array<string, string>
     */
    private function generateValidationReason(string $fr, string $en, string $language): array
    {
        if ('fr' === $language) {
            return [
                'fr' => $fr,
                'en' => $en,
            ];
        }

        return [
            'fr' => $fr,
            'en' => $en,
        ];
    }

    /**
     * Get AI validation status based on confidence score.
     */
    private function getAiValidationStatusFromConfidence(int $confidenceScore): AiValidationStatus
    {
        if ($confidenceScore >= 75) {
            return AiValidationStatus::VALIDATED;
        }

        if ($confidenceScore >= 25) {
            return AiValidationStatus::UNCERTAIN;
        }

        return AiValidationStatus::REJECTED;
    }

    /**
     * Create DocumentSeenStatus entries for the main user (basil@chapsvision.com).
     * The first 3 documents per watchfile are marked as unread (no DocumentSeenStatus entry).
     * All other documents are marked as read (DocumentSeenStatus entry with seenAt date).
     *
     * @param list<array<string, mixed>> $documents
     */
    private function createDocumentSeenStatuses(ObjectManager $manager, array $documents): void
    {
        // Get the main user using reference
        $basilUser = $this->getReference(UserFixtures::BASIL_USER_REFERENCE, User::class);

        // Group documents by watchfile
        /** @var array<string, list<array{id: string, datePublish: string, watchFile: array{id: string}}>> $documentsByWatchFile */
        $documentsByWatchFile = [];
        foreach ($documents as $document) {
            /** @var array{id: string, datePublish: string, watchFile: array{id: string}} $document */
            $watchFileId = $document['watchFile']['id'];
            if (!isset($documentsByWatchFile[$watchFileId])) {
                $documentsByWatchFile[$watchFileId] = [];
            }
            $documentsByWatchFile[$watchFileId][] = $document;
        }

        // Get watchfiles using references
        $watchFileMap = [];
        for ($i = 0; $i < 5; ++$i) {
            /** @var WatchFile $watchFile */
            $watchFile = $this->getReference(WatchFileFixtures::WATCHFILE_REFERENCE . $i, WatchFile::class);
            $watchFileMap[$watchFile->getId()] = $watchFile;
        }

        $totalSeenStatuses = 0;

        foreach ($documentsByWatchFile as $watchFileId => $watchFileDocuments) {
            $watchFile = $watchFileMap[$watchFileId] ?? null;
            if (!$watchFile) {
                continue;
            }

            // Sort documents by datePublish (most recent first)
            usort($watchFileDocuments, fn ($a, $b) => strtotime($b['datePublish']) <=> strtotime($a['datePublish']));

            // Always mark the first 3 documents as unread, the rest as read
            $unreadCount = 3;

            foreach ($watchFileDocuments as $index => $document) {
                // Only create DocumentSeenStatus for read documents (index >= 3)
                // Unread documents (index < 3) don't have a DocumentSeenStatus entry
                if ($index >= $unreadCount) {
                    $documentSeenStatus = new DocumentSeenStatus();
                    $documentSeenStatus->setUser($basilUser);
                    $documentSeenStatus->setDocumentId(Uuid::fromString($document['id']));
                    $documentSeenStatus->setWatchFile($watchFile);

                    // Set seenAt to a date after document publish
                    $documentPublishDate = new \DateTimeImmutable($document['datePublish']);
                    $seenAt = $documentPublishDate->modify('+1 day');
                    $documentSeenStatus->setSeenAt($seenAt);

                    $manager->persist($documentSeenStatus);
                    ++$totalSeenStatuses;
                }
            }
        }

        $manager->flush();
    }

    /*
     * Generate a random event type for fixtures.
     */
}
