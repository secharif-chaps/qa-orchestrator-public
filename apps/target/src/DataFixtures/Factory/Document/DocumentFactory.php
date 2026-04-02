<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Document;

use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\Actor\Actor;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Document\Summary;
use App\Domain\Document\SummaryStatus;
use App\Domain\Document\ValidationReason;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Faker\Generator;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<Document>
 */
class DocumentFactory extends ObjectFactory
{
    public static function class(): string
    {
        return Document::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * manualStatus, validatedBy, and validatedAt are set via Document::manuallyValidate()
     * in initialize() method to respect domain encapsulation.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     excerpt: string,
     *     type: string,
     *     datePublish: \DateTimeImmutable,
     *     dateCollect: \DateTimeImmutable,
     *     content: string,
     *     status: DocumentStatus,
     *     validationReason: ValidationReason,
     *     language: string,
     *     isInteresting: bool,
     *     insight: string|null,
     *     cfcRestricted: bool,
     *     updatedAt: \DateTimeImmutable|null,
     *     updatedBy: User|null,
     *     summary: Summary|null,
     *     summaryStatus: SummaryStatus|null,
     *     summaryGeneratedAt: \DateTimeImmutable|null,
     *     aiValidation: AIValidation|null,
     * }
     */
    protected function defaults(): array
    {
        $faker = self::faker();
        $datePublish = $faker->dateTimeBetween('-1 year', 'now');
        $dateCollect = $faker->dateTimeBetween($datePublish, 'now');

        $statuses = [
            DocumentStatus::VALIDATED,
            DocumentStatus::VALIDATED,
            DocumentStatus::VALIDATED, // 30% validated
            DocumentStatus::REJECTED,
            DocumentStatus::REJECTED, // 20% rejected
            DocumentStatus::PENDING,
            DocumentStatus::PENDING, // 50% pending
        ];
        $status = $statuses[array_rand($statuses)];

        $types = ['pdf', 'html'];
        $type = $types[array_rand($types)];

        $languages = ['en', 'fr'];
        $language = $languages[array_rand($languages)];

        $updatedAt = $faker->boolean(30) ?
            \DateTimeImmutable::createFromInterface($faker->dateTimeBetween($dateCollect, 'now'))
            : null;

        $updatedBy = null;
        if ($updatedAt instanceof \DateTimeImmutable) {
            $updatedBy = UserFactory::new()
                ->random();
        }

        return [
            'id' => $faker->uuid(),
            'title' => $faker->sentence(6),
            'excerpt' => $faker->paragraph(2),
            'type' => $type,
            'datePublish' => \DateTimeImmutable::createFromInterface($datePublish),
            'dateCollect' => \DateTimeImmutable::createFromInterface($dateCollect),
            'content' => $this->generateContent($type, $faker),
            'status' => $status,
            'validationReason' => $this->generateGeneralValidationReason($status, $faker),
            'language' => $language,
            'isInteresting' => $faker->boolean(25),
            'insight' => $faker->boolean(20) ? $faker->sentence(10) : null,
            'cfcRestricted' => $faker->boolean(30),
            'updatedAt' => $updatedAt,
            'updatedBy' => $updatedBy,
            'summary' => $faker->boolean(90) ? $this->generateSummary($faker) : null,
            'summaryStatus' => $faker->boolean(90) ? $this->generateSummaryStatus($status, $faker) : null,
            'summaryGeneratedAt' => $faker->boolean(90) ? \DateTimeImmutable::createFromInterface(
                $faker->dateTimeBetween($dateCollect, 'now')
            ) : null,
            'aiValidation' => $faker->boolean(80) ? $this->generateAiValidation(
                $status,
                \DateTimeImmutable::createFromInterface($dateCollect),
                $faker
            ) : null,
        ];
    }

    private function generateSummary(Generator $faker): Summary
    {
        return new Summary(fr: $faker->paragraph(3), en: $faker->paragraph(3));
    }

