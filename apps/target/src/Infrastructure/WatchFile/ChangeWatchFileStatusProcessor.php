<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, WatchFile>
 */
class ChangeWatchFileStatusProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        if (!isset($uriVariables['watchFileId'])) {
            throw new BadRequestHttpException('WatchFile ID must be provided in the URL');
        }

        $watchFileId = $uriVariables['watchFileId'];
        if (!\is_string($watchFileId)) {
            throw new BadRequestHttpException('WatchFile ID must be a string');
        }

        // {status} is a path parameter, not an entity identifier — extract from request attributes
        /** @var \Symfony\Component\HttpFoundation\Request|null $request */
        $request = $context['request'] ?? null;
        $statusValue = $request?->attributes->get('status')
            ?? $uriVariables['status']
            ?? null;

        if (!\is_string($statusValue) || '' === $statusValue) {
            throw new BadRequestHttpException('Status must be provided in the URL');
        }

        try {
            $status = WatchFileStatus::from($statusValue);
        } catch (\ValueError $e) {
            throw new BadRequestHttpException(\sprintf(
                'Invalid status value. Valid statuses are: %s',
                implode(', ', array_column(WatchFileStatus::cases(), 'value')),
            ), previous: $e, );
        }

        try {
            $watchFile = $this->watchFileGateway->get($watchFileId);
        } catch (WatchFileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('WatchFile with ID %s not found.', $watchFileId), previous: $e);
        }

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException(\sprintf(
                'You do not have permission to change the status of watch file with ID %s.',
                $watchFileId
            ), );
        }

        $action = new ChangeWatchFileStatusAction(watchFileId: $watchFileId, status: $status);

        /** @var WatchFile $watchFile */
        $watchFile = $this->handle($action);

        return $watchFile;
    }
}
