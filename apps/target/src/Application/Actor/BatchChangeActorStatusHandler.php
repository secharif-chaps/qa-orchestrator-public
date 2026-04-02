<?php

declare(strict_types=1);

namespace App\Application\Actor;

use App\Domain\Actor\ActorStatus;
use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class BatchChangeActorStatusHandler
{
    public function __construct(
        private readonly ChangeActorStatusHandler $changeActorStatusHandler,
        private readonly Security $security,
    ) {
    }

    /**
     * @return array{success: bool, message: string, results: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, total: int, processed: int, failed: int}
     */
    public function __invoke(BatchChangeActorStatusAction $action): array
    {
        $results = [];
        $errors = [];

        foreach ($action->actors as $actorData) {
            $id = $actorData->id;
            $watchFileId = $actorData->watchFileId;
            $sourceIds = $actorData->sourceIds;

            if (empty($id) || empty($watchFileId)) {
                $errors[] = [
                    'id' => $id,
                    'watchFileId' => $watchFileId,
                    'error' => 'Missing or empty required actor data (id or watchFileId)',
                ];
                continue;
            }

            try {
                $user = $this->security->getUser();
                if (!$user instanceof User) {
                    throw new \RuntimeException('User must be authenticated');
                }

                $changeAction = new ChangeActorStatusAction(
                    watchFileId: $watchFileId,
                    actorId: $id,
                    sourceIds: $sourceIds,
                    newStatus: ActorStatus::ACTIVE,
                    user: $user,
                );

                $response = $this->changeActorStatusHandler->__invoke($changeAction);

                $results[] = [
                    'id' => $id,
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                    'data' => $response,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $id,
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
                'Batch operation completed with partial success: %d actor(s) processed successfully, %d failed.',
                $resultCount,
                $errorCount
            );
        } elseif ($hasErrors) {
            $message = \sprintf('Batch operation failed: All %d actor(s) failed to process.', $errorCount);
        } elseif ($resultCount > 0) {
            $message = \sprintf('Batch operation completed successfully: All %d actor(s) processed.', $resultCount);
        } else {
            $message = 'Batch operation completed: No actors to process.';
        }

        return [
            'success' => !$hasErrors,
            'message' => $message,
            'results' => $results,
            'errors' => $errors,
            'total' => \count($action->actors),
            'processed' => $resultCount,
            'failed' => $errorCount,
        ];
    }
}
