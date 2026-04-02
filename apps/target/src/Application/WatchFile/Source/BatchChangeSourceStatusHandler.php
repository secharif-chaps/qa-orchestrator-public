<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Source\Source;
use App\Domain\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class BatchChangeSourceStatusHandler
{
    use GetSourceTrait;
    use GetWatchFileTrait;
    use HandleTrait;

    public function __construct(
        private readonly Security $security,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{success: bool, message: string, results: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, total: int, processed: int, failed: int}
     */
    public function __invoke(BatchChangeSourceStatusAction $action): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated');
        }

        $results = [];
        $errors = [];
        $sourceIds = [];
        $watchFileId = null;
        $hasDifferentWatchFileIds = false;

        foreach ($action->sources as $sourceData) {
            $id = $sourceData->id;
            $currentWatchFileId = $sourceData->watchFileId;

            if (empty($id) || empty($currentWatchFileId)) {
                $errors[] = [
                    'id' => $id,
                    'watchFileId' => $currentWatchFileId,
                    'error' => 'Missing or empty required source data (id or watchFileId)',
                ];
                continue;
            }

            if (null === $watchFileId) {
                $watchFileId = $currentWatchFileId;
            } elseif ($watchFileId !== $currentWatchFileId) {
                $hasDifferentWatchFileIds = true;
            }

            $sourceIds[] = $id;
        }

        // If we detected different watchFileIds, fail all sources immediately
        if ($hasDifferentWatchFileIds) {
            $errors = [];
            foreach ($action->sources as $sourceData) {
                $errors[] = [
                    'id' => $sourceData->id,
                    'watchFileId' => $sourceData->watchFileId,
                    'error' => 'All sources must belong to the same watch file',
                ];
            }
            $errorCount = \count($errors);

            return [
                'success' => false,
                'message' => \sprintf('Batch operation failed: All %d source(s) failed to process.', $errorCount),
                'results' => [],
                'errors' => $errors,
                'total' => \count($action->sources),
                'processed' => 0,
                'failed' => $errorCount,
            ];
        }

        if (empty($sourceIds)) {
            $errorCount = \count($errors);
            if ($errorCount > 0) {
                return [
                    'success' => false,
                    'message' => \sprintf('Batch operation failed: All %d source(s) failed to process.', $errorCount),
                    'results' => [],
                    'errors' => $errors,
                    'total' => \count($action->sources),
                    'processed' => 0,
                    'failed' => $errorCount,
                ];
            }

            return [
                'success' => true,
                'message' => 'Batch operation completed: No sources to process.',
                'results' => [],
                'errors' => [],
                'total' => \count($action->sources),
                'processed' => 0,
                'failed' => 0,
            ];
        }

        // If we have validation errors (like different watchFileIds), don't continue processing
        if (!empty($errors)) {
            $errorCount = \count($errors);

            return [
                'success' => false,
                'message' => \sprintf('Batch operation failed: All %d source(s) failed to process.', $errorCount),
                'results' => [],
                'errors' => $errors,
                'total' => \count($action->sources),
                'processed' => 0,
                'failed' => $errorCount,
            ];
        }

        try {
            if (null === $watchFileId) {
                throw new \RuntimeException('WatchFile ID is required');
            }

            $watchFile = $this->getWatchFile($watchFileId);

            $sourcesById = $this->sourceGateway->findByIds($sourceIds);
            $sources = array_values($sourcesById);

            // Handle partial failures: some sources found, some not
            $foundSourceIds = array_map(fn (Source $source) => $source->getId(), $sources);
            $missingSourceIds = array_diff($sourceIds, $foundSourceIds);

            if (!empty($missingSourceIds)) {
                $this->logger?->warning('Some given sources do not belong to watch file or do not exist', [
                    'watchFileId' => $watchFileId,
                    'requested_sources' => $sourceIds,
                    'found_sources' => $foundSourceIds,
                ]);

                foreach ($missingSourceIds as $missingSourceId) {
                    $errors[] = [
                        'id' => $missingSourceId,
                        'watchFileId' => $watchFileId,
                        'error' => 'Unable to find sources',
                    ];
                }
            }

            // Verify that all found sources belong to the specified watch file
            $sourcesNotInWatchFile = [];
            foreach ($sources as $source) {
                if ($source->getWatchFile()->getId() !== $watchFileId) {
                    $sourcesNotInWatchFile[] = $source;
                }
            }

            // If any source doesn't belong to the watch file, fail all sources
            if (!empty($sourcesNotInWatchFile)) {
                $errors = [];
                foreach ($action->sources as $sourceData) {
                    $errors[] = [
                        'id' => $sourceData->id,
                        'watchFileId' => $sourceData->watchFileId,
                        'error' => 'All sources must belong to the same watch file',
                    ];
                }
                $errorCount = \count($errors);

                return [
                    'success' => false,
                    'message' => \sprintf('Batch operation failed: All %d source(s) failed to process.', $errorCount),
                    'results' => [],
                    'errors' => $errors,
                    'total' => \count($action->sources),
                    'processed' => 0,
                    'failed' => $errorCount,
                ];
            }

            // Only handle sources which do not have the same status already
            $oldStatuses = [];
            foreach ($sources as $source) {
                $oldStatuses[$source->getId()] = $source->getStatus();
                if ($action->status !== $source->getStatus()) {
                    $this->sourceGateway->updateSourcesStatus([$source], $action->status);
                    $changeSourceStatusAction = new ChangeSourceStatusAction(
                        watchFileId: $watchFileId,
                        sourceId: $source->getId(),
                        status: $action->status,
                        user: $user,
                    );
                    try {
                        $this->handle($changeSourceStatusAction);
                        $results[] = [
                            'id' => $source->getId(),
                            'watchFileId' => $watchFileId,
                            'status' => $action->status->value,
                            'success' => true,
                        ];
                    } catch (\Exception $e) {
                        $errors[] = [
                            'id' => $source->getId(),
                            'watchFileId' => $watchFileId,
                            'error' => $e->getMessage(),
                        ];
                    }
                } else {
                    // Source already has the target status, count it as processed
                    $results[] = [
                        'id' => $source->getId(),
                        'watchFileId' => $watchFileId,
                        'status' => $action->status->value,
                        'success' => true,
                    ];
                }
            }
        } catch (\Exception $e) {
            foreach ($sourceIds as $sourceId) {
                $errors[] = [
                    'id' => $sourceId,
                    'watchFileId' => $watchFileId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $errorCount = \count($errors);
        $hasErrors = $errorCount > 0;
        $resultCount = \count($results);

        if ($hasErrors && $resultCount > 0) {
            $message = \sprintf(
                'Batch operation completed with partial success: %d source(s) processed successfully, %d failed.',
                $resultCount,
                $errorCount
            );
        } elseif ($hasErrors) {
            $message = \sprintf('Batch operation failed: All %d source(s) failed to process.', $errorCount);
        } elseif ($resultCount > 0) {
            $message = \sprintf('Batch operation completed successfully: All %d source(s) processed.', $resultCount);
        } else {
            $message = 'Batch operation completed: No sources to process.';
        }

        return [
            'success' => !$hasErrors,
            'message' => $message,
            'results' => $results,
            'errors' => $errors,
            'total' => \count($action->sources),
            'processed' => $resultCount,
            'failed' => $errorCount,
        ];
    }
}
