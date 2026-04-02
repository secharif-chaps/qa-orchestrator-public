<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class NullNotifier implements NotifierInterface
{
    /**
     * @var list<array{0: Notification, 1: array<RecipientInterface>}>
     */
    public array $sent = [];

    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        $this->sent[] = [$notification, $recipients];
    }
}
