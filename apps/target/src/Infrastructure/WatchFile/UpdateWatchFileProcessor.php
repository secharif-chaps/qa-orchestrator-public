<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\CalculateWatchFileChangesAction;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<WatchFile, WatchFile>
 */
class UpdateWatchFileProcessor implements ProcessorInterface
{
    use HandleTrait;

    /**
     * @param ProcessorInterface<WatchFile, WatchFile> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WatchFile
    {
        $watchFile = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        if (isset($context['previous_data']) && $context['previous_data'] instanceof WatchFile) {
            $previousWatchFile = $context['previous_data'];

            // If the name changed, mark as manually set by user
            if ($previousWatchFile->getName() !== $watchFile->getName()) {
                $watchFile->setTitleManuallySetByUser(true);
            }

            /** @var array<string, array{old: mixed, new: mixed}> $changes */
            $changes = $this->handle(new CalculateWatchFileChangesAction($context['previous_data'], $watchFile));
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $this->eventDispatcher->dispatch(new WatchFileUpdatedEvent($watchFile, $user, $changes));
            }
        }

        return $watchFile;
    }
}
