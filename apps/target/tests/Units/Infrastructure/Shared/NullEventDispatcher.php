<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared;

use Psr\EventDispatcher\EventDispatcherInterface;

class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