    private function generateSummaryStatus(DocumentStatus $status, Generator $faker): SummaryStatus
    {
        /** @var SummaryStatus $result */
        $result = match ($status) {
            DocumentStatus::VALIDATED => SummaryStatus::COMPLETED,
            DocumentStatus::REJECTED => SummaryStatus::COMPLETED,
            DocumentStatus::PENDING => $faker->randomElement(
                [SummaryStatus::COMPLETED, SummaryStatus::PENDING, SummaryStatus::FAILED]
            ),
        };

        return $result;
    }

    private function generateAiValidation(
        DocumentStatus $status,
        \DateTimeImmutable $dateCollect,
        Generator $faker,
    ): AIValidation {
        $aiStatuses = [
            AiValidationStatus::VALIDATED,
            AiValidationStatus::VALIDATED,
            AiValidationStatus::VALIDATED, // 30% validated
            AiValidationStatus::REJECTED,
            AiValidationStatus::REJECTED, // 20% rejected
            AiValidationStatus::UNCERTAIN,
            AiValidationStatus::UNCERTAIN, // 20% uncertain
            AiValidationStatus::PENDING,
            AiValidationStatus::PENDING, // 20% pending
            AiValidationStatus::FAILED, // 10% failed
        ];
        $aiStatus = $aiStatuses[array_rand($aiStatuses)];

        $confidenceScore = match ($aiStatus) {
            AiValidationStatus::VALIDATED => $faker->numberBetween(75, 100),
            AiValidationStatus::REJECTED => $faker->numberBetween(0, 24),
            AiValidationStatus::UNCERTAIN => $faker->numberBetween(25, 74),
            AiValidationStatus::PENDING => $faker->numberBetween(0, 24),
            AiValidationStatus::FAILED => $faker->numberBetween(0, 24),
        };

        $validationReason = $this->generateValidationReason($aiStatus, $faker);

        $processedAt = \DateTimeImmutable::createFromInterface(
            $faker->dateTimeBetween($dateCollect->format('Y-m-d H:i:s'), 'now')
        );

        return new AIValidation(
            status: $aiStatus,
            confidenceScore: $confidenceScore,
            validationReason: $validationReason,
            processedAt: $processedAt,
            referenceSubject: $faker->optional(0.7)
->sentence(3),
        );
    }

    private function generateValidationReason(AiValidationStatus $status, Generator $faker): ValidationReason
    {
        $reasonsFr = [
            AiValidationStatus::VALIDATED->value => [
                'Document validé automatiquement par l\'IA',
                'Contenu pertinent et fiable',
                'Source vérifiée et authentique',
                'Informations cohérentes et précises',
            ],
            AiValidationStatus::REJECTED->value => [
                'Document non pertinent pour le sujet',
                'Source non fiable identifiée',
                'Contenu incohérent ou erroné',
                'Document dupliqué ou similaire',
            ],
            AiValidationStatus::UNCERTAIN->value => [
                'Niveau de confiance moyen',
                'Nécessite une validation manuelle',
                'Informations partiellement vérifiables',
                'Source partiellement fiable',
            ],
            AiValidationStatus::PENDING->value => [
                'En attente de traitement',
                'Document en cours d\'analyse',
                'Validation en attente',
            ],
            AiValidationStatus::FAILED->value => [
                'Échec du traitement automatique',
                'Erreur lors de l\'analyse',
                'Document corrompu ou illisible',
            ],
        ];

        $reasonsEn = [
            AiValidationStatus::VALIDATED->value => [
                'Document automatically validated by AI',
                'Relevant and reliable content',
                'Verified and authentic source',
                'Consistent and accurate information',
            ],
            AiValidationStatus::REJECTED->value => [
                'Document not relevant to the subject',
                'Unreliable source identified',
                'Inconsistent or erroneous content',
                'Duplicate or similar document',
            ],
            AiValidationStatus::UNCERTAIN->value => [
                'Medium confidence level',
                'Requires manual validation',
                'Partially verifiable information',
                'Partially reliable source',
            ],
            AiValidationStatus::PENDING->value => [
                'Awaiting processing',
                'Document under analysis',
                'Validation pending',
            ],
            AiValidationStatus::FAILED->value => [
                'Automatic processing failed',
                'Error during analysis',
                'Corrupted or unreadable document',
            ],
        ];

        $frReasons = $reasonsFr[$status->value];
        $enReasons = $reasonsEn[$status->value];

        $selectedFr = $frReasons[array_rand($frReasons)];
        $selectedEn = $enReasons[array_rand($enReasons)];

        return new ValidationReason($selectedFr, $selectedEn);
    }

