<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @property LoggerInterface|null $logger
 */
trait GetWatchFileTrait
{
    protected readonly WatchFileGatewayInterface $watchFileGateway;

    #[Required]
    public function setWatchFileGateway(WatchFileGatewayInterface $watchFileGateway): void
    {
        $this->watchFileGateway = $watchFileGateway;
    }

    /**
     * Retrieves a WatchFile by ID and optionally enriched with virtual properties.
     *
     * @param string    $watchFileId   The ID of the WatchFile to retrieve
     * @param User|null $userForEnrich optional user to enrich with virtual properties the WatchFile
     *
     * @return WatchFile The retrieved WatchFile, optionally enriched with virtual properties
     */
    private function getWatchFile(string $watchFileId, ?User $userForEnrich = null): WatchFile
    {
        if (empty($watchFileId)) {
            $this->logger?->error('Invalid watchfile ID provided', [
                'watch_file_id' => $watchFileId,
            ]);

            throw new UnrecoverableMessageHandlingException('Invalid watchfile ID provided.');
        }

        try {
            $watchFile = $this->watchFileGateway->get($watchFileId, $userForEnrich);
        } catch (WatchFileNotFoundException $e) {
            $message = 'Failed to retrieve watchfile "%1$s" for user "%2$s"';
            if (null === $userForEnrich) {
                $message = 'Failed to retrieve watchfile "%1$s" (anonymous user)';
            }

            $this->logger?->error(
                \sprintf($message, $watchFileId, $userForEnrich?->getId() ?? ''),
                [
                    'watch_file_id' => $watchFileId,
                    'user_id' => $userForEnrich?->getId(),
                    'exception' => $e->getMessage(),
                ],
            );

            throw new UnrecoverableMessageHandlingException('WatchFile not found', 0, $e);
        }

        return $watchFile;
    }
}
