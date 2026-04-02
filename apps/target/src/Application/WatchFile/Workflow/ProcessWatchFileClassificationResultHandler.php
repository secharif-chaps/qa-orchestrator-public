<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\DomainException;
use App\Domain\WatchFile\AnalysisResultGatewayInterface;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileTypeClassificationResult;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AsMessageHandler]
class ProcessWatchFileClassificationResultHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly WatchFileActivityGatewayInterface $activityGateway,
        private readonly WatchFileActivityLogger $activityLogger,
        private readonly AnalysisResultGatewayInterface $analysisResultGateway,
        private readonly NormalizerInterface $normalizer,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ProcessWatchFileClassificationResultAction $action): void
    {
        try {
            /**
             * @var array{
             *     primaryType: string,
             *     primarySubtype: string|null,
             *     confidenceScore: int,
             *     justification: array{en: string, fr: string},
             *     secondaryTypes: list<array{type: string, subtype: string|null, score: int, justification: array{en: string, fr: string}}>,
             *     topics: list<array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}>,
             *     analysis: array{detectedKeywords: list<string>, detectedEntities: list<string>, userObjective: string, geographicScope: string|null},
             *     suggestions: array{
             *         actors: list<array{name: string, type: string, relevance: string, score: int}>,
             *         sources: list<array{name: string, url?: string, type: string, relevance: string, score: int}>,
             *         searchQueries: list<string>
             *     },
             *     deepSearchReadiness: array{ready: bool, reason: array{en: string, fr: string}, suggestedSearchQueries: list<string>}
             * } $classification
             */
            $classification = $action->classification;
            $classificationResult = WatchFileTypeClassificationResult::fromArray($classification);

            $watchFile = $this->getWatchFile($action->watchFileId);

            $this->storeClassificationInAnalysisResult($watchFile, $classificationResult);

            $monitoringType = $this->setMonitoringTypeIfConfident($watchFile, $classificationResult);

            $this->watchFileGateway->save($watchFile);
            $this->logClassificationActivity($watchFile, $classificationResult, $monitoringType);

            $this->logger?->info('Classification processed successfully', [
                'watch_file_id' => $watchFile->getId(),
                'monitoring_type' => $monitoringType->value ?? 'none',
                'confidence' => $classificationResult->primaryType->confidenceScore,
            ]);
        } catch (DomainException $e) {
            $this->logger?->error('Failed to process classification result', [
                'watch_file_id' => $action->watchFileId ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function storeClassificationInAnalysisResult(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
    ): void {
        $analysisResult = $this->analysisResultGateway->getLastOrCreate($watchFile);
        $analysisResult->setClassificationData($classificationResult);
        $analysisResult->setConfidenceScore($classificationResult->primaryType->confidenceScore);
        $analysisResult->addMetadata('workflow', 'classify-watchfile');
        $analysisResult->addMetadata('timestamp', new \DateTimeImmutable()->format(\DateTimeInterface::ATOM));

        $this->analysisResultGateway->save($analysisResult);
    }

    private function setMonitoringTypeIfConfident(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
    ): ?MonitoringType {
        if ($classificationResult->primaryType->confidenceScore >= 60) {
            $monitoringType = $classificationResult->primaryType->type;

            $watchFile->detectMonitoringType($monitoringType);

            return $monitoringType;
        }

        return null;
    }

    private function logClassificationActivity(
        WatchFile $watchFile,
        WatchFileTypeClassificationResult $classificationResult,
        ?MonitoringType $monitoringType,
    ): void {
        $user = $watchFile->getCreatedBy();
        if ($user && $monitoringType) {
            /** @var array<string, mixed> $classificationData */
            $classificationData = $this->normalizer->normalize($classificationResult, 'json');

            $activity = $this->activityLogger->logMonitoringTypeDetection(
                $watchFile,
                $user,
                $monitoringType,
                $classificationData
            );
            $this->activityGateway->save($activity);
        }
    }
}