    private function generateGeneralValidationReason(DocumentStatus $status, Generator $faker): ValidationReason
    {
        $reasonsFr = [
            DocumentStatus::VALIDATED->value => [
                'Document validé par l\'utilisateur',
                'Contenu approuvé manuellement',
                'Source vérifiée',
            ],
            DocumentStatus::REJECTED->value => [
                'Document rejeté par l\'utilisateur',
                'Contenu non pertinent',
                'Source non fiable',
            ],
            DocumentStatus::PENDING->value => [
                'En attente de validation',
                'Document en cours de traitement',
                'Validation manuelle requise',
            ],
        ];

        $reasonsEn = [
            DocumentStatus::VALIDATED->value => [
                'Document validated by user',
                'Content manually approved',
                'Source verified',
            ],
            DocumentStatus::REJECTED->value => [
                'Document rejected by user',
                'Content not relevant',
                'Source unreliable',
            ],
            DocumentStatus::PENDING->value => [
                'Awaiting validation',
                'Document under processing',
                'Manual validation required',
            ],
        ];

        $frReasons = $reasonsFr[$status->value];
        $enReasons = $reasonsEn[$status->value];

        $selectedFr = $frReasons[array_rand($frReasons)];
        $selectedEn = $enReasons[array_rand($enReasons)];

        return new ValidationReason($selectedFr, $selectedEn);
    }

    public function withWatchFile(WatchFile $watchFile): self
    {
        return $this->with([
            'watchFile' => $watchFile,
        ]);
    }

    public function withSource(Source $source): self
    {
        return $this->with([
            'source' => $source,
        ]);
    }

    public function withActor(Actor $actor): self
    {
        return $this->with([
            'actor' => $actor,
        ]);
    }

    public function withStatus(DocumentStatus $status): self
    {
        return $this->with([
            'status' => $status,
        ]);
    }

    public function withAiValidation(AIValidation $aiValidation): self
    {
        return $this->with([
            'aiValidation' => $aiValidation,
        ]);
    }

    private function generateContent(string $type, Generator $faker): string
    {
        return 'pdf' === $type
            ? $this->generatePdfBase64Content()
            : $this->generateHtmlContent($faker);
    }

