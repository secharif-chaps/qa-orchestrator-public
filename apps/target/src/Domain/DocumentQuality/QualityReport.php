<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'quality_report')]
#[ORM\Index(name: 'idx_quality_report_decision', columns: ['decision'])]
#[ORM\Index(name: 'idx_quality_report_score', columns: ['overall_score'])]
#[ORM\Index(name: 'idx_quality_report_computed_at', columns: ['computed_at'])]
#[ORM\UniqueConstraint(name: 'uniq_quality_report_document', columns: ['document_id'])]
#[ORM\HasLifecycleCallbacks]
class QualityReport
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    public string $id;

    #[ORM\Column(name: 'document_id', type: 'guid')]
    public string $documentId;

    #[ORM\Column(name: 'overall_score', type: 'float', nullable: true)]
    public ?float $overallScore;

    /**
     * @var array<string, float>
     */
    public array $categoryScores = [];

    /**
     * @var array<string, Signal>
     */
    #[ORM\Column(type: 'signals')]
    public array $signals;

    #[ORM\Column(type: 'string', enumType: QualityDecision::class)]
    public QualityDecision $decision;

    #[ORM\Column(name: 'computed_at', type: 'datetimetz_immutable')]
    public \DateTimeImmutable $computedAt;

    #[ORM\Column(name: 'decision_reason', type: 'text', nullable: true)]
    public ?string $decisionReason;

    /**
     * @param array<string, float>  $categoryScores
     * @param array<string, Signal> $signals
     */
    public function __construct(
        string $documentId,
        ?float $overallScore,
        array $categoryScores,
        array $signals,
        QualityDecision $decision,
        ?\DateTimeImmutable $computedAt = null,
        ?string $decisionReason = null,
        ?string $id = null,
    ) {
        $this->id = $id ?? Uuid::v4()->toString();
        $this->documentId = $documentId;
        $this->overallScore = $overallScore;
        $this->categoryScores = $categoryScores;
        $this->signals = $signals;
        $this->decision = $decision;
        $this->computedAt = $computedAt ?? new \DateTimeImmutable();
        $this->decisionReason = $decisionReason;
    }

    #[ORM\PostLoad]
    public function recomputeCategoryScores(): void
    {
        $categories = [];

        foreach ($this->signals as $signal) {
            $cat = $signal->category->value;

            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'totalScore' => 0.0,
                    'totalWeight' => 0.0,
                ];
            }

            $categories[$cat]['totalScore'] += $signal->contribution();
            $categories[$cat]['totalWeight'] += $signal->weight;
        }

        $result = [];
        foreach ($categories as $cat => $data) {
            $result[$cat] = $data['totalWeight'] > 0
                ? $data['totalScore'] / $data['totalWeight']
                : 0.0;
        }

        $this->categoryScores = $result;
    }
}
