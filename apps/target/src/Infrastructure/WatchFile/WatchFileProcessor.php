<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Chat\CreateConversationAction;
use App\Application\WatchFile\CheckWatchFileOwnerQuotaAction;
use App\Application\WatchFile\Share\ShareWatchFileAction;
use App\Application\WatchFile\Share\ShareWatchFileBatchAction;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\UserInterface\Dto\Chat\UserMessageDto;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<UserMessageDto, WatchFile>
 */
class WatchFileProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly TenantContext $tenantContext,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $user = null;
        }

        // Check quota before creating WatchFile
        if ($user instanceof User) {
            $userId = $user->getId();
            if (null !== $userId) {
                $this->messageBus->dispatch(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
            }
        }

        // Create a WatchFile with empty required fields
        $watchFile = new WatchFile(
            $this->translator->trans('watch_file.untitled', [], 'messages'),
            '',
            $this->tenantContext->getCurrentOrganisation(),
            $user,
        );

        // Persist the WatchFile
        $this->watchFileGateway->save($watchFile);

        if ($user instanceof User) {
            $this->eventDispatcher->dispatch(new WatchFileCreatedEvent($watchFile, $user));
        }

        // Create WatchFileUser entry for the creator with owner role
        $createdById = $watchFile->getCreatedBy()?->getId();
        if (\is_string($createdById) && !empty($createdById)) {
            $this->messageBus->dispatch(
                new ShareWatchFileBatchAction(
                    [new ShareWatchFileAction($watchFile->getId(), $createdById, WatchFileUserRole::OWNER)],
                    $createdById,
                    new \DateTimeImmutable(),
                ),
            );
        }

        // Create conversation with the provided message
        $this->messageBus->dispatch(
            new CreateConversationAction(watchFileId: $watchFile->getId(), message: $data->content)
        );

        $this->logger?->info('WatchFile created with conversation', [
            'watch_file_id' => $watchFile->getId(),
            'message' => $data->content,
        ]);

        return $watchFile;
    }
}