    private function generateHtmlContent(Generator $faker): string
    {
        $percentage1 = $faker->numberBetween(10, 50);
        $percentage2 = $faker->numberBetween(5, 25);
        $percentage3 = $faker->numberBetween(15, 40);
        $year = $faker->numberBetween(2024, 2030);

        $ceoName = $faker->name();
        $expertName = $faker->name();
        $expertTitle = $faker->jobTitle();
        $expertRole = $this->ensureString(
            $faker->randomElement([
                'Head of Innovation',
                'Director of Technology',
                'VP of Engineering',
                'Chief Technology Officer',
            ])
        );

        $businessArea = $this->ensureString(
            $faker->randomElement([
                'manufacturing',
                'supply chain',
                'customer experience',
                'digital transformation',
                'market expansion',
            ])
        );
        $region = $this->ensureString(
            $faker->randomElement([
                'Southeast Asia',
                'Latin America',
                'Eastern Europe',
                'North America',
                'Western Europe',
            ])
        );

        $imageId = (string) $faker->numberBetween(1000, 9999);
        $imageId2 = (string) $faker->numberBetween(1000, 9999);

        $imageAlt = $this->ensureString($faker->words(6, true));
        $figcaption = $this->ensureString($faker->sentence(8));
        $period = $this->ensureString($faker->randomElement(['quarter', 'period', 'phase']));
        $performance = $this->ensureString($faker->words(3, true));
        $metric = $this->ensureString($faker->randomElement(['revenue', 'performance', 'growth']));
        $improvement = $this->ensureString(
            $faker->randomElement(['profit margins', 'customer satisfaction', 'market share'])
        );
        $sectionTitle = $this->ensureString(
            $faker->randomElement(['Key Performance Indicators', 'Main Results', 'Success Metrics'])
        );
        $revenueType = $this->ensureString($faker->randomElement(['revenue', 'sales', 'growth']));
        $efficiencyType = $this->ensureString($faker->randomElement(['efficiency', 'performance', 'quality']));
        $costType = $this->ensureString($faker->randomElement(['cost', 'expense', 'investment']));
        $quote = $this->ensureString($faker->sentence(15));
        $costCategory = $this->ensureString(
            $faker->randomElement(['Customer acquisition costs', 'Operational expenses', 'Processing times'])
        );
        $strategyType = $this->ensureString(
            $faker->randomElement(['digital strategies', 'automation processes', 'customer experience'])
        );
        $implementation = $this->ensureString(
            $faker->randomElement(['AI-powered systems', 'advanced analytics', 'machine learning algorithms'])
        );
        $improvementArea = $this->ensureString($faker->randomElement(['conversion rates', 'efficiency', 'accuracy']));
        $chartAlt = $this->ensureString($faker->words(5, true));
        $chartCaption = $this->ensureString($faker->sentence(10));
        $successTitle = $this->ensureString(
            $faker->randomElement(['Market Expansion', 'Strategic Initiative', 'Business Development'])
        );
        $expansionArea = $this->ensureString(
            $faker->randomElement(['emerging markets', 'new territories', 'international regions'])
        );
        $growthType = $this->ensureString(
            $faker->randomElement(['revenue growth', 'business expansion', 'market penetration'])
        );
        $partnershipType = $this->ensureString(
            $faker->randomElement(['local distributors', 'regional partners', 'strategic allies'])
        );
        $solutionType = $this->ensureString(
            $faker->randomElement(['tailored solutions', 'customized approaches', 'specialized services'])
        );
        $strategyTitle = $this->ensureString(
            $faker->randomElement(['Implementation Strategy', 'Action Plan', 'Next Steps'])
        );
        $assessmentType = $this->ensureString(
            $faker->randomElement(['immediate assessment', 'initial evaluation', 'preliminary analysis'])
        );
        $processType = $this->ensureString($faker->randomElement(['processes', 'systems', 'operations']));
        $analysisType = $this->ensureString(
            $faker->randomElement(['gap analysis', 'risk assessment', 'performance review'])
        );
        $planningType = $this->ensureString(
            $faker->randomElement(['strategic planning', 'resource allocation', 'timeline development'])
        );
        $redesignType = $this->ensureString(
            $faker->randomElement(['process redesign', 'system optimization', 'workflow improvement'])
        );
        $updateType = $this->ensureString(
            $faker->randomElement(['documentation updates', 'training programs', 'quality assurance'])
        );
        $trainingType = $this->ensureString(
            $faker->randomElement(['staff training', 'team development', 'skill enhancement'])
        );
        $certificationType = $this->ensureString(
            $faker->randomElement(['certification', 'accreditation', 'validation'])
        );
        $auditType = $this->ensureString(
            $faker->randomElement(['internal audit', 'performance monitoring', 'continuous improvement'])
        );
        $validationType = $this->ensureString($faker->randomElement(['validation', 'testing', 'optimization']));
        $benefitsTitle = $this->ensureString(
            $faker->randomElement(['Expected Benefits', 'Projected Outcomes', 'Success Metrics'])
        );
        $benefit1 = $this->ensureString(
            $faker->randomElement(['reduction in costs', 'improvement in efficiency', 'increase in productivity'])
        );
        $benefit2 = $this->ensureString(
            $faker->randomElement(['decrease in downtime', 'enhancement in quality', 'boost in performance'])
        );
        $benefit3 = $this->ensureString(
            $faker->randomElement(['overall savings', 'total improvement', 'comprehensive enhancement'])
        );
        $benefit4 = $this->ensureString(
            $faker->randomElement([
                'Improved customer satisfaction',
                'Enhanced user experience',
                'Better stakeholder engagement',
            ])
        );
        $timelineTitle = $this->ensureString(
            $faker->randomElement(['Timeline and Targets', 'Project Schedule', 'Implementation Roadmap'])
        );
        $goal1 = $this->ensureString(
            $faker->randomElement(['reduction in emissions', 'improvement in metrics', 'achievement of goals'])
        );
        $goal2 = $this->ensureString(
            $faker->randomElement(['reduction in costs', 'enhancement in performance', 'optimization of processes'])
        );
        $goal3 = $this->ensureString(
            $faker->randomElement(['Full implementation', 'Complete transformation', 'Total optimization'])
        );
        $expertQuote = $this->ensureString($faker->sentence(20));
        $resultsTitle = $this->ensureString(
            $faker->randomElement(['Results and Impact', 'Outcomes and Benefits', 'Performance and Success'])
        );
        $result1 = $this->ensureString(
            $faker->randomElement([
                'increase in satisfaction scores',
                'improvement in ratings',
                'enhancement in feedback',
            ])
        );
        $result2 = $this->ensureString(
            $faker->randomElement(['reduction in support tickets', 'decrease in issues', 'optimization of resources'])
        );
        $result3 = $this->ensureString(
            $faker->randomElement(['improvement in retention rates', 'enhancement in loyalty', 'boost in engagement'])
        );
        $result4 = $this->ensureString(
            $faker->randomElement([
                'Higher customer lifetime value',
                'Improved brand reputation',
                'Enhanced market position',
            ])
        );

        $content = \sprintf(
            '<article>
    <figure class="featured-image">
        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&h=400&fit=crop&id=%s" alt="%s" />
        <figcaption>%s</figcaption>
    </figure>

    <p>The %s demonstrated <strong>%s across all business segments</strong> with %s increasing by %d%% year-over-year. %s initiatives in %s contributed significantly to growth, while operational efficiency improvements led to a %d%% increase in %s.</p>

    <h2>%s</h2>
    <ul>
        <li>%s improvement: <strong>%d%% YoY</strong></li>
        <li>%s enhancement: <strong>%d%%</strong></li>
        <li>%s reduction: <strong>%d%%</strong></li>
    </ul>

    <blockquote>
        <p>"%s," said %s, %s.</p>
    </blockquote>

    <p>%s decreased by %d%% due to improved %s. The implementation of %s has significantly improved %s.</p>

    <figure class="chart">
        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&h=300&fit=crop&id=%s" alt="%s" />
        <figcaption>%s</figcaption>
    </figure>

    <h2>%s Success</h2>
    <p>Our expansion into %s has exceeded expectations, with %s showing particularly strong performance. The region contributed %d%% of total %s, driven by successful partnerships with %s and %s.</p>

    <h2>%s</h2>
    <p>Our team has developed a comprehensive strategy that includes:</p>
    <ol>
        <li>%s of current %s</li>
        <li>%s and %s</li>
        <li>%s and %s</li>
        <li>%s and %s</li>
        <li>%s and %s</li>
    </ol>

    <div class="benefits">
        <h3>%s</h3>
        <ul>
            <li>%d%% %s</li>
            <li>%d%% %s</li>
            <li>%d%% %s</li>
            <li>%s</li>
        </ul>
    </div>

    <h2>%s</h2>
    <div class="timeline">
        <div class="milestone">
            <h3>%d</h3>
            <p>%d%% %s</p>
        </div>
        <div class="milestone">
            <h3>%d</h3>
            <p>%d%% %s</p>
        </div>
        <div class="milestone">
            <h3>%d</h3>
            <p>%s achieved</p>
        </div>
    </div>

    <blockquote>
        <p>"%s," explains %s, %s.</p>
    </blockquote>

    <h2>%s</h2>
    <p>The implementation of these improvements has resulted in:</p>
    <ul>
        <li>%d%% %s</li>
        <li>%d%% %s</li>
        <li>%d%% %s</li>
        <li>%s</li>
    </ul>
</article>',
            $imageId,
            $imageAlt,
            $figcaption,
            $period,
            $performance,
            $metric,
            $percentage1,
            ucfirst($businessArea),
            $region,
            $percentage2,
            $improvement,
            $sectionTitle,
            ucfirst($revenueType),
            $percentage1,
            ucfirst($efficiencyType),
            $percentage2,
            ucfirst($costType),
            $percentage3,
            $quote,
            $ceoName,
            $expertTitle,
            ucfirst($costCategory),
            $percentage3,
            $strategyType,
            $implementation,
            $improvementArea,
            $imageId2,
            $chartAlt,
            $chartCaption,
            $successTitle,
            $expansionArea,
            $region,
            $percentage1,
            $growthType,
            $partnershipType,
            $solutionType,
            $strategyTitle,
            ucfirst($assessmentType),
            $processType,
            ucfirst($analysisType),
            $planningType,
            ucfirst($redesignType),
            $updateType,
            ucfirst($trainingType),
            $certificationType,
            ucfirst($auditType),
            $validationType,
            $benefitsTitle,
            $percentage1,
            $benefit1,
            $percentage2,
            $benefit2,
            $percentage3,
            $benefit3,
            $benefit4,
            $timelineTitle,
            $year,
            $percentage1,
            $goal1,
            $year + 2,
            $percentage2,
            $goal2,
            $year + 4,
            $goal3,
            $expertQuote,
            $expertName,
            $expertRole,
            $resultsTitle,
            $percentage1,
            $result1,
            $percentage2,
            $result2,
            $percentage3,
            $result3,
            $result4
        );

        return $content;
    }

