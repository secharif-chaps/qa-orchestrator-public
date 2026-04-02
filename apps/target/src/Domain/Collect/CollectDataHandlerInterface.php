<?php

declare(strict_types=1);

namespace App\Domain\Collect;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;

interface CollectDataHandlerInterface
{
    /**
     * Determines if this handler supports the given collect data event.
     */
    public function supports(CollectDataReceivedEvent $event): bool;

    /**
     * Handles the collect data event.
     */
    public function __invoke(CollectDataReceivedEvent $event): void;
}
