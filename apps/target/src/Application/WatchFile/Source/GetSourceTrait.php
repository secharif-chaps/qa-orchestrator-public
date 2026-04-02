<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @property LoggerInterface|null $logger
 */
trait GetSourceTrait
{
    protected readonly SourceGatewayInterface $sourceGateway;

    #[Required]
    public function setSourceGateway(SourceGatewayInterface $sourceGateway): void
    {
        $this->sourceGateway = $sourceGateway;
    }

    public function getSource(string $sourceId): Source
    {
        if (empty($sourceId)) {
            $this->logger?->error('Invalid source ID provided', [
                'source_id' => $sourceId,
            ]);
            throw new UnrecoverableMessageHandlingException('Invalid source ID provided.');
        }

        try {
            return $this->sourceGateway->get($sourceId);
        } catch (SourceNotFoundException  $e) {
            $this->logger?->error('Failed to retrieve source', [
                'source_id' => $sourceId,
                'exception' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException('Source not found', 0, $e);
        }
    }
}