    private function generatePdfBase64Content(): string
    {
        return 'JVBERi0xLjQKMSAwIG9iago8PC9UeXBlIC9DYXRhbG9nCi9QYWdlcyAyIDAgUgo+PgplbmRvYmoKMiAwIG9iago8PC9UeXBlIC9QYWdlcwovS2lkcyBbMyAwIFJdCi9Db3VudCAxCj4+CmVuZG9iagozIDAgb2JqCjw8L1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA1OTUgODQyXQovQ29udGVudHMgNSAwIFIKL1Jlc291cmNlcyA8PC9Qcm9jU2V0IFsvUERGIC9UZXh0XQovRm9udCA8PC9GMSA0IDAgUj4+Cj4+Cj4+CmVuZG9iago0IDAgb2JqCjw8L1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9OYW1lIC9GMQovQmFzZUZvbnQgL0hlbHZldGljYQovRW5jb2RpbmcgL01hY1JvbWFuRW5jb2RpbmcKPj4KZW5kb2JqCjUgMCBvYmoKPDwvTGVuZ3RoIDUzCj4+CnN0cmVhbQpCVAovRjEgMjAgVGYKMjIwIDQwMCBUZAooU2FtcGxlIFBERiBEb2N1bWVudCkgVGoKRVQKZW5kc3RyZWFtCmVuZG9iagp4cmVmCjAgNgowMDAwMDAwMDAwIDY1NTM1IGYKMDAwMDAwMDAwOSAwMDAwMCBuCjAwMDAwMDAwNjMgMDAwMDAgbgowMDAwMDAwMTI0IDAwMDAwIG4KMDAwMDAwMDI3NyAwMDAwMCBuCjAwMDAwMDAzOTIgMDAwMDAgbgp0cmFpbGVyCjw8L1NpemUgNgovUm9vdCAxIDAgUgo+PgpzdGFydHhyZWYKNDk1CiUlRU9G';
    }

    private function ensureString(mixed $value): string
    {
        if (\is_array($value)) {
            return implode(' ', $value);
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function generateManualValidationStatus(Generator $faker): ManualValidationStatus
    {
        $statuses = [ManualValidationStatus::ACCEPTED, ManualValidationStatus::REFUSED];

        return $statuses[array_rand($statuses)];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Document $document): void {
            if (self::faker()->boolean(30)) {
                $user = UserFactory::new()->random();
                $status = $this->generateManualValidationStatus(self::faker());
                $document->manuallyValidate($status, $user);
            }
        });
    }
}
